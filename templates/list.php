<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Releases</title>
    <style>
        .releases {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .release {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .release img {
            max-width: 100%;
            height: auto;
        }
        .date-added {
            color: #666;
            font-size: 0.9em;
            font-style: italic;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <h1>My Releases</h1>
    <p>
        <a href="?action=search">Add New Release</a> |
        <a href="?action=import">Import Collection</a>
    </p>
    <div class="releases">
        <?php foreach ($db->getAllReleases() as $release): ?>
            <div class="release">
                <?php if ($release->coverPath): ?>
                    <img src="<?= htmlspecialchars($release->coverPath) ?>" alt="Cover" class="cover">
                <?php endif; ?>
                <div class="details">
                    <h3><?= htmlspecialchars($release->title) ?></h3>
                    <p class="artist"><?= htmlspecialchars($release->artist) ?></p>
                    <p class="format"><?= htmlspecialchars($release->format) ?> <?= htmlspecialchars($release->formatDetails) ?></p>
                    <?php if ($release->year): ?>
                        <p class="year"><?= $release->year ?></p>
                    <?php endif; ?>
                    <?php if ($release->dateAdded): ?>
                        <p class="date-added">Added to collection: <?= date('F j, Y', strtotime($release->dateAdded)) ?></p>
                    <?php endif; ?>
                    <p class="actions">
                        <a href="?action=view&id=<?= $release->id ?>" class="button">View Details</a>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html> 