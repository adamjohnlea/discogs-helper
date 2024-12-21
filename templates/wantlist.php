<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */

use DiscogsHelper\Security\Csrf;

$userId = $auth->getCurrentUser()->id;
$wantlist = $db->getWantlistItems($userId);

$content = '
<div class="wantlist-container">
    <div class="wantlist-toolbar">
        <h1>Want List</h1>
        <button id="syncWantlist" class="button">Sync with Discogs</button>
    </div>
    
    <div class="wantlist-grid">';

foreach ($wantlist as $item) {
    $content .= '
    <div class="wantlist-item" data-release-id="' . $item['discogs_id'] . '">
        <h3>' . htmlspecialchars($item['artist']) . ' - ' . htmlspecialchars($item['title']) . '</h3>
        <div class="item-actions">
            <button class="set-price-alert">Set Price Alert</button>
            <button class="remove-item">Remove</button>
        </div>
        <div class="price-alert" style="display: none;">
            <input type="number" step="0.01" placeholder="Price threshold">
            <button class="save-threshold">Save</button>
        </div>
    </div>';
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
    
    syncButton.addEventListener("click", syncWantlist);
});
</script>';

$styles = '
<style>
    .wantlist-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
    }

    .wantlist-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .wantlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .wantlist-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .wantlist-item h3 {
        margin: 0 0 1rem 0;
        font-size: 1.1rem;
        line-height: 1.4;
    }

    .item-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .item-actions button {
        flex: 1;
        padding: 0.5rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .set-price-alert {
        background: #28a745;
        color: white;
    }

    .remove-item {
        background: #dc3545;
        color: white;
    }

    .price-alert {
        margin-top: 1rem;
        display: flex;
        gap: 0.5rem;
    }

    .price-alert input {
        flex: 1;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .save-threshold {
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 0.5rem 1rem;
        cursor: pointer;
    }
</style>';

require __DIR__ . '/layout.php';