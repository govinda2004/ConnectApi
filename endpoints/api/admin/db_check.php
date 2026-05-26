<?php

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/_common.php';

$connected = false;
$error = null;
$hasAdmin = false;

try {
    $db = getDB();
    $db->query('SELECT 1');
    $connected = true;

    // Check if any super admin exists
    ensureSuperAdminsTable($db);
    $stmt = $db->query('SELECT COUNT(*) FROM super_admins WHERE is_active = 1');
    $hasAdmin = ((int)$stmt->fetchColumn()) > 0;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// Minimal data for the login page to ensure security
jsonSuccess([
    'connected' => $connected,
    'has_admin' => $hasAdmin,
    'error' => $connected ? null : $error
], $connected ? 'Database connected' : 'Database connection failed');
