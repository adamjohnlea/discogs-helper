<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var int|null $id Release ID */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logging\Logger;

if (!$auth->isLoggedIn()) {
    header('Location: ?action=login');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$release = $db->getReleaseById($userId, $id);
if (!$release) {
    header('HTTP/1.0 404 Not Found');
    echo 'Release not found';
    exit;
}

// Generate CSRF token
$csrfToken = Csrf::generate();

$content = '
<div class="release-view">
    <a href="?action=list" class="button">← Back to list</a>
    
    <!-- Title Section -->
    <div class="title-section" id="titleSection">
        <div class="view-mode" id="titleView">
            <h1>' . htmlspecialchars($release->title) . '</h1>
            <button type="button" class="button edit-button" id="editDetailsBtn">Edit</button>
        </div>
        
        <!-- Edit Mode for Title and Artist -->
        <div class="edit-mode" id="detailsEdit" style="display: none;">
            <form id="editDetailsForm" method="post" action="?action=process-edit-details">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">
                <input type="hidden" name="releaseId" value="' . htmlspecialchars($id) . '">
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" value="' . htmlspecialchars($release->title) . '" required class="edit-input">
                </div>
                <div class="form-group">
                    <label for="artist">Artist:</label>
                    <input type="text" id="artist" name="artist" value="' . htmlspecialchars($release->artist) . '" required class="edit-input">
                </div>
                <div class="button-group">
                    <button type="submit" class="button save-button">Save</button>
                    <button type="button" class="button cancel-button" id="cancelDetailsBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="release-details">
        <div class="cover-section">
            ' . ($release->coverPath
        ? '<img class="cover" src="' . htmlspecialchars($release->getCoverUrl()) . '" 
                       alt="' . htmlspecialchars($release->title) . '">'
        : '') . '
        </div>

        <div class="info-section">
            <p id="artistDisplay"><strong>Artist(s):</strong> <span class="artist-value">' . htmlspecialchars($release->artist) . '</span></p>
            <p><strong>Year:</strong> ' . ($release->year ?? 'Unknown') . '</p>
            <p><strong>Format:</strong> ' . htmlspecialchars($release->formatDetails) . '</p>';

if ($identifiers = $release->getIdentifiersArray()) {
    // Log all identifier types
    Logger::log('Identifier types for release ' . $release->discogsId . ': ' . 
        implode(', ', array_unique(array_column($identifiers, 'type'))));
        
    $content .= '
            <div class="identifiers">
                <p><strong>Identifiers:</strong></p>
                <ul>';
    // Group identifiers by type
    $groupedIdentifiers = [];
    foreach ($identifiers as $identifier) {
        $type = strtolower($identifier['type']);
        if (!isset($groupedIdentifiers[$type])) {
            $groupedIdentifiers[$type] = [];
        }
        $groupedIdentifiers[$type][] = $identifier;
    }
    
    // Display identifiers in a specific order
    $typeOrder = ['barcode', 'upc', 'matrix', 'matrix / runout', 'label code', 'rights society', 'catalog number', 'other'];
    foreach ($typeOrder as $type) {
        if (isset($groupedIdentifiers[$type])) {
            foreach ($groupedIdentifiers[$type] as $identifier) {
                $content .= '
                    <li>
                        ' . htmlspecialchars(ucfirst($identifier['type'])) . ': 
                        <code>' . htmlspecialchars($identifier['value']) . '</code>
                        ' . (!empty($identifier['description']) ? 
                            '<small>(' . htmlspecialchars($identifier['description']) . ')</small>' : '') . '
                    </li>';
            }
        }
    }
    
    // Show any remaining types that weren't in our predefined order
    foreach ($groupedIdentifiers as $type => $typeIdentifiers) {
        if (!in_array($type, $typeOrder)) {
            foreach ($typeIdentifiers as $identifier) {
                $content .= '
                    <li>
                        ' . htmlspecialchars(ucfirst($identifier['type'])) . ': 
                        <code>' . htmlspecialchars($identifier['value']) . '</code>
                        ' . (!empty($identifier['description']) ? 
                            '<small>(' . htmlspecialchars($identifier['description']) . ')</small>' : '') . '
                    </li>';
            }
        }
    }
    $content .= '
                </ul>
            </div>';
}

$content .= '
        </div>
    </div>';

if ($tracklist = $release->getTracklistArray()) {
    $content .= '
    <div class="tracklist-section">
        <h2>Tracklist</h2>
        <ol class="tracklist">';
    foreach ($tracklist as $track) {
        $content .= '
            <li>
                <span class="position">' . htmlspecialchars($track['position'] ?? '') . '</span>
                <span class="title">' . htmlspecialchars($track['title']) . '</span>
                ' . (!empty($track['duration']) ? '<span class="duration">' . htmlspecialchars($track['duration']) . '</span>' : '') . '
            </li>';
    }
    $content .= '
        </ol>
    </div>';
}

$content .= '
    <div class="notes-section" id="notesSection">
        <div class="notes-header">
            <h2>Notes</h2>
            <button type="button" class="button edit-button" id="editNotesBtn">Edit</button>
        </div>
        
        <!-- View Mode -->
        <div class="notes-view" id="notesView">
            <p>' . ($release->notes
        ? htmlspecialchars($release->notes)
        : '<em>No notes available</em>') . '</p>
        </div>
        
        <!-- Edit Mode -->
        <div class="notes-edit" id="notesEdit" style="display: none;">
            <form id="editNotesForm" method="post" action="?action=process-edit">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">
                <input type="hidden" name="releaseId" value="' . htmlspecialchars($id) . '">
                <textarea name="notes" class="notes-textarea" rows="6">' . htmlspecialchars($release->notes ?? '') . '</textarea>
                <div class="button-group">
                    <button type="submit" class="button save-button">Save</button>
                    <button type="button" class="button cancel-button" id="cancelNotesBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>';

$styles = '
<style>
    .release-view {
        max-width: 1000px;
        margin: 0 auto;
    }

    .title-section {
        margin-bottom: 2rem;
    }

    .view-mode {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .view-mode h1 {
        margin: 0;
    }

    .edit-mode {
        background: rgba(0, 0, 0, 0.03);
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
    }

    .edit-input {
        width: 100%;
        padding: 0.5rem;
        font-size: 1rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 0.5rem;
    }

    .release-details {
        display: grid;
        grid-template-columns: minmax(300px, 1fr) 2fr;
        gap: 2rem;
        margin: 2rem 0;
    }

    .cover {
        width: 100%;
        height: auto;
        border-radius: 8px;
    }

    .info-section {
        padding: 1rem;
    }

    .tracklist {
        list-style-position: inside;
        padding: 0;
    }

    .tracklist li {
        padding: 0.5rem;
        display: flex;
        gap: 1rem;
        align-items: baseline;
    }

    .tracklist li:nth-child(odd) {
        background: rgba(0, 0, 0, 0.03);
    }

    .position {
        opacity: 0.7;
        min-width: 2.5rem;
    }

    .title {
        flex: 1;
    }

    .duration {
        opacity: 0.7;
    }

    .notes-section {
        margin-top: 2rem;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
    }

    .notes-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .notes-textarea {
        width: 100%;
        padding: 0.5rem;
        margin-bottom: 1rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: inherit;
        font-size: inherit;
        line-height: 1.5;
        white-space: pre-line;
    }

    .notes-view p {
        white-space: pre-line;
        margin: 0;
        line-height: 1.5;
    }

    .button-group {
        display: flex;
        gap: 0.5rem;
    }

    .edit-button, .save-button, .cancel-button {
        padding: 0.25rem 1rem;
    }

    .save-button {
        background-color: #4CAF50;
        color: white;
    }

    .cancel-button {
        background-color: #f44336;
        color: white;
    }

    @media (max-width: 768px) {
        .release-details {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Notes editing functionality
    const notesView = document.getElementById("notesView");
    const notesEdit = document.getElementById("notesEdit");
    const editBtn = document.getElementById("editNotesBtn");
    const cancelBtn = document.getElementById("cancelNotesBtn");
    const editForm = document.getElementById("editNotesForm");

    // Details editing elements
    const titleView = document.getElementById("titleView");
    const detailsEdit = document.getElementById("detailsEdit");
    const editDetailsBtn = document.getElementById("editDetailsBtn");
    const cancelDetailsBtn = document.getElementById("cancelDetailsBtn");
    const editDetailsForm = document.getElementById("editDetailsForm");
    const artistDisplay = document.getElementById("artistDisplay");

    // Notes editing handlers
    editBtn.addEventListener("click", function() {
        notesView.style.display = "none";
        notesEdit.style.display = "block";
        editBtn.style.display = "none";
    });

    cancelBtn.addEventListener("click", function() {
        notesView.style.display = "block";
        notesEdit.style.display = "none";
        editBtn.style.display = "inline-block";
    });

    editForm.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const formData = new FormData(editForm);
        
        fetch("?action=process-edit", {
            method: "POST",
            body: formData
        })
        .then(response => response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON parse error:", e);
                throw new Error("Server returned invalid JSON");
            }
        }))
        .then(data => {
            if (data.success) {
                const formattedNotes = data.notes
                    ? data.notes
                        .replace(/\r\n/g, "\n")
                        .split("\n")
                        .map(line => line.trim())
                        .join("\n")
                    : "";
                
                notesView.innerHTML = formattedNotes
                    ? "<p>" + formattedNotes + "</p>"
                    : "<p><em>No notes available</em></p>";
                
                notesView.style.display = "block";
                notesEdit.style.display = "none";
                editBtn.style.display = "inline-block";
            } else {
                alert("Error saving notes: " + (data.message || "Unknown error"));
            }
        })
        .catch(error => {
            alert("Error saving notes. Please try again. Error: " + error.message);
        });
    });

    // Details editing handlers
    editDetailsBtn.addEventListener("click", function() {
        titleView.style.display = "none";
        detailsEdit.style.display = "block";
    });

    cancelDetailsBtn.addEventListener("click", function() {
        titleView.style.display = "flex";
        detailsEdit.style.display = "none";
    });

    editDetailsForm.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const formData = new FormData(editDetailsForm);
        
        fetch("?action=process-edit-details", {
            method: "POST",
            body: formData
        })
        .then(response => response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON parse error:", e);
                throw new Error("Server returned invalid JSON");
            }
        }))
        .then(data => {
            if (data.success) {
                document.querySelector("#titleView h1").textContent = data.title;
                document.querySelector(".artist-value").textContent = data.artist;
                
                titleView.style.display = "flex";
                detailsEdit.style.display = "none";
            } else {
                alert("Error saving details: " + (data.message || "Unknown error"));
            }
        })
        .catch(error => {
            alert("Error saving details. Please try again. Error: " + error.message);
        });
    });
});
</script>';

require __DIR__ . '/layout.php';