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
        body {
            max-width: none;
        }

        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Make the Discogs username input wider */
        #username {
            font-size: 1.5rem;
            padding: 0.5rem;
            width: 100%;
            max-width: 800px; /* Much wider now */
        }

        /* Make the username form take up more space */
        form:has(#username) {
            max-width: 800px;
            width: 100%;
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
    </style>
    <?php echo $styles ?? ''; // Add any page-specific styles ?>
</head>
<body>
    <header>
        <h1>Discogs Helper</h1>
        <nav>
            <a href="/">Home</a>
            <a href="/list">My Collection</a>
        </nav>
    </header>

    <main>
        <?php echo $content ?? ''; ?>
    </main>

    <footer>
        <p><small>Powered by Discogs API & Coffee</small></p>
    </footer>
</body>
</html> 