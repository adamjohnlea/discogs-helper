<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DiscogsHelper\Database\Migrations\CreateWantlistTable;

$config = require __DIR__ . '/../config/config.php';

// Create PDO instance
$pdo = new PDO("sqlite:{$config['database']['path']}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Run the specific migration
$migration = new CreateWantlistTable($pdo);
$migration->up();

echo "Wantlist table migration completed!\n"; 