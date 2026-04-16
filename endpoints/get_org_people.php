<?php
/**
 * GET /get_org_people?org_user_id=123
 * Auth required.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

requireAuth();
$orgUserId = (int)($_GET['org_user_id'] ?? 0);
if ($orgUserId <= 0) jsonError('org_user_id is required');

$db = getDB();
ensureWorkExperienceOrgUserColumn($db);

$stmt = $db->prepare(
    'SELECT u.id AS user_id, u.name, p.profile_image, p.headline,
            we.position, we.org_name, we.end_date
     FROM work_experience we
     JOIN users u ON u.id = we.user_id
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE we.org_user_id = ?
       AND (we.end_date IS NULL OR TRIM(we.end_date) = "")
     ORDER BY we.id DESC'
);
$stmt->execute([$orgUserId]);
$rows = $stmt->fetchAll() ?: [];

$people = array_map(static function ($r) {
    return [
        'user_id' => (int)($r['user_id'] ?? 0),
        'name' => (string)($r['name'] ?? ''),
        'profile_image' => (string)($r['profile_image'] ?? ''),
        'headline' => (string)($r['headline'] ?? ''),
        'position' => (string)($r['position'] ?? ''),
        'org_name' => (string)($r['org_name'] ?? ''),
    ];
}, $rows);

jsonSuccess(['people' => $people], 'Organization people fetched');
