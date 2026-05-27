<?php
/**
 * GET /get_invitations
 * Auth: Bearer token required
 * Returns: sent, received, and suggested connections
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$db = getDB();

// Received invitations (pending)
$stmt = $db->prepare('
    SELECT c.id AS connection_id, c.sender_id AS user_id, c.status, c.created_at AS sent_at,
           u.name, u.email, u.account_type, pr.profile_image AS image, pr.headline AS role,
           pr.location, pr.about AS bio
    FROM connections c
    JOIN users u ON c.sender_id = u.id
    LEFT JOIN profiles pr ON c.sender_id = pr.user_id
    WHERE c.receiver_id = ? AND c.status = ?
    ORDER BY c.created_at DESC
');
$stmt->execute([$userId, 'pending']);
$received = $stmt->fetchAll();

// Sent invitations (pending)
$stmt = $db->prepare('
    SELECT c.id AS connection_id, c.receiver_id AS user_id, c.status, c.created_at AS sent_at,
           u.name, u.email, u.account_type, pr.profile_image AS image, pr.headline AS role,
           pr.location, pr.about AS bio
    FROM connections c
    JOIN users u ON c.receiver_id = u.id
    LEFT JOIN profiles pr ON c.receiver_id = pr.user_id
    WHERE c.sender_id = ? AND c.status = ?
    ORDER BY c.created_at DESC
');
$stmt->execute([$userId, 'pending']);
$sent = $stmt->fetchAll();

// Suggested connections (users not yet connected, excluding admins)
$stmt = $db->prepare('
    SELECT u.id AS user_id, u.name, u.email, u.account_type,
           pr.profile_image AS image, pr.headline AS role, pr.location, pr.about AS bio
    FROM users u
    LEFT JOIN profiles pr ON u.id = pr.user_id
    WHERE u.id != ?
      AND u.id NOT IN (
          SELECT receiver_id FROM connections WHERE sender_id = ?
          UNION
          SELECT sender_id FROM connections WHERE receiver_id = ?
      )
      AND u.email NOT IN (SELECT email FROM super_admins)
    ORDER BY RAND()
    LIMIT 20
');
$stmt->execute([$userId, $userId, $userId]);
$suggested = $stmt->fetchAll();

// Cast IDs
foreach ($received as &$r) { $r['user_id'] = (int)$r['user_id']; $r['connection_id'] = (int)$r['connection_id']; }
foreach ($sent as &$s) { $s['user_id'] = (int)$s['user_id']; $s['connection_id'] = (int)$s['connection_id']; }
foreach ($suggested as &$sg) { $sg['user_id'] = (int)$sg['user_id']; }

jsonSuccess([
    'received'  => $received,
    'sent'      => $sent,
    'suggested' => $suggested,
], 'Invitations fetched');
