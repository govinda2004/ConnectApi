<?php
/**
 * POST /submit_feedback
 * Auth: Bearer token required
 * Body: message (required), subject (optional)
 */
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$userId = requireAuth();
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$subject = trim((string)($payload['subject'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
if ($message === '') {
    jsonError('message is required');
}
if (mb_strlen($message) > 5000) {
    jsonError('message is too long');
}
if ($subject !== '' && mb_strlen($subject) > 255) {
    jsonError('subject is too long');
}

$db = getDB();
ensureFeedbacksTable($db);

$stmt = $db->prepare('INSERT INTO feedbacks (user_id, subject, message, status) VALUES (?, ?, ?, "new")');
$stmt->execute([$userId, $subject !== '' ? $subject : null, $message]);

jsonSuccess([
    'feedback_id' => (int)$db->lastInsertId(),
], 'Feedback submitted');
