<?php
/**
 * POST /accept_invitation
 * Auth: Bearer token required
 * Body: connection_id
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$connectionId = (int)($_POST['connection_id'] ?? 0);

if ($connectionId <= 0) {
    jsonError('connection_id is required');
}

$db = getDB();

$stmt = $db->prepare('SELECT * FROM connections WHERE id = ? AND receiver_id = ? AND status = ?');
$stmt->execute([$connectionId, $userId, 'pending']);
$conn = $stmt->fetch();

if (!$conn) {
    jsonError('Invitation not found or already processed', 404);
}

$db->prepare('UPDATE connections SET status = ? WHERE id = ?')
   ->execute(['accepted', $connectionId]);

// Notify the sender that their request was accepted
require_once __DIR__ . '/../helpers/notifications.php';
$stmt = $db->prepare('SELECT name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$acceptorName = $stmt->fetchColumn();
createNotification($db, (int)$conn['sender_id'], 'connection_accepted', $userId, $connectionId, "$acceptorName accepted your connection request");

jsonSuccess(null, 'Connection accepted');
