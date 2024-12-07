<?php

declare(strict_types=1);

namespace DiscogsHelper;

use PDO;
use RuntimeException;
use DiscogsHelper\Exceptions\DuplicateReleaseException;

final class Database
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        $this->pdo = new PDO('sqlite:' . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function saveRelease(
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
        // Check if release already exists
        $stmt = $this->pdo->prepare('SELECT id FROM releases WHERE discogs_id = :discogs_id');
        $stmt->execute(['discogs_id' => $discogsId]);
        
        if ($stmt->fetch()) {
            throw new DuplicateReleaseException($discogsId);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO releases (
                discogs_id, title, artist, year, format, format_details,
                cover_path, notes, tracklist, identifiers, date_added
            ) VALUES (
                :discogs_id, :title, :artist, :year, :format, :format_details,
                :cover_path, :notes, :tracklist, :identifiers, :date_added
            )'
        );

        $stmt->execute([
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

    public function getAllReleases(string $search = null): array
    {
        $query = 'SELECT * FROM releases';
        $params = [];
        
        if ($search) {
            $query .= ' WHERE title LIKE :search 
                       OR artist LIKE :search 
                       OR format LIKE :search 
                       OR format_details LIKE :search';
            $params['search'] = '%' . $search . '%';
        }
        
        $query .= ' ORDER BY date_added DESC';
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        
        return array_map(
            fn(array $row) => $this->createReleaseFromRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getReleaseById(int $id): ?Release
    {
        $stmt = $this->pdo->prepare('SELECT * FROM releases WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->createReleaseFromRow($row);
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

    public function getDiscogsReleaseId(int $discogsId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM releases WHERE discogs_id = :discogs_id');
        $stmt->execute(['discogs_id' => $discogsId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['id'] : null;
    }
} 