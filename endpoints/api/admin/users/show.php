<?php

require_once __DIR__ . '/_users_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureUsersIsActiveColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid user id');

$hasAccountType = false;
try {
    $c = $db->query("SHOW COLUMNS FROM users LIKE 'account_type'");
    $hasAccountType = $c !== false && $c->fetch() !== false;
} catch (Throwable $e) {
    $hasAccountType = false;
}

$hasProfiles = false;
$hasContactNo = false;
try {
    $t = $db->query("SHOW TABLES LIKE 'profiles'");
    $hasProfiles = $t !== false && $t->fetch() !== false;
    if ($hasProfiles) {
        $p = $db->query("SHOW COLUMNS FROM profiles LIKE 'contact_no'");
        $hasContactNo = $p !== false && $p->fetch() !== false;
    }
} catch (Throwable $e) {
    $hasProfiles = false;
    $hasContactNo = false;
}

$accountSelect = $hasAccountType ? 'u.account_type' : "NULL AS account_type";
$phoneSelect = ($hasProfiles && $hasContactNo) ? 'p.contact_no AS phone' : "NULL AS phone";
$joinProfiles = $hasProfiles ? 'LEFT JOIN profiles p ON p.user_id = u.id' : '';

$sql = "SELECT u.id, u.name, u.email, {$phoneSelect}, {$accountSelect}, u.created_at, u.is_active
        FROM users u
        {$joinProfiles}
        WHERE u.id = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) jsonError('User not found', 404);

$user = normalizeUserStatus($user);
jsonSuccess($user, 'User details fetched');
