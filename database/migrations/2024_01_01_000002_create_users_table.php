<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class CreateUsersTable
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(255) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Add indexes for faster lookups
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_username ON users(username)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_email ON users(email)');
    }

    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS users');
    }
}
