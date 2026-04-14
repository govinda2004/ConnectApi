<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureNotificationsImageColumn($db);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$count = $db->query("SELECT COUNT(*) FROM notifications WHERE type = 'admin_notice'");
$total = (int)$count->fetchColumn();

$stmt = $db->prepare("
    SELECT n.id, n.created_at, n.user_id, n.actor_id, n.type, n.message, n.image_url,
           au.name AS actor_name, au.email AS actor_email,
           uu.name AS receiver_name, uu.email AS receiver_email
    FROM notifications n
    LEFT JOIN users au ON au.id = n.actor_id
    LEFT JOIN users uu ON uu.id = n.user_id
    WHERE n.type = 'admin_notice'
    ORDER BY n.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

jsonSuccess([
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
], 'Admin notification logs fetched');
