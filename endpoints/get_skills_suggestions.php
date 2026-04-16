<?php
/**
 * GET /get_skills_suggestions?q=rea&limit=12
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
ensureSkillsMasterTable($db);

$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$limit = (int)($_GET['limit'] ?? 12);
if ($limit <= 0) $limit = 12;
if ($limit > 30) $limit = 30;

$where = ['is_active = 1'];
$params = [];

if ($q !== '') {
    $where[] = 'name LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
}

$sql = 'SELECT id, name, category FROM skills_master WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC LIMIT ' . $limit;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

$items = array_map(static function ($r) {
    return [
        'id' => (int)($r['id'] ?? 0),
        'name' => (string)($r['name'] ?? ''),
        'category' => $r['category'] ?? null,
    ];
}, $rows);

jsonSuccess(['items' => $items], 'Skills suggestions fetched');
