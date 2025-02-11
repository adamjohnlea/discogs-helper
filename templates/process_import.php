<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;

// Add debug logging
Logger::log("Starting import process...");

// Validate Discogs service
if (!isset($discogs) || !$discogs instanceof DiscogsService) {
    Logger::error("Discogs service not available");
    Session::setMessage('Error: Discogs service not available, please check your credentials.');
    header('Location: ?action=profile_edit');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$importStateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$importStateId) {
    Logger::error("Invalid import state ID");
    Session::setMessage('Invalid import state');
    header('Location: ?action=import');
    exit;
}

$importState = $db->getImportState($userId);
if (!$importState || $importState['status'] !== 'pending') {
    Logger::error("No pending import found");
    Session::setMessage('No pending import found');
    header('Location: ?action=import');
    exit;
}

Logger::log("Found import state: " . json_encode($importState));

// Start the actual processing
try {
    // Get the profile
    $profile = $db->getUserProfile($userId);
    if (!$profile || !$profile->discogsUsername) {
        throw new RuntimeException('Discogs username not found');
    }

    Logger::log("Starting import for user: " . $profile->discogsUsername);

    // Make the initial request
    $response = $discogs->client->get("/users/{$profile->discogsUsername}/collection/folders/0/releases", [
        'query' => [
            'page' => $importState['current_page'],
            'per_page' => 5
        ]
    ]);

    Logger::log("Got response from Discogs API");

    $data = json_decode($response->getBody()->getContents(), true);
    if (!isset($data['releases'])) {
        throw new RuntimeException('Unable to fetch collection data');
    }

    Logger::log("Processing " . count($data['releases']) . " releases");

    // Process each release
    foreach ($data['releases'] as $item) {
        try {
            Logger::log("Processing release: " . $item['id']);
            // ... rest of the processing code ...
        } catch (Exception $e) {
            Logger::error("Failed to process release: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    Logger::error("Import process error: " . $e->getMessage());
    Session::setMessage('Import error: ' . $e->getMessage());
}

// Set unlimited execution time for this script
set_time_limit(0);
ini_set('memory_limit', '256M');

$content = '
<div class="import-progress">
    <h1>Importing Collection</h1>
    
    <div class="progress-section">
        <h2>Release Progress</h2>
        <div class="progress-info">
            <p>Imported <span id="processed-count">' . $importState['processed_items'] . '</span> 
               of <span id="total-count">' . $importState['total_items'] . '</span> releases</p>
        </div>
        <progress id="progress-bar" 
                  value="' . $importState['processed_items'] . '" 
                  max="' . $importState['total_items'] . '"></progress>
    </div>
    
    <div id="error-section" class="error-section" style="display: none;">
        <h2>Failed Items</h2>
        <ul id="error-list"></ul>
    </div>
    
    <p id="status-message" aria-live="polite">Processing...</p>
</div>

<script>
// Add retry count tracking
let progressRetryCount = 0;
let batchRetryCount = 0;
const MAX_RETRIES = 30; // Increased from 10 since retries are actually working
const BASE_RETRY_DELAY = 1000; // 1 second

function calculateRetryDelay(retryCount) {
    // Exponential backoff with max of 30 seconds
    return Math.min(BASE_RETRY_DELAY * Math.pow(2, retryCount), 30000);
}

function resetRetryCount() {
    progressRetryCount = 0;
    batchRetryCount = 0;
}

function isExpectedError(status) {
    // These are expected errors during long-running processes
    return status === 502 || status === 504;
}

function updateProgress() {
    fetch("?action=check_import_progress&id=' . $importStateId . '")
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            resetRetryCount(); // Reset on successful response
            return response.json();
        })
        .then(data => {
            // Clear any error messages if we got a successful response
            document.getElementById("status-message").className = "";
            
            // Update release progress
            document.getElementById("processed-count").textContent = data.processed_items;
            document.getElementById("progress-bar").value = data.processed_items;
            
            // Update failed items
            const errorSection = document.getElementById("error-section");
            const errorList = document.getElementById("error-list");
            if (data.failed_items && data.failed_items.length > 0) {
                errorSection.style.display = "block";
                errorList.innerHTML = data.failed_items
                    .map(item => `<li>Release ${item.id}: ${item.error}</li>`)
                    .join("");
            }
            
            if (data.status === "completed") {
                window.location.href = "?action=list";
            } else if (data.status === "error" && !isExpectedError(response.status)) {
                // Only show error message for unexpected errors
                document.getElementById("status-message").textContent = "Error occurred: " + (data.message || "Unknown error");
                document.getElementById("status-message").className = "error-message";
            }
            
            // Always continue retrying for progress updates
            setTimeout(updateProgress, 1000);
        })
        .catch(error => {
            console.error("Error checking progress:", error);
            const status = error.message.match(/status: (\d+)/)?.[1];
            
            // For expected errors, show a more reassuring message
            if (status && isExpectedError(parseInt(status))) {
                document.getElementById("status-message").textContent = "Still processing... (will retry automatically)";
            } else {
                document.getElementById("status-message").textContent = "Temporary error occurred, retrying...";
            }
            document.getElementById("status-message").className = "info-message";
            
            // Always retry with backoff
            const delay = calculateRetryDelay(progressRetryCount++);
            setTimeout(updateProgress, delay);
        });
}

function processBatch() {
    fetch("?action=process_import_batch&id=' . $importStateId . '")
        .then(response => {
            if (!response.ok) {
                return response.json().catch(() => {
                    // If JSON parsing fails, create a basic error object
                    return { message: `HTTP error! status: ${response.status}` };
                }).then(errorData => {
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                });
            }
            resetRetryCount(); // Reset on successful response
            return response.json();
        })
        .then(data => {
            if (data.error && !isExpectedError(response.status)) {
                console.error("Import error:", data.error);
                document.getElementById("status-message").textContent = "Error: " + data.error;
                document.getElementById("status-message").className = "error-message";
            } else {
                console.log("Batch processed:", data);
                if (data.status === "pending") {
                    // Update UI with current progress
                    document.getElementById("status-message").textContent = 
                        `Processing page ${data.nextPage}... (${data.processedItems} of ${data.totalItems} items)`;
                    document.getElementById("status-message").className = "";
                    document.getElementById("processed-count").textContent = data.processedItems;
                    document.getElementById("progress-bar").value = data.processedItems;
                } else if (data.status === "completed") {
                    document.getElementById("status-message").textContent = "Import completed!";
                    document.getElementById("status-message").className = "success-message";
                    setTimeout(() => window.location.href = "?action=list", 2000);
                    return;
                }
            }
            
            // Always continue processing with delay
            setTimeout(processBatch, 1000);
        })
        .catch(error => {
            console.error("Error processing batch:", error);
            const status = error.message.match(/status: (\d+)/)?.[1];
            
            // For expected errors, show a more reassuring message
            if (status && isExpectedError(parseInt(status))) {
                document.getElementById("status-message").textContent = "Still processing... (will retry automatically)";
            } else {
                document.getElementById("status-message").textContent = "Temporary error occurred, retrying...";
            }
            document.getElementById("status-message").className = "info-message";
            
            // Always retry with backoff
            const delay = calculateRetryDelay(batchRetryCount++);
            setTimeout(processBatch, delay);
        });
}

// Start both the progress updates and processing
updateProgress();
processBatch();
</script>

<style>
.import-progress {
    max-width: 800px;
    margin: 2rem auto;
    padding: 1rem;
}

.progress-section {
    margin: 2rem 0;
}

.progress-info {
    margin: 1rem 0;
}

progress {
    width: 100%;
    height: 20px;
    margin: 1rem 0;
}

.error-text {
    color: #dc3545;
}

.error-section {
    margin: 2rem 0;
    padding: 1rem;
    background: #fff3f3;
    border-radius: 4px;
}

#status-message {
    font-style: italic;
    color: #666;
}

#status-message.error-message {
    color: #dc3545;
    font-weight: bold;
}

#status-message.success-message {
    color: #28a745;
    font-weight: bold;
}

#status-message.info-message {
    color: #0066cc;
    font-style: italic;
}
</style>';

require __DIR__ . '/layout.php'; 