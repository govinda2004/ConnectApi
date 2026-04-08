<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$title = trim($data['title'] ?? '');
$company = trim($data['company'] ?? '');
$location = trim($data['location'] ?? '');
$jobType = trim($data['job_type'] ?? 'Full-time');
$salaryMin = (int)($data['salary_min'] ?? 0);
$salaryMax = (int)($data['salary_max'] ?? 0);
$description = trim($data['description'] ?? '');
$skills = trim($data['skills'] ?? '');
$isRemote = ($data['is_remote'] ?? false) ? 1 : 0;

if (empty($title) || empty($company)) jsonError('title and company are required');

$db = getDB();
$stmt = $db->prepare('INSERT INTO jobs (user_id, title, company, location, job_type, salary_min, salary_max, description, skills, is_remote) VALUES (?,?,?,?,?,?,?,?,?,?)');
$stmt->execute([$userId, $title, $company, $location, $jobType, $salaryMin ?: null, $salaryMax ?: null, $description, $skills, $isRemote]);
$jobId = (int)$db->lastInsertId();

$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
jsonSuccess($stmt->fetch(), 'Job posted successfully');
