<?php
/**
 * Helper to create notifications
 */

require_once __DIR__ . '/fcm.php';

function createNotification($db, $userId, $type, $actorId, $targetId, $message, $sendPush = true) {
    if ($userId == $actorId) return; // don't notify yourself
    $stmt = $db->prepare('INSERT INTO notifications (user_id, type, actor_id, target_id, message) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $actorId, $targetId, $message]);

    if (!$sendPush) return;

    // Best-effort push notification for all in-app actions.
    $stmt = $db->prepare('SELECT device_token FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $deviceToken = trim((string)$stmt->fetchColumn());
    if ($deviceToken === '') return;

    $stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$actorId]);
    $actorName = trim((string)$stmt->fetchColumn());
    $title = $actorName !== '' ? $actorName : 'ConnectIn';

    sendFcmToDeviceToken(
        $deviceToken,
        $title,
        $message,
        [
            'type' => (string)$type,
            'actor_id' => (string)$actorId,
            'target_id' => $targetId !== null ? (string)$targetId : '',
        ]
    );
}
