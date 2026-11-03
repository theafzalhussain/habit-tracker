-- One-time upgrade from Hector Habit v1.2 to the Sheet-parity release.
-- Run this only when upgrading an existing database; fresh installs use schema.sql.
SET NAMES utf8mb4;

ALTER TABLE habits
  ADD COLUMN archived_at DATE NULL DEFAULT NULL AFTER sort_order,
  ADD KEY idx_habits_user_active_sort (user_id, archived_at, sort_order);

-- The supplied Sheet snapshot has this additional unique completion on 3 Aug.
-- INSERT IGNORE makes this safe if the row is already present.
INSERT IGNORE INTO logs (user_id, habit_id, log_date, xp)
SELECT h.user_id, h.id, '2026-08-03', h.xp
  FROM habits h
  JOIN users u ON u.id = h.user_id
 WHERE u.username = 'afzal'
   AND h.name = '🚫 Dopamine Detox (No Porn / Fap)'
   AND h.archived_at IS NULL;
