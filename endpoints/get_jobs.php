<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$remote = (int)($_GET['remote'] ?? -1);

$db = getDB();

$where = '1=1';
$params = [];

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

$total = (int)$db->prepare("SELECT COUNT(*) FROM jobs j WHERE $where")->execute($params) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
// Simpler count
$countStmt = $db->prepare("SELECT COUNT(*) FROM jobs j WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT j.*, u.name AS posted_by,
    (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) AS applications_count,
    (SELECT COUNT(*) FROM saved_jobs WHERE job_id = j.id AND user_id = ?) AS is_saved
    FROM jobs j JOIN users u ON j.user_id = u.id
    WHERE $where ORDER BY j.created_at DESC LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
$stmt->execute(array_merge([$userId], $params, [$limit, $offset]));
$jobs = $stmt->fetchAll();

foreach ($jobs as &$job) {
    $job['id'] = (int)$job['id'];
    $job['user_id'] = (int)$job['user_id'];
    $job['is_saved'] = (bool)$job['is_saved'];
    $job['is_mine'] = ($job['user_id'] === $userId);
    $job['applications_count'] = (int)$job['applications_count'];
}

jsonSuccess(['jobs' => $jobs, 'total' => $total, 'page' => $page, 'has_more' => ($offset + $limit) < $total], 'Jobs fetched');
