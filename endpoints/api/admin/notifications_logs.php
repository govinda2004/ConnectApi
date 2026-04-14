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
$user = trim((string)($_GET['user'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$where = ["n.type = 'admin_notice'"];
$params = [];
if ($user !== '') {
    $where[] = '(uu.name LIKE ? OR uu.email LIKE ? OR au.name LIKE ? OR au.email LIKE ?)';
    $params[] = "%{$user}%";
    $params[] = "%{$user}%";
    $params[] = "%{$user}%";
    $params[] = "%{$user}%";
}
if ($action !== '') {
    $where[] = 'n.type LIKE ?';
    $params[] = "%{$action}%";
}
if ($search !== '') {
    $where[] = '(n.message LIKE ? OR n.image_url LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($from !== '') {
    $where[] = 'DATE(n.created_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(n.created_at) <= ?';
    $params[] = $to;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countSql = "
    SELECT COUNT(*)
    FROM notifications n
    LEFT JOIN users au ON au.id = n.actor_id
    LEFT JOIN users uu ON uu.id = n.user_id
    {$whereSql}
";
$countStmt = $db->prepare($countSql);
$i = 1;
foreach ($params as $p) $countStmt->bindValue($i++, $p, PDO::PARAM_STR);
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

$sql = "
    SELECT n.id, n.created_at, n.user_id, n.actor_id, n.type, n.message, n.image_url,
           au.name AS actor_name, au.email AS actor_email,
           uu.name AS receiver_name, uu.email AS receiver_email
    FROM notifications n
    LEFT JOIN users au ON au.id = n.actor_id
    LEFT JOIN users uu ON uu.id = n.user_id
    {$whereSql}
    ORDER BY n.id DESC
    LIMIT ? OFFSET ?
$stmt = $db->prepare($sql);
$i = 1;
foreach ($params as $p) $stmt->bindValue($i++, $p, PDO::PARAM_STR);
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

jsonSuccess([
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
], 'Admin notification logs fetched');
