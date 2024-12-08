<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DiscogsHelper\Database;
use DiscogsHelper\DatabaseSetup;
use DiscogsHelper\DiscogsService;

$config = require __DIR__ . '/../config/config.php';

// Initialize the database if it doesn't exist
DatabaseSetup::initialize($config['database']['path']);

$db = new Database($config['database']['path']);
$discogs = new DiscogsService(
    $config['discogs']['consumer_key'],
    $config['discogs']['consumer_secret'],
    $config['discogs']['user_agent']
);

$action = $_GET['action'] ?? 'home';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

match ($action) {
    'search' => require __DIR__ . '/../templates/search.php',
    'view' => require __DIR__ . '/../templates/view.php',
    'preview' => require __DIR__ . '/../templates/preview.php',
    'add' => require __DIR__ . '/../templates/add.php',
    'import' => require __DIR__ . '/../templates/import.php',
    'list' => require __DIR__ . '/../templates/list.php',
    'home' => require __DIR__ . '/../templates/index.php',
    // default => require __DIR__ . '/../templates/index.php',
}; 