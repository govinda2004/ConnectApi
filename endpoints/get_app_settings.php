<?php
/**
 * GET /get_app_settings
 * Auth: Bearer token required
 * Returns: settings menu + dynamic page content used by Flutter drawer.
 */
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);

// Keep authenticated so app-only settings are not scraped anonymously.
requireAuth();
$db = getDB();
ensureAppContentsTable($db);

$defaultPages = [
    'terms_conditions' => [
        'title' => 'Terms & Conditions',
        'html_content' => '<h2>Terms & Conditions</h2><p>By using ConnectIn, you agree to our terms of service. We respect your privacy and protect your data according to applicable laws.</p>',
    ],
    'privacy_policy' => [
        'title' => 'Privacy Policy',
        'html_content' => '<h2>Privacy Policy</h2><p>Your privacy is important to us. We collect minimal data and never sell it to third parties.</p>',
    ],
    'about' => [
        'title' => 'About ConnectIn',
        'html_content' => '<h2>About ConnectIn</h2><p>ConnectIn is a professional networking platform that helps you build meaningful connections, discover opportunities, and grow your career.</p><p><strong>Version:</strong> 1.0.0</p>',
    ],
    'help_support' => [
        'title' => 'Help & Support',
        'html_content' => '<h2>Help & Support</h2><p>Need help? Contact us at <a href="mailto:support@connectin.app">support@connectin.app</a></p><h4>FAQ</h4><ul><li>How to connect with people?</li><li>How to create a post?</li><li>How to update my profile?</li></ul>',
    ],
];

try {
    $seedStmt = $db->prepare(
        'INSERT INTO app_contents (content_key, title, html_content, is_active)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
           title = IF(title IS NULL OR title = "", VALUES(title), title),
           html_content = IF(html_content IS NULL OR html_content = "", VALUES(html_content), html_content)'
    );
    foreach ($defaultPages as $key => $page) {
        $seedStmt->execute([$key, $page['title'], $page['html_content']]);
    }
} catch (Throwable $e) {
    // Best effort. Fallback to defaults below.
}

$pages = [];
try {
    $stmt = $db->query('SELECT content_key, title, html_content FROM app_contents WHERE is_active = 1');
    $rows = $stmt ? $stmt->fetchAll() : [];
    foreach ($rows as $r) {
        $key = (string)($r['content_key'] ?? '');
        if ($key === '') continue;
        $pages[$key] = [
            'title' => (string)($r['title'] ?? ''),
            'html_content' => (string)($r['html_content'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    // Fallback to defaults.
}

foreach ($defaultPages as $key => $page) {
    if (!isset($pages[$key])) {
        $pages[$key] = $page;
    }
}

$data = [
    'version' => '1.0.0',
    'menu_items' => [
        [
            'key' => 'blocked_users',
            'label' => 'Blocked Users',
            'icon' => 'block_outlined',
            'action' => 'screen',
            'screen' => 'blocked_users',
        ],
        [
            'key' => 'terms_conditions',
            'label' => 'Terms & Conditions',
            'icon' => 'description_outlined',
            'action' => 'content',
            'content_key' => 'terms_conditions',
        ],
        [
            'key' => 'privacy_policy',
            'label' => 'Privacy Policy',
            'icon' => 'privacy_tip_outlined',
            'action' => 'content',
            'content_key' => 'privacy_policy',
        ],
        [
            'key' => 'about',
            'label' => 'About',
            'icon' => 'info_outline',
            'action' => 'content',
            'content_key' => 'about',
        ],
        [
            'key' => 'feedback',
            'label' => 'Feedback',
            'icon' => 'feedback_outlined',
            'action' => 'feedback_form',
        ],
        [
            'key' => 'help_support',
            'label' => 'Help & Support',
            'icon' => 'help_outline',
            'action' => 'content',
            'content_key' => 'help_support',
        ],
    ],
    'pages' => $pages,
];

jsonSuccess($data, 'App settings fetched');
