<?php
/**
 * POST /set_account_type
 * Auth: Bearer token required
 * Body: account_type = normal|organization
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$db = getDB();
ensureAccountTypeColumn($db);

$accountType = strtolower(trim((string)($_POST['account_type'] ?? '')));
if (!in_array($accountType, ['normal', 'organization'], true)) {
    jsonError('Invalid account_type. Allowed values: normal, organization');
}

$stmt = $db->prepare('UPDATE users SET account_type = ? WHERE id = ?');
$stmt->execute([$accountType, $userId]);

jsonSuccess([
    'account_type' => $accountType,
], 'Account type saved successfully');
