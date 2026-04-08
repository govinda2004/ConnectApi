<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$jobId = (int)($_POST['job_id'] ?? 0);
if ($jobId <= 0) jsonError('job_id is required');

$db = getDB();
$stmt = $db->prepare('SELECT id FROM jobs WHERE id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
if (!$stmt->fetch()) jsonError('Job not found or not yours', 403);

$db->prepare('DELETE FROM saved_jobs WHERE job_id = ?')->execute([$jobId]);
$db->prepare('DELETE FROM job_applications WHERE job_id = ?')->execute([$jobId]);
$db->prepare('DELETE FROM jobs WHERE id = ? AND user_id = ?')->execute([$jobId, $userId]);

jsonSuccess(null, 'Job deleted');
