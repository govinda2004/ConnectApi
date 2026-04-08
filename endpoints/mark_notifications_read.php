<?php
/**
 * POST /mark_notifications_read
 * Auth: Bearer token required
 * Body: notification_id (optional - if empty, marks all as read)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$notifId = (int)($_POST['notification_id'] ?? 0);

$db = getDB();

if ($notifId > 0) {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$notifId, $userId]);
    $count = 1;
} else {
    $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    $count = $stmt->rowCount();
}

jsonSuccess(['marked_count' => $count], "$count notifications marked as read");
