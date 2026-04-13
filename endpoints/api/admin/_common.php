<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/auth.php';

function adminAllowedEmailList(): array
{
    $single = trim((string)(getenv('SUPER_ADMIN_EMAIL') ?: ''));
    $multi = trim((string)(getenv('SUPER_ADMIN_EMAILS') ?: ''));

    $emails = [];
    if ($single !== '') $emails[] = strtolower($single);
    if ($multi !== '') {
        foreach (explode(',', $multi) as $e) {
            $e = strtolower(trim($e));
            if ($e !== '') $emails[] = $e;
        }
    }
    return array_values(array_unique($emails));
}

function isSuperAdminEmail(string $email): bool
{
    $email = strtolower(trim($email));
    $allowed = adminAllowedEmailList();
    // If env is not configured, allow login for any valid user as fallback.
    if (empty($allowed)) return true;
    return in_array($email, $allowed, true);
}

function adminRequireAuth(): array
{
    $userId = requireAuth();
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) jsonError('Unauthorized', 401);
    if (!isSuperAdminEmail((string)$user['email'])) {
        jsonError('Forbidden: super admin access required', 403);
    }
    return $user;
}
