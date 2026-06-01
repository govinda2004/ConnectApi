<?php
/**
 * Token authentication helpers.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

/**
 * Generate a random 64-character token.
 */
function generateToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Create and store a new token for a user.
 */
function createToken(int $userId, string $deviceName = 'Flutter'): string {
    $db = getDB();
    $token = generateToken();
    $stmt = $db->prepare('INSERT INTO auth_tokens (user_id, token, device_name) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $token, $deviceName]);
    return $token;
}

/**
 * Verify Bearer token from Authorization header.
 * Returns user_id on success, calls jsonError on failure.
 */
function requireAuth(): int {
    // Attempt to get the Authorization header from various sources (Render/Apache fix)
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (empty($header) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (empty($header) || !str_starts_with($header, 'Bearer ')) {
        jsonError('Unauthorized: Missing or invalid Authorization header', 401);
    }

    $token = substr($header, 7);
    $db = getDB();
    $stmt = $db->prepare('SELECT user_id FROM auth_tokens WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonError('Invalid or expired token', 401);
    }
    return (int)$row['user_id'];
}

/**
 * Get token string from Authorization header (without verifying).
 */
function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (empty($header) || !str_starts_with($header, 'Bearer ')) {
        return null;
    }
    return substr($header, 7);
}

/**
 * Delete a specific token (logout).
 */
function deleteToken(string $token): void {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM auth_tokens WHERE token = ?');
    $stmt->execute([$token]);
}

/**
 * Delete all tokens for a user (logout all devices).
 */
function deleteAllTokens(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM auth_tokens WHERE user_id = ?');
    $stmt->execute([$userId]);
}
