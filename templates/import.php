<?php
// Set unlimited execution time for this script
set_time_limit(0);
ini_set('memory_limit', '256M');

session_start();

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
                    'per_page' => 1
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
                    if ($db->getDiscogsReleaseId((int)$item['id'])) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Discogs Collection</title>
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            font-size: 16px;
        }
        .button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .button:hover {
            background-color: #45a049;
        }
        .progress {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .progress-bar {
            background-color: #e9ecef;
            height: 20px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-bar-fill {
            background-color: #4CAF50;
            height: 100%;
            transition: width 0.3s ease;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        #status-message {
            margin-top: 10px;
            font-style: italic;
            color: #666;
        }
        .progress-details {
            margin: 10px 0;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Import Discogs Collection</h1>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
                <p>
                    <form method="post" action="?action=import" style="display: inline;">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($progress['username'] ?? '') ?>">
                        <button type="submit" class="button">Try Again</button>
                    </form>
                    <a href="?action=import&reset=1" class="button" style="background-color: #dc3545;">Start Over</a>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($progress): ?>
            <div class="progress">
                <h3>Importing collection for <?= htmlspecialchars($progress['username']) ?></h3>
                <div class="progress-details">
                    <p>Page <span id="current-page"><?= $progress['page'] ?></span> of <span id="total-pages"><?= $progress['total_pages'] ?></span></p>
                    <p>Processed <span id="processed-count"><?= $progress['processed'] ?></span> of <span id="total-count"><?= $progress['total'] ?></span> releases</p>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-bar-fill" id="progress-bar" style="width: <?= ($progress['processed'] / $progress['total']) * 100 ?>%"></div>
                </div>
                
                <div id="status-message"></div>
                
                <?php if (!($progress['processing'] ?? false)): ?>
                    <?php if (!isImportComplete($progress)): ?>
                        <p>
                            <form method="post" action="?action=import" style="display: inline;" id="continue-form">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($progress['username']) ?>">
                                <button type="submit" class="button">Continue Import</button>
                            </form>
                            <a href="?action=import&reset=1" class="button" style="background-color: #dc3545;">Start Over</a>
                        </p>
                    <?php else: ?>
                        <div class="success" style="margin-top: 20px; padding: 10px; background-color: #d4edda; color: #155724; border-radius: 4px;">
                            <p>Import completed successfully! 🎉</p>
                            <p>Imported <?= $progress['processed'] ?> releases from your collection.</p>
                            <p>
                                <a href="?action=list" class="button" style="background-color: #28a745;">View Collection</a>
                                <a href="?action=import&reset=1" class="button" style="background-color: #6c757d;">Start New Import</a>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($progress['processing'] ?? false): ?>
                <script>
                    function updateProgress() {
                        fetch('?action=import&check_progress=1')
                            .then(response => response.json())
                            .then(data => {
                                if (data.error) {
                                    document.getElementById('status-message').textContent = 'Error: ' + data.error;
                                    return;
                                }
                                
                                document.getElementById('current-page').textContent = data.page;
                                document.getElementById('processed-count').textContent = data.processed;
                                document.getElementById('progress-bar').style.width = 
                                    ((data.processed / data.total) * 100) + '%';
                                
                                if (data.current_action) {
                                    document.getElementById('status-message').textContent = data.current_action;
                                }
                                
                                if (data.processing) {
                                    setTimeout(updateProgress, 1000);
                                } else if (data.processed >= data.total) {
                                    window.location.href = '?action=import&completed=1';
                                } else {
                                    window.location.reload();
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                setTimeout(updateProgress, 2000);
                            });
                    }
                    
                    // Start progress updates
                    updateProgress();
                </script>
            <?php endif; ?>
        <?php else: ?>
            <p>Enter your Discogs username to import your collection.</p>
            <form method="post" action="?action=import">
                <div class="form-group">
                    <label for="username">Discogs Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <button type="submit" class="button">Import Collection</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 