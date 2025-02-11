<?php

declare(strict_types=1);

/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;

header('Content-Type: application/json');

// Get JSON input
$jsonInput = file_get_contents('php://input');
Logger::log('Received remove wantlist request with input: ' . $jsonInput);

if (empty($jsonInput)) {
    Logger::error('Empty JSON input received');
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

$data = json_decode($jsonInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    Logger::error('Invalid JSON received: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Validate CSRF token
try {
    Csrf::validateOrFail($data['csrf_token'] ?? null);
} catch (Exception $e) {
    Logger::error('CSRF validation failed: ' . $e->getMessage());
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

try {
    $userId = $auth->getCurrentUser()->id;
    $discogsId = (int)($data['id'] ?? 0);
    Logger::log("Processing remove request for user {$userId}, discogs_id {$discogsId}");
    
    if ($discogsId <= 0) {
        throw new RuntimeException('Invalid release ID');
    }
    
    // Get the item before deleting to check if it exists
    $item = $db->getWantlistItem($userId, $discogsId);
    if (!$item) {
        throw new RuntimeException('Item not found in wantlist');
    }
    
    // Get user's Discogs username
    $profile = $db->getUserProfile($userId);
    Logger::log("Found user profile with Discogs username: " . ($profile ? $profile->discogsUsername : 'null'));
    
    if ($profile && $profile->discogsUsername) {
        try {
            // Remove from Discogs wantlist
            Logger::log("Attempting to remove item {$discogsId} from Discogs wantlist");
            $discogs->removeFromWantlist($profile->discogsUsername, $discogsId);
            Logger::log("Successfully removed item {$discogsId} from Discogs wantlist for user {$profile->discogsUsername}");
        } catch (Exception $e) {
            // Log the error but continue with local deletion
            Logger::error("Failed to remove item from Discogs wantlist: " . $e->getMessage());
        }
    }
    
    // Delete from database
    Logger::log("Attempting to delete item from local database");
    if (!$db->deleteWantlistItem($userId, $discogsId)) {
        throw new RuntimeException('Failed to remove item from wantlist');
    }
    Logger::log("Successfully deleted item from local database");
    
    // Delete cover image if it exists
    if (!empty($item['cover_path'])) {
        $coverPath = __DIR__ . '/../public/' . $item['cover_path'];
        if (file_exists($coverPath)) {
            unlink($coverPath);
            Logger::log("Deleted cover image: {$coverPath}");
        }
    }
    
    // Set success message in session
    Session::setMessage('Item removed from wantlist successfully');
    Logger::log("Remove operation completed successfully");
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    Logger::error('Error removing wantlist item: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 