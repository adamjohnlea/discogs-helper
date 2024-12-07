<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Releases</title>
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .search-form {
            margin-bottom: 20px;
        }
        .search-form input[type="text"] {
            width: 70%;
            padding: 8px;
        }
        .search-form button {
            padding: 8px 16px;
        }
        .results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .result {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .result img {
            max-width: 100%;
            height: auto;
        }
        .result a {
            text-decoration: none;
            color: inherit;
        }
        .result a:hover {
            opacity: 0.8;
        }
        .result a[href*="action=preview"] {
            display: inline-block;
            padding: 5px 10px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            text-decoration: none;
        }
        .search-options {
            margin-bottom: 10px;
        }
        .search-type {
            margin-right: 15px;
        }
        .barcode {
            font-family: monospace;
            color: #666;
            font-size: 0.9em;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="q"]');
            const radioButtons = document.querySelectorAll('input[name="search_type"]');
            
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    searchInput.placeholder = this.value === 'barcode' 
                        ? 'Enter UPC/Barcode number...'
                        : 'Enter artist or release title...';
                    
                    if (this.value === 'barcode') {
                        searchInput.pattern = '[0-9]*';
                        searchInput.inputMode = 'numeric';
                    } else {
                        searchInput.removeAttribute('pattern');
                        searchInput.inputMode = 'text';
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="?action=list">← Back to list</a>
        <h1>Search Releases</h1>
        
        <form class="search-form" method="GET">
            <input type="hidden" name="action" value="search">
            <div class="search-options">
                <label class="search-type">
                    <input type="radio" name="search_type" value="text" 
                           <?= (!isset($_GET['search_type']) || $_GET['search_type'] === 'text') ? 'checked' : '' ?>>
                    Artist/Title
                </label>
                <label class="search-type">
                    <input type="radio" name="search_type" value="barcode"
                           <?= (isset($_GET['search_type']) && $_GET['search_type'] === 'barcode') ? 'checked' : '' ?>>
                    UPC/Barcode
                </label>
            </div>
            <input type="text" name="q" 
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
                   placeholder="<?= (isset($_GET['search_type']) && $_GET['search_type'] === 'barcode') 
                       ? 'Enter UPC/Barcode number...' 
                       : 'Enter artist or release title...' ?>">
            <button type="submit">Search</button>
        </form>

        <?php if (isset($_GET['q'])): ?>
            <div class="results">
                <?php
                try {
                    $results = $discogs->searchRelease($_GET['q']);
                } catch (Exception $e) {
                    echo '<div class="error">Search failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $results = [];
                }
                foreach ($results as $result):
                ?>
                    <div class="result">
                        <?php if (!empty($result['cover_image'])): ?>
                            <a href="?action=preview&id=<?= $result['id'] ?>">
                                <img src="<?= htmlspecialchars($result['cover_image']) ?>" alt="Cover">
                            </a>
                        <?php endif; ?>
                        <h3><a href="?action=preview&id=<?= $result['id'] ?>"><?= htmlspecialchars($result['title']) ?></a></h3>
                        <p>Year: <?= $result['year'] ?? 'Unknown' ?></p>
                        <a href="?action=preview&id=<?= $result['id'] ?>">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 