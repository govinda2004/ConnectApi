<?php
/**
 * POST /save_post
 * Auth: Bearer token required
 * Body: post_id
 * Toggle: saves if not saved, unsaves if already saved
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$postId = (int)($_POST['post_id'] ?? 0);

if ($postId <= 0) jsonError('post_id is required');

$db = getDB();

// Ensure table exists
try { $db->exec("CREATE TABLE IF NOT EXISTS saved_posts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, post_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_save (user_id, post_id), INDEX idx_user (user_id))"); } catch (PDOException $e) {}

// Check if already saved
$stmt = $db->prepare('SELECT id FROM saved_posts WHERE user_id = ? AND post_id = ?');
$stmt->execute([$userId, $postId]);
$existing = $stmt->fetch();

if ($existing) {
    // Unsave
    $stmt = $db->prepare('DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?');
    $stmt->execute([$userId, $postId]);
    jsonSuccess(['saved' => false, 'post_id' => $postId], 'Post unsaved');
} else {
    // Save
    $stmt = $db->prepare('INSERT IGNORE INTO saved_posts (user_id, post_id) VALUES (?, ?)');
    $stmt->execute([$userId, $postId]);
    jsonSuccess(['saved' => true, 'post_id' => $postId], 'Post saved');
}
