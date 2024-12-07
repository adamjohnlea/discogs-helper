<?php

declare(strict_types=1);

namespace DiscogsHelper;

use PDO;
use RuntimeException;
use DiscogsHelper\Migrations\CreateReleasesTable;

final class DatabaseSetup
{
    public static function initialize(string $databasePath): PDO
    {
        $databaseDir = dirname($databasePath);
        Setup::ensureDirectoryExists($databaseDir);

        // Ensure covers directory exists
        Setup::ensureDirectoryExists(__DIR__ . '/../public/images/covers');

        $pdo = new PDO("sqlite:{$databasePath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Run migrations if table doesn't exist
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='releases'")->fetch();
        if (!$tableExists) {
            $migration = new CreateReleasesTable($pdo);
            $migration->up();
        }
        
        return $pdo;
    }
} 