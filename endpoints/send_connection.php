<?php
/**
 * POST /send_connection
 * Auth: Bearer token required
 * Body: receiver_id
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$receiverId = (int)($_POST['receiver_id'] ?? 0);

if ($receiverId <= 0) {
    jsonError('receiver_id is required');
}

if ($receiverId === $userId) {
    jsonError('Cannot connect with yourself');
}

$db = getDB();

// Check if receiver exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$receiverId]);
if (!$stmt->fetch()) {
    jsonError('User not found', 404);
}

// Check existing connection
$stmt = $db->prepare('
    SELECT id, status FROM connections
    WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
');
$stmt->execute([$userId, $receiverId, $receiverId, $userId]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['status'] === 'accepted') {
        jsonError('Already connected');
    }
    if ($existing['status'] === 'pending') {
        jsonError('Connection request already sent');
    }
}

// Create connection request
$stmt = $db->prepare('INSERT INTO connections (sender_id, receiver_id, status) VALUES (?, ?, ?)');
$stmt->execute([$userId, $receiverId, 'pending']);

jsonSuccess(null, 'Connection request sent');
