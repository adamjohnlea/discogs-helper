<?php
// Set unlimited execution time for this script
set_time_limit(0);
ini_set('memory_limit', '256M');

// Replace direct session_start() with a check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only reset session if explicitly requested
if (isset($_GET['reset'])) {
    unset($_SESSION['import_progress']);
}

// AJAX progress check
if (isset($_GET['check_progress']) && isset($_SESSION['import_progress'])) {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['import_progress']);
    exit;
}

// Recovery check
if (isset($_SESSION['import_progress']['last_update'])) {
    if (time() - $_SESSION['import_progress']['last_update'] > 30) {
        $_SESSION['import_progress']['processing'] = false;
    }
}

function logImportError($message): void {
    $logFile = __DIR__ . '/../logs/import.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function isImportComplete($progress): bool {
    return $progress['processed'] >= $progress['total'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])) {
    try {
        $username = trim($_POST['username']);
        
        // Initialize import only if not already in progress
        if (!isset($_SESSION['import_progress'])) {
            // First, get the total number of items
            $response = $discogs->client->get("/users/{$username}/collection/folders/0/releases", [
                'query' => [
                    'page' => 1,
                    'per_page' => 50  // Set this to match your batch size
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['pagination'])) {
                throw new RuntimeException('Unable to fetch collection data');
            }

            $_SESSION['import_progress'] = [
                    'username' => $username,
                    'page' => 1,
                    'total_pages' => $data['pagination']['pages'],
                    'processed' => 0,
                    'total' => $data['pagination']['items'],
                    'processing' => false,
                    'last_update' => time(),
                    'current_action' => 'Starting import...',
                    'items_per_batch' => 5  // Process 5 items at a time
            ];
        }

        $progress = &$_SESSION['import_progress'];
        
        if (!$progress['processing']) {
            $progress['processing'] = true;
            $progress['last_update'] = time();

            // Fetch current page
            $response = $discogs->client->get("/users/{$username}/collection/folders/0/releases", [
                'query' => [
                    'page' => $progress['page'],
                    'per_page' => 50
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            // Process only a small batch of items
            $batch_start = ($progress['processed'] % 50);
            $batch_end = min($batch_start + $progress['items_per_batch'], count($data['releases']));
            
            for ($i = $batch_start; $i < $batch_end; $i++) {
                $item = $data['releases'][$i];
                
                try {
                    // Check if release already exists before making any API calls
                    $currentUserId = $auth->getCurrentUser()->id;

                    // Check if release already exists before making any API calls
                    if ($db->getDiscogsReleaseId($currentUserId, (int)$item['id'])) {
                        logImportError("Skipping existing release {$item['id']} (early check)");
                        $progress['processed']++;
                        $progress['last_update'] = time();
                        $_SESSION['import_progress'] = $progress;
                        continue;
                    }

                    $progress['current_action'] = "Fetching release {$item['id']}...";
                    $_SESSION['import_progress'] = $progress;
                    
                    $release = $discogs->getRelease($item['id']);
                    
                    $progress['current_action'] = "Processing release details...";
                    $_SESSION['import_progress'] = $progress;
                    
                    $formatDetails = array_map(function($format) {
                        return $format['name'] . (!empty($format['descriptions']) 
                            ? ' (' . implode(', ', $format['descriptions']) . ')'
                            : '');
                    }, $release['formats']);

                    $coverPath = null;
                    if (!empty($release['images'][0]['uri'])) {
                        $progress['current_action'] = "Downloading cover image for {$release['title']}...";
                        $_SESSION['import_progress'] = $progress;
                        try {
                            $coverPath = $discogs->downloadCover($release['images'][0]['uri']);
                            if (!$coverPath) {
                                logImportError("Failed to download cover for release {$release['id']}: {$release['title']}");
                            }
                        } catch (Exception $e) {
                            logImportError("Error downloading cover for release {$release['id']}: " . $e->getMessage());
                        }
                    } else {
                        logImportError("No cover image URI found for release {$release['id']}: {$release['title']}");
                    }

                    // Verify cover file exists after download
                    if ($coverPath && !file_exists($coverPath)) {
                        logImportError("Cover file missing after download for release {$release['id']}: {$coverPath}");
                        $coverPath = null;
                    }

                    // Add logging for each release processing
                    logImportError("Processing release {$release['id']}: {$release['title']} (Page {$progress['page']}, Item {$i})");

                    $progress['current_action'] = "Saving to database...";
                    $_SESSION['import_progress'] = $progress;

                    $db->saveRelease(
                        userId: $currentUserId,
                        discogsId: (int)$release['id'],
                        title: $release['title'],
                        artist: implode(', ', array_column($release['artists'], 'name')),
                        year: isset($release['year']) ? (int)$release['year'] : null,
                        format: $release['formats'][0]['name'],
                        formatDetails: implode(', ', $formatDetails),
                        coverPath: $coverPath,
                        notes: $release['notes'] ?? null,
                        tracklist: json_encode($release['tracklist']),
                        identifiers: json_encode($release['identifiers'] ?? []),
                        dateAdded: $item['date_added']
                    );
                    
                    $progress['processed']++;
                    $progress['last_update'] = time();
                    $_SESSION['import_progress'] = $progress;
                    
                } catch (DiscogsHelper\Exceptions\DuplicateReleaseException $e) {
                    logImportError("Duplicate release found: {$item['id']} (database check)");
                    $progress['processed']++;
                    continue;
                } catch (Exception $e) {
                    logImportError("Error processing release: " . $e->getMessage());
                    throw $e;
                }
            }

            // Check if we need to move to next page
            if ($batch_end >= count($data['releases'])) {
                if (isImportComplete($progress)) {
                    // Import complete - don't destroy session or redirect
                    $progress['processing'] = false;
                    $progress['current_action'] = "Import completed successfully!";
                    $_SESSION['import_progress'] = $progress;
                    header('Location: ?action=import&completed=1');
                    exit;
                } else {
                    $progress['page']++;
                }
            }
            
            $progress['processing'] = false;
            $_SESSION['import_progress'] = $progress;
            
            // Redirect to continue processing
            header('Location: ?action=import');
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        $progress['processing'] = false;
        $progress['current_action'] = "Error: " . $error;
        $_SESSION['import_progress'] = $progress;
    }
}

$progress = $_SESSION['import_progress'] ?? null;

$content = '
<h1>Import Discogs Collection</h1>

' . (isset($error) ? '
<div role="alert">
    ' . htmlspecialchars($error) . '
    <form method="post" action="?action=import">
        <input type="hidden" name="username" value="' . htmlspecialchars($progress['username'] ?? '') . '">
        <button type="submit">Try Again</button>
        <a href="?action=import&reset=1" role="button" class="secondary">Start Over</a>
    </form>
</div>' : '') . '

' . ($progress ? '
<article>
    <h2>Importing collection for ' . htmlspecialchars($progress['username']) . '</h2>
    <div>
    <p>Processing page <span id="current-page">' . $progress['page'] . '</span> of <span id="total-pages">' . $progress['total_pages'] . '</span></p>
    <p>Imported <span id="processed-count">' . $progress['processed'] . '</span> of <span id="total-count">' . $progress['total'] . '</span> releases</p>
</div>
    
    <progress id="progress-bar" value="' . $progress['processed'] . '" max="' . $progress['total'] . '"></progress>
    
    <p id="status-message" aria-live="polite"></p>
    
    ' . (!($progress['processing'] ?? false) ? '
        ' . (!isImportComplete($progress) ? '
        <form method="post" action="?action=import" id="continue-form">
            <input type="hidden" name="username" value="' . htmlspecialchars($progress['username']) . '">
            <button type="submit">Continue Import</button>
            <a href="?action=import&reset=1" role="button" class="secondary">Start Over</a>
        </form>
        ' : '
        <div role="alert">
            <p>Import completed successfully! 🎉</p>
            <p>Imported ' . $progress['processed'] . ' releases from your collection.</p>
            <a href="?action=list" role="button">View Collection</a>
            <a href="?action=import&reset=1" role="button" class="secondary">Start New Import</a>
        </div>
        ') . '
    ' : '') . '
</article>

' . ($progress['processing'] ?? false ? '
<script>
    function updateProgress() {
        fetch("?action=import&check_progress=1")
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById("status-message").textContent = "Error: " + data.error;
                    return;
                }
                
                document.getElementById("current-page").textContent = data.page;
                document.getElementById("processed-count").textContent = data.processed;
                document.getElementById("progress-bar").value = data.processed;
                
                if (data.current_action) {
                    document.getElementById("status-message").textContent = data.current_action;
                }
                
                if (data.processing) {
                    setTimeout(updateProgress, 1000);
                } else if (data.processed >= data.total) {
                    window.location.href = "?action=import&completed=1";
                } else {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error("Error:", error);
                setTimeout(updateProgress, 2000);
            });
    }
    
    updateProgress();
</script>
' : '') . '

' : '
<article>
    <p>Enter your Discogs username to import your collection.</p>
    <form method="post" action="?action=import">
        <label for="username">Discogs Username:</label>
        <input type="text" 
               id="username" 
               name="username" 
               required 
               placeholder="Enter your Discogs username...">
        <button type="submit">Import Collection</button>
    </form>
</article>
') . '
';

require __DIR__ . '/layout.php'; 