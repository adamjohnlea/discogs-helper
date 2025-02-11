<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Http\Session;

// Check if user has valid Discogs credentials
if (!isset($discogs)) {
    Session::setMessage('Please set up your Discogs username in your profile first.');
    header('Location: ?action=profile_edit');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$wantlist = $db->getWantlistItems($userId);

$content = '
<div class="wantlist-container">';

// Display success message if exists
if (Session::hasMessage()) {
    $content .= '
    <div class="success-message">
        ' . htmlspecialchars(Session::getMessage()) . '
    </div>';
}

$content .= '
    <div class="collection-toolbar">
        <h1>Want List</h1>
        <div class="toolbar-actions">
            <button id="syncWantlist" class="button">Sync with Discogs</button>
        </div>
    </div>
    
    <div class="album-grid">';

if (empty($wantlist)) {
    $content .= '<p>Your wantlist is empty. Use the "Sync with Discogs" button to import your Discogs wantlist.</p>';
} else {
    foreach ($wantlist as $item) {
        $content .= '
        <div class="album-card" data-release-id="' . $item['discogs_id'] . '">
            ' . (!empty($item['cover_path']) ? '
            <img src="' . htmlspecialchars($item['cover_path']) . '" alt="Album cover">' : '') . '
            <div class="details">
                <h3>' . htmlspecialchars($item['title']) . '</h3>
                <p>' . htmlspecialchars($item['artist']) . '</p>';
                
        if ($item['year']) {
            $content .= '<p><small>Released: ' . htmlspecialchars($item['year']) . '</small></p>';
        }
        
        if ($item['format']) {
            $content .= '<p><small>Format: ' . htmlspecialchars($item['format']);
            if ($item['format_details']) {
                $content .= ' (' . htmlspecialchars($item['format_details']) . ')';
            }
            $content .= '</small></p>';
        }
        
        $content .= '
                <div class="button-groups">
                    <div class="button-group">
                        <button class="button add-to-collection" data-release-id="' . $item['discogs_id'] . '">Add to Collection</button>
                        <button class="button remove-item" data-release-id="' . $item['discogs_id'] . '">Remove</button>
                    </div>
                    <div class="button-group">
                        <a href="?action=view_wantlist&id=' . $item['id'] . '" class="button view-details">View Details</a>
                    </div>
                </div>
            </div>
        </div>';
    }
}

$content .= '
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const syncButton = document.getElementById("syncWantlist");
    
    async function syncWantlist() {
        try {
            syncButton.disabled = true;
            syncButton.textContent = "Syncing...";
            
            const response = await fetch("?action=sync_wantlist", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: "' . Csrf::generate() . '"
                })
            });
            
            const responseText = await response.text();
            console.log("Raw response:", responseText);
            console.log("Response status:", response.status);
            console.log("Response headers:", Object.fromEntries(response.headers));
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error("Parse error:", e);
                throw new Error("Server response was not valid JSON: " + responseText);
            }
            
            if (!response.ok) {
                throw new Error(data.error || "Sync failed");
            }
            
            location.reload();
        } catch (error) {
            console.error("Error syncing wantlist:", error);
            alert("Error syncing wantlist: " + error.message);
        } finally {
            syncButton.disabled = false;
            syncButton.textContent = "Sync with Discogs";
        }
    }
    
    // Add to Collection functionality
    document.querySelectorAll(".add-to-collection").forEach(button => {
        button.addEventListener("click", async function() {
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
                    
                    // Remove card with animation
                    const card = this.closest(".album-card");
                    card.style.opacity = "0";
                    setTimeout(() => {
                        card.remove();
                        // If no more cards, reload to show empty state
                        if (document.querySelectorAll(".album-card").length === 0) {
                            location.reload();
                        }
                    }, 300);
                    
                    // Show success message
                    const messageContainer = document.querySelector(".wantlist-container");
                    const existingMessages = document.querySelectorAll(".success-message");
                    existingMessages.forEach(msg => msg.remove()); // Remove any existing messages

                    const message = document.createElement("div");
                    message.className = "success-message";
                    message.textContent = "Release added to collection successfully!";
                    messageContainer.insertBefore(message, messageContainer.firstChild);

                    // Remove message after 3 seconds
                    setTimeout(() => {
                        message.style.opacity = "0";
                        setTimeout(() => message.remove(), 300);
                    }, 3000);
                    
                } catch (error) {
                    console.error("Error adding to collection:", error);
                    alert("Error adding to collection: " + error.message);
                }
            }
        });
    });
    
    // Remove item functionality
    document.querySelectorAll(".remove-item").forEach(button => {
        button.addEventListener("click", async function() {
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
                    
                    // Remove card with animation
                    const card = this.closest(".album-card");
                    card.style.opacity = "0";
                    setTimeout(() => {
                        card.remove();
                        // If no more cards, reload to show empty state
                        if (document.querySelectorAll(".album-card").length === 0) {
                            location.reload();
                        }
                    }, 300);
                    
                    // Show success message
                    const messageContainer = document.querySelector(".wantlist-container");
                    const existingMessages = document.querySelectorAll(".success-message");
                    existingMessages.forEach(msg => msg.remove()); // Remove any existing messages

                    const message = document.createElement("div");
                    message.className = "success-message";
                    message.textContent = "Release removed from wantlist successfully!";
                    messageContainer.insertBefore(message, messageContainer.firstChild);

                    // Remove message after 3 seconds
                    setTimeout(() => {
                        message.style.opacity = "0";
                        setTimeout(() => message.remove(), 300);
                    }, 3000);
                    
                } catch (error) {
                    console.error("Error removing from wantlist:", error);
                    alert("Error removing from wantlist: " + error.message);
                }
            }
        });
    });
    
    syncButton.addEventListener("click", syncWantlist);
});

// Auto-hide success messages after 3 seconds
document.querySelectorAll(".success-message").forEach(message => {
    setTimeout(() => {
        message.style.opacity = "0";
        setTimeout(() => message.remove(), 300);
    }, 3000);
});
</script>';

$styles = '
<style>
    .wantlist-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
    }

    .toolbar-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .button-groups {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .button-group {
        display: flex;
        gap: 0.5rem;
    }

    .button-group .button {
        flex: 1;
        text-align: center;
        cursor: pointer;
    }

    .add-to-collection {
        background: #28a745;
    }

    .remove-item {
        background: #dc3545;
    }

    .view-details {
        background: #17a2b8;
    }

    .album-card {
        transition: opacity 0.3s ease;
    }

    .success-message {
        opacity: 1;
        transition: opacity 0.3s ease;
        max-width: 800px;
        margin: 1rem auto;
        padding: 1rem;
        background: #e8f5e9;
        border: 1px solid #4caf50;
        border-radius: 4px;
        color: #2e7d32;
        text-align: center;
    }

    @media (max-width: 768px) {
        .button-group {
            flex-direction: column;
        }
    }
</style>';

require __DIR__ . '/layout.php';