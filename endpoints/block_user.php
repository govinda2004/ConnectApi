<?php
/**
 * POST /block_user
 * Auth: Bearer token required
 * Body: user_id (the user to block)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$blockedId = (int)($_POST['user_id'] ?? 0);

if ($blockedId <= 0) jsonError('user_id is required');
if ($blockedId === $userId) jsonError('You cannot block yourself');

$db = getDB();

// Ensure table exists
try { $db->exec("CREATE TABLE IF NOT EXISTS blocks (id INT AUTO_INCREMENT PRIMARY KEY, blocker_id INT NOT NULL, blocked_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_block (blocker_id, blocked_id), INDEX idx_blocker (blocker_id), INDEX idx_blocked (blocked_id))"); } catch (PDOException $e) {}

// Verify target user exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$blockedId]);
if (!$stmt->fetch()) jsonError('User not found', 404);

// Insert (ignore if already blocked)
$stmt = $db->prepare('INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)');
$stmt->execute([$userId, $blockedId]);

jsonSuccess(['blocked_user_id' => $blockedId], 'User blocked successfully');
