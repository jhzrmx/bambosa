-- Migration: 008_login_log_status.sql
-- Lets login_logs record FAILED login attempts as well as successful ones,
-- so an admin can spot suspicious activity (e.g. repeated wrong passwords,
-- or someone guessing email addresses). See login.php, includes/login_logs.php
-- and admin/login_logs.php.
--
-- Adds to login_logs:
--   status          VARCHAR(100) NOT NULL DEFAULT 'Success'
--                   e.g. 'Success', 'Wrong password, attempt #2',
--                   'Unknown email, attempt #1',
--                   'Correct password, account pending',
--                   'Correct password, account inactive'
--   email_attempted VARCHAR(150) NULL
--                   only filled when the email typed in matches NO account
--                   (user_id is NULL then); known accounts use user_id.
-- and makes user_id nullable (an unknown email has no user to point to).
-- Existing rows automatically become status 'Success'.
--
-- Run this against an EXISTING bambosa_db database. If setting up a brand
-- new database instead, just import sql/bambosa_db.sql, which already
-- includes this change.
--
-- Safe to re-run: existence checks before each change; no rows are
-- modified or deleted.

USE bambosa_db;

-- user_id may now be NULL (MODIFY keeps the foreign key and ON DELETE CASCADE)
ALTER TABLE login_logs MODIFY COLUMN user_id INT NULL;

SET @col1_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'login_logs' AND column_name = 'status'
);
SET @ddl1 = IF(@col1_exists = 0,
    'ALTER TABLE login_logs ADD COLUMN status VARCHAR(100) NOT NULL DEFAULT ''Success''',
    'SELECT 1'
);
PREPARE stmt1 FROM @ddl1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @col2_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'login_logs' AND column_name = 'email_attempted'
);
SET @ddl2 = IF(@col2_exists = 0,
    'ALTER TABLE login_logs ADD COLUMN email_attempted VARCHAR(150) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
