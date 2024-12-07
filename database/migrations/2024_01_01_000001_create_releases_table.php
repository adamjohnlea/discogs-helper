<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class CreateReleasesTable
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec('
            CREATE TABLE releases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                discogs_id INTEGER NOT NULL UNIQUE,
                title TEXT NOT NULL,
                artist TEXT NOT NULL,
                year INTEGER,
                format TEXT NOT NULL,
                format_details TEXT NOT NULL,
                cover_path TEXT,
                notes TEXT,
                tracklist TEXT NOT NULL,
                identifiers TEXT NOT NULL,
                date_added TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Add index for discogs_id
        $this->pdo->exec('CREATE INDEX idx_discogs_id ON releases(discogs_id)');
    }

    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS releases');
    }
} 