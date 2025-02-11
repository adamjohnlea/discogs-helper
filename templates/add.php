<?php
/** @var DiscogsHelper\Auth $auth Authentication instance */
/** @var DiscogsHelper\Database $db Database instance */
/** @var DiscogsHelper\Services\Discogs\DiscogsService $discogs Discogs service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Controllers\ReleaseController;

// Determine if this is a JSON request
$isJsonRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isJsonRequest) {
    header('Content-Type: application/json');
}

if (!isset($_GET['id']) && !isset($_POST['id'])) {
    // Handle JSON request
    $jsonInput = file_get_contents('php://input');
    if (!empty($jsonInput)) {
        $data = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Validate CSRF token
            try {
                Csrf::validateOrFail($data['csrf_token'] ?? null);
            } catch (Exception $e) {
                if ($isJsonRequest) {
                    echo json_encode(['error' => 'Invalid security token']);
                    exit;
                }
                Session::setErrors(['Invalid security token']);
                header('Location: ?action=search');
                exit;
            }
            $_POST['id'] = $data['id'];
            $_POST['selected_image'] = $data['selected_image'] ?? null;
        }
    }

    if (!isset($_POST['id'])) {
        if ($isJsonRequest) {
            echo json_encode(['error' => 'No release ID provided']);
            exit;
        }
        header('Location: ?action=search');
        exit;
    }
}

try {
    if (!isset($discogs)) {
        throw new RuntimeException('Discogs service not available');
    }

    $releaseController = new ReleaseController($auth, $db, $discogs);
    $releaseController->processAdd();
    
    // If we get here and it's a JSON request, we need to send success response
    if ($isJsonRequest) {
        echo json_encode(['success' => true]);
        exit;
    }
    
    // For non-JSON requests, redirect to the collection
    header('Location: ?action=list');
    exit;

} catch (Exception $e) {
    Logger::error('Add release error: ' . $e->getMessage());
    if ($isJsonRequest) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    
    $error = $e->getMessage();
    $isDuplicate = $e instanceof DiscogsHelper\Exceptions\DuplicateReleaseException;
    
    // Only continue to HTML output for non-JSON requests
    if (!$isJsonRequest) {
        require __DIR__ . '/layout.php';
    }
    exit;
}