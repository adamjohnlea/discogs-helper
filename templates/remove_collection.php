<?php

declare(strict_types=1);

/** @var DiscogsHelper\Auth $auth Authentication instance */
/** @var DiscogsHelper\Database $db Database instance */
/** @var DiscogsHelper\Services\Discogs\DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;

// Set JSON content type early
header('Content-Type: application/json');

try {
    // Get JSON input
    $jsonInput = file_get_contents('php://input');
    Logger::log('Received remove collection request with input: ' . $jsonInput);

    if (empty($jsonInput)) {
        throw new RuntimeException('No data received');
    }

    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON data: ' . json_last_error_msg());
    }

    // Validate CSRF token
    Csrf::validateOrFail($data['csrf_token'] ?? null);

    if (!isset($data['id'])) {
        throw new RuntimeException('No release ID provided');
    }

    $releaseId = (int)$data['id'];
    $userId = $auth->getCurrentUser()->id;
    $profile = $db->getUserProfile($userId);

    if (!$profile || !$profile->discogsUsername) {
        throw new RuntimeException('Discogs username not found in profile');
    }

    // Get the instance ID from Discogs
    $instanceId = $discogs->getCollectionItemInstance($profile->discogsUsername, $releaseId);
    if ($instanceId) {
        // Remove from Discogs collection
        $discogs->removeFromCollection($profile->discogsUsername, $releaseId, $instanceId);
    }

    // Remove from local database
    $db->deleteRelease($userId, $releaseId);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    Logger::error('Remove collection error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 