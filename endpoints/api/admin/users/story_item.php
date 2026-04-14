<?php

require_once __DIR__ . '/_users_common.php';

adminRequireAuth();
$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
$storyId = (int)($_GET['story_id'] ?? 0);
if ($userId <= 0 || $storyId <= 0) jsonError('Invalid id');

$stmt = $db->prepare("
    SELECT s.*, u.name AS user_name, u.email AS user_email
    FROM stories s
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.id = ? AND s.user_id = ?
    LIMIT 1
");
$stmt->execute([$storyId, $userId]);
$story = $stmt->fetch();
if (!$story) jsonError('Story not found', 404);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $vc = $db->prepare('SELECT COUNT(*) FROM story_views WHERE story_id = ?');
    $vc->execute([$storyId]);
    $story['view_count'] = (int)$vc->fetchColumn();
    jsonSuccess($story, 'Story fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $text = trim((string)($input['text_content'] ?? ''));
    $imageUrl = trim((string)($input['image_url'] ?? ''));
    $expiresAt = trim((string)($input['expires_at'] ?? ''));

    $sets = [];
    $vals = [];
    if ($text !== '') { $sets[] = 'text_content = ?'; $vals[] = $text; }
    if ($imageUrl !== '') { $sets[] = 'image_url = ?'; $vals[] = $imageUrl; }
    if ($expiresAt !== '') { $sets[] = 'expires_at = ?'; $vals[] = $expiresAt; }
    if (empty($sets)) jsonError('No valid fields to update');

    $vals[] = $storyId;
    $vals[] = $userId;
    $sql = 'UPDATE stories SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?';
    $up = $db->prepare($sql);
    $up->execute($vals);
    jsonSuccess(['id' => $storyId], 'Story updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $db->prepare('DELETE FROM story_views WHERE story_id = ?')->execute([$storyId]);
    $del = $db->prepare('DELETE FROM stories WHERE id = ? AND user_id = ?');
    $del->execute([$storyId, $userId]);
    if ($del->rowCount() === 0) jsonError('Story not found', 404);
    jsonSuccess(['id' => $storyId], 'Story deleted');
}

jsonError('Method not allowed', 405);
