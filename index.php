<?php
/**
 * ConnectIn API Router
 * Usage: index.php?route=endpoint_name
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

$endpoints = [
    // Auth
    'register'               => 'endpoints/register.php',
    'login'                  => 'endpoints/login.php',
    'login_gmail'            => 'endpoints/login_gmail.php',
    'logout'                 => 'endpoints/logout.php',
    'logout/all-devices'     => 'endpoints/logout.php',
    'forgot_password'        => 'endpoints/forgot_password.php',

    // Profile
    'get_profile_details'    => 'endpoints/get_profile_details.php',
    'update_profile_details' => 'endpoints/update_profile_details.php',
    'update_profile_image'   => 'endpoints/update_profile_image.php',
    'update_profile_banner'  => 'endpoints/update_profile_banner.php',

    // Posts
    'add_post'               => 'endpoints/add_post.php',
    'post_list'              => 'endpoints/post_list.php',

    // Connections
    'send_connection'        => 'endpoints/send_connection.php',
    'get_invitations'        => 'endpoints/get_invitations.php',
    'accept_invitation'      => 'endpoints/accept_invitation.php',
    'reject_invitation'      => 'endpoints/reject_invitation.php',

    // Messages
    'send_message'           => 'endpoints/send_message.php',
    'get_chat_list'          => 'endpoints/get_chat_list.php',

    // Likes & Comments
    'like_post'              => 'endpoints/like_post.php',
    'add_comment'            => 'endpoints/add_comment.php',
    'get_comments'           => 'endpoints/get_comments.php',

    // Search & Stats
    'search'                 => 'endpoints/search.php',
    'get_user_stats'         => 'endpoints/get_user_stats.php',

    // Setup (run once)
    'setup_db'               => 'endpoints/setup_db.php',
];

if (!isset($endpoints[$route])) {
    jsonError('Route not found: ' . $route, 404);
}

require_once __DIR__ . '/' . $endpoints[$route];
