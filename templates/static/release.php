<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($release->title) ?> by <?= htmlspecialchars($release->artist) ?> - <?= htmlspecialchars($username) ?>'s Collection</title>
    <style>
        :root {
            --primary-color: #1a73e8;
            --text-color: #333;
            --background-color: #fff;
            --card-background: #f8f9fa;
            --border-color: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .release-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .release-title {
            font-size: 2.5rem;
            margin: 0;
            color: var(--text-color);
        }

        .release-artist {
            font-size: 1.5rem;
            color: #666;
            margin: 0.5rem 0;
        }

        .release-meta {
            color: #666;
            font-size: 1.1rem;
        }

        .release-cover {
            text-align: center;
            margin: 2rem 0;
        }

        .release-cover img {
            max-width: 300px;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .release-details {
            background: var(--card-background);
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1.1rem;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--primary-color);
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .tracklist {
            list-style: none;
            padding: 0;
        }

        .track-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .track-item:last-child {
            border-bottom: none;
        }

        .track-position {
            color: #666;
            margin-right: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .release-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/collections/<?= htmlspecialchars($username) ?>#collection" class="back-link">← Back to Collection</a>

        <header class="release-header">
            <h1 class="release-title"><?= htmlspecialchars($release->title) ?></h1>
            <p class="release-artist"><?= htmlspecialchars($release->artist) ?></p>
            <p class="release-meta">
                Added <?= date('F j, Y', strtotime($release->dateAdded)) ?>
            </p>
        </header>

        <?php if ($release->coverPath): ?>
        <div class="release-cover">
            <img src="<?= htmlspecialchars($release->getCoverUrl()) ?>" 
                 alt="Cover of <?= htmlspecialchars($release->title) ?>"
                 loading="lazy">
        </div>
        <?php endif; ?>

        <div class="release-details">
            <div class="detail-group">
                <div class="detail-label">Format</div>
                <div class="detail-value">
                    <?= htmlspecialchars($release->format) ?>
                    <?php if ($release->formatDetails): ?>
                        (<?= htmlspecialchars($release->formatDetails) ?>)
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($release->year): ?>
            <div class="detail-group">
                <div class="detail-label">Release Year</div>
                <div class="detail-value"><?= $release->year ?></div>
            </div>
            <?php endif; ?>

            <?php 
            $tracklist = $release->getTracklistArray();
            if (!empty($tracklist)): 
            ?>
            <div class="detail-group">
                <div class="detail-label">Tracklist</div>
                <ol class="tracklist">
                    <?php foreach ($tracklist as $track): ?>
                    <li class="track-item">
                        <span class="track-position"><?= htmlspecialchars($track['position'] ?? '') ?></span>
                        <?= htmlspecialchars($track['title']) ?>
                        <?php if (!empty($track['duration'])): ?>
                            <span class="track-duration">(<?= htmlspecialchars($track['duration']) ?>)</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

            <?php if ($release->notes): ?>
            <div class="detail-group">
                <div class="detail-label">Notes</div>
                <div class="detail-value"><?= nl2br(htmlspecialchars($release->notes)) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 