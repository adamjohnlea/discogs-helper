<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Security\Csrf;

$styles = '<style>
.button-groups {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.button-group {
    display: flex;
    gap: 0.5rem;
}

.button-group .button {
    flex: 1;
    text-align: center;
}

.remove-item {
    background: #dc3545;
}

.success-message {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    text-align: center;
}

@media (max-width: 768px) {
    .button-group {
        flex-direction: column;
    }
}
</style>';

$searchQuery = $_GET['q'] ?? null;
// Make sure user is logged in and get their ID from the session
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header('Location: ?action=login');
    exit;
}
$sort = $_GET['sort'] ?? $_COOKIE['collection_sort'] ?? null;
$direction = $_GET['direction'] ?? $_COOKIE['collection_direction'] ?? 'ASC';

$releases = $db->getAllReleases(
    userId: $_SESSION['user_id'],
    search: $searchQuery,
    format: $_GET['format'] ?? null,
    sort: $sort,
    direction: $direction
);

$content = '
<h1>My Releases' . ($searchQuery ? ': Search Results' : '') . '</h1>

<div class="collection-toolbar">
    <form class="search-group" method="GET">
        <input type="hidden" name="action" value="list">
        <input type="search"
               name="q"
               placeholder="Search your collection..."
               value="' . htmlspecialchars($searchQuery ?? '') . '"
               aria-label="Search collection">
        <button type="submit">Search</button>
    </form>

    <div class="filter-group">
        <label for="format">Format:</label>
        <select name="format" id="format">
            <option value="">All Formats</option>';

// Get unique formats for the current user
$formats = $db->getUniqueFormats($_SESSION['user_id']);
foreach ($formats as $format) {
    $selected = isset($_GET['format']) && $_GET['format'] === $format ? ' selected' : '';
    $content .= '<option value="' . htmlspecialchars($format) . '"' . $selected . '>' 
              . htmlspecialchars($format) . '</option>';
}

$content .= '
        </select>
    </div>

    <div class="sort-group">
        <label for="sort">Sort by:</label>
        <select name="sort" id="sort">
            <option value="">Default Order</option>
            <option value="date_added"' . (isset($_GET['sort']) && $_GET['sort'] === 'date_added' ? ' selected' : '') . '>Date Added</option>
            <option value="year"' . (isset($_GET['sort']) && $_GET['sort'] === 'year' ? ' selected' : '') . '>Release Year</option>
            <option value="title"' . (isset($_GET['sort']) && $_GET['sort'] === 'title' ? ' selected' : '') . '>Title</option>
            <option value="artist"' . (isset($_GET['sort']) && $_GET['sort'] === 'artist' ? ' selected' : '') . '>Artist</option>
        </select>
        <select name="direction" id="direction">
            <option value="ASC"' . (!isset($_GET['direction']) || $_GET['direction'] === 'ASC' ? ' selected' : '') . '>Ascending</option>
            <option value="DESC"' . (isset($_GET['direction']) && $_GET['direction'] === 'DESC' ? ' selected' : '') . '>Descending</option>
        </select>
    </div>
</div>

<div class="album-grid">';

if (empty($releases) && $searchQuery) {
    $content .= '<p>No releases found matching "' . htmlspecialchars($searchQuery) . '"</p>';
} elseif (empty($releases)) {
    $content .= '<p>Your collection is empty. <a href="?action=search">Add some releases</a> or <a href="?action=import">import your Discogs collection</a>.</p>';
} else {
    foreach ($releases as $release) {
        $content .= '
    <div class="album-card">
        ' . ($release->coverPath ? '<img src="' . htmlspecialchars($release->coverPath) . '" alt="Cover">' : '') . '
        <div class="details">
            <h3>' . htmlspecialchars($release->title) . '</h3>
            <p>' . htmlspecialchars($release->artist) . '</p>';
            
    if ($release->year) {
        $content .= '<p><small>Released: ' . $release->year . '</small></p>';
    }
    
    if ($release->dateAdded) {
        $content .= '<p><small>Added: ' . date('F j, Y', strtotime($release->dateAdded)) . '</small></p>';
    }
    
    $content .= '
            <p>
                <div class="button-groups">
                    <div class="button-group">
                        <a href="?action=view&id=' . $release->id . '" class="button">View Details</a>
                    </div>
                    <div class="button-group">
                        <button class="button remove-item" data-release-id="' . $release->discogsId . '">Remove</button>
                    </div>
                </div>
            </p>
        </div>
    </div>';
    }
}

$content .= '</div>';

$content .= '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const format = document.getElementById("format");
    const sort = document.getElementById("sort");
    const direction = document.getElementById("direction");
    const currentSearch = new URLSearchParams(window.location.search).get("q");

    // Load preferences from cookies if no URL parameters
    if (!new URLSearchParams(window.location.search).has("sort")) {
        const savedSort = getCookie("collection_sort");
        const savedDirection = getCookie("collection_direction");
        if (savedSort) sort.value = savedSort;
        if (savedDirection) direction.value = savedDirection;
    }

    function updateCollection() {
        const params = new URLSearchParams();
        params.set("action", "list");
        
        if (currentSearch) {
            params.set("q", currentSearch);
        }
        if (format.value) {
            params.set("format", format.value);
        }
        if (sort.value) {
            params.set("sort", sort.value);
            setCookie("collection_sort", sort.value, 365);
        }
        if (direction.value) {
            params.set("direction", direction.value);
            setCookie("collection_direction", direction.value, 365);
        }
        
        window.location.href = "?" + params.toString();
    }

    // Cookie helper functions
    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + "=" + value + ";path=/;expires=" + d.toUTCString();
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
    }

    format.addEventListener("change", updateCollection);
    sort.addEventListener("change", updateCollection);
    direction.addEventListener("change", updateCollection);

    // Remove item functionality
    document.querySelectorAll(".remove-item").forEach(button => {
        button.addEventListener("click", async function() {
            const releaseId = this.dataset.releaseId;
            console.log("Remove clicked for release ID:", releaseId); // Debug log
            
            if (confirm("Remove this release from your collection? This will also remove it from your Discogs collection.")) {
                try {
                    console.log("Sending remove request..."); // Debug log
                    const response = await fetch("?action=remove_collection", {
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
                    
                    console.log("Response status:", response.status); // Debug log
                    const responseData = await response.json();
                    console.log("Response data:", responseData); // Debug log
                    
                    if (!response.ok) {
                        throw new Error(responseData.error || "Failed to remove from collection");
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
                    const message = document.createElement("div");
                    message.className = "success-message";
                    message.textContent = "Release removed from collection successfully!";
                    document.querySelector(".album-grid").insertAdjacentElement("beforebegin", message);
                    
                    // Remove message after 3 seconds
                    setTimeout(() => message.remove(), 3000);
                    
                } catch (error) {
                    console.error("Error removing from collection:", error);
                    alert("Error removing from collection: " + error.message);
                }
            }
        });
    });
});
</script>';

require __DIR__ . '/layout.php'; 