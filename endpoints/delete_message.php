<?php
/**
 * POST /delete_message
 * Auth: Bearer token required
 * Body: message_id
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$messageId = (int)($_POST['message_id'] ?? 0);
if ($messageId <= 0) jsonError('message_id is required');

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM messages WHERE id = ? AND sender_id = ?');
$stmt->execute([$messageId, $userId]);
if (!$stmt->fetch()) jsonError('Message not found or not yours', 403);

$db->prepare('UPDATE messages SET is_deleted = 1 WHERE id = ?')->execute([$messageId]);

jsonSuccess(null, 'Message deleted');
