<?php
/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */

use DiscogsHelper\Auth;
use DiscogsHelper\Database;
use DiscogsHelper\Logger;

header('Content-Type: application/json');

$userId = $auth->getCurrentUser()->id;
$importStateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$importStateId) {
    echo json_encode(['error' => 'Invalid import state ID']);
    exit;
}

$importState = $db->getImportState($userId);
if (!$importState) {
    echo json_encode(['error' => 'Import state not found']);
    exit;
}

// Parse cover stats
$coverStats = json_decode($importState['cover_stats'] ?? '{"total":0,"success":0,"failed":0}', true);

echo json_encode([
    'status' => $importState['status'],
    'current_page' => $importState['current_page'],
    'total_pages' => $importState['total_pages'],
    'processed_items' => $importState['processed_items'],
    'total_items' => $importState['total_items'],
    'cover_stats' => $coverStats,
    'failed_items' => json_decode($importState['failed_items'] ?? '[]', true)
]); 