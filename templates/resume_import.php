<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\DiscogsService;
use DiscogsHelper\Logger;
use DiscogsHelper\Session;
use DiscogsHelper\Security\Csrf;

if (!isset($discogs) || !$discogs instanceof DiscogsService) {
    Logger::error("Discogs service not available");
    Session::setMessage('Error: Discogs service not available. Please check your credentials.');
    header('Location: ?action=profile_edit');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$importStateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$importStateId) {
    Session::setMessage('Invalid import state');
    header('Location: ?action=import');
    exit;
}

$importState = $db->getImportState($userId);
if (!$importState) {
    Session::setMessage('No import found');
    header('Location: ?action=import');
    exit;
}

Logger::log("Resume import page loaded for state: " . json_encode($importState));

$content = '
<div class="import-resume">
    <h1>Resume Import</h1>
    
    <div class="import-status">
        <h2>Current Progress</h2>
        <p>Processed ' . $importState['processed_items'] . ' of ' . $importState['total_items'] . ' items</p>
        <p>Current page: ' . $importState['current_page'] . ' of ' . $importState['total_pages'] . '</p>
        
        <div class="progress-bar">
            <progress value="' . $importState['processed_items'] . '" max="' . $importState['total_items'] . '"></progress>
        </div>
    </div>
    
    <div class="actions">
        <form method="post" class="resume-form">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars(Csrf::generate()) . '">
            
            <button type="submit" name="action" value="resume" class="button">
                Resume Import
            </button>
            
            <button type="submit" name="action" value="restart" class="button secondary">
                Start New Import
            </button>
            
            <a href="?action=list" class="button link">Cancel</a>
        </form>
    </div>
</div>

<style>
.import-resume {
    max-width: 600px;
    margin: 2rem auto;
    padding: 1rem;
}

.import-status {
    margin: 2rem 0;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 4px;
}

.progress-bar {
    margin: 1rem 0;
}

progress {
    width: 100%;
    height: 20px;
}

.actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.button.secondary {
    background: #666;
}

.button.link {
    background: none;
    color: #333;
    text-decoration: underline;
}
</style>';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::validateOrFail($_POST['csrf_token'] ?? null);
        
        if ($_POST['action'] === 'resume') {
            // Redirect to process import
            header('Location: ?action=process_import&id=' . $importStateId);
            exit;
        } elseif ($_POST['action'] === 'restart') {
            // Delete existing import state
            $db->deleteImportState($userId);
            
            // Redirect to start new import
            header('Location: ?action=import');
            exit;
        }
    } catch (Exception $e) {
        Logger::error('Resume import error: ' . $e->getMessage());
        Session::setMessage('Error: ' . $e->getMessage());
    }
}

require __DIR__ . '/layout.php'; 