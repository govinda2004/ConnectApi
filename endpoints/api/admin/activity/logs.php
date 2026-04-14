<?php

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureNotificationsImageColumn($db);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;
$user = trim((string)($_GET['user'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$where = [];
$params = [];
if ($user !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$user}%";
    $params[] = "%{$user}%";
}
if ($action !== '') {
    $where[] = '(n.type LIKE ? OR n.message LIKE ?)';
    $params[] = "%{$action}%";
    $params[] = "%{$action}%";
}
if ($from !== '') {
    $where[] = 'DATE(n.created_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(n.created_at) <= ?';
    $params[] = $to;
}
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$count = $db->prepare("SELECT COUNT(*) FROM notifications n LEFT JOIN users u ON u.id = n.actor_id {$whereSql}");
$count->execute($params);
$total = (int)$count->fetchColumn();

$sql = "SELECT n.id, n.created_at, COALESCE(u.name, 'System') AS actor_name, u.email AS actor_email, ru.name AS receiver_name, ru.email AS receiver_email, 'user' AS actor_type, n.type AS action, n.message, n.image_url, n.target_id
        FROM notifications n
        LEFT JOIN users u ON u.id = n.actor_id
        LEFT JOIN users ru ON ru.id = n.user_id
        {$whereSql}
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$i = 1;
foreach ($params as $p) $stmt->bindValue($i++, $p, PDO::PARAM_STR);
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

foreach ($items as &$it) {
    $it['is_admin_notice'] = ($it['action'] ?? '') === 'admin_notice';
    $it['meta'] = [
        'target_id' => $it['target_id'] ?? null,
        'message' => $it['message'] ?? null,
        'image_url' => $it['image_url'] ?? null
    ];
}

jsonSuccess(['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit], 'Activity logs fetched');
