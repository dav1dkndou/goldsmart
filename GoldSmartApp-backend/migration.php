<?php

/**
 * GoldSmart Database Migration Runner
 * ====================================
 * Buka file ini di browser untuk menjalankan migrasi database secara otomatis.
 * URL: https://goldsmart.online/migration.php
 *
 * PENTING: Hapus file ini setelah migrasi selesai!
 *
 * Fitur:
 * - Idempotent: aman dijalankan berulang kali (CREATE IF NOT EXISTS + ON DUPLICATE KEY)
 * - ALTER TABLE yang aman dengan pengecekan kolom
 * - Laporan detail setiap langkah
 * - Auto-detect environment (production/development)
 */

// Prevent timeout for large migrations
set_time_limit(300);
error_reporting(E_ALL);

// =====================================================
// ENVIRONMENT & DATABASE CONFIG (same as config/database.php)
// =====================================================
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = ($host === 'localhost' || $host === '127.0.0.1' || str_starts_with($host, 'localhost:') || str_starts_with($host, '127.0.0.1:'));

if ($isLocal) {
    $dbHost = 'localhost';
    $dbName = 'goldsmart_goldcash';
    $dbUser = 'root';
    $dbPass = '';
} else {
    $dbHost = 'localhost';
    $dbName = 'golp4259_goldcash';
    $dbUser = 'golp4259_renmelone';
    $dbPass = 'narutoA1@';
}

// =====================================================
// SECURITY CHECK - Only allow from specific IPs or with secret key
// =====================================================
$allowedKey = 'goldsmart_migrate_2026';
$inputKey = $_GET['key'] ?? '';

if ($inputKey !== $allowedKey) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body>';
    echo '<h1>403 - Access Denied</h1>';
    echo '<p>Tambahkan parameter <code>?key=YOUR_SECRET_KEY</code> untuk mengakses migrasi.</p>';
    echo '</body></html>';
    exit;
}

// =====================================================
// HTML OUTPUT START
// =====================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoldSmart - Database Migration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0a0a0a; color: #e0e0e0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #FFD700; margin-bottom: 5px; font-size: 28px; }
        .subtitle { color: #888; margin-bottom: 20px; }
        .step { background: #1a1a1a; border-radius: 8px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #333; }
        .step.success { border-left-color: #4CAF50; }
        .step.error { border-left-color: #f44336; }
        .step.warning { border-left-color: #FF9800; }
        .step.info { border-left-color: #2196F3; }
        .step-title { font-weight: bold; margin-bottom: 5px; }
        .step-detail { color: #888; font-size: 13px; font-family: monospace; }
        .summary { background: #1a2a1a; border: 1px solid #4CAF50; border-radius: 8px; padding: 20px; margin-top: 20px; }
        .summary.has-errors { background: #2a1a1a; border-color: #f44336; }
        .summary h2 { color: #FFD700; margin-bottom: 10px; }
        .stat { display: inline-block; padding: 8px 15px; margin: 5px; background: #222; border-radius: 5px; }
        .stat .num { font-size: 24px; font-weight: bold; color: #FFD700; }
        .stat .label { font-size: 12px; color: #888; }
        .warning-box { background: #2a2200; border: 1px solid #FF9800; border-radius: 8px; padding: 15px; margin-top: 15px; }
        .warning-box h3 { color: #FF9800; margin-bottom: 5px; }
        .env-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .env-prod { background: #f44336; color: #fff; }
        .env-dev { background: #4CAF50; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚡ GoldSmart Migration</h1>
    <p class="subtitle">
        Database: <strong><?= htmlspecialchars($dbName) ?></strong> &nbsp;|&nbsp; 
        Environment: <span class="env-badge <?= $isLocal ? 'env-dev' : 'env-prod' ?>"><?= $isLocal ? 'DEVELOPMENT' : 'PRODUCTION' ?></span> &nbsp;|&nbsp;
        <?= date('Y-m-d H:i:s') ?>
    </p>
<?php
// =====================================================
// CONNECT TO DATABASE
// =====================================================
$results = [];
$successCount = 0;
$errorCount = 0;
$skipCount = 0;

function logStep(string $title, string $status, string $detail = ''): void
{
    global $results, $successCount, $errorCount, $skipCount;
    $results[] = ['title' => $title, 'status' => $status, 'detail' => $detail];
    if ($status === 'success')
        $successCount++;
    elseif ($status === 'error')
        $errorCount++;
    elseif ($status === 'warning')
        $skipCount++;
}

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);
    logStep('Koneksi Database', 'success', "Terhubung ke {$dbName}@{$dbHost}");
} catch (PDOException $e) {
    logStep('Koneksi Database', 'error', $e->getMessage());
    goto output;
}

// =====================================================
// DISABLE FOREIGN KEY CHECKS
// =====================================================
$db->exec('SET FOREIGN_KEY_CHECKS = 0');

// =====================================================
// STEP 1: CORE TABLES
// =====================================================

// --- users ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `phone` VARCHAR(20) DEFAULT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'user', 'member') NOT NULL DEFAULT 'user',
            `referral_code` VARCHAR(20) UNIQUE DEFAULT NULL,
            `referred_by` INT UNSIGNED DEFAULT NULL,
            `gc_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_gc_earned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_gc_withdrawn` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `avatar` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_role` (`role`),
            INDEX `idx_is_active` (`is_active`),
            INDEX `idx_referral_code` (`referral_code`),
            INDEX `idx_referred_by` (`referred_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: users', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: users', 'error', $e->getMessage());
}

// --- categories ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `icon` VARCHAR(50) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_slug` (`slug`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: categories', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: categories', 'error', $e->getMessage());
}

// --- products ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `products` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `category_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gc_bonus` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `stock` INT NOT NULL DEFAULT 0,
            `items_per_unit` INT NOT NULL DEFAULT 1 COMMENT 'Number of items per unit/pack',
            `image` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
            INDEX `idx_category` (`category_id`),
            INDEX `idx_is_active` (`is_active`),
            INDEX `idx_is_featured` (`is_featured`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: products', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: products', 'error', $e->getMessage());
}

// products: ensure items_per_unit exists (migration)
try {
    if (!columnExists($db, 'products', 'items_per_unit')) {
        $db->exec("ALTER TABLE `products` ADD COLUMN `items_per_unit` INT NOT NULL DEFAULT 1 COMMENT 'Number of items per unit/pack' AFTER `stock`");
        logStep('ALTER: products.items_per_unit', 'success', 'Column added');
    } else {
        logStep('ALTER: products.items_per_unit', 'warning', 'Column already exists, skipped');
    }
} catch (PDOException $e) {
    logStep('ALTER: products.items_per_unit', 'error', $e->getMessage());
}

// --- cart ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `cart` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_user_product` (`user_id`, `product_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: cart', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: cart', 'error', $e->getMessage());
}

// --- transactions ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(50) NOT NULL UNIQUE,
            `user_id` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gc_earned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('pending','verified','completed','cancelled') NOT NULL DEFAULT 'pending',
            `payment_method` VARCHAR(50) DEFAULT NULL,
            `payment_proof` VARCHAR(255) DEFAULT NULL,
            `shipping_address` TEXT DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            INDEX `idx_order_number` (`order_number`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_product` (`product_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: transactions', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: transactions', 'error', $e->getMessage());
}

// --- withdrawals ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `withdrawals` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `gc_amount` DECIMAL(15,2) NOT NULL,
            `gc_price` DECIMAL(15,2) NOT NULL,
            `rupiah_amount` DECIMAL(15,2) NOT NULL,
            `admin_fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `final_amount` DECIMAL(15,2) NOT NULL,
            `bank_name` VARCHAR(100) NOT NULL,
            `bank_account_number` VARCHAR(50) NOT NULL,
            `bank_account_name` VARCHAR(100) NOT NULL,
            `status` ENUM('pending','approved','processing','completed','rejected') NOT NULL DEFAULT 'pending',
            `notes` TEXT DEFAULT NULL,
            `admin_notes` TEXT DEFAULT NULL,
            `rejection_reason` TEXT DEFAULT NULL,
            `processed_by` INT UNSIGNED DEFAULT NULL,
            `processed_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: withdrawals', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: withdrawals', 'error', $e->getMessage());
}

// withdrawals: ensure notes column exists (migration)
try {
    if (!columnExists($db, 'withdrawals', 'notes')) {
        $db->exec('ALTER TABLE `withdrawals` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `status`');
        logStep('ALTER: withdrawals.notes', 'success', 'Column added');
    } else {
        logStep('ALTER: withdrawals.notes', 'warning', 'Already exists, skipped');
    }
} catch (PDOException $e) {
    logStep('ALTER: withdrawals.notes', 'error', $e->getMessage());
}

// withdrawals: ensure admin_notes column exists (migration)
try {
    if (!columnExists($db, 'withdrawals', 'admin_notes')) {
        $db->exec('ALTER TABLE `withdrawals` ADD COLUMN `admin_notes` TEXT DEFAULT NULL AFTER `notes`');
        logStep('ALTER: withdrawals.admin_notes', 'success', 'Column added');
    } else {
        logStep('ALTER: withdrawals.admin_notes', 'warning', 'Already exists, skipped');
    }
} catch (PDOException $e) {
    logStep('ALTER: withdrawals.admin_notes', 'error', $e->getMessage());
}

// --- configs ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `configs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `config_key` VARCHAR(100) NOT NULL UNIQUE,
            `config_value` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_key` (`config_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: configs', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: configs', 'error', $e->getMessage());
}

// --- membership_requests ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `membership_requests` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `request_type` ENUM('upgrade','downgrade') NOT NULL DEFAULT 'upgrade',
            `reason` TEXT DEFAULT NULL,
            `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `admin_notes` TEXT DEFAULT NULL,
            `processed_by` INT UNSIGNED DEFAULT NULL,
            `processed_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_type` (`request_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: membership_requests', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: membership_requests', 'error', $e->getMessage());
}

// --- referral_history ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `referral_history` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `referrer_id` INT UNSIGNED NOT NULL,
            `referred_id` INT UNSIGNED NOT NULL,
            `type` ENUM('signup_bonus','commission') NOT NULL,
            `gc_amount` DECIMAL(15,2) NOT NULL,
            `transaction_id` INT UNSIGNED DEFAULT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL,
            INDEX `idx_referrer` (`referrer_id`),
            INDEX `idx_referred` (`referred_id`),
            INDEX `idx_type` (`type`),
            INDEX `idx_transaction` (`transaction_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: referral_history', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: referral_history', 'error', $e->getMessage());
}

// =====================================================
// STEP 2: VIDEO SYSTEM TABLES
// =====================================================

// --- video_categories ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `video_categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `icon` VARCHAR(50) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_slug` (`slug`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: video_categories', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: video_categories', 'error', $e->getMessage());
}

// --- videos ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `videos` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `video_url` VARCHAR(255) NOT NULL,
            `thumbnail_url` VARCHAR(255) DEFAULT NULL,
            `duration` INT DEFAULT NULL,
            `gc_reward` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `views` INT NOT NULL DEFAULT 0,
            `likes` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `video_categories`(`id`) ON DELETE SET NULL,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_category` (`category_id`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: videos', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: videos', 'error', $e->getMessage());
}

// videos: ensure user_id column exists (legacy migration)
try {
    if (!columnExists($db, 'videos', 'user_id')) {
        $db->exec('ALTER TABLE `videos` ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`');
        logStep('ALTER: videos.user_id', 'success', 'Column added');
    } else {
        logStep('ALTER: videos.user_id', 'warning', 'Already exists, skipped');
    }
} catch (PDOException $e) {
    logStep('ALTER: videos.user_id', 'error', $e->getMessage());
}

// videos: ensure likes column exists (legacy migration)
try {
    if (!columnExists($db, 'videos', 'likes')) {
        $db->exec('ALTER TABLE `videos` ADD COLUMN `likes` INT NOT NULL DEFAULT 0 AFTER `views`');
        logStep('ALTER: videos.likes', 'success', 'Column added');
    } else {
        logStep('ALTER: videos.likes', 'warning', 'Already exists, skipped');
    }
} catch (PDOException $e) {
    logStep('ALTER: videos.likes', 'error', $e->getMessage());
}

// --- video_rewards ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `video_rewards` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `video_id` INT UNSIGNED NOT NULL,
            `gc_earned` DECIMAL(15,2) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_user_video_date` (`user_id`, `video_id`, `created_at`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_video` (`video_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: video_rewards', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: video_rewards', 'error', $e->getMessage());
}

// --- video_comments ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `video_comments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `video_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `comment` TEXT NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_video` (`video_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: video_comments', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: video_comments', 'error', $e->getMessage());
}

// --- video_likes ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `video_likes` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `video_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_video_user_like` (`video_id`, `user_id`),
            INDEX `idx_video` (`video_id`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: video_likes', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: video_likes', 'error', $e->getMessage());
}

// --- video_views_log ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `video_views_log` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `video_id` INT UNSIGNED NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
            INDEX `idx_video_ip` (`video_id`, `ip_address`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: video_views_log', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: video_views_log', 'error', $e->getMessage());
}

// --- token_blacklist ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `token_blacklist` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `token_hash` VARCHAR(64) NOT NULL UNIQUE,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_token_hash` (`token_hash`),
            INDEX `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: token_blacklist', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: token_blacklist', 'error', $e->getMessage());
}

// =====================================================
// STEP 3: MINING SYSTEM V2
// =====================================================

// --- mining_plans ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `mining_plans` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `plan_id` VARCHAR(20) NOT NULL COMMENT 'abonus, bbonus, cbonus, vbonus, vipbonus',
            `plan_name` VARCHAR(100) NOT NULL,
            `start_date` DATETIME NOT NULL,
            `end_date` DATETIME NOT NULL,
            `gc_per_session` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            `sessions_per_day` TINYINT NOT NULL DEFAULT 1,
            `cost_gc` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            `deposit_gc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `profit_gc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `total_return_gc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `daily_claim_gc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `days_claimed` INT NOT NULL DEFAULT 0,
            `total_claimed_gc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_mining_plans_user` (`user_id`),
            INDEX `idx_mining_plans_active` (`user_id`, `is_active`, `end_date`),
            INDEX `idx_mining_plans_plan_id` (`plan_id`),
            CONSTRAINT `fk_mining_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: mining_plans', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: mining_plans', 'error', $e->getMessage());
}

// mining_plans: ensure V2 columns exist (migration from V1)
$miningV2Cols = [
    'deposit_gc' => "DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'GC deposited' AFTER `cost_gc`",
    'profit_gc' => "DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Total profit' AFTER `deposit_gc`",
    'total_return_gc' => "DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Total return' AFTER `profit_gc`",
    'daily_claim_gc' => "DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'GC per daily claim' AFTER `total_return_gc`",
    'days_claimed' => "INT NOT NULL DEFAULT 0 COMMENT 'Days claimed' AFTER `daily_claim_gc`",
    'total_claimed_gc' => "DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Total GC claimed' AFTER `days_claimed`",
];
foreach ($miningV2Cols as $col => $definition) {
    try {
        if (!columnExists($db, 'mining_plans', $col)) {
            $db->exec("ALTER TABLE `mining_plans` ADD COLUMN `{$col}` {$definition}");
            logStep("ALTER: mining_plans.{$col}", 'success', 'Column added');
        } else {
            logStep("ALTER: mining_plans.{$col}", 'warning', 'Already exists, skipped');
        }
    } catch (PDOException $e) {
        logStep("ALTER: mining_plans.{$col}", 'error', $e->getMessage());
    }
}

// --- daily_bonus_claims ---
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `daily_bonus_claims` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `gc_amount` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            `role_at_claim` VARCHAR(20) NOT NULL DEFAULT 'user',
            `claimed_at` DATE NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_daily_bonus_user_date` (`user_id`, `claimed_at`),
            INDEX `idx_daily_bonus_user` (`user_id`),
            INDEX `idx_daily_bonus_date` (`claimed_at`),
            CONSTRAINT `fk_daily_bonus_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    logStep('Table: daily_bonus_claims', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: daily_bonus_claims', 'error', $e->getMessage());
}

// --- mining_daily_claims ---
try {
    $db->exec('
        CREATE TABLE IF NOT EXISTS `mining_daily_claims` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `plan_record_id` INT UNSIGNED NOT NULL,
            `plan_id` VARCHAR(20) NOT NULL,
            `gc_earned` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `day_number` INT NOT NULL DEFAULT 1,
            `ad_watched` TINYINT(1) NOT NULL DEFAULT 0,
            `claimed_at` DATE NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mining_daily_user_plan_date` (`user_id`, `plan_record_id`, `claimed_at`),
            INDEX `idx_mining_daily_user` (`user_id`),
            INDEX `idx_mining_daily_plan` (`plan_record_id`),
            INDEX `idx_mining_daily_date` (`claimed_at`),
            CONSTRAINT `fk_mining_daily_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_mining_daily_plan` FOREIGN KEY (`plan_record_id`) REFERENCES `mining_plans`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    logStep('Table: mining_daily_claims', 'success', 'Created / already exists');
} catch (PDOException $e) {
    logStep('Table: mining_daily_claims', 'error', $e->getMessage());
}

// =====================================================
// STEP 4: RE-ENABLE FOREIGN KEY CHECKS
// =====================================================
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

// =====================================================
// STEP 5: DEFAULT CONFIGURATIONS (ON DUPLICATE KEY UPDATE)
// =====================================================
$configDefaults = [
    // App
    'app_name' => 'GoldSmart',
    'app_version' => '1.1.8',
    'app_currency' => 'IDR',
    // GC
    'gc_name' => 'Gold Cash',
    'gc_symbol' => 'GC',
    'gc_price' => '100',
    'gc_per_purchase' => '1',
    // Withdrawal
    'withdrawal_admin_fee' => '7000',
    'withdrawal_fee' => '1000',
    'withdrawal_fee_type' => 'fixed',
    'withdrawal_fee_percent' => '5',
    'withdrawal_min_amount' => '1',
    'min_withdrawal' => '25',
    'withdrawal_processing_time' => '1-3 hari kerja',
    // Referral
    'referral_bonus' => '0.1',
    'referral_bonus_gc' => '5',
    'commission_bonus' => '0.5',
    'signup_bonus_gc' => '10',
    // Video
    'video_reward_daily_limit' => '10',
    // Ads - flags
    'ads_enabled' => '1',
    'ads_show_on_app_open' => '1',
    'ads_show_before_withdrawal' => '1',
    'ads_show_before_purchase' => '1',
    'ads_show_before_bonus' => '1',
    // Ads - unit IDs
    'ad_banner_1' => 'ca-app-pub-4311953594369559/7077679972',
    'ad_banner_2' => 'ca-app-pub-4311953594369559/5249592983',
    'ad_rewarded' => 'ca-app-pub-4311953594369559/7035756873',
    'ad_interstitial' => 'ca-app-pub-4311953594369559/5178274943',
    'ad_native' => 'ca-app-pub-4311953594369559/3966207579',
    'ad_interstitial_bonus' => 'ca-app-pub-4311953594369559/9174904861',
    'ad_rewarded_bonus_1' => 'ca-app-pub-4311953594369559/9907023292',
    'ad_rewarded_bonus_2' => 'ca-app-pub-4311953594369559/8593941627',
    'ad_rewarded_bonus_3' => 'ca-app-pub-4311953594369559/6023618604',
    // System
    'maintenance_mode' => '0',
];

$configStmt = $db->prepare('INSERT INTO `configs` (`config_key`, `config_value`, `created_at`, `updated_at`) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`), `updated_at` = NOW()');
$configInserted = 0;
foreach ($configDefaults as $key => $value) {
    try {
        $configStmt->execute([$key, $value]);
        $configInserted++;
    } catch (PDOException $e) {
        logStep("Config: {$key}", 'error', $e->getMessage());
    }
}
logStep('Default Configurations', 'success', "{$configInserted} config values synced");

// =====================================================
// STEP 6: DEFAULT USERS (safe insert)
// =====================================================
$defaultPassword = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';  // "password"

$userStmt = $db->prepare('INSERT INTO `users` (`name`,`email`,`phone`,`password`,`role`,`referral_code`,`gc_balance`,`is_active`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)');

$defaultUsers = [
    ['Administrator', 'admin@goldcash.com', '081234567890', $defaultPassword, 'admin', 'ADMIN001', 0],
    ['User Demo', 'user@demo.com', '081234567891', $defaultPassword, 'user', 'USER0001', 50],
    ['Member Demo', 'member@demo.com', '081234567892', $defaultPassword, 'member', 'MEMBER01', 100],
];

$usersInserted = 0;
foreach ($defaultUsers as $u) {
    try {
        $userStmt->execute($u);
        $usersInserted++;
    } catch (PDOException $e) {
        logStep("User: {$u[1]}", 'error', $e->getMessage());
    }
}
logStep('Default Users', 'success', "{$usersInserted} users synced (password: password)");

// =====================================================
// STEP 7: DEFAULT CATEGORIES
// =====================================================
$catStmt = $db->prepare('INSERT INTO `categories` (`name`,`slug`,`icon`,`is_active`) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)');
$productCategories = [
    ['Rokok', 'rokok', ''],
    ['Minuman', 'minuman', ''],
    ['Makanan', 'makanan', ''],
    ['Elektronik', 'elektronik', ''],
    ['Lainnya', 'lainnya', ''],
];
foreach ($productCategories as $c) {
    try {
        $catStmt->execute($c);
    } catch (PDOException $e) {
    }
}
logStep('Product Categories', 'success', count($productCategories) . ' categories synced');

$vcatStmt = $db->prepare('INSERT INTO `video_categories` (`name`,`slug`,`icon`,`is_active`) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)');
$videoCategories = [
    ['Hiburan', 'hiburan', ''],
    ['Tutorial', 'tutorial', ''],
    ['Edukasi', 'edukasi', ''],
    ['Berita', 'berita', ''],
    ['Lainnya', 'lainnya', ''],
];
foreach ($videoCategories as $c) {
    try {
        $vcatStmt->execute($c);
    } catch (PDOException $e) {
    }
}
logStep('Video Categories', 'success', count($videoCategories) . ' categories synced');

// =====================================================
// STEP 8: TOKEN BLACKLIST CLEANUP EVENT
// =====================================================
try {
    $db->exec('CREATE EVENT IF NOT EXISTS `cleanup_token_blacklist` ON SCHEDULE EVERY 1 DAY DO DELETE FROM `token_blacklist` WHERE `expires_at` < NOW()');
    logStep('Event: cleanup_token_blacklist', 'success', 'Cleanup event created');
} catch (PDOException $e) {
    logStep('Event: cleanup_token_blacklist', 'warning', 'Skipped (event scheduler may be disabled): ' . $e->getMessage());
}

// =====================================================
// STEP 9: VERIFICATION
// =====================================================
try {
    $stmt = $db->query('SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    $tableCount = $stmt->fetch()['cnt'];
    logStep('Verifikasi', 'info', "Total {$tableCount} tabel dalam database");
} catch (PDOException $e) {
    logStep('Verifikasi', 'error', $e->getMessage());
}

// Expected tables
$expectedTables = [
    'users',
    'categories',
    'products',
    'cart',
    'transactions',
    'withdrawals',
    'configs',
    'membership_requests',
    'referral_history',
    'video_categories',
    'videos',
    'video_rewards',
    'video_comments',
    'video_likes',
    'video_views_log',
    'token_blacklist',
    'mining_plans',
    'daily_bonus_claims',
    'mining_daily_claims',
];
$missingTables = [];
foreach ($expectedTables as $tbl) {
    if (!tableExists($db, $tbl)) {
        $missingTables[] = $tbl;
    }
}
if (empty($missingTables)) {
    logStep('Table Check', 'success', 'Semua ' . count($expectedTables) . ' tabel tersedia');
} else {
    logStep('Table Check', 'error', 'Tabel hilang: ' . implode(', ', $missingTables));
}

// =====================================================
// OUTPUT RESULTS
// =====================================================
output:
foreach ($results as $r) {
    $icon = match ($r['status']) {
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        default => '•',
    };
    echo "<div class=\"step {$r['status']}\">";
    echo "<div class=\"step-title\">{$icon} {$r['title']}</div>";
    if ($r['detail'])
        echo "<div class=\"step-detail\">{$r['detail']}</div>";
    echo "</div>\n";
}

$hasErrors = $errorCount > 0;
?>

<div class="summary <?= $hasErrors ? 'has-errors' : '' ?>">
    <h2><?= $hasErrors ? '⚠️ Migrasi Selesai (Ada Error)' : '✅ Migrasi Berhasil!' ?></h2>
    <div>
        <div class="stat"><div class="num"><?= $successCount ?></div><div class="label">Sukses</div></div>
        <div class="stat"><div class="num"><?= $skipCount ?></div><div class="label">Dilewati</div></div>
        <div class="stat"><div class="num"><?= $errorCount ?></div><div class="label">Error</div></div>
        <div class="stat"><div class="num"><?= count($results) ?></div><div class="label">Total Step</div></div>
    </div>
</div>

<div class="warning-box">
    <h3>⚠️ PENTING - Hapus File Ini!</h3>
    <p>Setelah migrasi berhasil, <strong>segera hapus file <code>migration.php</code></strong> dari server untuk alasan keamanan.</p>
    <p style="margin-top:8px; color:#888;">File ini mengandung kredensial database dan tidak boleh tersedia di production.</p>
</div>

</div>
</body>
</html>
