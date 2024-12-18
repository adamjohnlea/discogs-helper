<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;

if (!$auth->isLoggedIn()) {
    $content = '
    <div class="welcome-section">
        <h1>Welcome to Discogs Helper</h1>
        
        <p>Discogs Helper is a tool designed to help you manage and explore your vinyl record collection. 
        With this application, you can:</p>
        
        <ul>
            <li>Search and browse the Discogs database</li>
            <li>Import your existing Discogs collection</li>
            <li>Manage and organize your vinyl records</li>
            <li>Keep track of your collection\'s details</li>
        </ul>

        <div class="action-buttons">
            <a href="?action=login" class="button">Login</a>
            <a href="?action=register" class="button">Register</a>
        </div>
    </div>';
} else {
    // Get the current user's collection stats
    $userId = $auth->getCurrentUser()->id;
    $collectionSize = $db->getCollectionSize($userId);
    $latestRelease = $db->getLatestRelease($userId);

    $content = '
    <div class="dashboard-section">
        <h1>Your Collection Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Collection Size</h3>
                <p class="stat-number">' . number_format($collectionSize) . '</p>
                <p class="stat-label">Records</p>
            </div>';

    if ($latestRelease) {
        $content .= '
            <div class="stat-card latest-addition">
                <h3>Latest Addition</h3>
                ' . ($latestRelease->coverPath 
                    ? '<img src="' . htmlspecialchars($latestRelease->getCoverUrl()) . '" 
                           alt="Latest addition cover" class="latest-cover">' 
                    : '') . '
                <p class="latest-title">' . htmlspecialchars($latestRelease->title) . '</p>
                <p class="latest-artist">' . htmlspecialchars($latestRelease->artist) . '</p>
                <p class="stat-label">Added ' . date('F j, Y', strtotime($latestRelease->dateAdded)) . '</p>
            </div>';
    } else {
        $content .= '
            <div class="stat-card">
                <h3>Latest Addition</h3>
                <p class="stat-label">No releases yet</p>
            </div>';
    }

    $content .= '
        </div>

        <div class="action-buttons">
            <a href="?action=search" class="button">Search Discogs</a>
            <a href="?action=list" class="button">View Collection</a>
            <a href="?action=import" class="button">Import Collection</a>
        </div>
    </div>';
}

// Add some specific styles for both welcome and dashboard pages
$styles = '
<style>
    .welcome-section,
    .dashboard-section {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        text-align: center;
    }

    .welcome-section p,
    .dashboard-section p {
        font-size: 1.1rem;
        line-height: 1.6;
        margin: 1.5rem 0;
    }

    .welcome-section ul {
        text-align: left;
        display: inline-block;
        margin: 1.5rem auto;
        font-size: 1.1rem;
        line-height: 1.6;
    }

    .action-buttons {
        margin-top: 2rem;
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .action-buttons .button {
        font-size: 1.1rem;
        padding: 0.75rem 1.5rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
        margin: 0 0 1rem 0;
        color: #666;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin: 0.5rem 0;
    }

    .stat-label {
        color: #666;
        margin: 0;
    }

    .latest-addition {
        text-align: center;
    }

    .latest-cover {
        width: 120px;
        height: 120px;
        object-fit: contain;
        margin: 1rem auto;
        border-radius: 4px;
    }

    .latest-title {
        font-weight: bold;
        margin: 0.5rem 0 0.25rem 0;
        font-size: 1.1rem;
    }

    .latest-artist {
        color: #666;
        margin: 0 0 0.5rem 0;
    }
</style>';

require __DIR__ . '/layout.php';