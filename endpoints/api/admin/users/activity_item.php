<?php

require_once __DIR__ . '/_users_common.php';

adminRequireAuth();
$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
$activityId = (int)($_GET['activity_id'] ?? 0);
if ($userId <= 0 || $activityId <= 0) jsonError('Invalid id');

$base = $db->prepare('
    SELECT n.*, au.name AS actor_name, uu.name AS receiver_name
    FROM notifications n
    LEFT JOIN users au ON au.id = n.actor_id
    LEFT JOIN users uu ON uu.id = n.user_id
    WHERE n.id = ? AND (n.user_id = ? OR n.actor_id = ?)
    LIMIT 1
');
$base->execute([$activityId, $userId, $userId]);
$row = $base->fetch();
if (!$row) jsonError('Activity not found for user', 404);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonSuccess($row, 'User activity fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = trim((string)($input['action'] ?? $input['type'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    if ($type === '' && $message === '') jsonError('action or message is required');

    $sets = [];
    $vals = [];
    if ($type !== '') { $sets[] = 'type = ?'; $vals[] = $type; }
    if ($message !== '') { $sets[] = 'message = ?'; $vals[] = $message; }
    $vals[] = $activityId;
    $vals[] = $userId;
    $vals[] = $userId;
    $sql = 'UPDATE notifications SET ' . implode(', ', $sets) . ' WHERE id = ? AND (user_id = ? OR actor_id = ?)';
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
    jsonSuccess(['id' => $activityId], 'Activity updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare('DELETE FROM notifications WHERE id = ? AND (user_id = ? OR actor_id = ?)');
    $stmt->execute([$activityId, $userId, $userId]);
    if ($stmt->rowCount() === 0) jsonError('Activity not found for user', 404);
    jsonSuccess(['id' => $activityId], 'Activity deleted');
}

jsonError('Method not allowed', 405);
