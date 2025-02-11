<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Security\Csrf;

// Ensure user is logged in
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

// Verify CSRF token
try {
    Csrf::validateOrFail($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get the current user's ID
$userId = $auth->getCurrentUser()->id;

// Validate input
$releaseId = filter_input(INPUT_POST, 'releaseId', FILTER_VALIDATE_INT);
$artist = trim($_POST['artist'] ?? '');
$title = trim($_POST['title'] ?? '');

// Validate required fields
if (!$releaseId) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid release ID'
    ]);
    exit;
}

if ($artist === '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Artist is required'
    ]);
    exit;
}

if ($title === '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Title is required'
    ]);
    exit;
}

// Verify the release belongs to the current user
$release = $db->getReleaseById($userId, $releaseId);
if (!$release) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Release not found or access denied'
    ]);
    exit;
}

// Update the details
try {
    $success = $db->updateReleaseDetails($userId, $releaseId, $artist, $title);

    header('Content-Type: application/json');
    if ($success) {
        echo json_encode([
            'success' => true,
            'artist' => $artist,
            'title' => $title
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update release details'
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating release details'
    ]);
}
