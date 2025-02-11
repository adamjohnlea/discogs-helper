<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var DiscogsService $discogs Discogs service instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */
/** @var int|null $id Release ID */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Http\Session;

// Check if user has valid Discogs credentials
if (!isset($discogs)) {
    Session::setMessage('Please set up your Discogs credentials in your profile to view release details.');
    header('Location: ?action=profile_edit');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: ?action=search');
    exit;
}

try {
    $release = $discogs->getRelease((int)$_GET['id']);
} catch (DiscogsHelper\Exceptions\DiscogsCredentialsException $e) {
    Session::setMessage('Your Discogs credentials appear to be invalid. Please check your settings.');
    header('Location: ?action=profile_edit');
    exit;
} catch (Exception $e) {
    Logger::error('Failed to fetch release: ' . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?= isset($release) ? htmlspecialchars($release['title']) : 'Release Preview' ?></title>
	<style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .cover {
            max-width: 300px;
            height: auto;
        }
        .error {
            color: red;
            padding: 10px;
            border: 1px solid red;
            margin-bottom: 20px;
        }
        .details {
            margin-top: 20px;
        }
        .tracklist {
            list-style: none;
            padding: 0;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .actions a,
        .actions button {
            padding: 10px 20px;
            text-decoration: none;
            color: white;
            border-radius: 4px;
            border: none;
            font-size: 1em;
            cursor: pointer;
        }
        .add-button {
            background-color: #4CAF50;
        }
        .back-button {
            background-color: #666;
        }
        .image-selector {
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .image-option {
            border: 2px solid transparent;
            padding: 5px;
            cursor: pointer;
            text-align: center;
        }
        .image-option.selected {
            border-color: #4CAF50;
        }
        .image-option img {
            max-width: 100%;
            height: auto;
        }
        .image-option .image-info {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }
        .identifiers ul {
            list-style: none;
            padding-left: 20px;
        }
        
        .identifiers li {
            margin-bottom: 0.5rem;
        }
        
        .identifiers code {
            background: #f5f5f5;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .identifiers small {
            color: #666;
            margin-left: 0.5rem;
        }
	</style>
	<script>
		function selectImage(imageUri) {
			document.querySelectorAll('.image-option').forEach(option => {
				option.classList.remove('selected');
			});
			document.querySelector(`[data-uri="${imageUri}"]`).classList.add('selected');
			document.getElementById('selected_image').value = imageUri;
		}
	</script>
</head>
<body>
<div class="container">
    <?php if (isset($error)): ?>
		<div class="error">
            <?= htmlspecialchars($error) ?>
		</div>
		<a href="?action=search" class="back-button">← Back to search</a>
    <?php elseif (isset($release)): ?>
		<h1><?= htmlspecialchars($release['title']) ?></h1>

        <?php if (!empty($release['images'])): ?>
			<h2>Select Cover Image</h2>
			<div class="image-selector">
                <?php foreach ($release['images'] as $index => $image): ?>
					<div class="image-option <?= $index === 0 ? 'selected' : '' ?>"
						 data-uri="<?= htmlspecialchars($image['uri']) ?>"
						 onclick="selectImage('<?= htmlspecialchars($image['uri']) ?>')">
						<img src="<?= htmlspecialchars($image['uri150'] ?? $image['uri']) ?>"
							 alt="Cover option <?= $index + 1 ?>">
						<div class="image-info">
                            <?= $image['width'] ?>x<?= $image['height'] ?>
                            <?php if (!empty($image['type'])): ?>
								<br><?= htmlspecialchars(ucfirst($image['type'])) ?>
                            <?php endif; ?>
						</div>
					</div>
                <?php endforeach; ?>
			</div>
        <?php endif; ?>

		<div class="details">
			<p><strong>Artist(s):</strong>
                <?= htmlspecialchars(implode(', ', array_column($release['artists'], 'name'))) ?>
			</p>

			<p><strong>Year:</strong> <?= $release['year'] ?? 'Unknown' ?></p>

            <?php if (!empty($release['identifiers'])): ?>
                <div class="identifiers">
                    <p><strong>Identifiers:</strong></p>
                    <ul>
                    <?php
                    // Group identifiers by type
                    $groupedIdentifiers = [];
                    foreach ($release['identifiers'] as $identifier) {
                        $type = strtolower($identifier['type']);
                        if (!isset($groupedIdentifiers[$type])) {
                            $groupedIdentifiers[$type] = [];
                        }
                        $groupedIdentifiers[$type][] = $identifier;
                    }
                    
                    // Display identifiers in a specific order
                    $typeOrder = ['barcode', 'upc', 'matrix', 'matrix / runout', 'label code', 'rights society', 'catalog number', 'other'];
                    foreach ($typeOrder as $type) {
                        if (isset($groupedIdentifiers[$type])) {
                            foreach ($groupedIdentifiers[$type] as $identifier) {
                                echo '<li>' .
                                    htmlspecialchars(ucfirst($identifier['type'])) . ': ' .
                                    '<code>' . htmlspecialchars($identifier['value']) . '</code>' .
                                    (!empty($identifier['description']) ? 
                                        ' <small>(' . htmlspecialchars($identifier['description']) . ')</small>' : '') .
                                    '</li>';
                            }
                        }
                    }
                    
                    // Show any remaining types that weren't in our predefined order
                    foreach ($groupedIdentifiers as $type => $typeIdentifiers) {
                        if (!in_array($type, $typeOrder)) {
                            foreach ($typeIdentifiers as $identifier) {
                                echo '<li>' .
                                    htmlspecialchars(ucfirst($identifier['type'])) . ': ' .
                                    '<code>' . htmlspecialchars($identifier['value']) . '</code>' .
                                    (!empty($identifier['description']) ? 
                                        ' <small>(' . htmlspecialchars($identifier['description']) . ')</small>' : '') .
                                    '</li>';
                            }
                        }
                    }
                    ?>
                    </ul>
                </div>
            <?php endif; ?>

			<p><strong>Format:</strong>
                <?= htmlspecialchars(implode(', ', array_map(function($format) {
                    return $format['name'] . (!empty($format['descriptions'])
                            ? ' (' . implode(', ', $format['descriptions']) . ')'
                            : '');
                }, $release['formats']))) ?>
			</p>

            <?php if (!empty($release['tracklist'])): ?>
				<h2>Tracklist:</h2>
				<ul class="tracklist">
                    <?php foreach ($release['tracklist'] as $track): ?>
						<li>
                            <?= htmlspecialchars($track['position'] ?? '') ?>
                            <?= htmlspecialchars($track['title']) ?>
                            <?= !empty($track['duration']) ? ' (' . htmlspecialchars($track['duration']) . ')' : '' ?>
						</li>
                    <?php endforeach; ?>
				</ul>
            <?php endif; ?>

            <?php if (!empty($release['notes'])): ?>
				<h2>Notes:</h2>
				<p><?= nl2br(htmlspecialchars($release['notes'])) ?></p>
            <?php endif; ?>
		</div>

		<div class="actions">
			<form action="?action=add" method="POST" id="addForm">
                <?= Csrf::getFormField() ?>
				<input type="hidden" name="id" value="<?= $release['id'] ?>">
				<input type="hidden" name="selected_image" id="selected_image"
					   value="<?= !empty($release['images']) ? htmlspecialchars($release['images'][0]['uri']) : '' ?>">
				<button type="submit" class="add-button" id="addButton">Add to Collection</button>
			</form>
			<a href="javascript:history.back()" class="back-button">← Back to search</a>
		</div>
    <?php endif; ?>
</div>

<script>
document.getElementById('addForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const button = document.getElementById('addButton');
    button.disabled = true;
    button.textContent = 'Adding...';

    // Submit the form
    fetch(this.action, {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            window.location.href = '?action=list';
        } else {
            console.error('Server error:', data.message);
            alert(data.message || 'Failed to add release to collection');
            button.disabled = false;
            button.textContent = 'Add to Collection';
        }
    })
    .catch(error => {
        console.error('Error details:', error);
        alert('An error occurred while adding the release: ' + error.message);
        button.disabled = false;
        button.textContent = 'Add to Collection';
    });
});
</script>
</body>
</html>