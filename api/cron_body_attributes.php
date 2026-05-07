<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is intended to run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/body_attributes.php';

$pdo = db_connect();
$userStmt = $pdo->query('SELECT id FROM users');
$userIds = $userStmt ? $userStmt->fetchAll(PDO::FETCH_COLUMN) : [];
$syncBodyAttributes = 'sync_user_body_attributes';

$updated = 0;
foreach ($userIds as $userId) {
    $syncBodyAttributes($pdo, (int)$userId);
    $updated++;
}

echo "Updated body attributes for {$updated} users.\n";
