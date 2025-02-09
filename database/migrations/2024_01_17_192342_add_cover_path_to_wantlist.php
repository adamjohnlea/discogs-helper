<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class AddCoverPathToWantlist
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec('
            ALTER TABLE wantlist_items
            ADD COLUMN cover_path TEXT
        ');
    }

    public function down(): void
    {
        // SQLite doesn't support dropping columns directly
        // We need to recreate the table without the column
        $this->pdo->exec('
            CREATE TABLE wantlist_items_temp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discogs_id INTEGER NOT NULL,
                artist TEXT NOT NULL,
                title TEXT NOT NULL,
                notes TEXT,
                rating INTEGER,
                date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
                price_threshold DECIMAL(10,2),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, discogs_id)
            )
        ');

        $this->pdo->exec('
            INSERT INTO wantlist_items_temp 
            SELECT id, user_id, discogs_id, artist, title, notes, rating, date_added, price_threshold
            FROM wantlist_items
        ');

        $this->pdo->exec('DROP TABLE wantlist_items');
        $this->pdo->exec('ALTER TABLE wantlist_items_temp RENAME TO wantlist_items');
        
        $this->pdo->exec('CREATE INDEX idx_wantlist_user ON wantlist_items(user_id)');
        $this->pdo->exec('CREATE INDEX idx_wantlist_discogs ON wantlist_items(discogs_id)');
    }
} 