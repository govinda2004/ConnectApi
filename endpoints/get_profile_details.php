<?php
/**
 * GET /get_profile_details
 * Auth: Bearer token required
 * Optional: ?user_id=X to view another user's profile
 * Returns: user + profile + work + education + skills + connection_status
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$loggedInUserId = requireAuth();
$db = getDB();
ensureAccountTypeColumn($db);

// If user_id param provided, show that user's profile; otherwise show own
$targetUserId = isset($_GET['user_id']) && !empty($_GET['user_id'])
    ? (int)$_GET['user_id']
    : $loggedInUserId;

$isOwnProfile = ($targetUserId === $loggedInUserId);

// User
$stmt = $db->prepare('SELECT id, name, email, created_at, account_type FROM users WHERE id = ?');
$stmt->execute([$targetUserId]);
$user = $stmt->fetch();
if (!$user) jsonError('User not found', 404);

// Profile
$stmt = $db->prepare('SELECT * FROM profiles WHERE user_id = ?');
$stmt->execute([$targetUserId]);
$profile = $stmt->fetch() ?: [];

// Work experience
$stmt = $db->prepare('SELECT * FROM work_experience WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$targetUserId]);
$work = $stmt->fetchAll();

// Education
$stmt = $db->prepare('SELECT * FROM education WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$targetUserId]);
$education = $stmt->fetchAll();

// Skills
$stmt = $db->prepare('SELECT * FROM skills WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$targetUserId]);
$skills = $stmt->fetchAll();

// Connection status (only if viewing other user)
$connectionStatus = null;
if (!$isOwnProfile) {
    $stmt = $db->prepare('SELECT id, status, sender_id FROM connections WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)');
    $stmt->execute([$loggedInUserId, $targetUserId, $targetUserId, $loggedInUserId]);
    $conn = $stmt->fetch();
    if ($conn) {
        $connectionStatus = $conn['status'];
    }
}

$data = [
    'user' => [
        'id'         => (int)$user['id'],
        'name'       => $user['name'],
        'email'      => $isOwnProfile ? $user['email'] : null, // hide email for others
        'account_type' => $user['account_type'] ?? null,
        'contact_no' => $isOwnProfile ? ($profile['contact_no'] ?? null) : null,
        'created_at' => $user['created_at'],
    ],
    'profile' => [
        'profile_id'     => isset($profile['id']) ? (int)$profile['id'] : null,
        'user_id'        => $targetUserId,
        'user_name'      => $user['name'],
        'profile_image'  => $profile['profile_image'] ?? null,
        'profile_banner' => $profile['profile_banner'] ?? null,
        'headline'       => $profile['headline'] ?? null,
        'location'       => $profile['location'] ?? null,
        'about'          => $profile['about'] ?? null,
        'created_at'     => $profile['created_at'] ?? null,
        'updated_at'     => $profile['updated_at'] ?? null,
    ],
    'work'              => $work,
    'education'         => $education,
    'skills'            => $skills,
    'is_own_profile'    => $isOwnProfile,
    'connection_status' => $connectionStatus, // null, pending, accepted, rejected
];

jsonSuccess($data, 'Profile fetched successfully');
