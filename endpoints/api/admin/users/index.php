<?php

require_once __DIR__ . '/_users_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureUsersIsActiveColumn($db);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($status === 'active') $where[] = 'is_active = 1';
if ($status === 'inactive') $where[] = 'is_active = 0';

$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$countStmt = $db->prepare("SELECT COUNT(*) FROM users {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT id, name, email, created_at, is_active FROM users {$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$idx = 1;
foreach ($params as $p) {
    $stmt->bindValue($idx++, $p, PDO::PARAM_STR);
}
$stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$rows = array_map('normalizeUserStatus', $rows);
jsonSuccess([
    'items' => $rows,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
], 'Admin users fetched');
