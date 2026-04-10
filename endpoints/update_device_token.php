<?php
/**
 * POST /update_device_token
 * Auth: Bearer token required
 * Body: fcm_token (preferred) OR device_token (legacy). Optional empty to clear.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$fcmToken = trim($_POST['fcm_token'] ?? $_POST['device_token'] ?? '');

$db = getDB();
ensureFcmTokenColumn($db);

// Keep both fields in sync for backward compatibility with old queries.
$stmt = $db->prepare('UPDATE users SET fcm_token = ?, device_token = ? WHERE id = ?');
$stmt->execute([$fcmToken, $fcmToken, $userId]);

jsonSuccess([
    'fcm_token' => $fcmToken,
    'device_token' => $fcmToken,
], 'Device token updated');
