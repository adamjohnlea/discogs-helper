<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($username) ?>'s Collection on DiscogsHelper</title>
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

        .collection-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-background);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 0;
        }

        .stat-label {
            color: #666;
            margin: 0;
        }

        .top-artists {
            margin-top: 2rem;
        }

        .artist-list {
            list-style: none;
            padding: 0;
        }

        .artist-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.03);
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }

        .latest-release {
            text-align: center;
        }

        .latest-release img {
            max-width: 200px;
            border-radius: 4px;
            margin: 1rem 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Add grid styles */
        .releases-section {
            margin-top: 3rem;
        }

        .releases-section h2 {
            text-align: center;
            margin-bottom: 2rem;
        }

        .releases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 2rem;
            padding: 1rem;
        }

        .release-card {
            background: var(--card-background);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .release-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .release-card .release-cover {
            aspect-ratio: 1;
            overflow: hidden;
        }

        .release-card .release-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .release-card .release-info {
            padding: 1rem;
        }

        .release-card .release-title {
            font-size: 1rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }

        .release-card .release-artist {
            font-size: 0.9rem;
            color: #666;
            margin: 0 0 0.25rem 0;
        }

        .release-card .release-year {
            font-size: 0.8rem;
            color: #888;
            margin: 0;
        }

        @media (max-width: 768px) {
            .releases-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
            }
        }

        .nav-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .nav-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            color: #666;
            background: var(--card-background);
            transition: all 0.2s ease;
        }

        .nav-tab:hover {
            background: #e9ecef;
        }

        .nav-tab.active {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="collection-header">
            <h1><?= htmlspecialchars($username) ?>'s Collection</h1>
            <p class="subtitle">Powered by DiscogsHelper</p>
        </header>

        <div class="nav-tabs">
            <a href="#stats" class="nav-tab active" data-view="stats">Collection Stats</a>
            <a href="#collection" class="nav-tab" data-view="collection">View Collection</a>
        </div>

        <div id="stats-view">
            <div class="stats-grid">
                <div class="stat-card">
                    <p class="stat-number"><?= number_format($collectionSize) ?></p>
                    <p class="stat-label">Records</p>
                </div>

                <div class="stat-card">
                    <p class="stat-number"><?= number_format($uniqueArtistCount) ?></p>
                    <p class="stat-label">Artists</p>
                </div>

                <?php if ($yearRange && isset($yearRange['oldest'], $yearRange['newest'])): ?>
                <div class="stat-card">
                    <p class="stat-number"><?= $yearRange['oldest'] ?> - <?= $yearRange['newest'] ?></p>
                    <p class="stat-label">Year Range</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($topArtists)): ?>
            <div class="stat-card top-artists">
                <h2>Top Artists</h2>
                <ul class="artist-list">
                    <?php foreach ($topArtists as $artist): ?>
                    <li class="artist-item">
                        <span class="artist-name"><?= htmlspecialchars($artist['artist']) ?></span>
                        <span class="artist-count"><?= number_format($artist['count']) ?> records</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($latestRelease): ?>
            <div class="stat-card latest-release">
                <h2>Latest Addition</h2>
                <?php if ($latestRelease->coverPath): ?>
                <img src="<?= htmlspecialchars($latestRelease->getCoverUrl()) ?>" 
                     alt="Cover of <?= htmlspecialchars($latestRelease->title) ?>" 
                     loading="lazy">
                <?php endif; ?>
                <h3><?= htmlspecialchars($latestRelease->title) ?></h3>
                <p><?= htmlspecialchars($latestRelease->artist) ?></p>
                <p class="stat-label">Added <?= date('F j, Y', strtotime($latestRelease->dateAdded)) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($releases)): ?>
        <div id="collection-view" style="display: none;">
            <div class="releases-section">
                <h2>Collection</h2>
                
                <div class="releases-grid">
                    <?php foreach ($releases as $release): ?>
                    <a href="/collections/<?= htmlspecialchars($username) ?>/releases/<?= $release->id ?>.html" class="release-card">
                        <?php if ($release->coverPath): ?>
                        <div class="release-cover">
                            <img src="<?= htmlspecialchars($release->getCoverUrl()) ?>" 
                                 alt="Cover of <?= htmlspecialchars($release->title) ?>"
                                 loading="lazy">
                        </div>
                        <?php endif; ?>
                        <div class="release-info">
                            <h3 class="release-title"><?= htmlspecialchars($release->title) ?></h3>
                            <p class="release-artist"><?= htmlspecialchars($release->artist) ?></p>
                            <?php if ($release->year): ?>
                            <p class="release-year"><?= $release->year ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-tab');
            const statsView = document.getElementById('stats-view');
            const collectionView = document.getElementById('collection-view');

            // Check hash on page load
            const hash = window.location.hash || '#stats';
            showView(hash.substring(1));

            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const view = this.dataset.view;
                    showView(view);
                    window.location.hash = view;
                });
            });

            function showView(view) {
                // Update tabs
                tabs.forEach(tab => {
                    tab.classList.toggle('active', tab.dataset.view === view);
                });

                // Show/hide views
                statsView.style.display = view === 'stats' ? 'block' : 'none';
                collectionView.style.display = view === 'collection' ? 'block' : 'none';
            }
        });
    </script>
</body>
</html> 