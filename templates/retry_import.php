<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;

$userId = $auth->getCurrentUser()->id;
$importStateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$importStateId) {
    Session::setMessage('Invalid import state ID');
    header('Location: ?action=import');
    exit;
}

$importState = $db->getImportState($userId);
if (!$importState) {
    Session::setMessage('Import state not found');
    header('Location: ?action=import');
    exit;
}

$failedItems = $db->getFailedItems($userId);
if (empty($failedItems)) {
    Session::setMessage('No failed items to retry');
    header('Location: ?action=import');
    exit;
}

try {
    $profile = $db->getUserProfile($userId);
    if (!$profile || !$profile->discogsUsername) {
        throw new RuntimeException('Discogs username not found');
    }

    $retryCount = 0;
    $maxRetries = 3;
    $successCount = 0;

    foreach ($failedItems as $item) {
        try {
            // Get full release details
            $release = $discogs->getRelease($item['id']);
            
            // Check rate limits
            $discogs->handleRateLimit($response->getHeaders());
            
            // Download cover image if available
            $coverPath = null;
            if (!empty($release['images'][0]['uri'])) {
                try {
                    $coverPath = $discogs->downloadCover($release['images'][0]['uri']);
                    Logger::log("Downloaded cover for release {$item['id']}");
                } catch (Exception $e) {
                    Logger::error("Failed to download cover for release {$item['id']}: " . $e->getMessage());
                }
            }
            
            // Save to database
            $db->saveRelease(
                userId: $userId,
                discogsId: $release['id'],
                title: $release['title'],
                artist: implode(', ', array_column($release['artists'], 'name')),
                year: $release['year'] ?? null,
                format: $release['formats'][0]['name'],
                formatDetails: implode(', ', $release['formats'][0]['descriptions'] ?? []),
                coverPath: $coverPath,  // Now using the downloaded cover path
                notes: $release['notes'] ?? null,
                tracklist: json_encode($release['tracklist']),
                identifiers: json_encode($release['identifiers'] ?? []),
                dateAdded: date('Y-m-d H:i:s')
            );

            $successCount++;
            Logger::log("Successfully retried release {$item['id']}");

        } catch (Exception $e) {
            Logger::error("Failed to retry release {$item['id']}: " . $e->getMessage());
            continue;
        }
    }

    // Update message based on results
    if ($successCount === count($failedItems)) {
        Session::setMessage('Successfully retried all failed items');
    } else {
        Session::setMessage("Retried {$successCount} of " . count($failedItems) . " failed items");
    }

    header('Location: ?action=import');
    exit;

} catch (Exception $e) {
    Logger::error('Retry error: ' . $e->getMessage());
    Session::setMessage('Error retrying failed items: ' . $e->getMessage());
    header('Location: ?action=import');
    exit;
} 