<?php

declare(strict_types=1);

namespace DiscogsHelper\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsPath
    ) {}

    public function run(): void
    {
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();

        // Get list of migration files
        $files = glob($this->migrationsPath . '/*.php');
        sort($files); // Ensure migrations run in order

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            require_once $file;
            
            // Convert filename to class name (e.g., 2024_01_01_000001_create_releases_table -> CreateReleasesTable)
            $parts = explode('_', $className);
            array_splice($parts, 0, 4); // Remove date prefix
            $className = str_replace(' ', '', ucwords(implode(' ', $parts)));
            
            $fullClassName = "DiscogsHelper\\Database\\Migrations\\{$className}";
            
            if (!class_exists($fullClassName)) {
                throw new RuntimeException("Migration class {$fullClassName} not found");
            }

            // Check if migration has already been run
            $stmt = $this->pdo->prepare('SELECT id FROM migrations WHERE migration = ?');
            $stmt->execute([$className]);
            
            if (!$stmt->fetch()) {
                // Run migration
                $migration = new $fullClassName($this->pdo);
                $migration->up();
                
                // Get next batch number
                $stmt = $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations');
                $nextBatch = $stmt->fetch(PDO::FETCH_ASSOC)['next_batch'];
                
                // Record migration
                $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)')
                    ->execute([$className, $nextBatch]);
            }
        }
    }

    private function createMigrationsTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
} 