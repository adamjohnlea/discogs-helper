<?php

declare(strict_types=1);

namespace DiscogsHelper;

use PDO;
use RuntimeException;
use DiscogsHelper\Database\MigrationRunner;

final class DatabaseSetup
{
    public static function initialize(string $databasePath): PDO
    {
        $databaseDir = dirname($databasePath);
        Setup::ensureDirectoryExists($databaseDir);
        Setup::ensureDirectoryExists(__DIR__ . '/../public/images/covers');

        $pdo = new PDO("sqlite:{$databasePath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Run migrations
        $runner = new MigrationRunner($pdo, __DIR__ . '/../database/migrations');
        $runner->run();
        
        return $pdo;
    }
} 