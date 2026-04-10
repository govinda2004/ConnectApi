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
$updatedAt = gmdate('Y-m-d H:i:s');

// Keep logs safe by masking sensitive token characters.
$maskToken = static function (string $token): string {
    $len = strlen($token);
    if ($len <= 12) return $token;
    return substr($token, 0, 6) . '...' . substr($token, -6);
};

$db = getDB();
ensureFcmTokenColumn($db);

// Keep both fields in sync for backward compatibility with old queries.
$stmt = $db->prepare('UPDATE users SET fcm_token = ?, device_token = ? WHERE id = ?');
$stmt->execute([$fcmToken, $fcmToken, $userId]);

error_log(
    '[FCM_TOKEN_UPDATE] user_id=' . $userId .
    ' updated_at_utc=' . $updatedAt .
    ' token=' . $maskToken($fcmToken)
);

jsonSuccess([
    'fcm_token' => $fcmToken,
    'device_token' => $fcmToken,
    'updated_at_utc' => $updatedAt,
], 'Device token updated');
