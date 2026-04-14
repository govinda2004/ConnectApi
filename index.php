<?php
/**
 * ConnectIn API Router
 * Usage: index.php?route=endpoint_name
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/helpers/response.php';

// Support both ?route=xxx and /xxx path-based routing
$route = $_GET['route'] ?? '';
if (empty($route)) {
    // Try ORIGINAL_URI (set by router.php), then REQUEST_URI, then PATH_INFO
    $uri = $_SERVER['ORIGINAL_URI']
        ?? parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)
        ?? '';
    $route = trim($uri, '/');
}

// Admin API dynamic routes
if ($route === 'api/admin/users') {
    require_once __DIR__ . '/endpoints/api/admin/users/index.php';
    exit;
}
if (preg_match('#^api/admin/users/(\d+)$#', $route, $m) === 1) {
    $_GET['id'] = $m[1];
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        require_once __DIR__ . '/endpoints/api/admin/users/delete.php';
    } else {
        require_once __DIR__ . '/endpoints/api/admin/users/show.php';
    }
    exit;
}
if (preg_match('#^api/admin/users/(\d+)/full$#', $route, $m) === 1) {
    $_GET['id'] = $m[1];
    require_once __DIR__ . '/endpoints/api/admin/users/full.php';
    exit;
}
if (preg_match('#^api/admin/users/(\d+)/activity$#', $route, $m) === 1) {
    $_GET['id'] = $m[1];
    require_once __DIR__ . '/endpoints/api/admin/users/activity.php';
    exit;
}
if (preg_match('#^api/admin/users/(\d+)/status$#', $route, $m) === 1) {
    $_GET['id'] = $m[1];
    require_once __DIR__ . '/endpoints/api/admin/users/status.php';
    exit;
}
if ($route === 'api/admin/admins') {
    require_once __DIR__ . '/endpoints/api/admin/admins/index.php';
    exit;
}
if (preg_match('#^api/admin/admins/(\d+)$#', $route, $m) === 1) {
    $_GET['id'] = $m[1];
    require_once __DIR__ . '/endpoints/api/admin/admins/item.php';
    exit;
}
if ($route === 'api/admin/activity/logs') {
    require_once __DIR__ . '/endpoints/api/admin/activity/logs.php';
    exit;
}
if (preg_match('#^api/admin/activity/(\d+)$#', $route, $m) === 1) {
    $_GET['id'] = $m[1];
    require_once __DIR__ . '/endpoints/api/admin/activity/item.php';
    exit;
}
if (preg_match('#^api/admin/data/([a-z_]+)$#', $route, $m) === 1) {
    $_GET['resource'] = $m[1];
    require_once __DIR__ . '/endpoints/api/admin/data/index.php';
    exit;
}
if (preg_match('#^api/admin/data/([a-z_]+)/(\d+)$#', $route, $m) === 1) {
    $_GET['resource'] = $m[1];
    $_GET['id'] = $m[2];
    require_once __DIR__ . '/endpoints/api/admin/data/item.php';
    exit;
}

$endpoints = [
    // Auth
    'register'               => 'endpoints/register.php',
    'login'                  => 'endpoints/login.php',
    'login_gmail'            => 'endpoints/login_gmail.php',
    'logout'                 => 'endpoints/logout.php',
    'logout/all-devices'     => 'endpoints/logout.php',
    'forgot_password'        => 'endpoints/forgot_password.php',
    'update_device_token'    => 'endpoints/update_device_token.php',
    'update_fcm_token'       => 'endpoints/update_fcm_token.php',

    // Profile
    'get_profile_details'    => 'endpoints/get_profile_details.php',
    'update_profile_details' => 'endpoints/update_profile_details.php',
    'update_profile_image'   => 'endpoints/update_profile_image.php',
    'update_profile_banner'  => 'endpoints/update_profile_banner.php',
    'remove_profile_image'   => 'endpoints/remove_profile_image.php',
    'set_account_type'       => 'endpoints/set_account_type.php',
    'get_account_type'       => 'endpoints/get_account_type.php',

    // Stories
    'add_story'              => 'endpoints/add_story.php',
    'get_stories'            => 'endpoints/get_stories.php',
    'view_story'             => 'endpoints/view_story.php',
    'get_story_views'        => 'endpoints/get_story_views.php',
    'delete_story'           => 'endpoints/delete_story.php',

    // Posts
    'add_post'               => 'endpoints/add_post.php',
    'edit_post'              => 'endpoints/edit_post.php',
    'delete_post'            => 'endpoints/delete_post.php',
    'post_list'              => 'endpoints/post_list.php',

    // Connections
    'send_connection'        => 'endpoints/send_connection.php',
    'get_invitations'        => 'endpoints/get_invitations.php',
    'accept_invitation'      => 'endpoints/accept_invitation.php',
    'reject_invitation'      => 'endpoints/reject_invitation.php',

    // Messages
    'send_message'           => 'endpoints/send_message.php',
    'get_messages'           => 'endpoints/get_messages.php',
    'get_chat_list'          => 'endpoints/get_chat_list.php',
    'mark_as_read'           => 'endpoints/mark_as_read.php',
    'unread_count'           => 'endpoints/unread_count.php',
    'delete_message'         => 'endpoints/delete_message.php',

    // Likes & Comments
    'like_post'              => 'endpoints/like_post.php',
    'add_comment'            => 'endpoints/add_comment.php',
    'get_comments'           => 'endpoints/get_comments.php',

    // Notifications
    'get_notifications'      => 'endpoints/get_notifications.php',
    'mark_notifications_read' => 'endpoints/mark_notifications_read.php',

    // Jobs
    'post_job'               => 'endpoints/post_job.php',
    'get_jobs'               => 'endpoints/get_jobs.php',
    'get_job_detail'         => 'endpoints/get_job_detail.php',
    'apply_job'              => 'endpoints/apply_job.php',
    'save_job'               => 'endpoints/save_job.php',
    'get_saved_jobs'         => 'endpoints/get_saved_jobs.php',
    'get_job_insights'       => 'endpoints/get_job_insights.php',
    'delete_job'             => 'endpoints/delete_job.php',

    // Search & Stats
    'search'                 => 'endpoints/search.php',
    'get_user_stats'         => 'endpoints/get_user_stats.php',

    // Block & Report
    'block_user'             => 'endpoints/block_user.php',
    'unblock_user'           => 'endpoints/unblock_user.php',
    'get_blocked_users'      => 'endpoints/get_blocked_users.php',
    'report_user'            => 'endpoints/report_user.php',

    // Save & Share Posts
    'save_post'              => 'endpoints/save_post.php',
    'get_saved_posts'        => 'endpoints/get_saved_posts.php',
    'share_post'             => 'endpoints/share_post.php',

    // Export & Activity
    'export_job_applicants'  => 'endpoints/export_job_applicants.php',
    'get_user_activity'      => 'endpoints/get_user_activity.php',

    // Setup (run once)
    'setup_db'               => 'endpoints/setup_db.php',
    // Admin API (static routes)
    'api/admin/login'        => 'endpoints/api/admin/login.php',
    'api/admin/profile'      => 'endpoints/api/admin/profile.php',
    'api/admin/dashboard/stats' => 'endpoints/api/admin/dashboard/stats.php',
    'api/admin/dashboard/analytics' => 'endpoints/api/admin/dashboard/analytics.php',
    'api/admin/activity/recent' => 'endpoints/api/admin/activity/recent.php',
    'api/admin/change-password' => 'endpoints/api/admin/change_password.php',
    'api/admin/settings'     => 'endpoints/api/admin/settings.php',
    'api/admin/notifications/send' => 'endpoints/api/admin/notifications_send.php',
];

if (!isset($endpoints[$route])) {
    // Backward-compatible fallback: allow direct file route if it exists.
    // Example: ?route=set_account_type -> endpoints/set_account_type.php
    if (preg_match('/^[a-zA-Z0-9_\/-]+$/', $route) === 1) {
        $direct = __DIR__ . '/endpoints/' . $route . '.php';
        if (is_file($direct)) {
            require_once $direct;
            exit;
        }
    }
    jsonError('Route not found: ' . $route, 404);
}

require_once __DIR__ . '/' . $endpoints[$route];
