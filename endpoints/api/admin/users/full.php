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

// Full profile
$profileSql = '
    SELECT
      u.id, u.name, u.email, u.account_type, u.login_type, u.created_at, u.is_active,
      p.profile_image, p.profile_banner, p.headline, p.location, p.about, p.contact_no, p.updated_at
    FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
';
$stmt = $db->prepare($profileSql);
$stmt->execute([$id]);
$profile = $stmt->fetch();
if (!$profile) jsonError('User not found', 404);
$profile = normalizeUserStatus($profile);

// Activity by/for this user
$activityStmt = $db->prepare('
    SELECT n.id, n.created_at, n.type AS action, n.message, n.user_id, n.actor_id, n.target_id,
           au.name AS actor_name, uu.name AS receiver_name
    FROM notifications n
    LEFT JOIN users au ON au.id = n.actor_id
    LEFT JOIN users uu ON uu.id = n.user_id
    WHERE n.user_id = ? OR n.actor_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
');
$activityStmt->execute([$id, $id]);
$activity = $activityStmt->fetchAll();

// Jobs applied by this user
$appliedStmt = $db->prepare('
    SELECT ja.id, ja.job_id, ja.status, ja.created_at AS applied_at, ja.full_name, ja.email, ja.phone,
           j.title, j.company, j.location, j.job_type, j.user_id AS job_owner_id
    FROM job_applications ja
    INNER JOIN jobs j ON j.id = ja.job_id
    WHERE ja.user_id = ?
    ORDER BY ja.created_at DESC
    LIMIT 100
');
$appliedStmt->execute([$id]);
$appliedJobs = $appliedStmt->fetchAll();

// Jobs created by this user
$createdStmt = $db->prepare('
    SELECT j.id, j.title, j.company, j.location, j.job_type, j.created_at,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) AS applications_count
    FROM jobs j
    WHERE j.user_id = ?
    ORDER BY j.created_at DESC
    LIMIT 100
');
$createdStmt->execute([$id]);
$createdJobs = $createdStmt->fetchAll();

// Summary/actions
$summary = [
    'activity_count' => count($activity),
    'applied_jobs_count' => count($appliedJobs),
    'created_jobs_count' => count($createdJobs),
];

jsonSuccess([
    'profile' => $profile,
    'activity' => $activity,
    'applied_jobs' => $appliedJobs,
    'created_jobs' => $createdJobs,
    'summary' => $summary,
], 'User full details fetched');
