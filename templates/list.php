<?php
$content = '
<h1>My Releases</h1>
<p>
    <a href="?action=search">Search Discogs</a> |
    <a href="?action=import">Import Collection</a>
</p>
<div class="album-grid">';

foreach ($db->getAllReleases() as $release) {
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

$content .= '</div>';

require __DIR__ . '/layout.php'; 