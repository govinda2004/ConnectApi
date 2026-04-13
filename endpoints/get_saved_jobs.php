<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/time.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$db = getDB();

$stmt = $db->prepare('SELECT j.*, sj.created_at AS saved_at FROM saved_jobs sj JOIN jobs j ON sj.job_id = j.id WHERE sj.user_id = ? ORDER BY sj.created_at DESC');
$stmt->execute([$userId]);
$jobs = $stmt->fetchAll();

foreach ($jobs as &$j) {
    $j['id'] = (int)$j['id'];
    $j['is_saved'] = true;
    $j['created_at_utc'] = toUtcIso8601($j['created_at'] ?? null);
    $j['saved_at_utc'] = toUtcIso8601($j['saved_at'] ?? null);
}

jsonSuccess($jobs, 'Saved jobs fetched');
