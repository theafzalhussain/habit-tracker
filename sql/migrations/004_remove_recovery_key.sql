-- Remove recovery key support: users reset password via username or email.
SET NAMES utf8mb4;

ALTER TABLE users
  DROP COLUMN IF EXISTS recovery_hash,
  DROP COLUMN IF EXISTS recovery_created_at;

