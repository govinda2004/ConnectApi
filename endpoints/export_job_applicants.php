<?php
/**
 * GET /export_job_applicants?job_id=123&format=csv
 * Auth: Bearer token required (must be job owner)
 * Returns: CSV download of job applicants
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

$userId = requireAuth();
$jobId = (int)($_GET['job_id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'csv'));

if ($jobId <= 0) jsonError('job_id is required');

$db = getDB();

// Check job ownership
$stmt = $db->prepare('SELECT id, title, company FROM jobs WHERE id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
$job = $stmt->fetch();
if (!$job) jsonError('Job not found or unauthorized', 403);

// Fetch applicants
$stmt = $db->prepare('
    SELECT ja.full_name, ja.email, ja.phone, ja.cover_letter,
           ja.salary_expectation, ja.status, ja.created_at AS applied_at,
           ja.resume_url
    FROM job_applications ja
    WHERE ja.job_id = ?
    ORDER BY ja.created_at DESC
');
$stmt->execute([$jobId]);
$applicants = $stmt->fetchAll();

if ($format === 'json') {
    jsonSuccess([
        'job' => ['id' => $jobId, 'title' => $job['title'], 'company' => $job['company']],
        'applicants' => $applicants,
        'total' => count($applicants),
    ], 'Applicants exported');
}

// Default: CSV download
$filename = 'applicants_' . preg_replace('/[^a-zA-Z0-9]/', '_', $job['title']) . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM for Excel UTF-8
fwrite($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, ['Full Name', 'Email', 'Phone', 'Salary Expectation', 'Status', 'Applied At', 'Cover Letter', 'Resume URL']);

foreach ($applicants as $a) {
    fputcsv($output, [
        $a['full_name'] ?? '',
        $a['email'] ?? '',
        $a['phone'] ?? '',
        $a['salary_expectation'] ?? '',
        $a['status'] ?? 'pending',
        $a['applied_at'] ?? '',
        $a['cover_letter'] ?? '',
        $a['resume_url'] ?? '',
    ]);
}

fclose($output);
exit;
