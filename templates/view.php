<?php
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

if ($release->notes) {
    $content .= '
    <div class="notes-section">
        <h2>Notes</h2>
        <p>' . nl2br(htmlspecialchars($release->notes)) . '</p>
    </div>';
}

$content .= '</div>';

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

    @media (max-width: 768px) {
        .release-details {
            grid-template-columns: 1fr;
        }
    }
</style>';

require __DIR__ . '/layout.php'; 