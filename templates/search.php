<?php
/** @var Auth $auth Authentication instance */
/** @var DiscogsService $discogs Discogs service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;
use DiscogsHelper\DiscogsService;
use DiscogsHelper\Logger;
use DiscogsHelper\Session;

// Check if user has valid Discogs credentials
if (!isset($discogs)) {
    Session::setMessage('Please set up your Discogs credentials in your profile to search Discogs.');
    header('Location: ?action=profile_edit');
    exit;
}

$content = '
<div class="search-section">
    <h1>Search Releases</h1>
    
    <form class="search-form" method="GET">
     <?= Csrf::getFormField() ?>
        <input type="hidden" name="action" value="search">
        <div class="search-options">
            <label class="search-type">
                <input type="radio" name="search_type" value="text" 
                       ' . (!isset($_GET['search_type']) || $_GET['search_type'] === 'text' ? 'checked' : '') . '>
                Artist/Title
            </label>
            <label class="search-type">
                <input type="radio" name="search_type" value="barcode"
                       ' . (isset($_GET['search_type']) && $_GET['search_type'] === 'barcode' ? 'checked' : '') . '>
                UPC/Barcode
            </label>
        </div>
        <input type="text" name="q" 
               value="' . htmlspecialchars($_GET['q'] ?? '') . '" 
               placeholder="' . ((isset($_GET['search_type']) && $_GET['search_type'] === 'barcode')
        ? 'Enter UPC/Barcode number...'
        : 'Enter artist or release title...') . '">
        <button type="submit">Search</button>
    </form>';

if (isset($_GET['q'])) {
    $content .= '<div class="album-grid">';

    try {
        $currentPage = max(1, intval($_GET['page'] ?? 1));
        
        $searchResults = $discogs->searchRelease(
            $_GET['q'], 
            $currentPage, 
            10,
            isset($_GET['search_type']) && $_GET['search_type'] === 'barcode'
        );
        
        $results = $searchResults['results'];
        $totalResults = $searchResults['pagination']['total'];
        $totalPages = max(1, ceil($totalResults / 10));

        if (empty($results)) {
            $content .= '<div class="no-results">No releases found matching your search.</div>';
        }

    } catch (DiscogsHelper\Exceptions\DiscogsCredentialsException $e) {
        Session::setMessage('Your Discogs credentials appear to be invalid. Please check your settings.');
        header('Location: ?action=profile_edit');
        exit;
    } catch (Exception $e) {
        Logger::error('Search failed: ' . $e->getMessage());
        $content .= '<div class="error">Search failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $results = [];
    }

    foreach ($results as $result) {
        $content .= '
        <div class="album-card">
            ' . (!empty($result['cover_image'])
                ? '<a href="?action=preview&id=' . $result['id'] . '">
                    <img src="' . htmlspecialchars($result['cover_image']) . '" alt="Cover">
                   </a>'
                : '') . '
            <div class="details">
                <h3>' . htmlspecialchars($result['title']) . '</h3>
                <p>Year: ' . ($result['year'] ?? 'Unknown') . '</p>
                <p>
                    <a href="?action=preview&id=' . $result['id'] . '" class="button">View Details</a>
                </p>
            </div>
        </div>';
    }

    $content .= '</div>';

    if ($totalPages > 1) {
        $content .= '<div class="pagination">';
        
        if ($currentPage > 1) {
            $content .= sprintf(
                '<a href="?action=search&q=%s&search_type=%s&page=%d" class="page-link">&laquo; Previous</a>',
                htmlspecialchars($_GET['q']),
                htmlspecialchars($_GET['search_type'] ?? 'text'),
                $currentPage - 1
            );
        }

        for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
            if ($i === $currentPage) {
                $content .= sprintf('<span class="page-link current">%d</span>', $i);
            } else {
                $content .= sprintf(
                    '<a href="?action=search&q=%s&search_type=%s&page=%d" class="page-link">%d</a>',
                    htmlspecialchars($_GET['q']),
                    htmlspecialchars($_GET['search_type'] ?? 'text'),
                    $i,
                    $i
                );
            }
        }

        if ($currentPage < $totalPages) {
            $content .= sprintf(
                '<a href="?action=search&q=%s&search_type=%s&page=%d" class="page-link">Next &raquo;</a>',
                htmlspecialchars($_GET['q']),
                htmlspecialchars($_GET['search_type'] ?? 'text'),
                $currentPage + 1
            );
        }

        $content .= sprintf(
            '<div class="results-count">Showing %d-%d of %d results</div>',
            (($currentPage - 1) * 10) + 1,
            min($currentPage * 10, $totalResults),
            $totalResults
        );

        $content .= '</div>';
    }
}

$content .= '</div>';

$styles = '
<style>
    .search-section {
        margin: 0 auto;
    }
    
    .search-form {
        margin: 2rem 0;
    }
    
    .search-form input[type="text"] {
        width: 100%;
        max-width: 600px;
        margin-bottom: 1rem;
    }
    
    .search-options {
        margin-bottom: 1rem;
    }
    
    .search-type {
        margin-right: 2rem;
    }
    
    .search-type input[type="radio"] {
        margin-right: 0.5rem;
    }
    
    .error {
        color: #d32f2f;
        padding: 1rem;
        background: #ffebee;
        border-radius: 4px;
        margin: 1rem 0;
    }
    
    .pagination {
        margin: 2rem 0;
        text-align: center;
    }
    
    .page-link {
        display: inline-block;
        padding: 0.5rem 1rem;
        margin: 0 0.25rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #333;
    }
    
    .page-link:hover {
        background-color: #f5f5f5;
    }
    
    .page-link.current {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
    }
    
    .results-count {
        margin-top: 1rem;
        color: #666;
        font-size: 0.9rem;
    }
    
    .no-results {
        padding: 2rem;
        text-align: center;
        color: #666;
        background: #f5f5f5;
        border-radius: 4px;
        margin: 2rem 0;
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.querySelector("input[name=\'q\']");
    const radioButtons = document.querySelectorAll("input[name=\'search_type\']");
    
    radioButtons.forEach(radio => {
        radio.addEventListener("change", function() {
            searchInput.placeholder = this.value === "barcode" 
                ? "Enter UPC/Barcode number..."
                : "Enter artist or release title...";
            
            if (this.value === "barcode") {
                searchInput.pattern = "[0-9]*";
                searchInput.inputMode = "numeric";
            } else {
                searchInput.removeAttribute("pattern");
                searchInput.inputMode = "text";
            }
        });
    });
});
</script>';

require __DIR__ . '/layout.php';