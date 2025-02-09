<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class CreateImportStatesTable
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS import_states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                current_page INTEGER NOT NULL,
                total_pages INTEGER NOT NULL,
                processed_items INTEGER NOT NULL DEFAULT 0,
                total_items INTEGER NOT NULL,
                last_processed_id INTEGER,
                failed_items TEXT DEFAULT "[]",
                retry_count INTEGER DEFAULT 0,
                cover_stats TEXT DEFAULT NULL,
                last_update DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->pdo->exec('CREATE INDEX idx_import_user ON import_states(user_id)');
    }

    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS import_states');
    }
} 