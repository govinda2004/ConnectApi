<?php

require_once __DIR__ . '/_common.php';

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

$targetUserIds = [];
if ($mode === 'all') {
    $stmt = $db->prepare('SELECT id FROM users WHERE id <> ?');
    $stmt->execute([(int)$admin['id']]);
    $targetUserIds = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
} else {
    if ($userId <= 0) jsonError('user_id is required for single mode');
    $targetUserIds = [$userId];
}

if (empty($targetUserIds)) {
    jsonSuccess(['sent_count' => 0], 'No target users found');
}

$ins = $db->prepare('
    INSERT INTO notifications (user_id, type, actor_id, target_id, message, is_read, created_at)
    VALUES (?, ?, ?, NULL, ?, 0, NOW())
');
$sent = 0;
foreach ($targetUserIds as $uid) {
    $ins->execute([$uid, $title, (int)$admin['id'], $message]);
    $sent++;
}

jsonSuccess([
    'sent_count' => $sent,
    'mode' => $mode,
], 'Notification sent');
