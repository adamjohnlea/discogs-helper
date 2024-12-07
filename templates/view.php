<?php
$release = $db->getReleaseById($id);
if (!$release) {
    header('HTTP/1.0 404 Not Found');
    echo 'Release not found';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($release->title) ?></title>
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .cover {
            max-width: 300px;
            height: auto;
        }
        .details {
            margin-top: 20px;
        }
        .tracklist {
            list-style: none;
            padding: 0;
        }
        .barcode {
            font-family: monospace;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="?action=list">← Back to list</a>
        <h1><?= htmlspecialchars($release->title) ?></h1>
        
        <?php if ($release->coverPath): ?>
            <img class="cover" src="<?= htmlspecialchars($release->getCoverUrl()) ?>" 
                 alt="<?= htmlspecialchars($release->title) ?>">
        <?php endif; ?>

        <div class="details">
            <p><strong>Artist(s):</strong> <?= htmlspecialchars($release->artist) ?></p>
            <p><strong>Year:</strong> <?= $release->year ?? 'Unknown' ?></p>
            <p><strong>Format:</strong> <?= htmlspecialchars($release->formatDetails) ?></p>

            <?php if ($identifiers = $release->getIdentifiersArray()): ?>
                <p><strong>Identifiers:</strong></p>
                <ul>
                    <?php foreach ($identifiers as $identifier): ?>
                        <?php if (in_array(strtolower($identifier['type']), ['barcode', 'upc'])): ?>
                            <li>
                                <?= htmlspecialchars(ucfirst($identifier['type'])) ?>: 
                                <span class="barcode"><?= htmlspecialchars($identifier['value']) ?></span>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($tracklist = $release->getTracklistArray()): ?>
                <h2>Tracklist:</h2>
                <ul class="tracklist">
                    <?php foreach ($tracklist as $track): ?>
                        <li>
                            <?= htmlspecialchars($track['position'] ?? '') ?>
                            <?= htmlspecialchars($track['title']) ?>
                            <?= !empty($track['duration']) ? ' (' . htmlspecialchars($track['duration']) . ')' : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($release->notes): ?>
                <h2>Notes:</h2>
                <p><?= nl2br(htmlspecialchars($release->notes)) ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 