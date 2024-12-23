<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\DiscogsService;
use DiscogsHelper\Session;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logger;

if (!isset($_GET['id']) && !isset($_POST['id'])) {
    // Handle JSON request
    $jsonInput = file_get_contents('php://input');
    if (!empty($jsonInput)) {
        $data = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Validate CSRF token
            try {
                Csrf::validateOrFail($data['csrf_token'] ?? null);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid security token']);
                exit;
            }
            $_POST['id'] = $data['id'];
        }
    }

    if (!isset($_POST['id'])) {
        header('Location: ?action=search');
        exit;
    }
}

try {
    $release = $discogs->getRelease((int)($_POST['id'] ?? $_GET['id']));

    // Get current user ID
    $userId = $auth->getCurrentUser()->id;

    // Check if this is from wantlist and get wantlist item
    $wantlistItem = $db->getWantlistItem($userId, (int)$release['id']);
    $coverPath = null;

    if ($wantlistItem && !empty($wantlistItem['cover_path'])) {
        // Move the cover from wantlist to collection covers directory
        $oldPath = __DIR__ . '/../public/' . $wantlistItem['cover_path'];
        $newFilename = basename($wantlistItem['cover_path']);
        $newPath = __DIR__ . '/../public/images/covers/' . $newFilename;
        
        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
            $coverPath = '/images/covers/' . $newFilename;
        }
    } else if (!empty($_POST['selected_image'])) {
        // If not from wantlist or no cover, download from Discogs
        $coverPath = $discogs->downloadCover($_POST['selected_image']);
    }

    // Format details
    $formatDetails = array_map(function($format) {
        return $format['name'] . (!empty($format['descriptions'])
                ? ' (' . implode(', ', $format['descriptions']) . ')'
                : '');
    }, $release['formats']);

    // Save to database
    try {
        $db->saveRelease(
            userId: $userId,
            discogsId: (int)$release['id'],
            title: $release['title'],
            artist: implode(', ', array_column($release['artists'], 'name')),
            year: isset($release['year']) ? (int)$release['year'] : null,
            format: $release['formats'][0]['name'],
            formatDetails: implode(', ', $formatDetails),
            coverPath: $coverPath,
            notes: $release['notes'] ?? null,
            tracklist: json_encode($release['tracklist']),
            identifiers: json_encode($release['identifiers'] ?? []),
            dateAdded: date('Y-m-d\TH:i:s')
        );

        // If this was from wantlist, remove it from wantlist
        if ($wantlistItem) {
            $db->deleteWantlistItem($userId, (int)$release['id']);
            
            // Also remove from Discogs wantlist and add to collection
            $profile = $db->getUserProfile($userId);
            if ($profile && $profile->discogsUsername) {
                try {
                    // First add to collection
                    $discogs->addToCollection($profile->discogsUsername, (int)$release['id']);
                    Logger::log("Added item {$release['id']} to Discogs collection for user {$profile->discogsUsername}");

                    // Then remove from wantlist
                    $discogs->removeFromWantlist($profile->discogsUsername, (int)$release['id']);
                    Logger::log("Removed item {$release['id']} from Discogs wantlist for user {$profile->discogsUsername}");
                } catch (Exception $e) {
                    // Log the error but continue with the process
                    Logger::error("Failed to update Discogs: " . $e->getMessage());
                }
            }
        }

        // If this was a JSON request, return JSON response
        if (!empty($jsonInput)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header('Location: ?action=list');
        exit;
    } catch (DiscogsHelper\Exceptions\DuplicateReleaseException $e) {
        $error = $e->getMessage();
        $isDuplicate = true;

        // If this was a JSON request, return JSON error
        if (!empty($jsonInput)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $error]);
            exit;
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();

    // If this was a JSON request, return JSON error
    if (!empty($jsonInput)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error]);
        exit;
    }
}

// Only show HTML response for non-JSON requests
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?= isset($isDuplicate) ? 'Release Already Exists' : 'Error Adding Release' ?></title>
	<style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .message {
            padding: 10px;
            border: 1px solid;
            margin-bottom: 20px;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .info {
            color: #004085;
            background-color: #cce5ff;
            border-color: #b8daff;
        }
        .actions {
            margin-top: 20px;
        }
        .actions a {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            color: white;
            background-color: #666;
            border-radius: 4px;
            margin-right: 10px;
        }
        .view-button {
            background-color: #28a745 !important;
        }
	</style>
</head>
<body>
<div class="container">
	<h1><?= isset($isDuplicate) ? 'Release Already Exists' : 'Error Adding Release' ?></h1>
    <?php if (isset($error)): ?>
		<div class="message <?= isset($isDuplicate) ? 'info' : 'error' ?>">
            <?= htmlspecialchars($error) ?>
		</div>
		<div class="actions">
            <?php if (isset($isDuplicate)): ?>
                <?php
                // Get the existing release ID
                $existingId = $db->getDiscogsReleaseId($userId, (int)$release['id']);
                ?>
				<a href="?action=view&id=<?= $existingId ?>" class="view-button">View Existing Entry</a>
            <?php endif; ?>
			<a href="?action=search">← Back to search</a>
		</div>
    <?php endif; ?>
</div>
</body>
</html>