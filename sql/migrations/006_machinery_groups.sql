-- Migration: 006_machinery_groups.sql
-- Adds machinery groups (pooled/interchangeable equipment), a damage
-- condition slider per machine, and lets a reservation target a GROUP
-- instead of one specific machine — see includes/machinery.php's top
-- comment and the README's "Machinery Reservation" section for the full
-- rationale (client feedback: a member shouldn't have to guess a specific
-- unit and get rejected/resubmit just because that ONE unit was busy
-- while a sibling unit in the same group was free the whole time).
--
-- Run this against an EXISTING bambosa_db database. If setting up a brand
-- new database instead, just import sql/bambosa_db.sql, which already
-- includes this schema.
--
-- Existing machinery/reservations are completely unaffected: every
-- machine defaults to group_id = NULL (standalone, exactly as before) and
-- damage_level = 0, and every existing reservation keeps its
-- machinery_id exactly as it was, with group_id = NULL. Nothing about
-- current behavior changes until an officer actually creates a group and
-- assigns machines to it.

USE bambosa_db;

CREATE TABLE IF NOT EXISTS machinery_groups (
    group_id     INT AUTO_INCREMENT PRIMARY KEY,
    group_name   VARCHAR(100) NOT NULL UNIQUE,
    description  VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --- machinery: add group_id + damage_level ---

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'machinery' AND column_name = 'group_id'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE machinery ADD COLUMN group_id INT DEFAULT NULL AFTER machinery_id, ADD FOREIGN KEY (group_id) REFERENCES machinery_groups(group_id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists2 = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'machinery' AND column_name = 'damage_level'
);
SET @ddl2 = IF(@col_exists2 = 0,
    'ALTER TABLE machinery ADD COLUMN damage_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_available',
    'SELECT 1'
);
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- MySQL/MariaDB versions old enough to be missing CHECK constraint support
-- silently ignore CHECK anyway, so this is added defensively but isn't
-- load-bearing for correctness — includes/machinery.php validates the
-- 0-100 range in PHP regardless before it ever reaches the database.
SET @chk_exists = (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'machinery' AND constraint_name = 'chk_machinery_damage_level'
);
SET @ddl3 = IF(@chk_exists = 0,
    'ALTER TABLE machinery ADD CONSTRAINT chk_machinery_damage_level CHECK (damage_level BETWEEN 0 AND 100)',
    'SELECT 1'
);
PREPARE stmt3 FROM @ddl3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- --- machinery_reservations: add group_id, make machinery_id nullable, add rejection_remark ---

SET @col_exists4 = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'machinery_reservations' AND column_name = 'group_id'
);
SET @ddl4 = IF(@col_exists4 = 0,
    'ALTER TABLE machinery_reservations ADD COLUMN group_id INT DEFAULT NULL AFTER machinery_id, ADD FOREIGN KEY (group_id) REFERENCES machinery_groups(group_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt4 FROM @ddl4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

SET @col_exists5 = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'machinery_reservations' AND column_name = 'rejection_remark'
);
SET @ddl5 = IF(@col_exists5 = 0,
    'ALTER TABLE machinery_reservations ADD COLUMN rejection_remark VARCHAR(255) DEFAULT NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt5 FROM @ddl5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

-- machinery_id must become nullable (a group-targeted request has no
-- specific unit until an officer assigns one at approval time). This
-- ALTER is safe to re-run — MODIFY COLUMN is idempotent.
ALTER TABLE machinery_reservations MODIFY COLUMN machinery_id INT DEFAULT NULL;

-- Existing FK on machinery_id was created with ON DELETE CASCADE and
-- NOT NULL originally; making the column nullable doesn't require
-- recreating the FK (MySQL allows NULL through an existing FK column
-- automatically), so no FK changes are needed here.
