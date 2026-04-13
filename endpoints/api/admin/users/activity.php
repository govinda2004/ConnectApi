<?php

require_once __DIR__ . '/_users_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid user id');

$stmt = $db->prepare('
    SELECT created_at, type AS action, message AS event
    FROM notifications
    WHERE user_id = ? OR actor_id = ?
    ORDER BY created_at DESC
    LIMIT 50
');
$stmt->execute([$id, $id]);
$items = $stmt->fetchAll();

jsonSuccess(['items' => $items], 'User activity fetched');
