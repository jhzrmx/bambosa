-- Migration: 005_password_resets.sql
-- Adds the password_resets table backing the "Forgot password" email
-- flow (see includes/password_reset.php, forgot_password.php,
-- reset_password.php). Only the SHA-256 HASH of each reset token is
-- stored, never the raw token — the raw token exists only in the emailed
-- link and briefly in memory while processing a request, same principle
-- as never storing a plaintext password.
--
-- Run this against an EXISTING bambosa_db database. If setting up a brand
-- new database instead, just import sql/bambosa_db.sql, which already
-- includes this table.
--
-- Safe to re-run: uses CREATE TABLE IF NOT EXISTS throughout.

USE bambosa_db;

CREATE TABLE IF NOT EXISTS password_resets (
    reset_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    expires_at   DATETIME NOT NULL,
    used_at      DATETIME DEFAULT NULL,
    requested_ip VARCHAR(45) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @idx1_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'password_resets' AND index_name = 'idx_password_resets_token_hash'
);
SET @ddl1 = IF(@idx1_exists = 0,
    'CREATE INDEX idx_password_resets_token_hash ON password_resets (token_hash)',
    'SELECT 1'
);
PREPARE stmt1 FROM @ddl1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @idx2_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'password_resets' AND index_name = 'idx_password_resets_user_id'
);
SET @ddl2 = IF(@idx2_exists = 0,
    'CREATE INDEX idx_password_resets_user_id ON password_resets (user_id)',
    'SELECT 1'
);
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
