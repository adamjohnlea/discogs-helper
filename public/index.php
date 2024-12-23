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
use DiscogsHelper\Security\Csrf;

// Initialize logger and session
Logger::initialize(__DIR__ . '/..');
Logger::log('Application startup');
Session::initialize();

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$config = require __DIR__ . '/../config/config.php';

// Initialize the database if it doesn't exist
DatabaseSetup::initialize($config['database']['path']);

$db = new Database($config['database']['path']);
$auth = new Auth($db);

// Create DiscogsService instance for routes that need it
$discogs = null;
if (in_array($action, ['search', 'import', 'view', 'preview', 'add', 'sync_wantlist', 'remove_wantlist', 'remove_collection'])) {
    $discogs = createDiscogsService($auth, $db);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        if ($auth->login($_POST['username'], $_POST['password'])) {
            // Redirect to intended page if set, otherwise go to list
            $intended = $_SESSION['intended_page'] ?? '?action=list';
            unset($_SESSION['intended_page']);
            header('Location: '.$intended);
            exit;
        }

        $error = '<div class="error">Invalid username or password</div>';
        require __DIR__.'/../templates/login.php';
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
$protected_routes = ['search', 'list', 'import', 'view', 'preview', 'add', 'process-edit', 'process-edit-details', 'wantlist', 'sync_wantlist', 'view_wantlist', 'process-wantlist-notes', 'remove_wantlist', 'remove_collection'];
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
        'process-edit' => 'Please log in to modify releases.',
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
            userAgent: 'DiscogsHelper/1.0',
            oauthToken: $profile->discogsOAuthToken,
            oauthTokenSecret: $profile->discogsOAuthTokenSecret
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
    $currentProfile = $db->getUserProfile($userId);
    $errors = [];

    // Validate Discogs credentials if provided
    if (!empty($_POST['discogs_consumer_key']) || !empty($_POST['discogs_consumer_secret'])) {
        // Both key and secret must be provided together
        if (empty($_POST['discogs_consumer_key']) || empty($_POST['discogs_consumer_secret'])) {
            $errors[] = 'Both Discogs Consumer Key and Consumer Secret must be provided';
        } else {
            try {
                $credentialsValid = DiscogsService::validateCredentials(
                    consumerKey: $_POST['discogs_consumer_key'],
                    consumerSecret: $_POST['discogs_consumer_secret'],
                    userAgent: 'DiscogsHelper/1.0'
                );

                if (!$credentialsValid) {
                    $errors[] = 'The provided Discogs credentials are invalid. Please check them and try again.';
                }
            } catch (Exception $e) {
                Logger::error('Discogs credential validation failed: ' . $e->getMessage());
                $errors[] = 'Unable to verify Discogs credentials. Please try again later.';
            }
        }
    }

    // Validate password change if attempted
    $passwordChanged = false;
    if (!empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
        if (empty($_POST['current_password'])) {
            $errors[] = 'Current password is required to change password';
        } elseif (!$auth->getCurrentUser()->verifyPassword($_POST['current_password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $errors[] = 'New passwords do not match';
        } elseif (strlen($_POST['new_password']) < 8) {
            $errors[] = 'New password must be at least 8 characters long';
        } else {
            $passwordChanged = true;
        }
    }

    // Handle profile updates if no errors
    if (empty($errors)) {
        try {
            // Start with current profile or create new one
            $updatedProfile = $currentProfile ?? UserProfile::create(
                userId: $userId
            );

            // Fix for empty string to null conversion
            $discogsUsername = !empty($_POST['discogs_username']) ? trim($_POST['discogs_username']) : null;
            $location = !empty($_POST['location']) ? trim($_POST['location']) : null;
            $consumerKey = !empty($_POST['discogs_consumer_key']) ? trim($_POST['discogs_consumer_key']) : null;
            $consumerSecret = !empty($_POST['discogs_consumer_secret']) ? trim($_POST['discogs_consumer_secret']) : null;

            // Create updated profile with new values
            $updatedProfile = new UserProfile(
                id: $updatedProfile->id,
                userId: $userId,
                location: $location,
                discogsUsername: $discogsUsername,
                discogsConsumerKey: $consumerKey,
                discogsConsumerSecret: $consumerSecret,
                discogsOAuthToken: $updatedProfile->discogsOAuthToken,
                discogsOAuthTokenSecret: $updatedProfile->discogsOAuthTokenSecret,
                createdAt: $updatedProfile->createdAt,
                updatedAt: date('Y-m-d H:i:s')
            );

            // Update or create profile
            if ($currentProfile) {
                $db->updateUserProfile($updatedProfile);
            } else {
                $db->createUserProfile($updatedProfile);
            }

            // Handle password update if needed
            if ($passwordChanged) {
                $db->updateUserPassword(
                    userId: $userId,
                    newPassword: $_POST['new_password']
                );
            }

            // Add success message
            Session::setMessage('Profile updated successfully');
            header('Location: ?action=profile&success=true');
            exit;

        } catch (DuplicateDiscogsUsernameException $e) {
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            Logger::error('Profile Update Error: ' . $e->getMessage());
            $errors[] = 'An error occurred while saving your profile';
        }
    }

    // If we get here, there were errors
    Session::setErrors($errors);
    header('Location: ?action=profile_edit');
    exit;
}

function requireAuth(): void {
    global $auth;
    if (!$auth->isLoggedIn()) {
        $_SESSION['intended_page'] = $_SERVER['REQUEST_URI'];
        Session::setMessage('Please log in to access this feature.');
        header('Location: ?action=login');
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
    'process-edit' => require __DIR__ . '/../templates/process-edit.php',
    'process-edit-details' => require __DIR__ . '/../templates/process-edit-details.php',
    'profile_update' => handleProfileUpdate($auth, $db),
    'wantlist' => require __DIR__ . '/../templates/wantlist.php',
    'sync_wantlist' => require __DIR__ . '/../templates/sync_wantlist.php',
    'remove_wantlist' => require __DIR__ . '/../templates/remove_wantlist.php',
    'view_wantlist' => require __DIR__ . '/../templates/view_wantlist.php',
    'process-wantlist-notes' => require __DIR__ . '/../templates/process-wantlist-notes.php',
    'discogs_auth' => require __DIR__ . '/../templates/discogs_auth.php',
    'remove_collection' => require __DIR__ . '/../templates/remove_collection.php',
    default => require __DIR__ . '/../templates/index.php',
};