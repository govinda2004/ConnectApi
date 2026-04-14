<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

$admin = adminRequireAuth();
$db = getDB();
ensureNotificationsImageColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid notification id');

$base = $db->prepare("
    SELECT n.id, n.created_at, n.user_id, n.actor_id, n.type, n.message, n.image_url,
           au.name AS actor_name, au.email AS actor_email,
           uu.name AS receiver_name, uu.email AS receiver_email
    FROM notifications n
    LEFT JOIN users au ON au.id = n.actor_id
    LEFT JOIN users uu ON uu.id = n.user_id
    WHERE n.id = ? AND n.type = 'admin_notice'
    LIMIT 1
");
$base->execute([$id]);
$row = $base->fetch();
if (!$row) jsonError('Admin notification not found', 404);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonSuccess($row, 'Notification detail fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $message = trim((string)($input['message'] ?? ''));
    $imageUrl = trim((string)($input['image_url'] ?? ''));
    $type = trim((string)($input['type'] ?? 'admin_notice'));
    if ($message === '' && $imageUrl === '' && $type === '') {
        jsonError('Nothing to update');
    }
    $sets = [];
    $vals = [];
    if ($message !== '') { $sets[] = 'message = ?'; $vals[] = $message; }
    if ($imageUrl !== '') { $sets[] = 'image_url = ?'; $vals[] = $imageUrl; }
    if ($type !== '') { $sets[] = 'type = ?'; $vals[] = $type; }
    if (empty($sets)) jsonError('No valid fields to update');
    $vals[] = $id;
    $stmt = $db->prepare('UPDATE notifications SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($vals);
    jsonSuccess(['id' => $id], 'Notification updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND type = 'admin_notice'");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) jsonError('Admin notification not found', 404);
    jsonSuccess(['id' => $id], 'Notification deleted');
}

jsonError('Method not allowed', 405);
