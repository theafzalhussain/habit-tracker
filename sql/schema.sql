-- ============================================================================
-- Hector Habit — schema + seed (MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4)
--   mysql -u USER -p DBNAME < sql/schema.sql
--   php sql/seed.php            (writes the first user's password hash)
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(255) NULL DEFAULT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  theme ENUM('ember','ink','moss','paper') NOT NULL DEFAULT 'ember',
  trial_ends_at DATE NOT NULL,
  paid_until DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS habits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(191) NOT NULL,
  xp INT NOT NULL DEFAULT 5,
  freq ENUM('every_day','days_of_week','every_x_days') NOT NULL,
  interval_days INT NULL,
  start_date DATE NOT NULL,
  mon TINYINT(1) NOT NULL DEFAULT 0,
  tue TINYINT(1) NOT NULL DEFAULT 0,
  wed TINYINT(1) NOT NULL DEFAULT 0,
  thu TINYINT(1) NOT NULL DEFAULT 0,
  fri TINYINT(1) NOT NULL DEFAULT 0,
  sat TINYINT(1) NOT NULL DEFAULT 0,
  sun TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  archived_at DATE NULL DEFAULT NULL,
  UNIQUE KEY uq_user_habit (user_id, name),
  KEY idx_habits_user_active_sort (user_id, archived_at, sort_order),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  habit_id INT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  xp INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_log (user_id, habit_id, log_date),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (habit_id) REFERENCES habits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  percent_off INT NOT NULL,
  uses_left INT NOT NULL,
  affiliate_name VARCHAR(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  razorpay_order_id VARCHAR(64) NOT NULL,
  razorpay_payment_id VARCHAR(64) NULL,
  amount_paise INT NOT NULL,
  coupon_code VARCHAR(32) NULL,
  status ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
  paid_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed coupon (affiliate programme): HECTOR20 = 20% off, 50 uses
-- ₹199.00 -> ₹159.20 (19900 -> 15920 paise)
-- ---------------------------------------------------------------------------
INSERT INTO coupons (code, percent_off, uses_left, affiliate_name)
VALUES ('HECTOR20', 20, 50, 'founder')
ON DUPLICATE KEY UPDATE percent_off = VALUES(percent_off);
