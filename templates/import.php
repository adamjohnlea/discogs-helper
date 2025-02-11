<?php
// templates/import.php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var array|null $progress Import progress data */
/** @var string|null $error Error message */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Security\Csrf;
use GuzzleHttp\Exception\GuzzleException;

// Check if user has valid Discogs credentials
if (!isset($discogs)) {
    Session::setMessage('Please set up your Discogs credentials in your profile first.');
    header('Location: ?action=profile_edit');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$profile = $db->getUserProfile($userId);

if (!$profile || !$profile->discogsUsername) {
    Session::setMessage('Please set your Discogs username in your profile first.');
    header('Location: ?action=profile_edit');
    exit;
}

// Check for OAuth tokens
if (!$profile->hasDiscogsOAuth()) {
    Session::setMessage('Please connect your Discogs account first.');
    header('Location: ?action=discogs_auth');
    exit;
}

// Check for existing import
$importState = $db->getImportState($userId);
if ($importState && $importState['status'] === 'pending') {
    // Resume existing import
    header('Location: ?action=resume_import&id=' . $importState['id']);
    exit;
}

$discogsUsername = $profile->discogsUsername;

if (empty($discogsUsername)) {
    Session::setMessage('Please set up your Discogs username in your profile first.');
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

$content = '
<div class="import-container">
    <h1>Import Collection</h1>
    <p>Import your Discogs collection into DiscogsHelper.</p>
    
    <form method="post" class="import-form">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(Csrf::generate()) . '">
        <button type="submit" class="button">Start Import</button>
    </form>
</div>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::validateOrFail($_POST['csrf_token'] ?? null);
        
        // Get collection size first
        $response = $discogs->client->get("/users/{$profile->discogsUsername}/collection/folders/0/releases", [
            'query' => [
                'page' => 1,
                'per_page' => 50
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (!isset($data['pagination'])) {
            throw new RuntimeException('Unable to fetch collection data');
        }

        // Create import state
        $importStateId = $db->createImportState(
            userId: $userId,
            totalPages: $data['pagination']['pages'],
            totalItems: $data['pagination']['items']
        );

        // Redirect to process page
        header('Location: ?action=process_import&id=' . $importStateId);
        exit;

    } catch (Exception $e) {
        Logger::error('Import initialization failed: ' . $e->getMessage());
        Session::setMessage('Failed to start import: ' . $e->getMessage());
    }
}

$progress = $_SESSION['import_progress'] ?? null;

if (isset($error)) {
    $content .= '
    <div role="alert" class="error">
        ' . htmlspecialchars($error) . '
        <form method="post" action="?action=import">
            ' . Csrf::getFormField() . '
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
                ' . Csrf::getFormField() . '
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
}

if (($progress['processing'] ?? false)) {
    $content .= '
    <script>
        function updateProgress() {
            const csrfToken = document.querySelector("input[name=\'csrf_token\']").value;
            
            fetch("?action=import&check_progress=1", {
                headers: {
                    "X-CSRF-Token": csrfToken
                }
            })
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