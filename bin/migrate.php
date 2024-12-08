<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DiscogsHelper\Database\MigrationRunner;

$config = require __DIR__ . '/../config/config.php';

// Create PDO instance
$pdo = new PDO("sqlite:{$config['database']['path']}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Run migrations
$runner = new MigrationRunner($pdo, __DIR__ . '/../database/migrations');
$runner->run();

echo "Migrations completed successfully!\n"; 