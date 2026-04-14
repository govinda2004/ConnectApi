<?php

require_once __DIR__ . '/_users_common.php';

adminRequireAuth();
$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
$jobId = (int)($_GET['job_id'] ?? 0);
if ($userId <= 0 || $jobId <= 0) jsonError('Invalid id');

$base = $db->prepare('SELECT * FROM jobs WHERE id = ? AND user_id = ? LIMIT 1');
$base->execute([$jobId, $userId]);
$row = $base->fetch();
if (!$row) jsonError('Created job not found', 404);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonSuccess($row, 'Created job fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $allowed = ['title', 'company', 'location', 'job_type', 'salary_min', 'salary_max', 'description', 'skills', 'is_remote', 'is_easy_apply'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $input)) continue;
        $sets[] = "{$k} = ?";
        $v = $input[$k];
        if (is_string($v)) $v = trim($v);
        $vals[] = $v;
    }
    if (empty($sets)) jsonError('No valid fields to update');
    $vals[] = $jobId;
    $vals[] = $userId;
    $sql = 'UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
    jsonSuccess(['id' => $jobId], 'Created job updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // cleanup child data first
    try {
        $delApp = $db->prepare('DELETE FROM job_applications WHERE job_id = ?');
        $delApp->execute([$jobId]);
    } catch (Throwable $e) {
        // ignore
    }
    $stmt = $db->prepare('DELETE FROM jobs WHERE id = ? AND user_id = ?');
    $stmt->execute([$jobId, $userId]);
    if ($stmt->rowCount() === 0) jsonError('Created job not found', 404);
    jsonSuccess(['id' => $jobId], 'Created job deleted');
}

jsonError('Method not allowed', 405);
