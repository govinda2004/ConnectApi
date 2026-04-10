<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$jobId = (int)($data['job_id'] ?? 0);
$fullName = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$coverLetter = trim($data['cover_letter'] ?? '');
$salaryExp = trim($data['salary_expectation'] ?? '');
$resumeUrl = null;

if ($jobId <= 0 || empty($fullName) || empty($email)) jsonError('job_id, full_name, email are required');

if (!isset($_FILES['resume']) || (int)($_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonError('Resume PDF is required');
}

$resumeFile = $_FILES['resume'];
$resumeExt = strtolower(pathinfo((string)$resumeFile['name'], PATHINFO_EXTENSION));
if ($resumeExt !== 'pdf') {
    jsonError('Only PDF resume is allowed');
}

$resumeSize = (int)($resumeFile['size'] ?? 0);
if ($resumeSize <= 0 || $resumeSize > (5 * 1024 * 1024)) {
    jsonError('Resume size must be under 5MB');
}

$resumeDestDir = __DIR__ . '/../uploads/resumes/';
if (!is_dir($resumeDestDir)) mkdir($resumeDestDir, 0777, true);

$resumeFileName = 'resume_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
if (!move_uploaded_file((string)$resumeFile['tmp_name'], $resumeDestDir . $resumeFileName)) {
    jsonError('Failed to upload resume', 500);
}

$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || strtolower((string)$forwardedProto) === 'https';
$scheme = $isHttps ? 'https' : 'http';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
}
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $scriptDir;
$resumeUrl = $baseUrl . '/uploads/resumes/' . $resumeFileName;

$db = getDB();

// Check job exists
$stmt = $db->prepare('SELECT id FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
if (!$stmt->fetch()) jsonError('Job not found', 404);

// Check not already applied
$stmt = $db->prepare('SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
if ($stmt->fetch()) jsonError('Already applied');

$stmt = $db->prepare('INSERT INTO job_applications (job_id, user_id, full_name, email, phone, cover_letter, resume_url, salary_expectation) VALUES (?,?,?,?,?,?,?,?)');
$stmt->execute([$jobId, $userId, $fullName, $email, $phone, $coverLetter, $resumeUrl, $salaryExp]);

// Notify job poster
require_once __DIR__ . '/../helpers/notifications.php';
$stmt = $db->prepare('SELECT user_id, title FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();
$stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$applicantName = $stmt->fetchColumn();
createNotification($db, (int)$job['user_id'], 'job_application', $userId, $jobId, "$applicantName applied for {$job['title']}");

jsonSuccess(null, 'Application submitted successfully');
