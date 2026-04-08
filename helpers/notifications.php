<?php
/**
 * Helper to create notifications
 */

function createNotification($db, $userId, $type, $actorId, $targetId, $message) {
    if ($userId == $actorId) return; // don't notify yourself
    $stmt = $db->prepare('INSERT INTO notifications (user_id, type, actor_id, target_id, message) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $actorId, $targetId, $message]);
}
