<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class AddDateAddedColumn
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec('
            ALTER TABLE releases 
            ADD COLUMN date_added TEXT
        ');
    }

    public function down(): void
    {
        // SQLite doesn't support dropping columns
        // We would need to recreate the table to remove a column
    }
}