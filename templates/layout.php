<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discogs Helper</title>
    <!-- Water.css - Light theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    
    <style>
        /* Reset and base styles */
        body {
            max-width: none;
            margin: 0;
            padding-top: 64px; /* Height of fixed header */
        }

        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Modern header design */
        .header-container {
            background: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            z-index: 1000;
        }

        header {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Logo area */
        .logo-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-area h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Main navigation */
        .header-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        nav a {
            color: #555;
            text-decoration: none;
            font-weight: 500;
        }

        nav a:hover {
            color: #1a73e8;
        }

        nav a.active {
            color: #1a73e8;
        }

        /* User area */
        .user-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            font-size: 0.9rem;
            color: #666;
            padding: 0.5rem 1rem;
            background: rgba(0,0,0,0.03);
            border-radius: 20px;
        }

        /* Search bar */
        .header-search {
            display: flex;
            gap: 0.5rem;
            max-width: 400px;
        }

        .header-search input[type="search"] {
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
            width: 100%;
            margin: 0;
        }

        .header-search button {
            border-radius: 20px;
            padding: 0.5rem 1.2rem;
            margin: 0;
            background: #1a73e8;
            border: none;
            color: white;
            cursor: pointer;
        }

        /* Mobile menu button */
        .nav-toggle {
            display: none;
        }

        /* Mobile styles */
        @media (max-width: 768px) {
            .nav-toggle {
                display: block;
                background: none;
                border: none;
                padding: 0.5rem;
                cursor: pointer;
            }

            .nav-toggle span {
                display: block;
                width: 24px;
                height: 2px;
                margin: 5px 0;
                background: #333;
                transition: all 0.3s ease;
            }

            .header-nav {
                display: none;
                position: fixed;
                top: 64px;
                left: 0;
                right: 0;
                background: white;
                padding: 1rem;
                border-top: 1px solid rgba(0,0,0,0.1);
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .header-nav.active {
                display: flex;
                flex-direction: column;
            }

            nav {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            nav a {
                padding: 0.5rem;
                text-align: center;
            }

            nav a.active {
                background: rgba(26, 115, 232, 0.1);
            }

            .header-search {
                width: 100%;
                max-width: none;
                margin-top: 1rem;
            }

            .user-area {
                flex-direction: column;
                width: 100%;
                align-items: stretch;
                gap: 0.5rem;
            }

            .user-info {
                text-align: center;
            }
        }

        /* Album grid improvements */
        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            padding: 1rem 0;
        }
        
        .album-card {
            text-align: center;
            background: rgba(0, 0, 0, 0.03);
            border-radius: 8px;
            padding: 1rem;
            transition: transform 0.2s;
        }
        
        .album-card:hover {
            transform: translateY(-5px);
        }
        
        .album-card img {
            width: 100%;
            height: 250px;
            object-fit: contain;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .album-card h3 {
            margin: 0.5rem 0;
            font-size: 1.1rem;
            line-height: 1.3;
        }

        .album-card p {
            margin: 0.3rem 0;
            line-height: 1.4;
        }

        .album-card small {
            color: #666;
        }

        /* Action buttons */
        .button {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin-top: 0.5rem;
            background: #1a73e8;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .button:hover {
            background: #1557b0;
        }

        /* Footer improvements */
        footer {
            text-align: center;
            padding: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Make the Discogs username input wider */
        #username {
            padding: 0.5rem;
            width: 100%;
            max-width: 800px; /* Increased from previous value */
        }

        form:has(#username) {
            max-width: 800px; /* Match the input width */
            width: 100%;
        }

        .collection-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .search-group {
            flex: 1;
            min-width: 300px;
            display: flex;
            gap: 0.5rem;
            margin: 0;
        }

        .search-group input[type="search"] {
            flex: 1;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
            margin: 0;
        }

        .search-group button {
            border-radius: 20px;
            padding: 0.5rem 1.2rem;
            margin: 0;
            background: #1a73e8;
            border: none;
            color: white;
            cursor: pointer;
        }

        .filter-group,
        .sort-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .collection-toolbar select {
            min-width: 140px;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #ccc;
            background-color: white;
        }

        .sort-group select {
            min-width: 140px; /* Makes sort options wider */
        }

        @media (max-width: 768px) {
            .collection-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-group,
            .filter-group,
            .sort-group {
                width: 100%;
            }

            .search-group input[type="search"] {
                width: 100%;
            }
        }
    </style>
    <?php echo $styles ?? ''; // Add any page-specific styles ?>
</head>
<body>
    <div class="header-container">
        <header>
            <div class="logo-area">
                <h1>Discogs Helper</h1>
            </div>

            <button class="nav-toggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="header-nav">
                <nav>
                    <a href="?action=home" <?= (!isset($_GET['action']) || $_GET['action'] === 'home') ? 'class="active"' : '' ?>>Home</a>
                    <?php if ($auth->isLoggedIn()): ?>
                        <a href="?action=search" <?= (isset($_GET['action']) && $_GET['action'] === 'search') ? 'class="active"' : '' ?>>Search Discogs</a>
                        <a href="?action=list" <?= (isset($_GET['action']) && $_GET['action'] === 'list') ? 'class="active"' : '' ?>>My Collection</a>
                        <a href="?action=import" <?= (isset($_GET['action']) && $_GET['action'] === 'import') ? 'class="active"' : '' ?>>Import Collection</a>
                        <a href="?action=wantlist" <?= (isset($_GET['action']) && $_GET['action'] === 'wantlist') ? 'class="active"' : '' ?>>Want List</a>
                        <a href="?action=recommendations" <?= (isset($_GET['action']) && $_GET['action'] === 'recommendations') ? 'class="active"' : '' ?>>Recommendations</a>
                        <a href="?action=profile" <?= (isset($_GET['action']) && $_GET['action'] === 'profile') ? 'class="active"' : '' ?>>Profile</a>   
                    <?php endif; ?>
                </nav>

                <div class="user-area">
                    <?php if ($auth->isLoggedIn()): ?>
                        <span class="user-info"><?= htmlspecialchars($auth->getCurrentUser()->username) ?></span>
                        <a href="?action=logout">Logout</a>
                    <?php else: ?>
                        <a href="?action=login">Login</a>
                        <a href="?action=register">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
    </div>

    <main>
        <?php echo $content ?? ''; ?>
    </main>

    <footer>
        <p><small>Powered by Discogs API & Coffee Since 2024 | Lovingly Crafted in Boise, Idaho</small></p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.querySelector('.nav-toggle');
            const headerNav = document.querySelector('.header-nav');

            navToggle.addEventListener('click', function() {
                headerNav.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
        });
    </script>
</body>
</html> 