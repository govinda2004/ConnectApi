<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$limit = max(1, min(50, (int)($_GET['limit'] ?? 8)));
$db = getDB();

$stmt = $db->prepare('
    SELECT n.created_at, u.name AS actor_name, n.type AS action, n.message AS target
    FROM notifications n
    LEFT JOIN users u ON u.id = n.actor_id
    ORDER BY n.created_at DESC
    LIMIT ?
');
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

jsonSuccess(['items' => $rows], 'Recent activity fetched');
