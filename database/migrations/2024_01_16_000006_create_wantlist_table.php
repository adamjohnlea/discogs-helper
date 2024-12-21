<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class CreateWantlistTable
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS wantlist_items (
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
        
        $this->pdo->exec('CREATE INDEX idx_wantlist_user ON wantlist_items(user_id)');
        $this->pdo->exec('CREATE INDEX idx_wantlist_discogs ON wantlist_items(discogs_id)');
    }

    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS wantlist_items');
    }
}