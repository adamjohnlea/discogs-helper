<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Logging\Logger;

if (!isset($_GET['id'])) {
    header('Location: ?action=wantlist');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$itemId = (int)$_GET['id'];

// Get the wantlist item
$item = $db->getWantlistItemById($userId, $itemId);
if (!$item) {
    Session::setMessage('Item not found in your wantlist');
    header('Location: ?action=wantlist');
    exit;
}

$content = '
<div class="release-view">
    <a href="?action=wantlist" class="button">← Back to Want List</a>
    
    <!-- Title Section -->
    <div class="title-section">
        <div class="view-mode">
            <h1>' . htmlspecialchars($item['title']) . '</h1>
        </div>
    </div>
    
    <div class="release-details">
        <div class="cover-section">
            ' . (!empty($item['cover_path']) ? '
            <img class="cover" src="' . htmlspecialchars($item['cover_path']) . '" 
                 alt="' . htmlspecialchars($item['title']) . '">' : '') . '
        </div>

        <div class="info-section">
            <p><strong>Artist(s):</strong> ' . htmlspecialchars($item['artist']) . '</p>
            <p><strong>Year:</strong> ' . ($item['year'] ?? 'Unknown') . '</p>';

if ($item['format']) {
    $content .= '<p><strong>Format:</strong> ' . htmlspecialchars($item['format']);
    if ($item['format_details']) {
        $content .= ' (' . htmlspecialchars($item['format_details']) . ')';
    }
    $content .= '</p>';
}

// Display identifiers if available
if (!empty($item['identifiers'])) {
    $identifiers = json_decode($item['identifiers'], true);
    if (!empty($identifiers)) {
        // Log all identifier types
        Logger::log('Identifier types for release ' . $item['discogs_id'] . ': ' . 
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
}

$content .= '
            <div class="button-group">
                <button class="button add-to-collection" data-release-id="' . $item['discogs_id'] . '">Add to Collection</button>
                <button class="button remove-item" data-release-id="' . $item['discogs_id'] . '">Remove from Want List</button>
            </div>
        </div>
    </div>';

// Display tracklist if available
if (!empty($item['tracklist'])) {
    $tracklist = json_decode($item['tracklist'], true);
    if (!empty($tracklist)) {
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
}

// Add notes section after tracklist
$content .= '
    <div class="notes-section">
        <div class="notes-header">
            <h2>Notes</h2>
            <button type="button" class="button edit-button" id="editNotesBtn">Edit</button>
        </div>
        
        <div class="notes-view" id="notesView">
            ' . (empty($item['notes']) ? '<p class="empty-notes">No notes added yet.</p>' : '<p>' . nl2br(htmlspecialchars($item['notes'])) . '</p>') . '
        </div>
        
        <div class="notes-edit" id="notesEdit" style="display: none;">
            <form id="editNotesForm" method="post" action="?action=process-wantlist-notes">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars(Csrf::generate()) . '">
                <input type="hidden" name="itemId" value="' . htmlspecialchars($itemId) . '">
                <textarea name="notes" class="notes-textarea" rows="5">' . htmlspecialchars($item['notes'] ?? '') . '</textarea>
                <div class="button-group">
                    <button type="submit" class="button save-button">Save</button>
                    <button type="button" class="button cancel-button" id="cancelNotesBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>';

$content .= '
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Add to Collection functionality
    document.querySelector(".add-to-collection").addEventListener("click", async function() {
        const releaseId = this.dataset.releaseId;
        if (confirm("Add this release to your collection?")) {
            try {
                const response = await fetch("?action=add", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        csrf_token: "' . Csrf::generate() . '",
                        id: releaseId
                    })
                });
                
                if (!response.ok) {
                    throw new Error("Failed to add to collection");
                }
                
                // Redirect to collection view
                window.location.href = "?action=list";
                
            } catch (error) {
                console.error("Error adding to collection:", error);
                alert("Error adding to collection: " + error.message);
            }
        }
    });
    
    // Remove item functionality
    document.querySelector(".remove-item").addEventListener("click", async function() {
        const releaseId = this.dataset.releaseId;
        if (confirm("Remove this release from your wantlist?")) {
            try {
                const response = await fetch("?action=remove_wantlist", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        csrf_token: "' . Csrf::generate() . '",
                        id: releaseId
                    })
                });
                
                if (!response.ok) {
                    throw new Error("Failed to remove from wantlist");
                }
                
                // Redirect back to wantlist
                window.location.href = "?action=wantlist";
                
            } catch (error) {
                console.error("Error removing from wantlist:", error);
                alert("Error removing from wantlist: " + error.message);
            }
        }
    });
    
    // Notes editing functionality
    const editNotesBtn = document.getElementById("editNotesBtn");
    const cancelNotesBtn = document.getElementById("cancelNotesBtn");
    const notesView = document.getElementById("notesView");
    const notesEdit = document.getElementById("notesEdit");
    
    editNotesBtn.addEventListener("click", function() {
        notesView.style.display = "none";
        notesEdit.style.display = "block";
        editNotesBtn.style.display = "none";
    });
    
    cancelNotesBtn.addEventListener("click", function() {
        notesView.style.display = "block";
        notesEdit.style.display = "none";
        editNotesBtn.style.display = "inline-block";
    });
});
</script>';

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

    .button-group {
        display: flex;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .button-group .button {
        flex: 1;
        text-align: center;
    }

    .add-to-collection {
        background: #28a745;
    }

    .remove-item {
        background: #dc3545;
    }

    @media (max-width: 768px) {
        .release-details {
            grid-template-columns: 1fr;
        }

        .button-group {
            flex-direction: column;
        }
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

    .empty-notes {
        color: #666;
        font-style: italic;
    }

    .edit-button {
        background: #17a2b8;
    }

    .save-button {
        background: #28a745;
    }

    .cancel-button {
        background: #6c757d;
    }
</style>';

require __DIR__ . '/layout.php'; 