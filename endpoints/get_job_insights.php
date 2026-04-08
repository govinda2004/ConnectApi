<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) jsonError('job_id is required');

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
$job = $stmt->fetch();
if (!$job) jsonError('Job not found or not yours', 403);

// Stats
$stmt = $db->prepare('SELECT COUNT(*) FROM job_applications WHERE job_id = ?');
$stmt->execute([$jobId]);
$appCount = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM saved_jobs WHERE job_id = ?');
$stmt->execute([$jobId]);
$saveCount = (int)$stmt->fetchColumn();

// Applicants list (used for analytics + export)
$stmt = $db->prepare('SELECT ja.*, u.name, pr.profile_image, pr.headline
    FROM job_applications ja JOIN users u ON ja.user_id = u.id
    LEFT JOIN profiles pr ON ja.user_id = pr.user_id
    WHERE ja.job_id = ? ORDER BY ja.created_at DESC');
$stmt->execute([$jobId]);
$applicants = $stmt->fetchAll();

jsonSuccess([
    'job' => $job,
    'applications' => $appCount,
    'saves' => $saveCount,
    'applicants' => $applicants,
], 'Insights fetched');
