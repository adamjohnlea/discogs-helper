<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class AddLastFmFieldsToUserProfiles
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        // Add Last.fm API fields
        $this->pdo->exec('
            ALTER TABLE user_profiles
            ADD COLUMN lastfm_api_key TEXT
        ');
        
        $this->pdo->exec('
            ALTER TABLE user_profiles
            ADD COLUMN lastfm_api_secret TEXT
        ');
    }

    public function down(): void
    {
        // SQLite doesn't support dropping columns directly
        // We need to recreate the table without the Last.fm columns
        $this->pdo->exec('
            CREATE TABLE user_profiles_temp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                location VARCHAR(255),
                discogs_username VARCHAR(255) UNIQUE,
                discogs_consumer_key TEXT,
                discogs_consumer_secret TEXT,
                discogs_oauth_token TEXT,
                discogs_oauth_token_secret TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ');

        $this->pdo->exec('
            INSERT INTO user_profiles_temp 
            SELECT id, user_id, location, discogs_username, discogs_consumer_key, discogs_consumer_secret, 
                   discogs_oauth_token, discogs_oauth_token_secret, created_at, updated_at
            FROM user_profiles
        ');

        $this->pdo->exec('DROP TABLE user_profiles');
        $this->pdo->exec('ALTER TABLE user_profiles_temp RENAME TO user_profiles');
    }
} 