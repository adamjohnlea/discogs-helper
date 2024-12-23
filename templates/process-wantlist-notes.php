<?php

declare(strict_types=1);

/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */

use DiscogsHelper\Security\Csrf;
use DiscogsHelper\Logger;
use DiscogsHelper\Session;

// Validate CSRF token
try {
    Csrf::validateOrFail($_POST['csrf_token'] ?? null);
} catch (Exception $e) {
    Logger::error('Invalid CSRF token in wantlist notes update');
    Session::setMessage('Invalid security token. Please try again.');
    header('Location: ?action=wantlist');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$itemId = isset($_POST['itemId']) ? (int)$_POST['itemId'] : 0;
$notes = $_POST['notes'] ?? '';

// Validate item exists and belongs to user
$item = $db->getWantlistItemById($userId, $itemId);
if (!$item) {
    Logger::error('Attempt to update notes for non-existent wantlist item');
    Session::setMessage('Item not found in your wantlist');
    header('Location: ?action=wantlist');
    exit;
}

try {
    if ($db->updateWantlistNotes($userId, $itemId, $notes)) {
        Session::setMessage('Notes updated successfully');
    } else {
        throw new RuntimeException('Failed to update notes');
    }
} catch (Exception $e) {
    Logger::error('Error updating wantlist notes: ' . $e->getMessage());
    Session::setMessage('Failed to update notes. Please try again.');
}

header('Location: ?action=view_wantlist&id=' . $itemId);
exit;