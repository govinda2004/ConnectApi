<?php
/**
 * GET /get_job_insights?job_id=123
 * Auth: Bearer token required (must be job owner)
 * Returns: job analytics with applicant breakdown by status
 */
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

// Total applicants
$stmt = $db->prepare('SELECT COUNT(*) FROM job_applications WHERE job_id = ?');
$stmt->execute([$jobId]);
$totalApplicants = (int)$stmt->fetchColumn();

// Breakdown by status
$stmt = $db->prepare("SELECT status, COUNT(*) AS count FROM job_applications WHERE job_id = ? GROUP BY status");
$stmt->execute([$jobId]);
$statusRows = $stmt->fetchAll();
$statusBreakdown = ['pending' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0];
foreach ($statusRows as $r) {
    $statusBreakdown[$r['status']] = (int)$r['count'];
}

// Saved count
$stmt = $db->prepare('SELECT COUNT(*) FROM saved_jobs WHERE job_id = ?');
$stmt->execute([$jobId]);
$saveCount = (int)$stmt->fetchColumn();

// Applicants list with details
$stmt = $db->prepare('
    SELECT ja.id, ja.user_id, ja.full_name, ja.email, ja.phone,
           ja.cover_letter, ja.salary_expectation, ja.status, ja.resume_url,
           ja.created_at AS applied_at,
           u.name, pr.profile_image, pr.headline
    FROM job_applications ja
    JOIN users u ON ja.user_id = u.id
    LEFT JOIN profiles pr ON ja.user_id = pr.user_id
    WHERE ja.job_id = ?
    ORDER BY ja.created_at DESC
');
$stmt->execute([$jobId]);
$applicants = $stmt->fetchAll();

foreach ($applicants as &$a) {
    $a['id'] = (int)$a['id'];
    $a['user_id'] = (int)$a['user_id'];
}

// Application rate: last 7 days daily breakdown
$stmt = $db->prepare("
    SELECT DATE(created_at) AS date, COUNT(*) AS count
    FROM job_applications
    WHERE job_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$jobId]);
$dailyTrend = $stmt->fetchAll();

jsonSuccess([
    'job' => $job,
    'analytics' => [
        'total_applicants' => $totalApplicants,
        'status_breakdown' => $statusBreakdown,
        'saves' => $saveCount,
        'daily_trend' => $dailyTrend,
    ],
    'applicants' => $applicants,
], 'Job insights fetched');
