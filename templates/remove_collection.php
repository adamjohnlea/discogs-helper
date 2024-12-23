<?php

declare(strict_types=1);

/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logger;
use DiscogsHelper\Session;

header('Content-Type: application/json');

// Get JSON input
$jsonInput = file_get_contents('php://input');
Logger::log('Received remove collection request with input: ' . $jsonInput);

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
    
    // Get the item before deleting to check if it exists and get its cover path
    $release = $db->getReleaseByDiscogsId($userId, $discogsId);
    Logger::log("Found release: " . ($release ? "yes" : "no"));
    if (!$release) {
        throw new RuntimeException('Item not found in collection');
    }
    Logger::log("Release details - ID: {$release->id}, Title: {$release->title}, Cover: {$release->coverPath}");
    
    // Get user's Discogs username
    $profile = $db->getUserProfile($userId);
    Logger::log("Found user profile with Discogs username: " . ($profile ? $profile->discogsUsername : 'null'));
    Logger::log("OAuth token present: " . ($profile && $profile->discogsOAuthToken ? "yes" : "no"));
    
    if ($profile && $profile->discogsUsername) {
        try {
            // Get the instance ID from Discogs
            Logger::log("Getting instance ID for release {$discogsId}");
            $instanceId = $discogs->getCollectionItemInstance($profile->discogsUsername, $discogsId);
            Logger::log("Instance ID found: " . ($instanceId ? $instanceId : "none"));
            
            if ($instanceId) {
                // Remove from Discogs collection
                Logger::log("Attempting to remove item {$discogsId} (instance {$instanceId}) from Discogs collection");
                $discogs->removeFromCollection($profile->discogsUsername, $discogsId, $instanceId);
                Logger::log("Successfully removed item {$discogsId} from Discogs collection for user {$profile->discogsUsername}");
            } else {
                Logger::log("Item {$discogsId} not found in Discogs collection");
            }
        } catch (Exception $e) {
            // Log the error but continue with local deletion
            Logger::error("Failed to remove item from Discogs collection: " . $e->getMessage());
            Logger::error("Error trace: " . $e->getTraceAsString());
        }
    }
    
    // Delete from database
    Logger::log("Attempting to delete item from local database");
    $deleteResult = $db->deleteRelease($userId, $discogsId);
    Logger::log("Delete result: " . ($deleteResult ? "success" : "failed"));
    if (!$deleteResult) {
        throw new RuntimeException('Failed to remove item from collection');
    }
    Logger::log("Successfully deleted item from local database");
    
    // Delete cover image if it exists
    if (!empty($release->coverPath)) {
        $coverPath = __DIR__ . '/../public/' . $release->coverPath;
        Logger::log("Checking for cover file: {$coverPath}");
        if (file_exists($coverPath)) {
            $unlinkResult = unlink($coverPath);
            Logger::log("Cover file deletion result: " . ($unlinkResult ? "success" : "failed"));
            if ($unlinkResult) {
                Logger::log("Deleted cover image: {$coverPath}");
            } else {
                Logger::error("Failed to delete cover image: {$coverPath}");
            }
        } else {
            Logger::log("Cover file not found: {$coverPath}");
        }
    }
    
    // Set success message in session
    Session::setMessage('Item removed from collection successfully');
    Logger::log("Remove operation completed successfully");
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    Logger::error('Error removing collection item: ' . $e->getMessage());
    Logger::error('Error trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 