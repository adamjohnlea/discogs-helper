<?php

declare(strict_types=1);

namespace DiscogsHelper;

use RuntimeException;

class StaticCollectionGenerator
{
    private const COLLECTIONS_DIR = __DIR__ . '/../public/collections';
    private const ASSETS_DIR = self::COLLECTIONS_DIR . '/assets';

    public function __construct(
        private Database $db,
        private Auth $auth
    ) {}

    /**
     * Generate or update static collection pages for a user
     */
    public function generateForUser(int $userId): void
    {
        // Get user profile and user info
        $user = $this->db->findUserById($userId);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        // Clean up old pages
        $this->cleanupOldPages($userId);

        $userDir = self::COLLECTIONS_DIR . '/' . $user['username'];
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        // Create releases directory if it doesn't exist
        $releasesDir = $userDir . '/releases';
        if (!is_dir($releasesDir)) {
            mkdir($releasesDir, 0755, true);
        }

        // Generate main collection page
        $this->generateCollectionPage($userId, $user['username']);

        // Generate individual release pages
        $releases = $this->db->getAllReleases($userId);
        foreach ($releases as $release) {
            $this->generateReleasePage($release, $user['username']);
        }

        // Log completion
        error_log(sprintf(
            'Generated static pages for user %s: %d releases, collection page at %s',
            $user['username'],
            count($releases),
            "$userDir/index.html"
        ));
    }

    /**
     * Clean up old static pages for a user
     */
    private function cleanupOldPages(int $userId): void
    {
        $user = $this->db->findUserById($userId);
        if (!$user) {
            return;
        }

        $userDir = self::COLLECTIONS_DIR . '/' . $user['username'];
        if (!is_dir($userDir)) {
            return;
        }

        // Clean up releases directory
        $releasesDir = $userDir . '/releases';
        if (is_dir($releasesDir)) {
            error_log("Cleaning up releases directory: $releasesDir");
            $this->recursiveDelete($releasesDir);
        }

        // Clean up index file
        $indexFile = $userDir . '/index.html';
        if (file_exists($indexFile)) {
            error_log("Cleaning up index file: $indexFile");
            unlink($indexFile);
        }
    }

    /**
     * Recursively delete a directory and its contents
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Generate the main collection page for a user
     */
    private function generateCollectionPage(int $userId, string $username): void
    {
        // Get collection data
        $collectionSize = $this->db->getCollectionSize($userId);
        $latestRelease = $this->db->getLatestRelease($userId);
        $uniqueArtistCount = $this->db->getUniqueArtistCount($userId);
        $topArtists = $this->db->getTopArtists($userId, 5);
        $formatDistribution = $this->db->getFormatDistribution($userId);
        $yearRange = $this->db->getYearRange($userId);
        
        // Get all releases for the grid view
        $releases = $this->db->getAllReleases($userId);

        // Log data being passed to template
        error_log(sprintf(
            'Rendering collection page with: %d releases, %d artists, %d top artists',
            count($releases),
            $uniqueArtistCount,
            count($topArtists)
        ));

        // Generate HTML content
        $content = $this->renderCollectionTemplate([
            'username' => $username,
            'collectionSize' => $collectionSize,
            'latestRelease' => $latestRelease,
            'uniqueArtistCount' => $uniqueArtistCount,
            'topArtists' => $topArtists,
            'formatDistribution' => $formatDistribution,
            'yearRange' => $yearRange,
            'releases' => $releases,
        ]);

        // Write to file
        $filePath = self::COLLECTIONS_DIR . "/$username/index.html";
        file_put_contents($filePath, $content);
    }

    /**
     * Generate a page for an individual release
     */
    private function generateReleasePage(Release $release, string $username): void
    {
        $content = $this->renderReleaseTemplate([
            'username' => $username,
            'release' => $release,
        ]);

        $filePath = self::COLLECTIONS_DIR . "/$username/releases/{$release->id}.html";
        file_put_contents($filePath, $content);
    }

    /**
     * Render the collection template with the given data
     */
    private function renderCollectionTemplate(array $data): string
    {
        ob_start();
        extract($data);
        require __DIR__ . '/../templates/static/collection.php';
        return ob_get_clean();
    }

    /**
     * Render the release template with the given data
     */
    private function renderReleaseTemplate(array $data): string
    {
        ob_start();
        extract($data);
        require __DIR__ . '/../templates/static/release.php';
        return ob_get_clean();
    }
} 