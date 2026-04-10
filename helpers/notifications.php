<?php
/**
 * Helper to create notifications
 */

require_once __DIR__ . '/fcm.php';
require_once __DIR__ . '/migrations.php';

function createNotification($db, $userId, $type, $actorId, $targetId, $message, $sendPush = true) {
    if ($userId == $actorId) return; // don't notify yourself
    ensureFcmTokenColumn($db);
    $stmt = $db->prepare('INSERT INTO notifications (user_id, type, actor_id, target_id, message) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $actorId, $targetId, $message]);

    if (!$sendPush) return;

    // Best-effort push notification for all in-app actions.
    $stmt = $db->prepare('SELECT COALESCE(NULLIF(fcm_token, ""), NULLIF(device_token, ""), "") AS push_token FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $fcmToken = trim((string)$stmt->fetchColumn());
    if ($fcmToken === '') return;

    $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$actorId]);
    $actorName = trim((string)$stmt->fetchColumn());
    $title = $actorName !== '' ? $actorName : 'ConnectIn';

    sendFcmToDeviceToken(
        $fcmToken,
        $title,
        $message,
        [
            'type' => (string)$type,
            'notification_type' => (string)$type,
            'actor_id' => (string)$actorId,
            'actor_name' => (string)$actorName,
            'target_id' => $targetId !== null ? (string)$targetId : '',
        ]
    );
}
