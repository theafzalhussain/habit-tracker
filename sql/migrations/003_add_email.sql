-- Add email column to users table for signup functionality.
SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL AFTER username,
  ADD UNIQUE KEY uq_users_email (email);

