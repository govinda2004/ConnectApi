<?php
/**
 * GET /get_blocked_users?page=1&limit=20
 * Auth: Bearer token required
 * Returns: paginated list of blocked users
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$db = getDB();

$stmt = $db->prepare('SELECT COUNT(*) FROM blocks WHERE blocker_id = ?');
$stmt->execute([$userId]);
$total = (int)$stmt->fetchColumn();

$stmt = $db->prepare('
    SELECT b.blocked_id AS user_id, b.created_at AS blocked_at,
           u.name, u.email, pr.profile_image, pr.headline
    FROM blocks b
    JOIN users u ON b.blocked_id = u.id
    LEFT JOIN profiles pr ON b.blocked_id = pr.user_id
    WHERE b.blocker_id = ?
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

foreach ($users as &$u) {
    $u['user_id'] = (int)$u['user_id'];
}

jsonSuccess([
    'blocked_users' => $users,
    'total' => $total,
    'page' => $page,
    'has_more' => ($offset + $limit) < $total,
], 'Blocked users fetched');
