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

if ($jobId <= 0 || empty($fullName) || empty($email)) jsonError('job_id, full_name, email are required');

$db = getDB();

// Check job exists
$stmt = $db->prepare('SELECT id FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
if (!$stmt->fetch()) jsonError('Job not found', 404);

// Check not already applied
$stmt = $db->prepare('SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?');
$stmt->execute([$jobId, $userId]);
if ($stmt->fetch()) jsonError('Already applied');

$stmt = $db->prepare('INSERT INTO job_applications (job_id, user_id, full_name, email, phone, cover_letter, salary_expectation) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$jobId, $userId, $fullName, $email, $phone, $coverLetter, $salaryExp]);

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
