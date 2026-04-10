<?php
/**
 * POST /unblock_user
 * Auth: Bearer token required
 * Body: user_id (the user to unblock)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$blockedId = (int)($_POST['user_id'] ?? 0);

if ($blockedId <= 0) jsonError('user_id is required');

$db = getDB();

$stmt = $db->prepare('DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?');
$stmt->execute([$userId, $blockedId]);

jsonSuccess(['unblocked_user_id' => $blockedId], 'User unblocked successfully');
