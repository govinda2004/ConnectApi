<?php
/**
 * GET /get_account_type
 * Auth: Bearer token required
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$db = getDB();
ensureAccountTypeColumn($db);

$stmt = $db->prepare('SELECT account_type FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    jsonError('User not found', 404);
}

jsonSuccess([
    'account_type' => $row['account_type'] ?? null,
], 'Account type fetched successfully');
