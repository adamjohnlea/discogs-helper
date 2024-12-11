<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DiscogsHelper\Logger;
use DiscogsHelper\Session;
use DiscogsHelper\Database;
use DiscogsHelper\DatabaseSetup;
use DiscogsHelper\DiscogsService;
use DiscogsHelper\Auth;
use DiscogsHelper\UserProfile;
use DiscogsHelper\Exceptions\DiscogsCredentialsException;
use DiscogsHelper\Exceptions\DuplicateDiscogsUsernameException;
use DiscogsHelper\Exceptions\SecurityException;
use DiscogsHelper\Exceptions\RateLimitExceededException;
use DiscogsHelper\Security\Headers;
use DiscogsHelper\Security\Csrf;

// Initialize logger and session
Logger::initialize(__DIR__ . '/..');
Logger::log('Application startup');
Session::initialize();

// At this point session is definitely initialized
Logger::log('Session max lifetime: ' . ini_get('session.gc_maxlifetime'));
Logger::log('Session cookie lifetime: ' . ini_get('session.cookie_lifetime'));
// Apply security headers
Headers::apply();

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$config = require __DIR__ . '/../config/config.php';

// Initialize the database if it doesn't exist
DatabaseSetup::initialize($config['database']['path']);

$db = new Database($config['database']['path']);
$auth = new Auth($db);

// Create DiscogsService instance for routes that need it
$discogs = null;
if (in_array($action, ['search', 'import', 'view', 'preview', 'add'])) {
    $discogs = createDiscogsService($auth, $db);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::validateOrFail($_POST['csrf_token'] ?? null);

        if ($action === 'register') {
            try {
                if ($_POST['password'] !== $_POST['password_confirm']) {
                    $error = '<div class="error">Passwords do not match</div>';
                    require __DIR__ . '/../templates/register.php';
                    exit;
                }

                $user = $auth->register(
                    $_POST['username'],
                    $_POST['email'],
                    $_POST['password']
                );

                $auth->login($_POST['username'], $_POST['password']);
                header('Location: ?action=list');
                exit;
            } catch (RuntimeException $e) {
                $error = '<div class="error">' . htmlspecialchars($e->getMessage()) . '</div>';
                require __DIR__ . '/../templates/register.php';
                exit;
            }
        }

        if ($action === 'login') {
            try {
                if ($auth->login($_POST['username'], $_POST['password'])) {
                    $intended = $_SESSION['intended_page'] ?? '?action=list';
                    unset($_SESSION['intended_page']);
                    header('Location: ' . $intended);
                    exit;
                }

                $error = '<div class="error">Invalid username or password</div>';
                require __DIR__ . '/../templates/login.php';
                exit;
            } catch (RateLimitExceededException $e) {
                $error = '<div class="error">' . htmlspecialchars($e->getMessage()) . '</div>';
                require __DIR__ . '/../templates/login.php';
                exit;
            }
        }

        if ($action === 'profile_update') {
            try {
                handleProfileUpdate($auth, $db);
            } catch (Exception $e) {
                $error = '<div class="error">' . htmlspecialchars($e->getMessage()) . '</div>';
                require __DIR__ . '/../templates/profile_edit.php';
                exit;
            }
        }

    } catch (SecurityException $e) {
        Logger::security('CSRF validation failed');
        $error = '<div class="error">Invalid request. Please try again.</div>';

        // Return to the appropriate form based on the action
        switch ($action) {
            case 'import':
                require __DIR__ . '/../templates/import.php';
                break;
            case 'profile_update':
                require __DIR__ . '/../templates/profile_edit.php';
                break;
            case 'login':
                require __DIR__ . '/../templates/login.php';
                break;
            default:
                require __DIR__ . '/../templates/login.php';
        }
        exit;
    }
}

// Handle logout
if ($action === 'logout') {
    $auth->logout();
    header('Location: ?');
    exit;
}

// Protected routes check
$protected_routes = ['search', 'list', 'import', 'view', 'preview', 'add'];
if (in_array($action, $protected_routes) && !$auth->isLoggedIn()) {
    // Store the intended page for post-login redirect
    $_SESSION['intended_page'] = $_SERVER['REQUEST_URI'];

    // Set a friendly message
    Session::setMessage(match ($action) {
        'search' => 'Please log in to search the Discogs database.',
        'list' => 'Please log in to view your collection.',
        'import' => 'Please log in to import your collection.',
        'view', 'preview' => 'Please log in to view release details.',
        'add' => 'Please log in to add releases.',
        default => 'Please log in to access this feature.'
    });

    header('Location: ?action=login');
    exit;
}

// Function to create DiscogsService instance for authenticated user
function createDiscogsService(Auth $auth, Database $db): ?DiscogsService {
    if (!$auth->isLoggedIn()) {
        return null;
    }

    $userId = $auth->getCurrentUser()->id;
    $profile = $db->getUserProfile($userId);

    if (!$profile || empty($profile->discogsConsumerKey) || empty($profile->discogsConsumerSecret)) {
        Session::setMessage('Please set up your Discogs credentials in your profile.');
        header('Location: ?action=profile_edit');
        exit;
    }

    try {
        return new DiscogsService(
            consumerKey: $profile->discogsConsumerKey,
            consumerSecret: $profile->discogsConsumerSecret,
            userAgent: 'DiscogsHelper/1.0'
        );
    } catch (DiscogsCredentialsException $e) {
        Session::setErrors(['Invalid Discogs credentials. Please check your settings.']);
        header('Location: ?action=profile_edit');
        exit;
    }
}

function handleProfileUpdate(Auth $auth, Database $db): void
{
    if (!$auth->isLoggedIn()) {
        header('Location: ?action=login');
        exit;
    }

    $userId = $auth->getCurrentUser()->id;
    $profile = $db->getUserProfile($userId);
    $user = $auth->getCurrentUser();

    // Validate current password if changing password
    if (!empty($_POST['new_password'])) {
        if (empty($_POST['current_password'])) {
            Session::setErrors(['Current password is required to set a new password']);
            header('Location: ?action=profile_edit');
            exit;
        }

        if (!$user->verifyPassword($_POST['current_password'])) {
            Session::setErrors(['Current password is incorrect']);
            header('Location: ?action=profile_edit');
            exit;
        }

        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            Session::setErrors(['New passwords do not match']);
            header('Location: ?action=profile_edit');
            exit;
        }

        $db->updateUserPassword($userId, $_POST['new_password']);
        Logger::security('User password updated successfully');
    }

    // Check if Discogs username is being changed
    $newDiscogsUsername = trim($_POST['discogs_username'] ?? '');
    if ($newDiscogsUsername !== ($profile?->discogsUsername ?? '')) {
        try {
            // Validate Discogs credentials if provided
            if (!empty($newDiscogsUsername)) {
                $newConsumerKey = trim($_POST['discogs_consumer_key'] ?? '');
                $newConsumerSecret = trim($_POST['discogs_consumer_secret'] ?? '');

                if (empty($newConsumerKey) || empty($newConsumerSecret)) {
                    Session::setErrors(['Discogs API credentials are required when setting a username']);
                    header('Location: ?action=profile_edit');
                    exit;
                }

                // Validate the credentials
                if (!DiscogsService::validateCredentials(
                    $newConsumerKey,
                    $newConsumerSecret,
                    'DiscogsHelper/1.0'
                )) {
                    Session::setErrors(['Invalid Discogs API credentials']);
                    header('Location: ?action=profile_edit');
                    exit;
                }
            }

            // Create or update profile
            $updatedProfile = new UserProfile(
                id: $profile?->id,
                userId: $userId,
                location: trim($_POST['location'] ?? ''),
                discogsUsername: $newDiscogsUsername,
                discogsConsumerKey: trim($_POST['discogs_consumer_key'] ?? ''),
                discogsConsumerSecret: trim($_POST['discogs_consumer_secret'] ?? ''),
                createdAt: $profile?->createdAt ?? date('Y-m-d H:i:s'),
                updatedAt: date('Y-m-d H:i:s')
            );

            if ($profile === null) {
                $db->createUserProfile($updatedProfile);
            } else {
                $db->updateUserProfile($updatedProfile);
            }

            Logger::security('User profile updated successfully');
            Session::setMessage('Profile updated successfully');
            header('Location: ?action=profile');
            exit;

        } catch (DuplicateDiscogsUsernameException $e) {
            Session::setErrors(['This Discogs username is already registered']);
            header('Location: ?action=profile_edit');
            exit;
        }
    } else {
        // Update profile without changing Discogs username
        $updatedProfile = new UserProfile(
            id: $profile?->id,
            userId: $userId,
            location: trim($_POST['location'] ?? ''),
            discogsUsername: $profile?->discogsUsername,
            discogsConsumerKey: trim($_POST['discogs_consumer_key'] ?? '') ?: $profile?->discogsConsumerKey,
            discogsConsumerSecret: trim($_POST['discogs_consumer_secret'] ?? '') ?: $profile?->discogsConsumerSecret,
            createdAt: $profile?->createdAt ?? date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s')
        );

        if ($profile === null) {
            $db->createUserProfile($updatedProfile);
        } else {
            $db->updateUserProfile($updatedProfile);
        }

        Logger::security('User profile updated successfully');
        Session::setMessage('Profile updated successfully');
        header('Location: ?action=profile');
        exit;
    }
}

match ($action) {
    'search' => require __DIR__ . '/../templates/search.php',
    'view' => require __DIR__ . '/../templates/view.php',
    'preview' => require __DIR__ . '/../templates/preview.php',
    'add' => require __DIR__ . '/../templates/add.php',
    'import' => require __DIR__ . '/../templates/import.php',
    'list' => require __DIR__ . '/../templates/list.php',
    'login' => require __DIR__ . '/../templates/login.php',
    'register' => require __DIR__ . '/../templates/register.php',
    'profile' => require __DIR__ . '/../templates/profile.php',
    'profile_edit' => require __DIR__ . '/../templates/profile_edit.php',
    'profile_update' => handleProfileUpdate($auth, $db),
    default => require __DIR__ . '/../templates/index.php',
};