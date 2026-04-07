<?php
/**
 * GET /get_profile_details
 * Auth: Bearer token required
 * Returns: user + profile + work + education
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$userId = requireAuth();
$db = getDB();

// User
$stmt = $db->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) jsonError('User not found', 404);

// Profile
$stmt = $db->prepare('SELECT * FROM profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: [];

// Work experience
$stmt = $db->prepare('SELECT * FROM work_experience WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$userId]);
$work = $stmt->fetchAll();

// Education
$stmt = $db->prepare('SELECT * FROM education WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$userId]);
$education = $stmt->fetchAll();

// Skills
$stmt = $db->prepare('SELECT * FROM skills WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$userId]);
$skills = $stmt->fetchAll();

$data = [
    'user' => [
        'id'         => (int)$user['id'],
        'name'       => $user['name'],
        'email'      => $user['email'],
        'contact_no' => $profile['contact_no'] ?? null,
        'created_at' => $user['created_at'],
    ],
    'profile' => [
        'profile_id'     => isset($profile['id']) ? (int)$profile['id'] : null,
        'user_id'        => $userId,
        'user_name'      => $user['name'],
        'profile_image'  => $profile['profile_image'] ?? null,
        'profile_banner' => $profile['profile_banner'] ?? null,
        'headline'       => $profile['headline'] ?? null,
        'location'       => $profile['location'] ?? null,
        'about'          => $profile['about'] ?? null,
        'created_at'     => $profile['created_at'] ?? null,
        'updated_at'     => $profile['updated_at'] ?? null,
    ],
    'work'      => $work,
    'education' => $education,
    'skills'    => $skills,
];

jsonSuccess($data, 'Profile fetched successfully');
