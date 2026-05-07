<?php
require_once __DIR__ . '/api/db.php';

$pdo = db_connect();
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM reward_catalog');
$result = $stmt->fetch();
echo "Rewards in catalog: " . $result['cnt'] . "\n";

$stmt = $pdo->query('SELECT id, code, title FROM reward_catalog ORDER BY id ASC');
$rewards = $stmt->fetchAll();
if ($rewards) {
    echo "Rewards:\n";
    foreach ($rewards as $r) {
        echo "  - " . $r['id'] . ": " . $r['code'] . " (" . $r['title'] . ")\n";
    }
} else {
    echo "No rewards found!\n";
}
