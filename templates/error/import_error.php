<?php
/** @var string $message Error message */
/** @var int|null $importStateId Import state ID for resuming */
/** @var Database $db Database instance */
/** @var Auth $auth Authentication instance */

$userId = $auth->getCurrentUser()->id;
$failedItems = $db->getFailedItems($userId);

$content = '
<div class="error-container">
    <h1>Import Error</h1>
    <p>' . htmlspecialchars($message) . '</p>';

if (!empty($failedItems)) {
    $content .= '
    <div class="failed-items">
        <h2>Failed Items</h2>
        <ul>';
    foreach ($failedItems as $item) {
        $content .= '<li>Release ID ' . $item['id'] . ': ' . htmlspecialchars($item['error']) . '</li>';
    }
    $content .= '
        </ul>
    </div>';
}

$content .= '
    <div class="actions">';

if ($importStateId) {
    $content .= '
        <a href="?action=retry_import&id=' . $importStateId . '" class="button">Retry Failed Items</a>
        <a href="?action=resume_import&id=' . $importStateId . '" class="button">Resume Import</a>';
}

$content .= '
        <a href="?action=list" class="button secondary">Return to Collection</a>
    </div>
</div>';

require __DIR__ . '/../layout.php'; 