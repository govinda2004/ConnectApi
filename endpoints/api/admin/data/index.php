<?php

require_once __DIR__ . '/_data_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();

$resource = (string)($_GET['resource'] ?? '');
$cfg = adminDataResourceConfig($resource);
if (!$cfg) jsonError('Unsupported resource', 404);
adminDataEnsureTable($db, $cfg);

$q = trim((string)($_GET['q'] ?? ''));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

$sql = "SELECT * FROM {$cfg['table']}";
$params = [];
if ($q !== '') {
    $sql .= " WHERE CAST(CONCAT_WS(' ', ";
    $cols = $db->query("SHOW COLUMNS FROM {$cfg['table']}")->fetchAll();
    $names = [];
    foreach ($cols as $c) $names[] = $c['Field'];
    $sql .= implode(', ', $names) . ") AS CHAR) LIKE ?";
    $params[] = "%{$q}%";
}
$sql .= " ORDER BY {$cfg['id']} DESC LIMIT ?";
$stmt = $db->prepare($sql);
$i = 1;
foreach ($params as $p) $stmt->bindValue($i++, $p, PDO::PARAM_STR);
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

if ($resource === 'admins') {
    foreach ($items as &$it) {
        $it['permissions'] = normalizePermissions($it['permissions'] ?? null);
    }
}
if ($resource === 'users') {
    foreach ($items as &$it) {
        unset($it['password'], $it['firebase_uid'], $it['device_token'], $it['fcm_token']);
    }
}

jsonSuccess(['items' => $items], 'Data fetched');
