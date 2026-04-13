<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/auth.php';

function ensureSuperAdminsTable(PDO $db): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec('CREATE TABLE IF NOT EXISTS super_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    } catch (Throwable $e) {
        // Best effort only.
    }
}

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
    if (in_array($email, $allowed, true)) return true;

    try {
        $db = getDB();
        ensureSuperAdminsTable($db);
        $stmt = $db->prepare('SELECT 1 FROM super_admins WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) return true;
    } catch (Throwable $e) {
        // Ignore DB lookup failure and fallback to env-only result.
    }

    return false;
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
