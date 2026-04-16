<?php
/**
 * POST /logout           - logout current device
 * POST /logout/all-devices - logout all devices
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$route = $_GET['route'] ?? '';
$db = getDB();
ensureFcmTokenColumn($db);

// Detach push token from current user on logout to prevent receiving
// notifications after account switch on the same device.
$clearPushToken = static function () use ($db, $userId): void {
    $stmt = $db->prepare('UPDATE users SET fcm_token = NULL, device_token = NULL WHERE id = ?');
    $stmt->execute([$userId]);
};

if ($route === 'logout/all-devices') {
    deleteAllTokens($userId);
    $clearPushToken();
    jsonSuccess(null, 'Logged out from all devices');
} else {
    $token = getBearerToken();
    if ($token) deleteToken($token);
    $clearPushToken();
    jsonSuccess(null, 'Logged out successfully');
}
