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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid notification id');

$stmt = $db->prepare("
    SELECT n.id, n.user_id, n.type, n.message, n.image_url, u.name, u.email,
           COALESCE(NULLIF(u.fcm_token, ''), NULLIF(u.device_token, ''), '') AS push_token
    FROM notifications n
    INNER JOIN users u ON u.id = n.user_id
    WHERE n.id = ? AND n.type = 'admin_notice'
    LIMIT 1
");
$stmt->execute([$id]);
$n = $stmt->fetch();
if (!$n) jsonError('Admin notification not found', 404);

$uid = (int)$n['user_id'];
$message = trim((string)$n['message']);
$imageUrl = trim((string)($n['image_url'] ?? ''));
$pushToken = trim((string)($n['push_token'] ?? ''));

if ($message === '') jsonError('Notification message is empty');

$ins = $db->prepare("
    INSERT INTO notifications (user_id, type, actor_id, target_id, message, image_url, is_read, created_at)
    VALUES (?, 'admin_notice', ?, NULL, ?, ?, 0, NOW())
");
$ins->execute([$uid, (int)$admin['id'], $message, ($imageUrl !== '' ? $imageUrl : null)]);

if ($pushToken === '') {
    jsonSuccess(['resent' => false, 'reason' => 'missing_token'], 'Notification cloned in logs, but push skipped (missing token)');
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
        'receiver_name' => (string)($n['name'] ?? ''),
        'mode' => 'resend',
        'image_url' => (string)$imageUrl,
    ],
    $meta,
    $imageUrl !== '' ? $imageUrl : null
);

if (!$ok) {
    jsonError('Notification resent in DB but push failed: ' . (string)($meta['reason'] ?? 'push_failed'), 422, [
        'reason' => (string)($meta['reason'] ?? 'push_failed'),
        'http_status' => $meta['http_status'] ?? null,
        'response_excerpt' => $meta['response_excerpt'] ?? null,
    ]);
}

jsonSuccess(['resent' => true], 'Notification resent successfully');
