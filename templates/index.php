<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;

if (!$auth->isLoggedIn()) {
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
            <a href="?action=login" class="button">Login</a>
            <a href="?action=register" class="button">Register</a>
        </div>
    </div>';
} else {
    // Get the current user's collection stats
    $userId = $auth->getCurrentUser()->id;
    $collectionSize = $db->getCollectionSize($userId);
    $latestRelease = $db->getLatestRelease($userId);
    $uniqueArtistCount = $db->getUniqueArtistCount($userId);
    $topArtists = $db->getTopArtists($userId, 5);
    $formatDistribution = $db->getFormatDistribution($userId);
    $yearRange = $db->getYearRange($userId);
    $recentActivity = $db->getRecentActivity($userId, 30);
    $monthlyGrowth = $db->getMonthlyGrowth($userId, 12);

    // Get the daily additions data
    $dailyAdditions = $db->getDailyAdditions($userId);

    // Calculate format percentages
    $formatPercentages = array_map(function($format) use ($collectionSize) {
        return [
            'format' => $format['format'],
            'percentage' => round(($format['count'] / $collectionSize) * 100)
        ];
    }, $formatDistribution);

    // Prepare monthly growth data for the chart
    $growthLabels = array_map(function($item) {
        return date('M Y', strtotime($item['month'] . '-01'));
    }, $monthlyGrowth);
    
    $growthData = array_map(function($item) {
        return $item['count'];
    }, $monthlyGrowth);

    $content = '
    <div class="dashboard-section">
        <h1>Your Collection Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card collection-stats">
                <h3>Collection Stats</h3>
                <div class="stat-row">
                    <div class="stat-item">
                        <p class="stat-number">' . number_format($collectionSize) . '</p>
                        <p class="stat-label">Records</p>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <p class="stat-number">' . number_format($uniqueArtistCount) . '</p>
                        <p class="stat-label">Artists</p>
                    </div>
                </div>
                
                <div class="additional-stats">
                    <div class="stat-detail formats">
                        <span class="detail-label">Formats:</span>
                        <span class="detail-value">' . 
                            implode(', ', array_map(function($format) {
                                return $format['format'] . ' ' . $format['percentage'] . '%';
                            }, array_slice($formatPercentages, 0, 3))) . 
                        '</span>
                    </div>';

    if ($yearRange && $yearRange['oldest'] && $yearRange['newest']) {
        $content .= '
                    <div class="stat-detail years">
                        <span class="detail-label">Years:</span>
                        <span class="detail-value">' . $yearRange['oldest'] . ' - ' . $yearRange['newest'] . '</span>
                    </div>';
    }

    if ($recentActivity > 0) {
        $content .= '
                    <div class="stat-detail recent">
                        <span class="detail-label">Last 30 Days:</span>
                        <span class="detail-value">+' . $recentActivity . ' records</span>
                    </div>';
    }

    $content .= '
                    <div class="growth-chart-container">
                        <div class="chart-header">
                            <h4>Collection Growth</h4>
                        </div>
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>
            </div>';

    if ($latestRelease) {
        $content .= '
            <div class="stat-card latest-addition">
                <h3>Latest Addition</h3>
                ' . ($latestRelease->coverPath 
                    ? '<img src="' . htmlspecialchars($latestRelease->getCoverUrl()) . '" 
                           alt="Latest addition cover" class="latest-cover">' 
                    : '') . '
                <p class="latest-title">' . htmlspecialchars($latestRelease->title) . '</p>
                <p class="latest-artist">' . htmlspecialchars($latestRelease->artist) . '</p>
                <p class="stat-label">Added ' . date('F j, Y', strtotime($latestRelease->dateAdded)) . '</p>
            </div>';
    }

    $content .= '
        </div>';

    if (!empty($topArtists)) {
        $content .= '
        <div class="top-artists-section">
            <h3>Top Artists</h3>
            <div class="artist-list">';
            
        foreach ($topArtists as $artist) {
            $content .= '
                <div class="artist-item">
                    <span class="artist-name"><a href="?action=list&q=' . urlencode($artist['artist']) . '">' . htmlspecialchars($artist['artist']) . '</a></span>
                    <span class="artist-count">' . number_format($artist['count']) . ' records</span>
                </div>';
        }
        
        $content .= '
            </div>
        </div>';
    }

    $content .= '
        <div class="action-buttons">
            <a href="?action=search" class="button">Search Discogs</a>
            <a href="?action=list" class="button">View Collection</a>
            <a href="?action=import" class="button">Import Collection</a>
        </div>
    </div>';
}

// Add some specific styles for both welcome and dashboard pages
$styles = '
<style>
    .welcome-section,
    .dashboard-section {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        text-align: center;
    }

    .welcome-section p,
    .dashboard-section p {
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
        margin: 0 0 1rem 0;
        color: #666;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin: 0.5rem 0;
        color: #1a73e8;
    }

    .stat-label {
        color: #666;
        margin: 0;
    }

    .top-artists-section {
        background: white;
        padding: 1.5rem;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin: 1.5rem 0;
        text-align: left;
    }

    .top-artists-section h3 {
        color: #666;
        margin: 0 0 1rem 0;
        text-align: center;
    }

    .artist-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .artist-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    .artist-item:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .artist-name {
        font-weight: 500;
    }

    .artist-name a {
        color: #1a73e8;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .artist-name a:hover {
        color: #1557b0;
        text-decoration: underline;
    }

    .artist-count {
        color: #666;
        font-size: 0.9rem;
    }

    .latest-addition {
        text-align: center;
    }

    .latest-cover {
        width: 120px;
        height: 120px;
        object-fit: contain;
        margin: 1rem auto;
        border-radius: 4px;
    }

    .latest-title {
        font-weight: bold;
        margin: 0.5rem 0 0.25rem 0;
        font-size: 1.1rem;
    }

    .latest-artist {
        color: #666;
        margin: 0 0 0.5rem 0;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-buttons .button {
            width: 100%;
        }
    }

    .collection-stats .stat-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 2rem;
    }

    .collection-stats .stat-item {
        flex: 1;
    }

    .stat-divider {
        width: 1px;
        height: 50px;
        background: rgba(0, 0, 0, 0.1);
    }

    .collection-stats .stat-number {
        margin: 0;
    }

    .additional-stats {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        text-align: left;
    }

    .stat-detail {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
    }

    .detail-label {
        color: #666;
        font-weight: 500;
    }

    .detail-value {
        color: #1a73e8;
    }

    .growth-chart-container {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .chart-header h4 {
        color: #666;
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0;
    }

    #growthChart {
        margin-top: 0.5rem;
        height: 60px !important;
        width: 100% !important;
    }
</style>';

// Add Chart.js implementation
$content .= '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById("growthChart").getContext("2d");
    new Chart(ctx, {
        type: "line",
        data: {
            labels: ' . json_encode($growthLabels) . ',
            datasets: [{
                data: ' . json_encode($growthData) . ',
                borderColor: "rgba(26, 115, 232, 0.8)",
                backgroundColor: "rgba(26, 115, 232, 0.1)",
                fill: true,
                borderWidth: 1.5,
                pointRadius: 0,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: true,
                    mode: "index",
                    intersect: false,
                    backgroundColor: "rgba(0, 0, 0, 0.8)",
                    padding: 8,
                    cornerRadius: 4,
                    titleFont: {
                        size: 12
                    },
                    bodyFont: {
                        size: 12
                    },
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + " records added";
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        },
                        color: "#666"
                    }
                },
                y: {
                    display: false,
                    beginAtZero: true
                }
            },
            interaction: {
                intersect: false,
                mode: "index"
            }
        }
    });
});
</script>';

require __DIR__ . '/layout.php';