<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discogs Helper</title>
    <!-- Water.css - Light theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
    
    <style>
        /* Layout improvements */
        /* Override Water.css default max-width */
        body {
            max-width: none;
        }

        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
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

        /* Header improvements */
        header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        header h1 {
            margin-bottom: 1rem;
        }

        nav a {
            margin: 0 1rem;
            text-decoration: none;
        }

        nav a:hover {
            text-decoration: underline;
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

        /* Add just the search bar styling */
        .header-search {
            margin: 1rem 0;
            text-align: center;
        }

        .header-search input[type="search"] {
            width: 400px;
            margin-right: 0.5rem;
        }

        /* Updated header layout */
        .header-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            margin: 1rem auto;
            max-width: 1400px;
            padding: 0 1rem;
        }

        .header-search {
            display: flex;
            flex: 0 0 auto;
            gap: 0.5rem;
            margin: 0;
            max-width: 400px;
        }

        .header-search input[type="search"] {
            flex: 1;
            margin: 0;
        }

        .header-search button {
            margin: 0;
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
    </style>
    <?php echo $styles ?? ''; // Add any page-specific styles ?>
</head>
<body>
    <header>
        <h1>Discogs Helper</h1>
        <div class="header-nav">
            <nav>
                <a href="?action=home">Home</a>
                <?php if ($auth->isLoggedIn()): ?>
				<a href="?action=search">Search Discogs</a>
				<a href="?action=list">My Collection</a>
				<a href="?action=import">Import Collection</a>
					<a href="?action=logout">Logout</a>
				<span>Welcome, <?= htmlspecialchars($auth->getCurrentUser()->username) ?></span>
                <?php else: ?>
				<a href="?action=login">Login</a>
				<a href="?action=register">Register</a>
                <?php endif; ?>
            </nav>
            <?php // Only show search form for logged-in users ?>
            <?php if ($auth->isLoggedIn() && (!isset($_GET['action']) || $_GET['action'] === 'list')): ?>
				<form class="header-search" method="GET">
					<input type="hidden" name="action" value="list">
					<input type="search"
						   name="q"
						   placeholder="Search your collection..."
						   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
						   aria-label="Search collection">
					<button type="submit">Search</button>
				</form>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <?php echo $content ?? ''; ?>
    </main>

    <footer>
        <p><small>Powered by Discogs API & Coffee</small></p>
    </footer>
</body>
</html> 