<?php

declare(strict_types=1);

/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logging\Logger;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use DiscogsHelper\Http\Session;

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
    // Clear any existing messages at the start of sync
    Session::setMessage('');
    
    $userId = $auth->getCurrentUser()->id;
    $profile = $db->getUserProfile($userId);
    
    if (!$profile || !$profile->discogsUsername) {
        throw new RuntimeException('Discogs username not set in your profile');
    }
    
    // Set unlimited execution time for this script
    set_time_limit(0);
    ini_set('memory_limit', '256M');
    
    $page = 1;
    $processedItems = 0;
    $skippedItems = 0;
    
    do {
        // Add delay between requests to respect rate limits
        usleep(1000000); // 1 second delay
        
        try {
            $wantlist = $discogs->getWantlist($profile->discogsUsername, $page);
            
            if (!isset($wantlist['wants'])) {
                throw new RuntimeException('Unable to fetch wantlist data');
            }
            
            foreach ($wantlist['wants'] as $item) {
                try {
                    // Check if item already exists
                    $existingItem = $db->getWantlistItem($userId, (int)$item['id']);
                    if ($existingItem) {
                        Logger::log("Skipping existing wantlist item {$item['id']}");
                        $skippedItems++;
                        continue;
                    }

                    // Add delay between each release request
                    usleep(1000000); // 1 second delay
                    
                    // Get full release details to access cover images
                    $release = $discogs->getRelease($item['id']);
                    
                    // Debug log the notes data
                    Logger::log("Wantlist item {$item['id']} notes data:");
                    Logger::log("Item notes: " . ($item['notes'] ?? 'null'));
                    Logger::log("Release notes: " . ($release['notes'] ?? 'null'));
                    Logger::log("Basic info notes: " . ($release['basic_information']['notes'] ?? 'null'));
                    
                    // Format details
                    $formatDetails = array_map(function($format) {
                        return $format['name'] . (!empty($format['descriptions'])
                                ? ' (' . implode(', ', $format['descriptions']) . ')'
                                : '');
                    }, $release['formats']);
                    
                    // Download cover image if available
                    $coverPath = null;
                    if (!empty($release['images'][0]['uri'])) {
                        try {
                            // Use wantlist directory instead of covers
                            $coverUrl = $release['images'][0]['uri'];
                            $originalFilename = basename(parse_url($coverUrl, PHP_URL_PATH));
                            $uniqueId = time() . '_' . bin2hex(random_bytes(8));
                            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'jpg';
                            $filename = $uniqueId . '.' . $extension;
                            
                            $response = $discogs->client->get($coverUrl);
                            $savePath = __DIR__ . '/../public/images/wantlist/' . $filename;
                            file_put_contents($savePath, $response->getBody()->getContents());
                            
                            $coverPath = 'images/wantlist/' . $filename;
                        } catch (Exception $e) {
                            Logger::error("Error downloading wantlist cover: " . $e->getMessage());
                        }
                    }
                    
                    $db->addWantlistItem($userId, [
                        'id' => $item['id'],
                        'artist' => $item['basic_information']['artists'][0]['name'] ?? 'Unknown Artist',
                        'title' => $item['basic_information']['title'] ?? 'Unknown Title',
                        'notes' => $release['notes'] ?? null,
                        'rating' => $item['rating'] ?? null,
                        'cover_path' => $coverPath,
                        'year' => $release['year'] ?? null,
                        'format' => $release['formats'][0]['name'] ?? null,
                        'format_details' => implode(', ', $formatDetails),
                        'tracklist' => json_encode($release['tracklist'] ?? []),
                        'identifiers' => json_encode($release['identifiers'] ?? [])
                    ]);
                    
                    $processedItems++;
                    
                } catch (Exception $e) {
                    Logger::error("Error processing wantlist item {$item['id']}: " . $e->getMessage());
                    continue;
                }
            }
            
            $page++;
            $hasMorePages = $wantlist['pagination']['pages'] > $wantlist['pagination']['page'];
            
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                continue;
            }
            throw $e;
        }
        
    } while ($hasMorePages);
    
    echo json_encode([
        'success' => true,
        'processed' => $processedItems,
        'skipped' => $skippedItems
    ]);
    
    // Set appropriate sync completion message
    Session::setMessage("Wantlist sync completed: {$processedItems} items processed");
    
} catch (GuzzleException $e) {
    Logger::error('Wantlist sync error (HTTP): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to Discogs: ' . $e->getMessage()]);
} catch (Exception $e) {
    Logger::error('Wantlist sync error: ' . $e->getMessage());
    Session::setMessage('Error syncing wantlist: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 