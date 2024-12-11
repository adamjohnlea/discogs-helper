<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var array|null $progress Import progress data */
/** @var string|null $error Error message */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\DiscogsService;
use DiscogsHelper\Logger;
use DiscogsHelper\Session;
use GuzzleHttp\Exception\GuzzleException;

// Check if user has valid Discogs credentials
if (!isset($discogs)) {
    Session::setMessage('Please set up your Discogs credentials in your profile to import your collection.');
    header('Location: ?action=profile_edit');
    exit;
}

// Set unlimited execution time for this script
set_time_limit(0);
ini_set('memory_limit', '256M');

Session::initialize();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])) {
    try {
        $username = trim($_POST['username']);

        // Initialize import only if not already in progress
        if (!isset($_SESSION['import_progress'])) {
            try {
                // First, get the total number of items
                $response = $discogs->client->get("/users/$username/collection/folders/0/releases", [
                    'query' => [
                        'page' => 1,
                        'per_page' => 50
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
                    'items_per_batch' => 5
                ];
            } catch (GuzzleException $e) {
                throw new RuntimeException('Failed to connect to Discogs: ' . $e->getMessage());
            }
        }

        $progress = &$_SESSION['import_progress'];

        if (!$progress['processing']) {
            $progress['processing'] = true;
            $progress['last_update'] = time();

            try {
                // Fetch current page
                $response = $discogs->client->get("/users/$username/collection/folders/0/releases", [
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
                        $currentUserId = $auth->getCurrentUser()->id;

                        // Check if release already exists
                        if ($db->getDiscogsReleaseId($currentUserId, (int)$item['id'])) {
                            Logger::log("Skipping existing release {$item['id']}");
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
                                    Logger::error("Failed to download cover for release {$release['id']}: {$release['title']}");
                                }
                            } catch (Exception $e) {
                                Logger::error("Error downloading cover for release {$release['id']}: " . $e->getMessage());
                            }
                        }

                        // Verify cover file exists after download
                        if ($coverPath && !file_exists(__DIR__ . '/../public/' . $coverPath)) {
                            Logger::error("Cover file missing after download for release {$release['id']}: $coverPath");
                            $coverPath = null;
                        }

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

                    } catch (Exception $e) {
                        Logger::error("Error processing release {$item['id']}: " . $e->getMessage());
                        continue;
                    }
                }

                // Check if we need to move to next page
                if ($batch_end >= count($data['releases'])) {
                    if ($progress['processed'] >= $progress['total']) {
                        // Import complete
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

            } catch (GuzzleException $e) {
                $error = "Failed to fetch data from Discogs: " . $e->getMessage();
                $progress['processing'] = false;
                $progress['current_action'] = "Error: " . $error;
                $_SESSION['import_progress'] = $progress;
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        if (isset($progress)) {
            $progress['processing'] = false;
            $progress['current_action'] = "Error: " . $error;
            $_SESSION['import_progress'] = $progress;
        }
    }
}

$progress = $_SESSION['import_progress'] ?? null;

$content = '
<h1>Import Discogs Collection</h1>
';

if (isset($error)) {
    $content .= '
    <div role="alert" class="error">
        ' . htmlspecialchars($error) . '
        <form method="post" action="?action=import">
         <?= Csrf::getFormField() ?>
            <input type="hidden" name="username" value="' . htmlspecialchars($progress['username'] ?? '') . '">
            <button type="submit">Try Again</button>
            <a href="?action=import&reset=1" role="button" class="secondary">Start Over</a>
        </form>
    </div>';
}

if ($progress) {
    $content .= '
    <article>
        <h2>Importing collection for ' . htmlspecialchars($progress['username']) . '</h2>
        <div>
            <p>Processing page <span id="current-page">' . $progress['page'] . '</span> of <span id="total-pages">' . $progress['total_pages'] . '</span></p>
            <p>Imported <span id="processed-count">' . $progress['processed'] . '</span> of <span id="total-count">' . $progress['total'] . '</span> releases</p>
        </div>
        
        <progress id="progress-bar" value="' . $progress['processed'] . '" max="' . $progress['total'] . '"></progress>
        
        <p id="status-message" aria-live="polite"></p>
        
        ' . (!($progress['processing'] ?? false) ? '
            ' . ($progress['processed'] < $progress['total'] ? '
            <form method="post" action="?action=import" id="continue-form">
                <input type="hidden" name="username" value="' . htmlspecialchars($progress['username']) . '">
                <button type="submit">Continue Import</button>
                <a href="?action=import&reset=1" role="button" class="secondary">Start Over</a>
            </form>
            ' : '
            <div role="alert" class="success">
                <p>Import completed successfully! 🎉</p>
                <p>Imported ' . $progress['processed'] . ' releases from your collection.</p>
                <a href="?action=list" role="button">View Collection</a>
                <a href="?action=import&reset=1" role="button" class="secondary">Start New Import</a>
            </div>
            ') . '
        ' : '') . '
    </article>';
} else {
    $content .= '
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
    </article>';
}

if (($progress['processing'] ?? false)) {
    $content .= '
    <script>
        function updateProgress() {
            fetch("?action=import&check_progress=1")
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById("status-message").textContent = "Error: " + data.error;
                        return;
                    }
                    
                    if (typeof data.page !== "undefined") {
                        document.getElementById("current-page").textContent = data.page;
                    }
                    if (typeof data.processed !== "undefined") {
                        document.getElementById("processed-count").textContent = data.processed;
                        document.getElementById("progress-bar").value = data.processed;
                    }
                    
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
    </script>';
}

$styles = '
<style>
    .error {
        color: #d32f2f;
        padding: 1rem;
        background: #ffebee;
        border-radius: 4px;
        margin: 1rem 0;
    }
    
    .success {
        color: #2e7d32;
        padding: 1rem;
        background: #e8f5e9;
        border-radius: 4px;
        margin: 1rem 0;
    }
    
    progress {
        width: 100%;
        height: 20px;
        margin: 1rem 0;
    }
    
    #status-message {
        font-style: italic;
        color: #666;
    }
</style>';

require __DIR__ . '/layout.php';