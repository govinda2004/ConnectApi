<?php
/**
 * POST /update_profile_details
 * Auth: Bearer token required
 * Content-Type: application/json
 * Body: user_name, headline, location, about, contact_no, work[], education[]
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

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fallback to form data
    $input = $_POST;
}

// Update user name
if (!empty($input['user_name'])) {
    $db->prepare('UPDATE users SET name = ? WHERE id = ?')
       ->execute([$input['user_name'], $userId]);
}

if (isset($input['account_type'])) {
    $accountType = strtolower(trim((string)$input['account_type']));
    if (!in_array($accountType, ['normal', 'organization'], true)) {
        jsonError('Invalid account_type. Allowed values: normal, organization');
    }
    $db->prepare('UPDATE users SET account_type = ? WHERE id = ?')
       ->execute([$accountType, $userId]);
}

// Update profile fields
$profileFields = [];
$profileValues = [];
foreach (['headline', 'location', 'about', 'contact_no'] as $field) {
    if (isset($input[$field])) {
        $profileFields[] = "$field = ?";
        $profileValues[] = $input[$field];
    }
}

if (!empty($profileFields)) {
    $profileValues[] = $userId;
    $sql = 'UPDATE profiles SET ' . implode(', ', $profileFields) . ' WHERE user_id = ?';
    $db->prepare($sql)->execute($profileValues);
}

// Update work experience (replace all)
if (isset($input['work']) && is_array($input['work'])) {
    $db->prepare('DELETE FROM work_experience WHERE user_id = ?')->execute([$userId]);
    $stmt = $db->prepare(
        'INSERT INTO work_experience (user_id, org_name, position, org_type, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($input['work'] as $w) {
        $stmt->execute([
            $userId,
            $w['org_name'] ?? '',
            $w['position'] ?? null,
            $w['org_type'] ?? null,
            $w['start_date'] ?? null,
            $w['end_date'] ?? null,
        ]);
    }
}

// Update education (replace all)
if (isset($input['education']) && is_array($input['education'])) {
    $db->prepare('DELETE FROM education WHERE user_id = ?')->execute([$userId]);
    $stmt = $db->prepare(
        'INSERT INTO education (user_id, org_name, degree, pass_year) VALUES (?, ?, ?, ?)'
    );
    foreach ($input['education'] as $e) {
        $stmt->execute([
            $userId,
            $e['org_name'] ?? '',
            $e['degree'] ?? null,
            $e['pass_year'] ?? null,
        ]);
    }
}

// Update skills (replace all)
if (isset($input['skills']) && is_array($input['skills'])) {
    $db->prepare('DELETE FROM skills WHERE user_id = ?')->execute([$userId]);
    $stmt = $db->prepare(
        'INSERT INTO skills (user_id, skill_name) VALUES (?, ?)'
    );
    foreach ($input['skills'] as $s) {
        $skillName = is_string($s) ? $s : ($s['skill_name'] ?? '');
        if (!empty(trim($skillName))) {
            $stmt->execute([$userId, trim($skillName)]);
        }
    }
}

jsonSuccess(null, 'Profile updated successfully');
