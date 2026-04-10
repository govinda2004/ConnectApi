<?php
/**
 * POST /report_user
 * Auth: Bearer token required
 * Body: user_id, reason, description (optional)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$reportedId = (int)($_POST['user_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($reportedId <= 0) jsonError('user_id is required');
if ($reportedId === $userId) jsonError('You cannot report yourself');
if (empty($reason)) jsonError('reason is required');

$db = getDB();

// Ensure table exists
try { $db->exec("CREATE TABLE IF NOT EXISTS reports (id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT NOT NULL, reported_id INT NOT NULL, reason VARCHAR(255) NOT NULL, description TEXT NULL, status ENUM('pending','reviewed','resolved') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_reported (reported_id), INDEX idx_status (status))"); } catch (PDOException $e) {}

// Verify target user exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$reportedId]);
if (!$stmt->fetch()) jsonError('User not found', 404);

$stmt = $db->prepare('INSERT INTO reports (reporter_id, reported_id, reason, description) VALUES (?, ?, ?, ?)');
$stmt->execute([$userId, $reportedId, $reason, $description !== '' ? $description : null]);

jsonSuccess(['report_id' => (int)$db->lastInsertId()], 'Report submitted successfully');
