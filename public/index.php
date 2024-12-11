<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DiscogsHelper\Database;
use DiscogsHelper\DatabaseSetup;
use DiscogsHelper\DiscogsService;
use DiscogsHelper\Auth;
use DiscogsHelper\UserProfile;
use DiscogsHelper\Exceptions\DuplicateDiscogsUsernameException;

$config = require __DIR__ . '/../config/config.php';

// Initialize the database if it doesn't exist
DatabaseSetup::initialize($config['database']['path']);

$db = new Database($config['database']['path']);
$discogs = new DiscogsService(
    $config['discogs']['consumer_key'],
    $config['discogs']['consumer_secret'],
    $config['discogs']['user_agent']
);
$auth = new Auth($db);

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle POST requests for login and register
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
        // Initialize auth_message from session if it exists
        $auth_message = $_SESSION['auth_message'] ?? null;

        if ($auth->login($_POST['username'], $_POST['password'])) {
            // Redirect to intended page if set, otherwise go to list
            $intended = $_SESSION['intended_page'] ?? '?action=list';
            unset($_SESSION['intended_page']);
            unset($_SESSION['auth_message']); // Clear the message
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
$protected_routes = ['search', 'list', 'import', 'view', 'preview', 'add'];
if (in_array($action, $protected_routes) && !$auth->isLoggedIn()) {
    // Store the intended page for post-login redirect
    $_SESSION['intended_page'] = $_SERVER['REQUEST_URI'];

    // Set a friendly message
    $_SESSION['auth_message'] = match ($action) {
        'search' => 'Please log in to search the Discogs database.',
        'list' => 'Please log in to view your collection.',
        'import' => 'Please log in to import your collection.',
        'view', 'preview' => 'Please log in to view release details.',
        'add' => 'Please log in to add releases.',
        default => 'Please log in to access this feature.'
    };

    header('Location: ?action=login');
    exit;
}

// Update login template to show auth message if exists
$auth_message = null;
if (isset($_SESSION['auth_message'])) {
    $auth_message = $_SESSION['auth_message'];
    unset($_SESSION['auth_message']);
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

    // Handle profile updates if no password errors
    if (empty($errors)) {
        try {
            // Start with current profile or create new one
            $updatedProfile = $currentProfile ?? UserProfile::create(
                userId: $userId
            );

            // Create updated profile with new values
            $updatedProfile = new UserProfile(
                id: $updatedProfile->id,
                userId: $userId,
                location: $_POST['location'] ?: null,
                discogsUsername: $_POST['discogs_username'] ?: null,
                discogsConsumerKey: $_POST['discogs_consumer_key'] ?: null,
                discogsConsumerSecret: $_POST['discogs_consumer_secret'] ?: null,
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

            // Redirect with success message
            header('Location: ?action=profile&success=true');
            exit;

        } catch (DuplicateDiscogsUsernameException $e) {
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            // Log the error for debugging
            error_log($e->getMessage());
            $errors[] = 'An error occurred while saving your profile';
        }
    }

    // If we get here, there were errors
    // Store errors in session and redirect back to form
    $_SESSION['profile_errors'] = $errors;
    header('Location: ?action=profile_edit');
    exit;
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