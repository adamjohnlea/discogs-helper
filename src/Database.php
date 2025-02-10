<?php

declare(strict_types=1);

namespace DiscogsHelper;

use PDO;
use Exception;
use RuntimeException;
use PDOException;
use DiscogsHelper\Release;
use DiscogsHelper\UserProfile;
use DiscogsHelper\Exceptions\DuplicateReleaseException;
use DiscogsHelper\Exceptions\DuplicateDiscogsUsernameException;

final class Database
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        $this->pdo = new PDO('sqlite:' . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function saveRelease(
        int $userId,  // Add userId parameter
        int $discogsId,
        string $title,
        string $artist,
        ?int $year,
        string $format,
        string $formatDetails,
        ?string $coverPath,
        ?string $notes,
        string $tracklist,
        string $identifiers,
        string $dateAdded = null
    ): void {
        // Check if release already exists for this user
        $stmt = $this->pdo->prepare('
            SELECT id 
            FROM releases 
            WHERE discogs_id = :discogs_id 
            AND user_id = :user_id
        ');

        $stmt->execute([
            'discogs_id' => $discogsId,
            'user_id' => $userId
        ]);

        if ($stmt->fetch()) {
            throw new DuplicateReleaseException($discogsId);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO releases (
                user_id, discogs_id, title, artist, year, format, format_details,
                cover_path, notes, tracklist, identifiers, date_added
            ) VALUES (
                :user_id, :discogs_id, :title, :artist, :year, :format, :format_details,
                :cover_path, :notes, :tracklist, :identifiers, :date_added
            )'
        );

        $stmt->execute([
            'user_id' => $userId,
            'discogs_id' => $discogsId,
            'title' => $title,
            'artist' => $artist,
            'year' => $year,
            'format' => $format,
            'format_details' => $formatDetails,
            'cover_path' => $coverPath,
            'notes' => $notes,
            'tracklist' => $tracklist,
            'identifiers' => $identifiers,
            'date_added' => $dateAdded,
        ]);
    }

    public function getAllReleases(
        int $userId,
        ?string $search = null,
        ?string $format = null,
        ?string $sort = null,
        string $direction = 'ASC'
    ): array {
        $query = 'SELECT * FROM releases WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($search) {
            $query .= ' AND (title LIKE :search OR artist LIKE :search)';
            $params['search'] = "%$search%";
        }

        if ($format) {
            $query .= ' AND format = :format';
            $params['format'] = $format;
        }

        // Default sort remains date_added DESC if no sort specified
        $query .= ' ORDER BY ' . match($sort) {
                'year' => 'year',
                'title' => 'title',
                'artist' => 'artist',
                'date_added' => 'date_added',
                default => 'date_added DESC'
            };

        // Only add direction if a sort is specified
        if ($sort) {
            $query .= ' ' . ($direction === 'DESC' ? 'DESC' : 'ASC');
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return array_map(
            fn(array $row) => $this->createReleaseFromRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getReleaseById(int $userId, int $id): ?Release
    {
        $stmt = $this->pdo->prepare('
            SELECT * 
            FROM releases 
            WHERE id = :id 
            AND user_id = :user_id
        ');

        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->createReleaseFromRow($row);
    }

    public function getDiscogsReleaseId(int $userId, int $discogsId): ?int
    {
        $stmt = $this->pdo->prepare('
            SELECT id 
            FROM releases 
            WHERE discogs_id = :discogs_id 
            AND user_id = :user_id
        ');

        $stmt->execute([
            'discogs_id' => $discogsId,
            'user_id' => $userId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['id'] : null;
    }

    public function findUserByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, username, email, password_hash, created_at, updated_at
            FROM users 
            WHERE username = :username
        ');

        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, username, email, password_hash, created_at, updated_at
            FROM users 
            WHERE email = :email
        ');

        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, username, email, password_hash, created_at, updated_at
            FROM users 
            WHERE id = :id
        ');

        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createUser(string $username, string $email, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (username, email, password_hash, created_at, updated_at)
            VALUES (:username, :email, :password_hash, :created_at, :updated_at)
        ');

        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function createUserProfile(UserProfile $profile): void
    {
        // Check for duplicate Discogs username if one is provided
        if ($profile->discogsUsername !== null) {
            $existing = $this->getProfileByDiscogsUsername($profile->discogsUsername);
            if ($existing !== null) {
                throw new DuplicateDiscogsUsernameException($profile->discogsUsername);
            }
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO user_profiles (
                user_id, location, discogs_username,
                discogs_consumer_key, discogs_consumer_secret,
                discogs_oauth_token, discogs_oauth_token_secret,
                created_at, updated_at
            ) VALUES (
                :user_id, :location, :discogs_username,
                :discogs_consumer_key, :discogs_consumer_secret,
                :discogs_oauth_token, :discogs_oauth_token_secret,
                :created_at, :updated_at
            )
        ');

        $stmt->execute([
            'user_id' => $profile->userId,
            'location' => $profile->location,
            'discogs_username' => $profile->discogsUsername,
            'discogs_consumer_key' => $profile->discogsConsumerKey,
            'discogs_consumer_secret' => $profile->discogsConsumerSecret,
            'discogs_oauth_token' => $profile->discogsOAuthToken,
            'discogs_oauth_token_secret' => $profile->discogsOAuthTokenSecret,
            'created_at' => $profile->createdAt,
            'updated_at' => $profile->updatedAt
        ]);
    }

    public function getUserProfile(int $userId): ?UserProfile
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM user_profiles WHERE user_id = :user_id
        ');

        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new UserProfile(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            location: $row['location'],
            discogsUsername: $row['discogs_username'],
            discogsConsumerKey: $row['discogs_consumer_key'],
            discogsConsumerSecret: $row['discogs_consumer_secret'],
            discogsOAuthToken: $row['discogs_oauth_token'],
            discogsOAuthTokenSecret: $row['discogs_oauth_token_secret'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    public function updateUserProfile(UserProfile $profile): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE user_profiles SET
                location = :location,
                discogs_username = :discogs_username,
                discogs_consumer_key = :discogs_consumer_key,
                discogs_consumer_secret = :discogs_consumer_secret,
                discogs_oauth_token = :discogs_oauth_token,
                discogs_oauth_token_secret = :discogs_oauth_token_secret,
                updated_at = :updated_at
            WHERE user_id = :user_id
        ');

        $stmt->execute([
            'user_id' => $profile->userId,
            'location' => $profile->location,
            'discogs_username' => $profile->discogsUsername,
            'discogs_consumer_key' => $profile->discogsConsumerKey,
            'discogs_consumer_secret' => $profile->discogsConsumerSecret,
            'discogs_oauth_token' => $profile->discogsOAuthToken,
            'discogs_oauth_token_secret' => $profile->discogsOAuthTokenSecret,
            'updated_at' => $profile->updatedAt
        ]);
    }

    public function getProfileByDiscogsUsername(string $discogsUsername): ?UserProfile
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM user_profiles WHERE discogs_username = :discogs_username
        ');

        $stmt->execute(['discogs_username' => $discogsUsername]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new UserProfile(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            location: $row['location'],
            discogsUsername: $row['discogs_username'],
            discogsConsumerKey: $row['discogs_consumer_key'],
            discogsConsumerSecret: $row['discogs_consumer_secret'],
            discogsOAuthToken: $row['discogs_oauth_token'],
            discogsOAuthTokenSecret: $row['discogs_oauth_token_secret'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    public function updateUserPassword(int $userId, string $newPassword): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE users 
            SET password_hash = :password_hash,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getUniqueFormats(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT DISTINCT format 
            FROM releases 
            WHERE user_id = :user_id 
            ORDER BY format ASC
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getCollectionSize(int $userId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) 
            FROM releases 
            WHERE user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getLatestRelease(int $userId): ?Release
    {
        $stmt = $this->pdo->prepare('
            SELECT * 
            FROM releases 
            WHERE user_id = :user_id 
            ORDER BY date_added DESC 
            LIMIT 1
        ');
        $stmt->execute(['user_id' => $userId]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->createReleaseFromRow($row);
        }

        return null;
    }

    public function updateReleaseNotes(int $userId, int $releaseId, ?string $notes): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE releases 
            SET notes = :notes,
                updated_at = :updated_at
            WHERE id = :id 
            AND user_id = :user_id
        ');

        return $stmt->execute([
            'id' => $releaseId,
            'user_id' => $userId,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function updateReleaseDetails(int $userId, int $releaseId, string $artist, string $title): bool
    {
        // Validate required fields
        if (trim($artist) === '' || trim($title) === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('
            UPDATE releases 
            SET artist = :artist,
                title = :title,
                updated_at = :updated_at
            WHERE id = :id 
            AND user_id = :user_id
        ');

        return $stmt->execute([
            'id' => $releaseId,
            'user_id' => $userId,
            'artist' => trim($artist),
            'title' => trim($title),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function deleteRelease(int $userId, int $discogsId): bool
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM releases 
            WHERE user_id = :user_id 
            AND discogs_id = :discogs_id
        ');
        
        return $stmt->execute([
            'user_id' => $userId,
            'discogs_id' => $discogsId
        ]);
    }

    public function getReleaseByDiscogsId(int $userId, int $discogsId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM releases 
            WHERE user_id = :user_id 
            AND discogs_id = :discogs_id
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'discogs_id' => $discogsId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function createReleaseFromRow(array $row): Release
    {
        return new Release(
            id: (int)$row['id'],
            discogsId: (int)$row['discogs_id'],
            title: $row['title'],
            artist: $row['artist'],
            year: $row['year'] ? (int)$row['year'] : null,
            format: $row['format'],
            formatDetails: $row['format_details'],
            coverPath: $row['cover_path'],
            notes: $row['notes'],
            tracklist: $row['tracklist'],
            identifiers: $row['identifiers'],
            dateAdded: $row['date_added'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }

    public function addWantlistItem(int $userId, array $release, ?float $priceThreshold = null): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO wantlist_items 
            (user_id, discogs_id, artist, title, notes, rating, price_threshold, cover_path,
             year, format, format_details, tracklist, identifiers)
            VALUES (:user_id, :discogs_id, :artist, :title, :notes, :rating, :price_threshold, :cover_path,
                    :year, :format, :format_details, :tracklist, :identifiers)
            ON CONFLICT(user_id, discogs_id) DO UPDATE SET
            notes = :notes,
            rating = :rating,
            price_threshold = :price_threshold,
            cover_path = :cover_path,
            year = :year,
            format = :format,
            format_details = :format_details,
            tracklist = :tracklist,
            identifiers = :identifiers
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'discogs_id' => $release['id'],
            'artist' => $release['artist'],
            'title' => $release['title'],
            'notes' => $release['notes'] ?? null,
            'rating' => $release['rating'] ?? null,
            'price_threshold' => $priceThreshold,
            'cover_path' => $release['cover_path'] ?? null,
            'year' => $release['year'] ?? null,
            'format' => $release['format'] ?? null,
            'format_details' => $release['format_details'] ?? null,
            'tracklist' => $release['tracklist'] ?? null,
            'identifiers' => $release['identifiers'] ?? null
        ]);
    }

    public function getWantlistItems(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM wantlist_items 
            WHERE user_id = :user_id 
            ORDER BY date_added DESC
        ');
        
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWantlistItem(int $userId, int $discogsId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM wantlist_items 
            WHERE user_id = :user_id 
            AND discogs_id = :discogs_id
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'discogs_id' => $discogsId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getWantlistItemById(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM wantlist_items 
            WHERE user_id = :user_id 
            AND id = :id
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'id' => $id
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deleteWantlistItem(int $userId, int $discogsId): bool
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM wantlist_items 
            WHERE user_id = :user_id 
            AND discogs_id = :discogs_id
        ');
        
        return $stmt->execute([
            'user_id' => $userId,
            'discogs_id' => $discogsId
        ]);
    }

    public function updateWantlistNotes(int $userId, int $itemId, ?string $notes): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE wantlist_items 
            SET notes = :notes
            WHERE id = :id 
            AND user_id = :user_id
        ');
        
        return $stmt->execute([
            'id' => $itemId,
            'user_id' => $userId,
            'notes' => $notes
        ]);
    }

    public function createImportState(
        int $userId,
        int $totalPages,
        int $totalItems
    ): int {
        $stmt = $this->pdo->prepare('
            INSERT INTO import_states 
            (user_id, status, current_page, total_pages, total_items, last_update)
            VALUES (:user_id, :status, 1, :total_pages, :total_items, :last_update)
        ');

        $stmt->execute([
            'user_id' => $userId,
            'status' => 'pending',
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'last_update' => date('Y-m-d H:i:s')
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getImportState(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM import_states 
            WHERE user_id = :user_id 
            ORDER BY last_update DESC 
            LIMIT 1
        ');
        
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateImportProgress(
        int $userId,
        int $currentPage,
        int $processedItems,
        ?int $lastProcessedId = null,
        ?array $coverStats = null
    ): void {
        Logger::log("Updating import progress: " . json_encode([
            'currentPage' => $currentPage,
            'processedItems' => $processedItems,
            'coverStats' => $coverStats
        ]));

        // First get current state to ensure we're updating correctly
        $currentState = $this->getImportState($userId);
        if (!$currentState) {
            Logger::error("No import state found to update");
            return;
        }

        // Calculate total processed items
        $totalProcessed = $processedItems;

        $stmt = $this->pdo->prepare('
            UPDATE import_states 
            SET current_page = :current_page,
                processed_items = :processed_items,
                last_processed_id = :last_processed_id,
                cover_stats = :cover_stats,
                last_update = :last_update
            WHERE user_id = :user_id
            AND status = :status
        ');

        $stmt->execute([
            'user_id' => $userId,
            'current_page' => $currentPage,
            'processed_items' => $totalProcessed,
            'last_processed_id' => $lastProcessedId,
            'cover_stats' => $coverStats ? json_encode($coverStats) : null,
            'status' => 'pending',
            'last_update' => date('Y-m-d H:i:s')
        ]);
    }

    public function completeImport(int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE import_states 
            SET status = :status,
                last_update = :last_update
            WHERE user_id = :user_id
            AND status = :old_status
        ');

        $stmt->execute([
            'user_id' => $userId,
            'status' => 'completed',
            'old_status' => 'pending',
            'last_update' => date('Y-m-d H:i:s')
        ]);

        // Clean up any orphaned cover files
        $this->cleanupFailedCovers();
    }

    public function addFailedItem(int $userId, int $discogsId, string $error): void
    {
        $importState = $this->getImportState($userId);
        if (!$importState) {
            return;
        }

        $failedItems = json_decode($importState['failed_items'] ?? '[]', true);
        $failedItems[] = [
            'id' => $discogsId,
            'error' => $error,
            'timestamp' => time()
        ];

        $stmt = $this->pdo->prepare('
            UPDATE import_states 
            SET failed_items = :failed_items
            WHERE user_id = :user_id
            AND status = :status
        ');

        $stmt->execute([
            'user_id' => $userId,
            'failed_items' => json_encode($failedItems),
            'status' => 'pending'
        ]);
    }

    public function getFailedItems(int $userId): array
    {
        $importState = $this->getImportState($userId);
        if (!$importState) {
            return [];
        }

        return json_decode($importState['failed_items'] ?? '[]', true);
    }

    public function cleanupFailedCovers(): void
    {
        $stmt = $this->pdo->prepare('
            SELECT cover_path 
            FROM releases 
            WHERE cover_path IS NOT NULL
        ');
        $stmt->execute();
        $validPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $coverDir = __DIR__ . '/../public/images/covers/';
        $files = glob($coverDir . '*');

        foreach ($files as $file) {
            $relativePath = 'images/covers/' . basename($file);
            if (!in_array($relativePath, $validPaths)) {
                unlink($file);
                Logger::log("Cleaned up unused cover file: " . basename($file));
            }
        }
    }

    public function deleteImportState(int $userId): void
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM import_states 
            WHERE user_id = :user_id
        ');
        
        $stmt->execute(['user_id' => $userId]);
    }

    public function resetImportState(int $userId): void
    {
        // First delete any existing import states
        $this->deleteImportState($userId);
        
        // Also clean up any orphaned covers
        $this->cleanupFailedCovers();
        
        Logger::log("Reset import state for user {$userId}");
    }

    public function getUniqueArtistCount(int $userId): int {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(DISTINCT artist) 
            FROM releases 
            WHERE user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getTopArtists(int $userId, int $limit = 5): array {
        $stmt = $this->pdo->prepare('
            SELECT artist, COUNT(*) as count 
            FROM releases 
            WHERE user_id = :user_id 
            GROUP BY artist 
            ORDER BY count DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormatDistribution(int $userId): array {
        $stmt = $this->pdo->prepare('
            SELECT format, COUNT(*) as count 
            FROM releases 
            WHERE user_id = :user_id 
            GROUP BY format
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getYearRange(int $userId): array {
        $stmt = $this->pdo->prepare('
            SELECT 
                MIN(year) as oldest,
                MAX(year) as newest,
                COUNT(*) as total_with_year
            FROM releases 
            WHERE user_id = :user_id 
            AND year IS NOT NULL
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRecentActivity(int $userId, int $days = 30): int {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) 
            FROM releases 
            WHERE user_id = :user_id 
            AND date_added >= date("now", :days_ago)
        ');
        $stmt->execute([
            'user_id' => $userId,
            'days_ago' => '-' . $days . ' days'
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function getMonthlyGrowth(int $userId, int $months = 12): array {
        $stmt = $this->pdo->prepare('
            WITH RECURSIVE months AS (
                SELECT date("now", "start of month") as month
                UNION ALL
                SELECT date(month, "-1 month")
                FROM months
                WHERE month > date("now", "start of month", :months_ago)
            )
            SELECT 
                strftime("%Y-%m", months.month) as month,
                COUNT(releases.id) as count
            FROM months
            LEFT JOIN releases ON 
                strftime("%Y-%m", releases.date_added) = strftime("%Y-%m", months.month)
                AND releases.user_id = :user_id
            GROUP BY strftime("%Y-%m", months.month)
            ORDER BY month ASC
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'months_ago' => '-' . ($months - 1) . ' months'
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDailyAdditions(int $userId, int $days = 365): array {
        $stmt = $this->pdo->prepare('
            SELECT 
                date(date_added) as date,
                COUNT(*) as count
            FROM releases 
            WHERE user_id = :user_id 
            AND date_added >= date("now", :days_ago)
            GROUP BY date(date_added)
            ORDER BY date_added ASC
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'days_ago' => '-' . $days . ' days'
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to timestamp => count format
        $data = [];
        foreach ($results as $row) {
            $timestamp = strtotime($row['date']) * 1000; // Convert to milliseconds for JavaScript
            $data[$timestamp] = (int)$row['count'];
        }
        
        return $data;
    }

    /**
     * Handle collection changes and trigger static generation
     */
    private function handleCollectionChange(int $userId): void
    {
        try {
            $generator = new StaticCollectionGenerator($this, new Auth($this));
            $generator->generateForUser($userId);
        } catch (Exception $e) {
            Logger::error('Failed to generate static pages: ' . $e->getMessage());
        }
    }

    public function addRelease(Release $release): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO releases (
                    user_id, discogs_id, title, artist, year, 
                    format, format_details, cover_path, date_added
                ) VALUES (
                    :user_id, :discogs_id, :title, :artist, :year,
                    :format, :format_details, :cover_path, :date_added
                )
            ');

            $stmt->execute([
                'user_id' => $release->id,
                'discogs_id' => $release->discogsId,
                'title' => $release->title,
                'artist' => $release->artist,
                'year' => $release->year,
                'format' => $release->format,
                'format_details' => $release->formatDetails,
                'cover_path' => $release->coverPath,
                'date_added' => $release->dateAdded
            ]);

            $this->pdo->commit();
            
            // Trigger static page generation
            $this->handleCollectionChange($release->id);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function removeRelease(int $userId, int $discogsId): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM releases 
                WHERE user_id = :user_id AND discogs_id = :discogs_id
            ');

            $stmt->execute([
                'user_id' => $userId,
                'discogs_id' => $discogsId
            ]);

            $this->pdo->commit();
            
            // Trigger static page generation
            $this->handleCollectionChange($userId);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get a user profile by their Discogs username
     */
    public function getUserProfileByDiscogsUsername(string $username): ?UserProfile
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM user_profiles 
            WHERE discogs_username = :username
        ');
        
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return new UserProfile(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            location: $row['location'],
            discogsUsername: $row['discogs_username'],
            discogsConsumerKey: $row['discogs_consumer_key'],
            discogsConsumerSecret: $row['discogs_consumer_secret'],
            discogsOAuthToken: $row['discogs_oauth_token'],
            discogsOAuthTokenSecret: $row['discogs_oauth_token_secret'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at']
        );
    }
}