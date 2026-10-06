-- Migration: 007_admin_role.sql
-- Adds the 'admin' role to users.role and seeds a default admin account.
-- Admins can add, edit and delete officer and member accounts
-- (see admin/users.php, admin/user_edit.php, admin/user_delete.php).
--
-- Run this against an EXISTING bambosa_db database. If setting up a brand
-- new database instead, just import sql/bambosa_db.sql, which already
-- includes this change.
--
-- Safe to re-run: MODIFY COLUMN is idempotent and the seed insert is
-- skipped if the admin email already exists.

USE bambosa_db;

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','officer','member') NOT NULL DEFAULT 'member';

-- Default password: 12345678  (CHANGE IT after first login via My Profile)
INSERT INTO users (full_name, email, password_hash, role, status)
SELECT 'Default Admin',
       'admin@bambosa.local',
       '$2y$10$74S9DMg5zKVTQTI.5ApgdOwHpAdhwuoNVG4c/h3uWKtkQiNoIUSJi',
       'admin',
       'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@bambosa.local');
