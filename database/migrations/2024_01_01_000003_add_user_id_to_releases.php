<?php

declare(strict_types=1);

namespace DiscogsHelper\Database\Migrations;

use PDO;

final class AddUserIdToReleases
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        // Add user_id column as nullable (so it doesn't break existing records)
        $this->pdo->exec('
            ALTER TABLE releases 
            ADD COLUMN user_id INTEGER NULL 
            REFERENCES users(id) ON DELETE CASCADE
        ');

        // Add index for faster joins
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_id ON releases(user_id)');
    }

    public function down(): void
    {
        // SQLite doesn't support DROP COLUMN, so we'd need to:
        // 1. Create new table without the column
        // 2. Copy data
        // 3. Drop old table
        // 4. Rename new table
        $this->pdo->exec('
            CREATE TABLE releases_temp AS 
            SELECT id, discogs_id, title, artist, year, format, format_details,
                   cover_path, notes, tracklist, identifiers, date_added,
                   created_at, updated_at
            FROM releases
        ');

        $this->pdo->exec('DROP TABLE releases');

        $this->pdo->exec('ALTER TABLE releases_temp RENAME TO releases');

        // Recreate any indexes that existed on the original table
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_discogs_id ON releases(discogs_id)');
    }
}