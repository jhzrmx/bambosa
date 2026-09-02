-- Migration: 001_isf_billing.sql
-- Adds Epic 3 (ISF Billing) support: billing periods, generated bills per
-- farm lot, and a payment ledger for partial-payment tracking.
--
-- Run this against an EXISTING bambosa_db database that was created from
-- the original sql/bambosa_db.sql. If you are setting up a brand new
-- database instead, just import sql/bambosa_db.sql followed by this file,
-- in that order.
--
-- Safe to re-run: every statement uses IF NOT EXISTS / INSERT IGNORE
-- equivalents where possible.

USE bambosa_db;

-- ---------------------------------------------------------------------
-- Billing periods (cropping seasons / cycles).
-- An officer opens a period with a name, date range, and a cash rate per
-- hectare for that period. Rates are stored per period (not globally)
-- because ISF rates commonly change season to season in practice.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS billing_periods (
    period_id       INT AUTO_INCREMENT PRIMARY KEY,
    period_name     VARCHAR(100) NOT NULL,          -- e.g. "Wet Season 2026"
    rate_per_ha     DECIMAL(10,2) NOT NULL,          -- cash rate, e.g. 750.00 per hectare
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    status          ENUM('open','closed') NOT NULL DEFAULT 'open',
    -- 'open'  : bills can still be generated/regenerated for this period
    -- 'closed': officer has locked the period; no further bill generation
    notes           VARCHAR(255) DEFAULT NULL,
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Individual bills: one row per farm lot per billing period.
-- amount_due is computed at generation time as land_area_ha * rate_per_ha
-- and stored (not recalculated live), so historical bills stay accurate
-- even if a farm lot's land area is corrected later.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS isf_bills (
    bill_id         INT AUTO_INCREMENT PRIMARY KEY,
    period_id       INT NOT NULL,
    lot_id          INT NOT NULL,
    user_id         INT NOT NULL,                    -- denormalized owner, for fast lookups
    land_area_ha    DECIMAL(6,2) NOT NULL,            -- snapshot at generation time
    rate_per_ha     DECIMAL(10,2) NOT NULL,           -- snapshot at generation time
    amount_due      DECIMAL(10,2) NOT NULL,           -- land_area_ha * rate_per_ha
    amount_paid     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    generated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES billing_periods(period_id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES farm_lots(lot_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_period_lot (period_id, lot_id)
    -- one bill per farm lot per billing period; re-generating a period
    -- updates existing bills rather than duplicating them
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Payment ledger: individual payment entries against a bill. A bill's
-- amount_paid is the sum of its payments; this table is the audit trail
-- (who recorded it, when, how much) behind that running total.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS isf_payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    bill_id         INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_date    DATE NOT NULL,
    recorded_by     INT DEFAULT NULL,                -- officer who recorded the payment
    notes           VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES isf_bills(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Helpful indexes for the officer billing dashboard and member "my bills" view.
-- MySQL's CREATE INDEX has no IF NOT EXISTS clause, so this uses a small
-- procedure to check information_schema first, keeping the migration safe
-- to re-run without erroring on duplicate index creation.
DELIMITER $$
CREATE PROCEDURE bambosa_add_index_if_missing(
    IN tbl VARCHAR(64), IN idx VARCHAR(64), IN cols VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = tbl AND index_name = idx
    ) THEN
        SET @ddl = CONCAT('CREATE INDEX ', idx, ' ON ', tbl, ' (', cols, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL bambosa_add_index_if_missing('isf_bills', 'idx_isf_bills_user', 'user_id');
CALL bambosa_add_index_if_missing('isf_bills', 'idx_isf_bills_period', 'period_id');
CALL bambosa_add_index_if_missing('isf_bills', 'idx_isf_bills_status', 'status');

DROP PROCEDURE IF EXISTS bambosa_add_index_if_missing;
