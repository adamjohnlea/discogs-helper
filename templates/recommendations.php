<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var LastFmService $lastfm Last.fm service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var array $recommendations Artist recommendations */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Services\LastFmService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Security\Csrf;

if (!$auth->isLoggedIn()) {
    header('Location: ?action=login');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$profile = $db->getUserProfile($userId);

// Check if Last.fm is configured
if (!$profile || !$profile->hasLastFmCredentials()) {
    Session::setMessage('Please set up your Last.fm API credentials in your profile first.');
    header('Location: ?action=profile_edit');
    exit;
}

// Get the last generation time
$stmt = $db->getPdo()->prepare('
    SELECT generated_at 
    FROM artist_recommendations 
    WHERE user_id = :user_id 
    ORDER BY generated_at DESC 
    LIMIT 1
');
$stmt->execute(['user_id' => $userId]);
$lastGenerated = $stmt->fetch(PDO::FETCH_ASSOC);

$content = '<div class="recommendations-page">';

// Add success/error messages if any
if (Session::hasMessage()) {
    $message = Session::getMessage();
    $content .= '
    <div class="info-message">
        ' . htmlspecialchars($message) . '
    </div>';
}

if (Session::hasErrors()) {
    $errors = Session::getErrors();
    $content .= '
    <div class="error-messages">
        <ul>
            ' . implode('', array_map(fn($error) => "<li>" . htmlspecialchars($error) . "</li>", $errors)) . '
        </ul>
    </div>';
}

$content .= '
    <div class="recommendations-header">
        <h1>Artist Recommendations</h1>
        <div class="recommendations-actions">
            <form method="GET" action="" class="regenerate-form">
                <input type="hidden" name="action" value="recommendations">
                <input type="hidden" name="regenerate" value="1">
                ' . Csrf::getFormField() . '
                <button type="submit" class="button">Regenerate Recommendations</button>
            </form>';

if ($lastGenerated) {
    $content .= '
            <div class="last-generated">
                Last updated: ' . date('F j, Y', strtotime($lastGenerated['generated_at'])) . '
            </div>';
}

$content .= '
        </div>
    </div>
    
    <p class="description">Based on the artists in your collection, you might enjoy:</p>

    <div class="recommendations-grid">';

foreach ($recommendations as $rec) {
    $content .= '
        <div class="recommendation-card">
            <div class="recommendation-header">
                <h2>' . htmlspecialchars($rec['name']) . '</h2>
                <span class="match-score">' . $rec['match'] . '% Match</span>
            </div>
            
            <div class="similar-to">
                <strong>Similar to:</strong> ' . 
                htmlspecialchars(implode(', ', $rec['similar_to'])) . '
            </div>
            
            <div class="tags">
                ' . implode('', array_map(
                    fn($tag) => '<span class="tag">' . htmlspecialchars($tag) . '</span>',
                    $rec['tags']
                )) . '
            </div>
            
            <div class="actions">
                <a href="https://www.discogs.com/search/?q=' . urlencode($rec['name']) . '&type=artist" 
                   target="_blank" class="button">View on Discogs</a>
            </div>
        </div>';
}

if (empty($recommendations)) {
    $content .= '
        <div class="no-recommendations">
            <p>No recommendations available at the moment. This could be because:</p>
            <ul>
                <li>Your collection is empty</li>
                <li>Last.fm API is temporarily unavailable</li>
                <li>All similar artists are already in your collection</li>
            </ul>
            <p>Try clicking the "Regenerate Recommendations" button above.</p>
        </div>';
}

$content .= '
    </div>
</div>';

$styles = '
<style>
    .recommendations-page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
    }

    .recommendations-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .recommendations-header h1 {
        margin: 0;
    }

    .recommendations-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .last-generated {
        color: #666;
        font-size: 0.9rem;
    }

    .description {
        color: #666;
        margin-bottom: 2rem;
    }

    .recommendations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .recommendation-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .recommendation-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .recommendation-header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: #333;
    }

    .match-score {
        background: #e3f2fd;
        color: #1976d2;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-weight: bold;
        font-size: 0.875rem;
    }

    .similar-to {
        font-size: 0.875rem;
        color: #666;
    }

    .tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .tag {
        background: #f5f5f5;
        color: #666;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }

    .actions {
        margin-top: auto;
    }

    .actions .button {
        display: inline-block;
        padding: 0.5rem 1rem;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 0.875rem;
        transition: background-color 0.2s;
    }

    .actions .button:hover {
        background: #0056b3;
    }

    .no-recommendations {
        grid-column: 1 / -1;
        background: #f8f9fa;
        padding: 2rem;
        border-radius: 8px;
        text-align: center;
    }

    .no-recommendations ul {
        text-align: left;
        max-width: 400px;
        margin: 1rem auto;
    }

    .info-message {
        background: #e3f2fd;
        border: 1px solid #90caf9;
        color: #1976d2;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }

    .error-messages {
        background: #ffebee;
        border: 1px solid #ef5350;
        color: #c62828;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }

    .error-messages ul {
        margin: 0;
        padding-left: 1.5rem;
    }

    @media (max-width: 768px) {
        .recommendations-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .recommendations-actions {
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
        }

        .regenerate-form {
            width: 100%;
        }

        .regenerate-form button {
            width: 100%;
        }
    }
</style>';

require __DIR__ . '/layout.php'; 