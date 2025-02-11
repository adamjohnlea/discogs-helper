<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Exceptions\RateLimitExceededException;

// At the very top of the file, before any output
ob_start();

// Set longer timeout for the script
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', '300');

// Ensure we're sending JSON response
header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        // Clear any output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        
        Logger::error("Fatal error in batch process: " . json_encode($error));
        
        echo json_encode([
            'status' => 'error',
            'message' => 'A system error occurred. The import will automatically retry.',
            'error_details' => $error['message'] ?? 'Unknown error'
        ]);
    }
});

// Add error handler for non-fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    Logger::error("PHP Error ($errno): $errstr in $errfile on line $errline");
    
    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred. The import will automatically retry.',
        'error_details' => $errstr
    ]);
    exit;
});

Logger::log("Starting batch import process...");
Logger::log("Discogs service status: " . (isset($discogs) ? 'available' : 'not available'));

if (!isset($discogs) || !$discogs instanceof DiscogsService) {
    Logger::error("Discogs service not available in process_import_batch");
    echo json_encode(['error' => 'Discogs service not available']);
    exit;
}

$userId = $auth->getCurrentUser()->id;
$importStateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$importStateId) {
    echo json_encode(['error' => 'Invalid import state ID']);
    exit;
}

$importState = $db->getImportState($userId);
if (!$importState || $importState['status'] !== 'pending') {
    echo json_encode(['error' => 'No pending import found']);
    exit;
}

$processedItems = $importState['processed_items']; // Initialize counter

try {
    $profile = $db->getUserProfile($userId);
    Logger::log("User profile check: " . json_encode([
        'hasProfile' => (bool)$profile,
        'hasUsername' => $profile ? (bool)$profile->discogsUsername : false,
        'hasConsumerKey' => $profile ? (bool)$profile->discogsConsumerKey : false,
        'hasConsumerSecret' => $profile ? (bool)$profile->discogsConsumerSecret : false,
    ]));
    if (!$profile || !$profile->discogsUsername) {
        throw new RuntimeException('Discogs username not found');
    }

    // Get the current page of releases
    $response = $discogs->client->get("/users/{$profile->discogsUsername}/collection/folders/0/releases", [
        'query' => [
            'page' => $importState['current_page'],
            'per_page' => 5
        ]
    ]);

    $data = json_decode($response->getBody()->getContents(), true);
    if (!isset($data['releases'])) {
        throw new RuntimeException('Unable to fetch collection data');
    }

    $coverStats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0
    ];

    // Process each release
    foreach ($data['releases'] as $item) {
        try {
            Logger::log("Processing release: " . $item['id']);
            
            // Add more detailed logging
            Logger::log("Release data: " . json_encode($item));
            
            // Check if release already exists
            $existingRelease = $db->getReleaseByDiscogsId($userId, $item['id']);
            if ($existingRelease) {
                Logger::log("Release {$item['id']} already exists in collection");
                $processedItems++;
                continue;
            }
            
            // Get full release details
            try {
                $release = $discogs->getRelease($item['id']);
                Logger::log("Got release details for {$item['id']}");
            } catch (Exception $e) {
                Logger::error("Failed to get release details: " . $e->getMessage());
                throw $e;
            }
            
            // Handle cover image with better error handling
            $coverPath = null;
            if (!empty($release['images'][0]['uri'])) {
                $coverStats['total']++;
                try {
                    $coverPath = $discogs->downloadCover($release['images'][0]['uri']);
                    if ($coverPath) {
                        $coverStats['success']++;
                        Logger::log("Successfully downloaded cover for {$item['id']}");
                    } else {
                        $coverStats['failed']++;
                        Logger::log("Cover download returned null for {$item['id']}");
                    }
                } catch (Exception $e) {
                    Logger::error("Cover download failed for {$item['id']}: " . $e->getMessage());
                    $coverStats['failed']++;
                }
            }

            // Parse the date_added with validation
            try {
                $dateAdded = isset($item['date_added']) && strtotime($item['date_added'])
                    ? date('Y-m-d H:i:s', strtotime($item['date_added']))
                    : date('Y-m-d H:i:s');
                Logger::log("Date added for {$item['id']}: $dateAdded");
            } catch (Exception $e) {
                Logger::error("Date parsing failed for {$item['id']}: " . $e->getMessage());
                $dateAdded = date('Y-m-d H:i:s');
            }

            // Save to database with validation
            try {
                $db->saveRelease(
                    userId: $userId,
                    discogsId: $release['id'],
                    title: $release['title'],
                    artist: implode(', ', array_column($release['artists'], 'name')),
                    year: $release['year'] ?? null,
                    format: $release['formats'][0]['name'] ?? 'Unknown',
                    formatDetails: implode(', ', $release['formats'][0]['descriptions'] ?? []),
                    coverPath: $coverPath,
                    notes: $release['notes'] ?? null,
                    tracklist: json_encode($release['tracklist'] ?? []),
                    identifiers: json_encode($release['identifiers'] ?? []),
                    dateAdded: $dateAdded
                );
                Logger::log("Successfully saved release {$item['id']} to database");
            } catch (Exception $e) {
                Logger::error("Database save failed for {$item['id']}: " . $e->getMessage());
                throw $e;
            }

            $processedItems++;
            
            // Update progress
            $db->updateImportProgress(
                userId: $userId,
                currentPage: $importState['current_page'],
                processedItems: $processedItems,
                lastProcessedId: $release['id'],
                coverStats: $coverStats
            );

        } catch (Exception $e) {
            Logger::error("Failed to process release {$item['id']}: " . $e->getMessage());
            $db->addFailedItem($userId, $item['id'], $e->getMessage());
            $processedItems++;
        }
    }

    // After processing releases
    Logger::log("Batch complete. Stats: " . json_encode([
        'currentPage' => $importState['current_page'],
        'totalPages' => $importState['total_pages'],
        'processedItems' => $processedItems,
        'totalItems' => $importState['total_items'],
        'coverStats' => $coverStats
    ]));

    // Check if we're done
    if ($processedItems >= $importState['total_items']) {
        Logger::log("Import completed with {$processedItems} items processed");
        $db->completeImport($userId);
        echo json_encode(['status' => 'completed']);
    } else {
        Logger::log("Moving to next page: " . ($importState['current_page'] + 1));
        // Move to next page
        $db->updateImportProgress(
            userId: $userId,
            currentPage: $importState['current_page'] + 1,
            processedItems: $processedItems,
            lastProcessedId: null,
            coverStats: $coverStats
        );
        echo json_encode([
            'status' => 'pending', 
            'nextPage' => $importState['current_page'] + 1,
            'processedItems' => $processedItems,
            'totalItems' => $importState['total_items']
        ]);
    }

} catch (Exception $e) {
    Logger::error('Import error: ' . $e->getMessage());
    
    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Import error occurred. The process will automatically retry.',
        'error_details' => $e->getMessage()
    ]);
    exit;
} 