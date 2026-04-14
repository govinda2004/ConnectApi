<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../helpers/fcm.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$admin = adminRequireAuth();
$db = getDB();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$mode = trim((string)($input['mode'] ?? 'single')); // single | all
$userId = (int)($input['user_id'] ?? 0);
$title = trim((string)($input['title'] ?? 'Admin Notice'));
$message = trim((string)($input['message'] ?? ''));

if ($message === '') jsonError('message is required');

$targetUsers = [];
if ($mode === 'all') {
    $stmt = $db->prepare('
        SELECT id, name, email, COALESCE(NULLIF(fcm_token, ""), NULLIF(device_token, ""), "") AS push_token
        FROM users
        WHERE id <> ?
    ');
    $stmt->execute([(int)$admin['id']]);
    $targetUsers = $stmt->fetchAll();
} else {
    if ($userId <= 0) jsonError('user_id is required for single mode');
    $stmt = $db->prepare('
        SELECT id, name, email, COALESCE(NULLIF(fcm_token, ""), NULLIF(device_token, ""), "") AS push_token
        FROM users
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) jsonError('Target user not found', 404);
    $targetUsers = [$user];
}

if (empty($targetUsers)) {
    jsonSuccess(['sent_count' => 0], 'No target users found');
}

ensureFcmTokenColumn($db);

$ins = $db->prepare('
    INSERT INTO notifications (user_id, type, actor_id, target_id, message, is_read, created_at)
    VALUES (?, ?, ?, NULL, ?, 0, NOW())
');

$sent = 0;
$pushSent = 0;
$pushFailed = 0;
$pushSkipped = 0;
$failures = [];

foreach ($targetUsers as $tu) {
    $uid = (int)$tu['id'];
    $pushToken = trim((string)($tu['push_token'] ?? ''));
    $receiverName = trim((string)($tu['name'] ?? ''));

    $ins->execute([$uid, $title, (int)$admin['id'], $message]);
    $sent++;

    if ($pushToken === '') {
        $pushSkipped++;
        $failures[] = [
            'user_id' => $uid,
            'email' => (string)($tu['email'] ?? ''),
            'reason' => 'missing_token',
        ];
        continue;
    }

    $meta = null;
    $ok = sendFcmToDeviceToken(
        $pushToken,
        $title !== '' ? $title : 'Admin Notice',
        $message,
        [
            'type' => 'admin_notice',
            'notification_type' => 'admin_notice',
            'sender_id' => (string)$admin['id'],
            'sender_name' => (string)($admin['name'] ?? 'Super Admin'),
            'receiver_id' => (string)$uid,
            'receiver_name' => (string)$receiverName,
            'mode' => (string)$mode,
        ],
        $meta
    );
    if ($ok) {
        $pushSent++;
    } else {
        $pushFailed++;
        $failures[] = [
            'user_id' => $uid,
            'email' => (string)($tu['email'] ?? ''),
            'reason' => (string)($meta['reason'] ?? 'push_failed'),
            'http_status' => $meta['http_status'] ?? null,
            'response_excerpt' => $meta['response_excerpt'] ?? null,
        ];
    }
}

if ($mode === 'single' && $pushSent === 0) {
    $reason = $failures[0]['reason'] ?? 'push_failed';
    jsonError(
        'Notification saved in DB but push failed: ' . $reason,
        422,
        [
            'sent_count' => $sent,
            'push_sent' => $pushSent,
            'push_failed' => $pushFailed,
            'push_skipped' => $pushSkipped,
            'failures' => $failures,
        ]
    );
}

jsonSuccess([
    'sent_count' => $sent,
    'mode' => $mode,
    'push_sent' => $pushSent,
    'push_failed' => $pushFailed,
    'push_skipped' => $pushSkipped,
    'failures' => array_slice($failures, 0, 10),
], $pushFailed > 0 || $pushSkipped > 0
    ? "Notification sent to {$sent} users, push success {$pushSent}, failed {$pushFailed}, skipped {$pushSkipped}"
    : "Notification and push delivered to {$pushSent} users");
