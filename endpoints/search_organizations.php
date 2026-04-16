<?php
/**
 * GET /search_organizations?q=abc&limit=8
 * Auth required.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

requireAuth();
$db = getDB();
ensureAccountTypeColumn($db);

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 8);
if ($limit <= 0) $limit = 8;
if ($limit > 20) $limit = 20;

if ($q === '' || mb_strlen($q) < 2) {
    jsonSuccess(['organizations' => []], 'Organizations fetched');
}

$like = '%' . $q . '%';
$stmt = $db->prepare(
    'SELECT u.id AS user_id, u.name, p.profile_image, p.headline
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE u.account_type = "organization" AND u.name LIKE ?
     ORDER BY u.name ASC
     LIMIT ' . $limit
);
$stmt->execute([$like]);
$rows = $stmt->fetchAll() ?: [];

$orgs = array_map(static function ($r) {
    return [
        'user_id' => (int)($r['user_id'] ?? 0),
        'name' => (string)($r['name'] ?? ''),
        'profile_image' => (string)($r['profile_image'] ?? ''),
        'headline' => (string)($r['headline'] ?? ''),
    ];
}, $rows);

jsonSuccess(['organizations' => $orgs], 'Organizations fetched');
