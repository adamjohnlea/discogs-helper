<?php
/** @var Auth $auth Authentication instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;

$content = '
<div class="welcome-section">
    <h1>Welcome to Discogs Helper</h1>
    
    <p>Discogs Helper is a tool designed to help you manage and explore your vinyl record collection. 
    With this application, you can:</p>
    
    <ul>
        <li>Search and browse the Discogs database</li>
        <li>Import your existing Discogs collection</li>
        <li>Manage and organize your vinyl records</li>
        <li>Keep track of your collection\'s details</li>
    </ul>

    <div class="action-buttons">
        <a href="?action=search" class="button">Search Discogs</a>
        <a href="?action=list" class="button">View Collection</a>
        <a href="?action=import" class="button">Import Collection</a>
    </div>
</div>';

// Add some specific styles for the welcome page
$styles = '
<style>
    .welcome-section {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        text-align: center;
    }

    .welcome-section p {
        font-size: 1.1rem;
        line-height: 1.6;
        margin: 1.5rem 0;
    }

    .welcome-section ul {
        text-align: left;
        display: inline-block;
        margin: 1.5rem auto;
        font-size: 1.1rem;
        line-height: 1.6;
    }

    .action-buttons {
        margin-top: 2rem;
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .action-buttons .button {
        font-size: 1.1rem;
        padding: 0.75rem 1.5rem;
    }
</style>';

require __DIR__ . '/layout.php';