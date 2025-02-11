<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Database\DatabaseSetup;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Security\Auth;
use DiscogsHelper\Models\UserProfile;
use DiscogsHelper\Generator\StaticCollectionGenerator;
use DiscogsHelper\Exceptions\DiscogsCredentialsException;
use DiscogsHelper\Exceptions\DuplicateDiscogsUsernameException;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Controllers\ReleaseController;
use DiscogsHelper\Services\LastFmService;
use DiscogsHelper\Controllers\RecommendationsController;

// Initialize logger and session
Logger::initialize(__DIR__ . '/..');
Logger::log('Application startup');
Session::initialize();

// Check if this is a static collection request
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/collections/([^/]+)(?:/releases/(\d+))?$#', $path, $matches)) {
    $username = $matches[1];
    $releaseId = $matches[2] ?? null;
    
    // Determine the static file path
    $staticPath = $releaseId 
        ? __DIR__ . "/collections/$username/releases/$releaseId.html"
        : __DIR__ . "/collections/$username/index.html";
    
    if (file_exists($staticPath)) {
        // Serve the static file
        readfile($staticPath);
        exit;
    }
    
    // If file doesn't exist, try to generate it
    $config = require __DIR__ . '/../config/config.php';
    $db = new Database($config['database']['path']);
    $auth = new Auth($db);
    
    // Find user by username
    $user = $db->findUserByUsername($username);
    if ($user) {
        $generator = new StaticCollectionGenerator($db, $auth);
        $generator->generateForUser($user['id']);
        
        // Try serving the file again
        if (file_exists($staticPath)) {
            readfile($staticPath);
            exit;
        }
    }
    
    // If we still can't serve the file, show 404
    http_response_code(404);
    echo 'Collection not found';
    exit;
}

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$config = require __DIR__ . '/../config/config.php';

// Initialize the database if it doesn't exist
DatabaseSetup::initialize($config['database']['path']);

$db = new Database($config['database']['path']);
$auth = new Auth($db);

// Create DiscogsService instance for routes that need it
$discogs = null;
if (in_array($action, [
    'search', 'import', 'view', 'preview', 'add', 
    'sync_wantlist', 'remove_wantlist', 'remove_collection', 
    'wantlist', 'process_import', 'resume_import', 
    'check_import_progress', 'process_import_batch'
])) {
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
$protected_routes = [
    'search', 'list', 'import', 'view', 'preview', 'add', 
    'process-edit', 'process-edit-details', 'wantlist', 
    'sync_wantlist', 'view_wantlist', 'process-wantlist-notes', 
    'remove_wantlist', 'remove_collection', 'recommendations',
    'process_import', 'resume_import', 'check_import_progress'
];
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
function createDiscogsService(Auth $auth, Database $db): ?DiscogsHelper\Services\Discogs\DiscogsService {
    if (!$auth->isLoggedIn()) {
        Logger::error('Discogs service not available: User not logged in');
        return null;
    }

    $userId = $auth->getCurrentUser()->id;
    $profile = $db->getUserProfile($userId);

    Logger::log('Creating Discogs service with profile data: ' . json_encode([
        'has_profile' => $profile !== null,
        'has_consumer_key' => !empty($profile?->discogsConsumerKey),
        'has_consumer_secret' => !empty($profile?->discogsConsumerSecret),
        'has_oauth_token' => !empty($profile?->discogsOAuthToken),
        'has_oauth_token_secret' => !empty($profile?->discogsOAuthTokenSecret)
    ]));

    if (!$profile || empty($profile->discogsConsumerKey) || empty($profile->discogsConsumerSecret)) {
        Logger::error('Discogs service not available: Missing consumer credentials');
        Session::setMessage('Please set up your Discogs credentials in your profile.');
        header('Location: ?action=profile_edit');
        exit;
    }

    try {
        $service = new DiscogsService(
            consumerKey: $profile->discogsConsumerKey,
            consumerSecret: $profile->discogsConsumerSecret,
            userAgent: 'DiscogsHelper/1.0',
            oauthToken: $profile->discogsOAuthToken,
            oauthTokenSecret: $profile->discogsOAuthTokenSecret
        );
        Logger::log('Successfully created Discogs service');
        return $service;
    } catch (DiscogsCredentialsException $e) {
        Logger::error('Discogs service creation failed: ' . $e->getMessage());
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
                $credentialsValid = DiscogsHelper\Services\Discogs\DiscogsService::validateCredentials(
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

    // Validate Last.fm credentials if provided
    if (!empty($_POST['lastfm_api_key']) || !empty($_POST['lastfm_api_secret'])) {
        // Both key and secret must be provided together
        if (empty($_POST['lastfm_api_key']) || empty($_POST['lastfm_api_secret'])) {
            $errors[] = 'Both Last.fm API Key and API Secret must be provided';
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
            $lastfmApiKey = !empty($_POST['lastfm_api_key']) ? trim($_POST['lastfm_api_key']) : null;
            $lastfmApiSecret = !empty($_POST['lastfm_api_secret']) ? trim($_POST['lastfm_api_secret']) : null;

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
                lastfmApiKey: $lastfmApiKey,
                lastfmApiSecret: $lastfmApiSecret,
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
    'add' => (function() use ($auth, $db, $discogs) {
        $releaseController = new ReleaseController($auth, $db, $discogs);
        return $releaseController->processAdd();
    })(),
    'recommendations' => (function() use ($auth, $db) {
        $userId = $auth->getCurrentUser()->id;
        $profile = $db->getUserProfile($userId);
        
        if (!$profile || !$profile->hasLastFmCredentials()) {
            Session::setMessage('Please set up your Last.fm API credentials in your profile first.');
            header('Location: ?action=profile_edit');
            exit;
        }

        // Check if regenerating
        $regenerate = isset($_GET['regenerate']) && $_GET['regenerate'] === '1';
        if ($regenerate) {
            Csrf::validateOrFail($_GET['csrf_token'] ?? null);
        }

        $lastfm = new LastFmService($db, $userId);
        $recommendationsController = new RecommendationsController($auth, $db, $lastfm);
        $recommendations = $recommendationsController->getRecommendations($regenerate);
        
        if ($regenerate) {
            Session::setMessage('Recommendations have been regenerated.');
            header('Location: ?action=recommendations');
            exit;
        }
        
        require __DIR__ . '/../templates/recommendations.php';
    })(),
    'generate_static' => (function() use ($auth, $db) {
        requireAuth();
        
        // Validate CSRF token
        $data = json_decode(file_get_contents('php://input'), true);
        Csrf::validateOrFail($data['csrf_token'] ?? null);
        
        // Generate static pages
        $generator = new StaticCollectionGenerator($db, $auth);
        $generator->generateForUser($auth->getCurrentUser()->id);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    })(),
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
    'process_import' => require __DIR__ . '/../templates/process_import.php',
    'check_import_progress' => require __DIR__ . '/../templates/check_import_progress.php',
    'resume_import' => require __DIR__ . '/../templates/resume_import.php',
    'process_import_batch' => require __DIR__ . '/../templates/process_import_batch.php',
    default => require __DIR__ . '/../templates/index.php',
};