-- Migration: 004_schedule_groups.sql
-- Adds rotation_schedules.group_id, which ties together several schedule
-- rows created as "the same water-release window, applied to multiple
-- sectors at once" — see officer/schedules.php's "Also create this same
-- schedule for other sectors" option. Editing one schedule in a group can
-- then update every sibling row's start/end/notes together.
--
-- NULL means a schedule entry stands alone, same as every schedule
-- created before this feature existed — nothing changes for those.
--
-- Run this against an EXISTING bambosa_db database. If setting up a brand
-- new database instead, just import sql/bambosa_db.sql, which already
-- includes this schema.
--
-- Safe to re-run: uses a column-existence check before altering, since
-- ADD COLUMN has no native IF NOT EXISTS in MySQL.

USE bambosa_db;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'rotation_schedules' AND column_name = 'group_id'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE rotation_schedules ADD COLUMN group_id INT DEFAULT NULL AFTER notes',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- An index helps the "find sibling rows in the same group" lookup used
-- when loading a schedule for editing, and when propagating an edit to
-- every row sharing a group_id.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'rotation_schedules' AND index_name = 'idx_rotation_schedules_group_id'
);
SET @ddl2 = IF(@idx_exists = 0,
    'CREATE INDEX idx_rotation_schedules_group_id ON rotation_schedules (group_id)',
    'SELECT 1'
);
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
