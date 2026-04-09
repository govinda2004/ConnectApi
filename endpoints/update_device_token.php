<?php
/**
 * POST /update_device_token
 * Auth: Bearer token required
 * Body: device_token (optional empty to clear)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$deviceToken = trim($_POST['device_token'] ?? '');

$db = getDB();
$stmt = $db->prepare('UPDATE users SET device_token = ? WHERE id = ?');
$stmt->execute([$deviceToken, $userId]);

jsonSuccess([
    'device_token' => $deviceToken,
], 'Device token updated');
