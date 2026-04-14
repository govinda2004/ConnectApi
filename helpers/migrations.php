<?php
/**
 * Lightweight runtime-safe schema guards for incremental deployments.
 */

function ensureAccountTypeColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS account_type ENUM('normal','organization') NULL AFTER firebase_uid");
    } catch (Throwable $e) {
        // Fallback for MySQL versions that don't support "ADD COLUMN IF NOT EXISTS".
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'account_type'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE users ADD COLUMN account_type ENUM('normal','organization') NULL AFTER firebase_uid");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}

function ensureFcmTokenColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(512) NULL AFTER device_token");
    } catch (Throwable $e) {
        // Fallback for MySQL versions that don't support "ADD COLUMN IF NOT EXISTS".
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'fcm_token'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE users ADD COLUMN fcm_token VARCHAR(512) NULL AFTER device_token");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}

function ensureNotificationsImageColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS image_url VARCHAR(512) NULL AFTER message");
    } catch (Throwable $e) {
        // Fallback for MySQL versions that don't support "ADD COLUMN IF NOT EXISTS".
        try {
            $stmt = $db->query("SHOW COLUMNS FROM notifications LIKE 'image_url'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE notifications ADD COLUMN image_url VARCHAR(512) NULL AFTER message");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}

function ensureNotificationsBroadcastBatchColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS broadcast_batch_id VARCHAR(64) NULL AFTER image_url");
    } catch (Throwable $e) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM notifications LIKE 'broadcast_batch_id'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE notifications ADD COLUMN broadcast_batch_id VARCHAR(64) NULL AFTER image_url");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard; endpoints should continue to run.
        }
    }
}

function ensureAppContentsTable(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS app_contents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_key VARCHAR(64) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                html_content MEDIUMTEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        ensureDefaultAppContentsRecords($db);
    } catch (Throwable $e) {
        // Best-effort guard; endpoint should still run with defaults.
    }
}

function ensureDefaultAppContentsRecords(PDO $db): void {
    $defaults = [
        'terms_conditions' => [
            'title' => 'Terms & Conditions',
            'html_content' => '<h2>Terms & Conditions</h2><p>By using ConnectIn, you agree to our terms of service. We respect your privacy and protect your data according to applicable laws.</p>',
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
        $stmt = $db->prepare(
            'INSERT INTO app_contents (content_key, title, html_content, is_active)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
               title = IF(title IS NULL OR title = "", VALUES(title), title),
               html_content = IF(html_content IS NULL OR html_content = "", VALUES(html_content), html_content)'
        );
        foreach ($defaults as $key => $row) {
            $stmt->execute([$key, $row['title'], $row['html_content']]);
        }
    } catch (Throwable $e) {
        // Best-effort seeding.
    }
}

function ensureFeedbacksTable(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS feedbacks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                subject VARCHAR(255) NULL,
                message TEXT NOT NULL,
                status ENUM('new','in_progress','resolved','rejected') DEFAULT 'new',
                admin_note TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_status (status)
            )
        ");
    } catch (Throwable $e) {
        // Best-effort guard.
    }
}

function ensureProfilesWebsiteColumn(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS website VARCHAR(500) NULL AFTER contact_no");
    } catch (Throwable $e) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM profiles LIKE 'website'");
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if (!$exists) {
                $db->exec("ALTER TABLE profiles ADD COLUMN website VARCHAR(500) NULL AFTER contact_no");
            }
        } catch (Throwable $inner) {
            // Best-effort migration guard.
        }
    }
}
