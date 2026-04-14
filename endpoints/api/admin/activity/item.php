<?php

require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

$admin = adminRequireAuth();
$db = getDB();
ensureNotificationsImageColumn($db);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid activity id');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('
        SELECT n.id, n.user_id, n.actor_id, n.type, n.message, n.image_url, n.target_id, n.is_read, n.created_at,
               u.name AS actor_name, u.email AS actor_email,
               tu.name AS user_name, tu.email AS user_email
        FROM notifications n
        LEFT JOIN users u ON u.id = n.actor_id
        LEFT JOIN users tu ON tu.id = n.user_id
        WHERE n.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Activity not found', 404);
    jsonSuccess($row, 'Activity detail fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = trim((string)($input['action'] ?? $input['type'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    $imageUrl = trim((string)($input['image_url'] ?? ''));
    if ($type === '' && $message === '' && $imageUrl === '') jsonError('action or message or image_url is required');

    $sets = [];
    $vals = [];
    if ($type !== '') {
        $sets[] = 'type = ?';
        $vals[] = $type;
    }
    if ($message !== '') {
        $sets[] = 'message = ?';
        $vals[] = $message;
    }
    if ($imageUrl !== '') {
        $sets[] = 'image_url = ?';
        $vals[] = $imageUrl;
    }
    $vals[] = $id;
    $sql = 'UPDATE notifications SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
    if ($stmt->rowCount() === 0) {
        $c = $db->prepare('SELECT id FROM notifications WHERE id = ? LIMIT 1');
        $c->execute([$id]);
        if (!$c->fetch()) jsonError('Activity not found', 404);
    }
    jsonSuccess(['id' => $id], 'Activity updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare('DELETE FROM notifications WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) jsonError('Activity not found', 404);
    jsonSuccess(['id' => $id], 'Activity deleted');
}

jsonError('Method not allowed', 405);
