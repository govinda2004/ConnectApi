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

$isMultipart = isset($_SERVER['CONTENT_TYPE']) && stripos((string)$_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
$input = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);

$mode = trim((string)($input['mode'] ?? 'single')); // single | all
$userId = (int)($input['user_id'] ?? 0);
$title = trim((string)($input['title'] ?? 'Admin Notice'));
$message = trim((string)($input['message'] ?? ''));
$imageUrl = trim((string)($input['image_url'] ?? ''));

if ($message === '') jsonError('message is required');

$uploadedImageUrl = null;
if ($isMultipart && isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (($_FILES['image']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        jsonError('Image upload failed');
    }
    $tmp = $_FILES['image']['tmp_name'] ?? '';
    $orig = $_FILES['image']['name'] ?? 'image';
    $size = (int)($_FILES['image']['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) jsonError('Invalid uploaded file');
    if ($size > 5 * 1024 * 1024) jsonError('Image size must be <= 5MB');

    $ext = strtolower(pathinfo((string)$orig, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) jsonError('Only jpg, jpeg, png, webp, gif images are allowed');

    $dirRel = '/uploads/admin_notifications';
    $dirAbs = dirname(__DIR__, 3) . $dirRel;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0777, true) && !is_dir($dirAbs)) {
        jsonError('Unable to create upload directory', 500);
    }
    $file = 'notice_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dirAbs . '/' . $file;
    if (!move_uploaded_file($tmp, $dest)) jsonError('Unable to store uploaded image', 500);

    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    $base = rtrim((string)(getenv('APP_BASE_URL') ?: ($scheme . '://' . $host)), '/');
    $uploadedImageUrl = $base . $dirRel . '/' . $file;
}
if ($uploadedImageUrl !== null) $imageUrl = $uploadedImageUrl;

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

$ins = $db->prepare('
    INSERT INTO notifications (user_id, type, actor_id, target_id, message, image_url, broadcast_batch_id, is_read, created_at)
    VALUES (?, ?, ?, NULL, ?, ?, ?, 0, NOW())
');
$broadcastBatchId = ($mode === 'all') ? ('batch_' . time() . '_' . bin2hex(random_bytes(4))) : null;

$sent = 0;
$pushSent = 0;
$pushFailed = 0;
$pushSkipped = 0;
$failures = [];

foreach ($targetUsers as $tu) {
    $uid = (int)$tu['id'];
    $pushToken = trim((string)($tu['push_token'] ?? ''));
    $receiverName = trim((string)($tu['name'] ?? ''));

    $ins->execute([$uid, 'admin_notice', (int)$admin['id'], $message, ($imageUrl !== '' ? $imageUrl : null), $broadcastBatchId]);
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
            'image_url' => (string)$imageUrl,
        ],
        $meta,
        $imageUrl !== '' ? $imageUrl : null
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
    'broadcast_batch_id' => $broadcastBatchId,
    'logical_entries' => $mode === 'all' ? 1 : $sent,
    'push_sent' => $pushSent,
    'push_failed' => $pushFailed,
    'push_skipped' => $pushSkipped,
    'image_url' => ($imageUrl !== '' ? $imageUrl : null),
    'failures' => array_slice($failures, 0, 10),
], $pushFailed > 0 || $pushSkipped > 0
    ? "Notification sent to {$sent} users, push success {$pushSent}, failed {$pushFailed}, skipped {$pushSkipped}"
    : "Notification and push delivered to {$pushSent} users");
