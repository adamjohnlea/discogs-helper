<?php
$searchQuery = $_GET['q'] ?? null;
// Make sure user is logged in and get their ID from the session
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header('Location: ?action=login');
    exit;
}
$releases = $db->getAllReleases($_SESSION['user_id'], $searchQuery);

$content = '
<h1>My Releases' . ($searchQuery ? ': Search Results' : '') . '</h1>

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
                <a href="?action=view&id=' . $release->id . '" class="button">View Details</a>
            </p>
        </div>
    </div>';
    }
}

$content .= '</div>';

require __DIR__ . '/layout.php'; 