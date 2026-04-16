<?php
/**
 * GET /get_institutions_suggestions?q=abc&type=university&limit=10
 * Auth required.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

requireAuth();
$db = getDB();
ensureInstitutionsMasterTable($db);

$q = trim((string)($_GET['q'] ?? ''));
$type = strtolower(trim((string)($_GET['type'] ?? '')));
$limit = (int)($_GET['limit'] ?? 10);
if ($limit <= 0) $limit = 10;
if ($limit > 30) $limit = 30;

$where = ['is_active = 1'];
$params = [];

if ($q !== '') {
    $where[] = 'name LIKE ?';
    $params[] = '%' . $q . '%';
}

$allowedTypes = ['university', 'college', 'institute', 'school', 'other'];
if ($type !== '' && in_array($type, $allowedTypes, true)) {
    $where[] = 'type = ?';
    $params[] = $type;
}

$sql = 'SELECT id, name, type FROM institutions_master WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC LIMIT ' . $limit;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$items = array_map(static function ($r) {
    return [
        'id' => (int)($r['id'] ?? 0),
        'name' => (string)($r['name'] ?? ''),
        'type' => (string)($r['type'] ?? 'other'),
    ];
}, $rows);

jsonSuccess(['items' => $items], 'Institution suggestions fetched');
