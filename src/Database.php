<?php

declare(strict_types=1);

namespace DiscogsHelper;

use PDO;
use RuntimeException;
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
                created_at, updated_at
            ) VALUES (
                :user_id, :location, :discogs_username,
                :discogs_consumer_key, :discogs_consumer_secret,
                :created_at, :updated_at
            )
        ');

        $stmt->execute([
            'user_id' => $profile->userId,
            'location' => $profile->location,
            'discogs_username' => $profile->discogsUsername,
            'discogs_consumer_key' => $profile->discogsConsumerKey,
            'discogs_consumer_secret' => $profile->discogsConsumerSecret,
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
                updated_at = :updated_at
            WHERE user_id = :user_id
        ');

        $stmt->execute([
            'user_id' => $profile->userId,
            'location' => $profile->location,
            'discogs_username' => $profile->discogsUsername,
            'discogs_consumer_key' => $profile->discogsConsumerKey,
            'discogs_consumer_secret' => $profile->discogsConsumerSecret,
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

}