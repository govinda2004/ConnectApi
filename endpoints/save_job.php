<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$jobId = (int)($_POST['job_id'] ?? 0);
if ($jobId <= 0) jsonError('job_id is required');

$db = getDB();

$stmt = $db->prepare('SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?');
$stmt->execute([$userId, $jobId]);
if ($stmt->fetch()) {
    $db->prepare('DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?')->execute([$userId, $jobId]);
    jsonSuccess(['saved' => false], 'Job unsaved');
} else {
    $db->prepare('INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)')->execute([$userId, $jobId]);
    jsonSuccess(['saved' => true], 'Job saved');
}
