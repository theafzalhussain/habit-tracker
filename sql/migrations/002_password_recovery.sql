-- Forgot-password support (recovery keys).
-- Run this once on a database created before this release. Fresh installs
-- already get these columns from schema.sql, so do not run it after schema.sql.
--   mysql -u USER -p hector_habit < sql/migrations/002_password_recovery.sql
--   phpMyAdmin: select the database -> Import -> this file.
SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN recovery_hash VARCHAR(255) NULL DEFAULT NULL AFTER password_hash,
  ADD COLUMN recovery_created_at DATETIME NULL DEFAULT NULL AFTER recovery_hash;
