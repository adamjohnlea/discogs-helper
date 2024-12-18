<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var int|null $id Release ID */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Security\Csrf;

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
    <h1>' . htmlspecialchars($release->title) . '</h1>
    
    <div class="release-details">
        <div class="cover-section">
            ' . ($release->coverPath
        ? '<img class="cover" src="' . htmlspecialchars($release->getCoverUrl()) . '" 
                       alt="' . htmlspecialchars($release->title) . '">'
        : '') . '
        </div>

        <div class="info-section">
            <p><strong>Artist(s):</strong> ' . htmlspecialchars($release->artist) . '</p>
            <p><strong>Year:</strong> ' . ($release->year ?? 'Unknown') . '</p>
            <p><strong>Format:</strong> ' . htmlspecialchars($release->formatDetails) . '</p>';

if ($identifiers = $release->getIdentifiersArray()) {
    $content .= '
            <div class="identifiers">
                <p><strong>Identifiers:</strong></p>
                <ul>';
    foreach ($identifiers as $identifier) {
        if (in_array(strtolower($identifier['type']), ['barcode', 'upc'])) {
            $content .= '
                    <li>
                        ' . htmlspecialchars(ucfirst($identifier['type'])) . ': 
                        <code>' . htmlspecialchars($identifier['value']) . '</code>
                    </li>';
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

    /* New styles for notes editing */
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
    const notesView = document.getElementById("notesView");
    const notesEdit = document.getElementById("notesEdit");
    const editBtn = document.getElementById("editNotesBtn");
    const cancelBtn = document.getElementById("cancelNotesBtn");
    const editForm = document.getElementById("editNotesForm");

    // Switch to edit mode
    editBtn.addEventListener("click", function() {
        notesView.style.display = "none";
        notesEdit.style.display = "block";
        editBtn.style.display = "none";
    });

    // Cancel editing
    cancelBtn.addEventListener("click", function() {
        notesView.style.display = "block";
        notesEdit.style.display = "none";
        editBtn.style.display = "inline-block";
    });

    // Handle form submission
    editForm.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const formData = new FormData(editForm);
        
        console.log("Submitting form...");
        
        fetch("?action=process-edit", {
            method: "POST",
            body: formData
        })
        .then(response => {
            console.log("Response status:", response.status);
            console.log("Response headers:", [...response.headers.entries()]);
            return response.text().then(text => {
                console.log("Raw response:", text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("JSON parse error:", e);
                    console.log("Invalid JSON response:", text);
                    throw new Error("Server returned invalid JSON");
                }
            });
        })
        .then(data => {
            console.log("Parsed data:", data);
            if (data.success) {
                // Update the view with new notes
                const formattedNotes = data.notes
                    ? data.notes
                        .replace(/\r\n/g, "\n")  // Normalize line endings
                        .split("\n")             // Split into lines
                        .map(line => line.trim()) // Trim each line
                        .join("\n")              // Join back together
                    : "";
                
                notesView.innerHTML = formattedNotes
                    ? "<p>" + formattedNotes + "</p>"
                    : "<p><em>No notes available</em></p>";
                
                // Switch back to view mode
                notesView.style.display = "block";
                notesEdit.style.display = "none";
                editBtn.style.display = "inline-block";
            } else {
                alert("Error saving notes: " + (data.message || "Unknown error"));
            }
        })
        .catch(error => {
            console.error("Detailed error:", error);
            console.error("Error stack:", error.stack);
            alert("Error saving notes. Please try again. Error: " + error.message);
        });
    });
});
</script>';

require __DIR__ . '/layout.php';