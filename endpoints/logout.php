<?php
/**
 * POST /logout           - logout current device
 * POST /logout/all-devices - logout all devices
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$route = $_GET['route'] ?? '';

if ($route === 'logout/all-devices') {
    deleteAllTokens($userId);
    jsonSuccess(null, 'Logged out from all devices');
} else {
    $token = getBearerToken();
    if ($token) deleteToken($token);
    jsonSuccess(null, 'Logged out successfully');
}
