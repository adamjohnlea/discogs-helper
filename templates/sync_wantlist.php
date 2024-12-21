<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logger;

header('Content-Type: application/json');

// Get JSON input
$jsonInput = file_get_contents('php://input');
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
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

try {
    $userId = $auth->getCurrentUser()->id;
    $profile = $db->getUserProfile($userId);
    
    if (!$profile || !$profile->discogsUsername) {
        throw new RuntimeException('Discogs username not set in profile');
    }
    
    $wantlist = $discogs->getWantlist($profile->discogsUsername);
    
    foreach ($wantlist['wants'] as $item) {
        $db->addWantlistItem($userId, [
            'id' => $item['id'],
            'artist' => $item['basic_information']['artists'][0]['name'] ?? 'Unknown Artist',
            'title' => $item['basic_information']['title'] ?? 'Unknown Title',
            'notes' => $item['notes'] ?? null,
            'rating' => $item['rating'] ?? null
        ]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    Logger::error('Wantlist sync error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 