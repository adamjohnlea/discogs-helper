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

if (!isset($_GET['id']) && !isset($_POST['id'])) {
    header('Location: ?action=search');
    exit;
}

try {
    $release = $discogs->getRelease((int)($_POST['id'] ?? $_GET['id']));

    // Download the largest available image
    $coverPath = null;
    if (!empty($_POST['selected_image'])) {
        $coverPath = $discogs->downloadCover($_POST['selected_image']);
    }

    // Format details
    $formatDetails = array_map(function($format) {
        return $format['name'] . (!empty($format['descriptions'])
                ? ' (' . implode(', ', $format['descriptions']) . ')'
                : '');
    }, $release['formats']);

    // Get current user ID
    $userId = $auth->getCurrentUser()->id;

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
            dateAdded: date('Y-m-d\TH:i:s') // Added this line to match Discogs format
        );

        header('Location: ?action=list');
        exit;
    } catch (DiscogsHelper\Exceptions\DuplicateReleaseException $e) {
        $error = $e->getMessage();
        $isDuplicate = true;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
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