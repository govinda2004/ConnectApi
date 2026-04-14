<?php

require_once __DIR__ . '/_users_common.php';

adminRequireAuth();
$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
$applicationId = (int)($_GET['application_id'] ?? 0);
if ($userId <= 0 || $applicationId <= 0) jsonError('Invalid id');

$base = $db->prepare('SELECT * FROM job_applications WHERE id = ? AND user_id = ? LIMIT 1');
$base->execute([$applicationId, $userId]);
$row = $base->fetch();
if (!$row) jsonError('Applied job not found', 404);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonSuccess($row, 'Applied job fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $allowed = ['full_name', 'email', 'phone', 'cover_letter', 'resume_url', 'salary_expectation', 'status'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $input)) continue;
        $sets[] = "{$k} = ?";
        $vals[] = is_string($input[$k]) ? trim($input[$k]) : $input[$k];
    }
    if (empty($sets)) jsonError('No valid fields to update');
    $vals[] = $applicationId;
    $vals[] = $userId;
    $sql = 'UPDATE job_applications SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
    jsonSuccess(['id' => $applicationId], 'Applied job updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare('DELETE FROM job_applications WHERE id = ? AND user_id = ?');
    $stmt->execute([$applicationId, $userId]);
    if ($stmt->rowCount() === 0) jsonError('Applied job not found', 404);
    jsonSuccess(['id' => $applicationId], 'Applied job deleted');
}

jsonError('Method not allowed', 405);
