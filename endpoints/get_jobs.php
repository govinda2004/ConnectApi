<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/time.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$remote = (int)($_GET['remote'] ?? -1);
$mine = (int)($_GET['mine'] ?? -1); // -1: default behavior, 0: all, 1: only my jobs

$db = getDB();

// Determine account type for backward-compatible filtering behavior.
$accountType = '';
try {
    $typeStmt = $db->prepare("SELECT account_type FROM users WHERE id = ? LIMIT 1");
    $typeStmt->execute([$userId]);
    $accountType = strtolower(trim((string)$typeStmt->fetchColumn()));
} catch (Throwable $e) {
    $accountType = '';
}

$where = '1=1';
$params = [];

if ($mine === 1) {
    $where .= ' AND j.user_id = ?';
    $params[] = $userId;
} elseif ($mine !== 0 && $accountType === 'organization') {
    // Backward compatibility: old clients for organization accounts still see only own jobs.
    $where .= ' AND j.user_id = ?';
    $params[] = $userId;
}

if (!empty($search)) {
    $where .= ' AND (j.title LIKE ? OR j.company LIKE ? OR j.skills LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if (!empty($type)) {
    $where .= ' AND j.job_type = ?';
    $params[] = $type;
}
if ($remote >= 0) {
    $where .= ' AND j.is_remote = ?';
    $params[] = $remote;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM jobs j WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT j.*, u.name AS posted_by,
    (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) AS applications_count,
    (SELECT COUNT(*) FROM saved_jobs WHERE job_id = j.id AND user_id = ?) AS is_saved
    FROM jobs j JOIN users u ON j.user_id = u.id
    WHERE $where ORDER BY j.created_at DESC LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$i = 2;
foreach ($params as $p) {
    $stmt->bindValue($i++, $p);
}
$stmt->bindValue($i++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($i++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

foreach ($jobs as &$job) {
    $job['id'] = (int)$job['id'];
    $job['user_id'] = (int)$job['user_id'];
    $job['is_saved'] = (bool)$job['is_saved'];
    $job['is_mine'] = ($job['user_id'] === $userId);
    $job['applications_count'] = (int)$job['applications_count'];
    $job['created_at_utc'] = toUtcIso8601($job['created_at'] ?? null);
}

jsonSuccess(['jobs' => $jobs, 'total' => $total, 'page' => $page, 'has_more' => ($offset + $limit) < $total], 'Jobs fetched');
