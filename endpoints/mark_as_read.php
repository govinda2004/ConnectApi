<?php
/**
 * POST /mark_as_read
 * Auth: Bearer token required
 * Body: sender_id (mark all messages from this sender as read)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$senderId = (int)($_POST['sender_id'] ?? 0);
if ($senderId <= 0) jsonError('sender_id is required');

$db = getDB();
$stmt = $db->prepare('UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL');
$stmt->execute([$senderId, $userId]);
$count = $stmt->rowCount();

jsonSuccess(['marked_count' => $count], "$count messages marked as read");
