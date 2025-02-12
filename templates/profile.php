<?php

declare(strict_types=1);

/** @var Auth $auth Authentication instance */
/** @var Database\Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Http\Session;

if (!$auth->isLoggedIn()) {
    header('Location: ?action=login');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$profile = $db->getUserProfile($userId);
$user = $auth->getCurrentUser();

$content = '<div class="profile-view">';

// Add success message if exists (immediately after profile-view div)
if (Session::hasMessage()) {
    $message = Session::getMessage();
    $content .= '
    <div class="success-message">
        ' . htmlspecialchars($message) . '
    </div>';
}

$content .= '
    <h1>Profile</h1>
    
    <div class="profile-sections">
        <section class="profile-section">
            <h2>Basic Information</h2>
            <div class="profile-details">
                <p><strong>Username:</strong> ' . htmlspecialchars($user->username) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($user->email) . '</p>
                <p><strong>Location:</strong> ' . ($profile?->location ? htmlspecialchars($profile->location) : '<em>Not set</em>') . '</p>
            </div>
        </section>

        <section class="profile-section">
            <h2>Discogs Integration</h2>
            <div class="profile-details">';

if ($profile?->hasDiscogsCredentials()) {
    $content .= '
                <p><strong>Discogs Username:</strong> ' . htmlspecialchars($profile->discogsUsername) . '</p>
                <p><strong>API Credentials:</strong> Configured</p>';
} else {
    $content .= '
                <p class="notice">Discogs integration not configured. Some features will be limited.</p>';
}

$content .= '
            </div>
        </section>

        <section class="profile-section">
            <h2>Last.fm Integration</h2>
            <div class="profile-details">';

if ($profile?->lastfmApiKey && $profile?->lastfmApiSecret) {
    $content .= '
                <p><strong>Last.fm API:</strong> Configured</p>';
} else {
    $content .= '
                <p class="notice">Last.fm integration not configured. Music recommendations will be limited.</p>';
}

$content .= '
            </div>
        </section>
    </div>

    <div class="profile-actions">
        <a href="?action=profile_edit" class="button">Edit Profile</a>
    </div>
</div>';

// Add both error and success message styles
$styles = '
<style>
    .profile-view {
        max-width: 800px;
        margin: 0 auto;
        padding: 1rem;
    }

    .profile-sections {
        display: grid;
        gap: 2rem;
        margin: 2rem 0;
    }

    .profile-section {
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        padding: 1.5rem;
    }

    .profile-section h2 {
        margin-top: 0;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .profile-details {
        display: grid;
        gap: 0.5rem;
    }

    .profile-details p {
        margin: 0;
        padding: 0.5rem 0;
    }

    .notice {
        color: #666;
        font-style: italic;
    }

    .profile-actions {
        margin-top: 2rem;
        text-align: center;
    }

    .success-message {
        max-width: 800px;
        margin: 1rem auto;
        padding: 1rem;
        background: #e8f5e9;
        border: 1px solid #4caf50;
        border-radius: 4px;
        color: #2e7d32;
        text-align: center;
    }

    .error-message {
        max-width: 800px;
        margin: 1rem auto;
        padding: 1rem;
        background: #ffebee;
        border: 1px solid #ef5350;
        border-radius: 4px;
        color: #c62828;
        text-align: center;
    }
</style>';

require __DIR__ . '/layout.php';