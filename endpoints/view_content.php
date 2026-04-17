<?php
/**
 * GET /view_content?key=terms_conditions
 * Public endpoint to view HTML content (Terms, Privacy, etc.)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

$key = $_GET['key'] ?? '';
if (empty($key)) {
    die('Content key is required');
}

$db = getDB();
$stmt = $db->prepare('SELECT title, html_content FROM app_contents WHERE content_key = ? AND is_active = 1');
$stmt->execute([$key]);
$content = $stmt->fetch();

if (!$content) {
    // Fallback for default keys if not in DB yet
    $defaults = [
        'terms_conditions' => [
            'title' => 'Terms & Conditions',
            'html_content' => '<h2>Terms & Conditions</h2><p>By using ConnectIn, you agree to our terms of service.</p>'
        ],
        'privacy_policy' => [
            'title' => 'Privacy Policy',
            'html_content' => '<h2>Privacy Policy</h2><p>Your privacy is important to us.</p>'
        ]
    ];

    if (isset($defaults[$key])) {
        $content = $defaults[$key];
    } else {
        http_response_code(404);
        die('Content not found');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($content['title']); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #000; }
    </style>
</head>
<body>
    <?php echo $content['html_content']; ?>
</body>
</html>
