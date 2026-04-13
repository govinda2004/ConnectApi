<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/time.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) jsonError('job_id is required');

$db = getDB();
$stmt = $db->prepare('SELECT j.*, u.name AS posted_by, pr.profile_image AS poster_image
    FROM jobs j JOIN users u ON j.user_id = u.id LEFT JOIN profiles pr ON j.user_id = pr.user_id
    WHERE j.id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();
if (!$job) jsonError('Job not found', 404);

$job['id'] = (int)$job['id'];
$job['is_mine'] = ((int)$job['user_id'] === $userId);
$job['created_at_utc'] = toUtcIso8601($job['created_at'] ?? null);

// Check if user already applied
$stmt = $db->prepare('SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
$job['has_applied'] = (bool)$stmt->fetch();

// Check if saved
$stmt = $db->prepare('SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
$job['is_saved'] = (bool)$stmt->fetch();

// Application count
$stmt = $db->prepare('SELECT COUNT(*) FROM job_applications WHERE job_id = ?');
$stmt->execute([$jobId]);
$job['applications_count'] = (int)$stmt->fetchColumn();

jsonSuccess($job, 'Job detail fetched');
