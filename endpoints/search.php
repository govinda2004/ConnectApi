<?php
/**
 * GET /search&q=query&type=all|people|posts
 * Auth: Bearer token (optional)
 * Returns: search results
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';

if (empty($query)) {
    jsonError('Search query is required');
}

$db = getDB();
$searchTerm = '%' . $query . '%';
$results = ['people' => [], 'posts' => []];

// Search people
if ($type === 'all' || $type === 'people') {
    $stmt = $db->prepare('
        SELECT u.id AS user_id, u.name, u.email,
               p.profile_image, p.headline, p.location
        FROM users u
        LEFT JOIN profiles p ON u.id = p.user_id
        WHERE u.name LIKE ? OR u.email LIKE ? OR p.headline LIKE ?
        LIMIT 20
    ');
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results['people'] = $stmt->fetchAll();

    foreach ($results['people'] as &$p) {
        $p['user_id'] = (int)$p['user_id'];
    }
}

// Search posts
if ($type === 'all' || $type === 'posts') {
    $stmt = $db->prepare('
        SELECT p.id, p.content, p.created_at,
               u.name AS author_name, pr.profile_image AS author_image, pr.headline AS author_headline
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN profiles pr ON p.user_id = pr.user_id
        WHERE p.content LIKE ?
        ORDER BY p.created_at DESC
        LIMIT 20
    ');
    $stmt->execute([$searchTerm]);
    $results['posts'] = $stmt->fetchAll();

    foreach ($results['posts'] as &$post) {
        $post['id'] = (int)$post['id'];
    }
}

jsonSuccess($results, 'Search results');
