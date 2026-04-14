<?php

require_once __DIR__ . '/_users_common.php';

adminRequireAuth();
$db = getDB();
ensureUsersIsActiveColumn($db);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid user id');

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $accountType = trim((string)($input['account_type'] ?? ''));
    $isActiveRaw = $input['is_active'] ?? null;
    $phone = trim((string)($input['phone'] ?? ''));
    $headline = trim((string)($input['headline'] ?? ''));
    $location = trim((string)($input['location'] ?? ''));
    $about = trim((string)($input['about'] ?? ''));

    $updates = [];
    $vals = [];
    if ($name !== '') { $updates[] = 'name = ?'; $vals[] = $name; }
    if ($email !== '') { $updates[] = 'email = ?'; $vals[] = $email; }
    if (in_array($accountType, ['normal', 'organization'], true)) {
        $updates[] = 'account_type = ?'; $vals[] = $accountType;
    }
    if ($isActiveRaw !== null) {
        $updates[] = 'is_active = ?'; $vals[] = ((int)$isActiveRaw === 1 ? 1 : 0);
    }

    if (!empty($updates)) {
        $vals[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($vals);
    }

    // Profiles optional update.
    $shouldProfileUpdate = ($phone !== '' || $headline !== '' || $location !== '' || $about !== '');
    if ($shouldProfileUpdate) {
        $check = $db->prepare('SELECT user_id FROM profiles WHERE user_id = ? LIMIT 1');
        $check->execute([$id]);
        $has = (bool)$check->fetch();
        if (!$has) {
            $ins = $db->prepare('INSERT INTO profiles (user_id, contact_no, headline, location, about) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$id, $phone !== '' ? $phone : null, $headline !== '' ? $headline : null, $location !== '' ? $location : null, $about !== '' ? $about : null]);
        } else {
            $pUpdates = [];
            $pVals = [];
            if ($phone !== '') { $pUpdates[] = 'contact_no = ?'; $pVals[] = $phone; }
            if ($headline !== '') { $pUpdates[] = 'headline = ?'; $pVals[] = $headline; }
            if ($location !== '') { $pUpdates[] = 'location = ?'; $pVals[] = $location; }
            if ($about !== '') { $pUpdates[] = 'about = ?'; $pVals[] = $about; }
            if (!empty($pUpdates)) {
                $pVals[] = $id;
                $pSql = 'UPDATE profiles SET ' . implode(', ', $pUpdates) . ' WHERE user_id = ?';
                $pStmt = $db->prepare($pSql);
                $pStmt->execute($pVals);
            }
        }
    }

    jsonSuccess(['id' => $id], 'User updated');
}

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
