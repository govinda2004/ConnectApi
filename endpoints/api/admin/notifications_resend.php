<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../helpers/fcm.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$admin = adminRequireAuth();
$db = getDB();
ensureFcmTokenColumn($db);
ensureNotificationsImageColumn($db);
ensureNotificationsBroadcastBatchColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid notification id');

$stmt = $db->prepare("
    SELECT n.id, n.user_id, n.type, n.message, n.image_url, n.broadcast_batch_id, u.name, u.email,
           COALESCE(NULLIF(u.fcm_token, ''), NULLIF(u.device_token, ''), '') AS push_token
    FROM notifications n
    INNER JOIN users u ON u.id = n.user_id
    WHERE n.id = ? AND n.type = 'admin_notice'
    LIMIT 1
");
$stmt->execute([$id]);
$n = $stmt->fetch();
if (!$n) jsonError('Admin notification not found', 404);

$message = trim((string)$n['message']);
$imageUrl = trim((string)($n['image_url'] ?? ''));
$batchId = trim((string)($n['broadcast_batch_id'] ?? ''));

if ($message === '') jsonError('Notification message is empty');

$targets = [];
if ($batchId !== '') {
    $t = $db->prepare("
        SELECT n.user_id, u.name, u.email,
               COALESCE(NULLIF(u.fcm_token, ''), NULLIF(u.device_token, ''), '') AS push_token
        FROM notifications n
        INNER JOIN users u ON u.id = n.user_id
        WHERE n.broadcast_batch_id = ? AND n.type = 'admin_notice'
    ");
    $t->execute([$batchId]);
    $targets = $t->fetchAll();
} else {
    $targets = [[
        'user_id' => $n['user_id'],
        'name' => $n['name'],
        'email' => $n['email'],
        'push_token' => $n['push_token'],
    ]];
}

$newBatchId = count($targets) > 1 ? ('batch_' . time() . '_' . bin2hex(random_bytes(4))) : null;
$ins = $db->prepare("
    INSERT INTO notifications (user_id, type, actor_id, target_id, message, image_url, broadcast_batch_id, is_read, created_at)
    VALUES (?, 'admin_notice', ?, NULL, ?, ?, ?, 0, NOW())
");

$pushSent = 0;
$pushFailed = 0;
$pushSkipped = 0;
$failures = [];
foreach ($targets as $t) {
    $uid = (int)$t['user_id'];
    $pushToken = trim((string)($t['push_token'] ?? ''));
    $ins->execute([$uid, (int)$admin['id'], $message, ($imageUrl !== '' ? $imageUrl : null), $newBatchId]);

    if ($pushToken === '') {
        $pushSkipped++;
        $failures[] = ['user_id' => $uid, 'reason' => 'missing_token'];
        continue;
    }
    $meta = null;
    $ok = sendFcmToDeviceToken(
        $pushToken,
        'Admin Notice',
        $message,
        [
            'type' => 'admin_notice',
            'notification_type' => 'admin_notice',
            'sender_id' => (string)$admin['id'],
            'sender_name' => (string)($admin['name'] ?? 'Super Admin'),
            'receiver_id' => (string)$uid,
            'receiver_name' => (string)($t['name'] ?? ''),
            'mode' => 'resend',
            'image_url' => (string)$imageUrl,
        ],
        $meta,
        $imageUrl !== '' ? $imageUrl : null
    );
    if ($ok) $pushSent++;
    else {
        $pushFailed++;
        $failures[] = [
            'user_id' => $uid,
            'reason' => (string)($meta['reason'] ?? 'push_failed'),
            'http_status' => $meta['http_status'] ?? null,
        ];
    }
}

$msg = ($pushFailed > 0 || $pushSkipped > 0)
    ? "Notification resent in DB. push success {$pushSent}, failed {$pushFailed}, skipped {$pushSkipped}"
    : 'Notification resent successfully';
jsonSuccess([
    'resent' => true,
    'broadcast_batch_id' => $newBatchId,
    'push_sent' => $pushSent,
    'push_failed' => $pushFailed,
    'push_skipped' => $pushSkipped,
    'failures' => array_slice($failures, 0, 10),
], $msg);
