<?php

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();
ensureAppContentsTable($db);

try {
    $db->exec('CREATE TABLE IF NOT EXISTS app_settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )');
} catch (Throwable $e) {
    jsonError('Unable to initialize settings table', 500);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$map = [
    'maintenance_mode' => isset($input['maintenance_mode']) ? ($input['maintenance_mode'] ? '1' : '0') : null,
    'registration_enabled' => isset($input['registration_enabled']) ? ($input['registration_enabled'] ? '1' : '0') : null,
    'default_page_size' => isset($input['default_page_size']) ? (string)max(1, (int)$input['default_page_size']) : null,
];

$stmt = $db->prepare('INSERT INTO app_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
foreach ($map as $k => $v) {
    if ($v === null) continue;
    $stmt->execute([$k, $v]);
}

$contentPayload = $input['app_contents'] ?? null;
if (is_array($contentPayload)) {
    $upsert = $db->prepare(
        'INSERT INTO app_contents (content_key, title, html_content, is_active)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
           title = VALUES(title),
           html_content = VALUES(html_content),
           is_active = 1'
    );
    foreach ($contentPayload as $key => $row) {
        if (!is_string($key) || !preg_match('/^[a-z_]+$/', $key)) continue;
        if (!is_array($row)) continue;
        $title = trim((string)($row['title'] ?? ''));
        $html = (string)($row['html_content'] ?? '');
        if ($title === '' || $html === '') continue;
        $upsert->execute([$key, $title, $html]);
    }
}

jsonSuccess($map, 'Settings saved');
