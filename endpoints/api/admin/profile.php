<?php

require_once __DIR__ . '/_common.php';

$admin = adminRequireAuth();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonSuccess($admin, 'Admin profile fetched');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['name'] ?? $admin['name']));
    $email = trim((string)($input['email'] ?? $admin['email']));

    if ($name === '' || $email === '') {
        jsonError('name and email are required');
    }

    $stmt = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $stmt->execute([$name, $email, (int)$admin['id']]);

    jsonSuccess([
        'id' => (int)$admin['id'],
        'name' => $name,
        'email' => $email,
    ], 'Admin profile updated');
}

jsonError('Method not allowed', 405);
