-- =====================================================
-- GoldSmart Database - Complete Schema
-- Database: golp4259_goldcash
-- Version: 3.0 (Consolidated: 2026-01-15)
-- 
-- Tables: 19 (users, categories, products, cart, transactions,
--   withdrawals, configs, membership_requests, referral_history,
--   video_categories, videos, video_rewards, video_comments,
--   video_likes, video_views_log, token_blacklist,
--   mining_plans, daily_bonus_claims, mining_daily_claims)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- =====================================================
-- PART 1: CORE TABLES
-- =====================================================
-- TABLE: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(20) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user', 'member') NOT NULL DEFAULT 'user',
    `referral_code` VARCHAR(20) UNIQUE DEFAULT NULL,
    `referred_by` INT UNSIGNED DEFAULT NULL,
    `gc_balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `total_gc_earned` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `total_gc_withdrawn` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_referral_code` (`referral_code`),
    INDEX `idx_referred_by` (`referred_by`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Self-referencing foreign key
ALTER TABLE `users`
ADD FOREIGN KEY (`referred_by`) REFERENCES `users`(`id`) ON DELETE
SET NULL;
-- TABLE: categories
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `gc_bonus` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `stock` INT NOT NULL DEFAULT 0,
    `items_per_unit` INT NOT NULL DEFAULT 1 COMMENT 'Number of items per unit/pack',
    `image` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE
    SET NULL,
        INDEX `idx_category` (`category_id`),
        INDEX `idx_is_active` (`is_active`),
        INDEX `idx_is_featured` (`is_featured`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: cart
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: transactions
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `gc_earned` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'verified', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: withdrawals
CREATE TABLE IF NOT EXISTS `withdrawals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `gc_amount` DECIMAL(15, 2) NOT NULL,
    `gc_price` DECIMAL(15, 2) NOT NULL,
    `rupiah_amount` DECIMAL(15, 2) NOT NULL,
    `admin_fee` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `final_amount` DECIMAL(15, 2) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_account_number` VARCHAR(50) NOT NULL,
    `bank_account_name` VARCHAR(100) NOT NULL,
    `status` ENUM(
        'pending',
        'approved',
        'processing',
        'completed',
        'rejected'
    ) NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `admin_notes` TEXT DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `processed_by` INT UNSIGNED DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE
    SET NULL,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_status` (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: configs
CREATE TABLE IF NOT EXISTS `configs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_key` VARCHAR(100) NOT NULL UNIQUE,
    `config_value` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`config_key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: membership_requests
CREATE TABLE IF NOT EXISTS `membership_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `request_type` ENUM('upgrade', 'downgrade') NOT NULL DEFAULT 'upgrade',
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `processed_by` INT UNSIGNED DEFAULT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE
    SET NULL,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_type` (`request_type`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- PART 2: REFERRAL SYSTEM
-- =====================================================
-- TABLE: referral_history
CREATE TABLE IF NOT EXISTS `referral_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `referrer_id` INT UNSIGNED NOT NULL COMMENT 'User yang mendapat bonus',
    `referred_id` INT UNSIGNED NOT NULL COMMENT 'User yang direferensikan',
    `type` ENUM('signup_bonus', 'commission') NOT NULL COMMENT 'Tipe bonus',
    `gc_amount` DECIMAL(15, 2) NOT NULL COMMENT 'Jumlah GC yang didapat',
    `transaction_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID transaksi (untuk commission)',
    `description` VARCHAR(255) DEFAULT NULL COMMENT 'Deskripsi bonus',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE
    SET NULL,
        INDEX `idx_referrer` (`referrer_id`),
        INDEX `idx_referred` (`referred_id`),
        INDEX `idx_type` (`type`),
        INDEX `idx_transaction` (`transaction_id`),
        INDEX `idx_created_at` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- PART 3: VIDEO SYSTEM
-- =====================================================
-- TABLE: video_categories
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: videos
CREATE TABLE IF NOT EXISTS `videos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `video_url` VARCHAR(255) NOT NULL,
    `thumbnail_url` VARCHAR(255) DEFAULT NULL,
    `duration` INT DEFAULT NULL,
    `gc_reward` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `views` INT NOT NULL DEFAULT 0,
    `likes` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `video_categories`(`id`) ON DELETE
    SET NULL,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_category` (`category_id`),
        INDEX `idx_is_active` (`is_active`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: video_rewards
CREATE TABLE IF NOT EXISTS `video_rewards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `video_id` INT UNSIGNED NOT NULL,
    `gc_earned` DECIMAL(15, 2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_video_date` (`user_id`, `video_id`, `created_at`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_video` (`video_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: video_comments
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: video_likes
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: video_views_log
CREATE TABLE IF NOT EXISTS `video_views_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `video_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
    INDEX `idx_video_ip` (`video_id`, `ip_address`),
    INDEX `idx_created` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- TABLE: token_blacklist
CREATE TABLE IF NOT EXISTS `token_blacklist` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- PART 4: MINING SYSTEM (V2)
-- =====================================================
-- TABLE: mining_plans
CREATE TABLE IF NOT EXISTS `mining_plans` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `plan_id` VARCHAR(20) NOT NULL COMMENT 'abonus, bbonus, cbonus, vbonus, vipbonus',
    `plan_name` VARCHAR(100) NOT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `gc_per_session` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    `sessions_per_day` TINYINT NOT NULL DEFAULT 1,
    `cost_gc` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    `deposit_gc` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'GC deposited for this package',
    `profit_gc` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Total profit over duration',
    `total_return_gc` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Total return (deposit + profit)',
    `daily_claim_gc` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'GC per daily claim',
    `days_claimed` INT NOT NULL DEFAULT 0 COMMENT 'Number of days already claimed',
    `total_claimed_gc` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Total GC already claimed',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_mining_plans_user` (`user_id`),
    INDEX `idx_mining_plans_active` (`user_id`, `is_active`, `end_date`),
    INDEX `idx_mining_plans_plan_id` (`plan_id`),
    CONSTRAINT `fk_mining_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Mining packages activated by users';
-- TABLE: daily_bonus_claims
CREATE TABLE IF NOT EXISTS `daily_bonus_claims` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `gc_amount` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    `role_at_claim` VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'user or member',
    `claimed_at` DATE NOT NULL COMMENT 'Date of claim (1x per day)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_daily_bonus_user_date` (`user_id`, `claimed_at`),
    INDEX `idx_daily_bonus_user` (`user_id`),
    INDEX `idx_daily_bonus_date` (`claimed_at`),
    CONSTRAINT `fk_daily_bonus_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Daily login bonus claims';
-- TABLE: mining_daily_claims
CREATE TABLE IF NOT EXISTS `mining_daily_claims` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `plan_record_id` INT UNSIGNED NOT NULL COMMENT 'Reference to mining_plans.id',
    `plan_id` VARCHAR(20) NOT NULL,
    `gc_earned` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    `day_number` INT NOT NULL DEFAULT 1 COMMENT 'Which day of the 60-day cycle',
    `ad_watched` TINYINT(1) NOT NULL DEFAULT 0,
    `claimed_at` DATE NOT NULL COMMENT 'Date of claim (1x per day)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_mining_daily_user_plan_date` (`user_id`, `plan_record_id`, `claimed_at`),
    INDEX `idx_mining_daily_user` (`user_id`),
    INDEX `idx_mining_daily_plan` (`plan_record_id`),
    INDEX `idx_mining_daily_date` (`claimed_at`),
    CONSTRAINT `fk_mining_daily_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mining_daily_plan` FOREIGN KEY (`plan_record_id`) REFERENCES `mining_plans`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Daily mining package claims';
SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================
-- PART 5: DEFAULT CONFIGURATIONS (ALL)
-- =====================================================
INSERT INTO `configs` (
        `config_key`,
        `config_value`,
        `created_at`,
        `updated_at`
    )
VALUES -- App Settings
    ('app_name', 'GoldSmart', NOW(), NOW()),
    ('app_version', '1.1.8', NOW(), NOW()),
    ('app_currency', 'IDR', NOW(), NOW()),
    -- GC Settings
    ('gc_name', 'Gold Cash', NOW(), NOW()),
    ('gc_symbol', 'GC', NOW(), NOW()),
    ('gc_price', '100', NOW(), NOW()),
    ('gc_per_purchase', '1', NOW(), NOW()),
    -- Withdrawal Settings
    ('withdrawal_admin_fee', '7000', NOW(), NOW()),
    ('withdrawal_fee', '1000', NOW(), NOW()),
    ('withdrawal_fee_type', 'fixed', NOW(), NOW()),
    ('withdrawal_fee_percent', '5', NOW(), NOW()),
    ('withdrawal_min_amount', '1', NOW(), NOW()),
    ('min_withdrawal', '25', NOW(), NOW()),
    (
        'withdrawal_processing_time',
        '1-3 hari kerja',
        NOW(),
        NOW()
    ),
    -- Referral Settings
    ('referral_bonus', '0.1', NOW(), NOW()),
    ('referral_bonus_gc', '5', NOW(), NOW()),
    ('commission_bonus', '0.5', NOW(), NOW()),
    ('signup_bonus_gc', '10', NOW(), NOW()),
    -- Video Settings
    ('video_reward_daily_limit', '10', NOW(), NOW()),
    -- Ads - Behavior Flags
    ('ads_enabled', '1', NOW(), NOW()),
    ('ads_show_on_app_open', '1', NOW(), NOW()),
    ('ads_show_before_withdrawal', '1', NOW(), NOW()),
    ('ads_show_before_purchase', '1', NOW(), NOW()),
    ('ads_show_before_bonus', '1', NOW(), NOW()),
    -- Ads - Unit IDs (Banner)
    (
        'ad_banner_1',
        'ca-app-pub-4311953594369559/7077679972',
        NOW(),
        NOW()
    ),
    (
        'ad_banner_2',
        'ca-app-pub-4311953594369559/5249592983',
        NOW(),
        NOW()
    ),
    -- Ads - Unit IDs (Rewarded)
    (
        'ad_rewarded',
        'ca-app-pub-4311953594369559/7035756873',
        NOW(),
        NOW()
    ),
    (
        'ad_rewarded_bonus_1',
        'ca-app-pub-4311953594369559/9907023292',
        NOW(),
        NOW()
    ),
    (
        'ad_rewarded_bonus_2',
        'ca-app-pub-4311953594369559/8593941627',
        NOW(),
        NOW()
    ),
    (
        'ad_rewarded_bonus_3',
        'ca-app-pub-4311953594369559/6023618604',
        NOW(),
        NOW()
    ),
    -- Ads - Unit IDs (Interstitial)
    (
        'ad_interstitial',
        'ca-app-pub-4311953594369559/5178274943',
        NOW(),
        NOW()
    ),
    (
        'ad_interstitial_bonus',
        'ca-app-pub-4311953594369559/9174904861',
        NOW(),
        NOW()
    ),
    -- Ads - Unit IDs (Native)
    (
        'ad_native',
        'ca-app-pub-4311953594369559/3966207579',
        NOW(),
        NOW()
    ),
    -- System
    ('maintenance_mode', '0', NOW(), NOW()) ON DUPLICATE KEY
UPDATE `config_value` =
VALUES(`config_value`),
    `updated_at` = NOW();
-- =====================================================
-- PART 6: DEFAULT USERS
-- =====================================================
-- Admin (password: password)
INSERT INTO `users` (
        `name`,
        `email`,
        `phone`,
        `password`,
        `role`,
        `referral_code`,
        `gc_balance`,
        `is_active`,
        `created_at`,
        `updated_at`
    )
VALUES (
        'Administrator',
        'admin@goldcash.com',
        '081234567890',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',
        'ADMIN001',
        0.00,
        1,
        NOW(),
        NOW()
    ) ON DUPLICATE KEY
UPDATE `name` =
VALUES(`name`);
-- Demo users (password: password)
INSERT INTO `users` (
        `name`,
        `email`,
        `phone`,
        `password`,
        `role`,
        `referral_code`,
        `referred_by`,
        `gc_balance`,
        `is_active`,
        `created_at`,
        `updated_at`
    )
VALUES (
        'User Demo',
        'user@demo.com',
        '081234567891',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'user',
        'USER0001',
        NULL,
        50.00,
        1,
        NOW(),
        NOW()
    ),
    (
        'Member Demo',
        'member@demo.com',
        '081234567892',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'member',
        'MEMBER01',
        NULL,
        100.00,
        1,
        NOW(),
        NOW()
    ) ON DUPLICATE KEY
UPDATE `name` =
VALUES(`name`);
-- =====================================================
-- PART 7: DEFAULT CATEGORIES
-- =====================================================
INSERT INTO `categories` (`name`, `slug`, `icon`, `is_active`)
VALUES ('Rokok', 'rokok', '', 1),
    ('Minuman', 'minuman', '', 1),
    ('Makanan', 'makanan', '', 1),
    ('Elektronik', 'elektronik', '', 1),
    ('Lainnya', 'lainnya', '', 1) ON DUPLICATE KEY
UPDATE `name` =
VALUES(`name`);
INSERT INTO `video_categories` (`name`, `slug`, `icon`, `is_active`)
VALUES ('Hiburan', 'hiburan', '', 1),
    ('Tutorial', 'tutorial', '', 1),
    ('Edukasi', 'edukasi', '', 1),
    ('Berita', 'berita', '', 1),
    ('Lainnya', 'lainnya', '', 1) ON DUPLICATE KEY
UPDATE `name` =
VALUES(`name`);
-- =====================================================
-- PART 8: SCHEDULED EVENTS
-- =====================================================
-- Auto-cleanup expired blacklisted tokens daily
CREATE EVENT IF NOT EXISTS `cleanup_token_blacklist` ON SCHEDULE EVERY 1 DAY DO
DELETE FROM `token_blacklist`
WHERE `expires_at` < NOW();
-- =====================================================
-- VERIFICATION
-- =====================================================
SELECT COUNT(*) AS total_tables
FROM information_schema.tables
WHERE table_schema = DATABASE();
SELECT 'GoldSmart database schema v3.0 - Complete!' AS status;