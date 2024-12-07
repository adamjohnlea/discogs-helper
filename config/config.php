<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$dotenv->required(['DISCOGS_CONSUMER_KEY', 'DISCOGS_CONSUMER_SECRET']);

return [
    'discogs' => [
        'consumer_key' => $_ENV['DISCOGS_CONSUMER_KEY'],
        'consumer_secret' => $_ENV['DISCOGS_CONSUMER_SECRET'],
        'user_agent' => 'MyDiscogsHelper/1.0',
    ],
    'database' => [
        'path' => __DIR__ . '/../database/discogs.sqlite',
    ],
]; 