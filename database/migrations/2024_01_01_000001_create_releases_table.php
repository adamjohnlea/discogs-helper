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
            CREATE TABLE IF NOT EXISTS releases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discogs_id INTEGER NOT NULL,
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
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, discogs_id)
            )
        ');

        // Create an index for the composite key for better performance
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_discogs ON releases(user_id, discogs_id)');
    }

    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS releases');
    }
} 