-- BAMBOSA Irrigation Management System
-- Core authentication schema (Chapter 1-3 scope: auth only for now)

CREATE DATABASE IF NOT EXISTS bambosa_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bambosa_db;

CREATE TABLE IF NOT EXISTS users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(150)        NOT NULL,
    email          VARCHAR(150)        NOT NULL UNIQUE,
    password_hash  VARCHAR(255)        NOT NULL,
    role           ENUM('officer','member') NOT NULL DEFAULT 'member',
    contact_number VARCHAR(20)         DEFAULT NULL,
    status         ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Optional: table to support "remember me" / session audit later
CREATE TABLE IF NOT EXISTS login_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    login_time  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address  VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sectors of Barangay Bikal used for water rotation grouping (Epic 4 will use this too)
CREATE TABLE IF NOT EXISTS sectors (
    sector_id        INT AUTO_INCREMENT PRIMARY KEY,
    sector_name      VARCHAR(80) NOT NULL UNIQUE,
    description      VARCHAR(255) DEFAULT NULL,
    -- Polygon vertices as a JSON array of [lat, lng] pairs, drawn by an
    -- officer on the map. NULL means no boundary has been drawn yet.
    boundary_geojson JSON DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO sectors (sector_name, description) VALUES
    ('Sector A', 'Upper Bikal irrigation zone'),
    ('Sector B', 'Central Bikal irrigation zone'),
    ('Sector C', 'Lower Bikal irrigation zone')
ON DUPLICATE KEY UPDATE sector_name = sector_name;

-- Epic 2: Member & Farm Management
-- A member can have one or more registered farm lots.
-- land_area_ha feeds directly into Epic 3 (ISF billing).
-- latitude/longitude support the QGIS-based mapping module.
CREATE TABLE IF NOT EXISTS farm_lots (
    lot_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    sector_id    INT DEFAULT NULL,
    lot_name     VARCHAR(100) NOT NULL,          -- e.g. "Lot 1", farmer's own label
    land_area_ha DECIMAL(6,2) NOT NULL,
    latitude     DECIMAL(10,7) DEFAULT NULL,
    longitude    DECIMAL(10,7) DEFAULT NULL,
    notes        VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (sector_id) REFERENCES sectors(sector_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Epic 3 (simplified for now): Machinery Reservation System
-- Officers maintain the equipment list; members submit reservation requests
-- against a date range; officers approve/reject/cancel.
CREATE TABLE IF NOT EXISTS machinery (
    machinery_id INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  VARCHAR(255) DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,   -- officer can mark under-repair/out of service
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machinery_reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    machinery_id   INT NOT NULL,
    user_id        INT NOT NULL,           -- member who requested it
    start_date     DATE NOT NULL,
    end_date       DATE NOT NULL,
    purpose        VARCHAR(255) DEFAULT NULL,
    status         ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES machinery(machinery_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO machinery (name, description) VALUES
    ('Hand Tractor #1', 'Kubota hand tractor for tilling'),
    ('Water Pump #1', 'Gasoline-powered irrigation pump'),
    ('Thresher #1', 'Mobile rice thresher')
ON DUPLICATE KEY UPDATE name = name;

-- Epic 4 (simplified for now): Digital Rotation Scheduling
-- Officers post water-release windows per sector; members view schedules
-- relevant to their own farm lot sectors. No conflict-detection logic yet —
-- this is the straightforward CRUD + listing version.
CREATE TABLE IF NOT EXISTS rotation_schedules (
    schedule_id  INT AUTO_INCREMENT PRIMARY KEY,
    sector_id    INT NOT NULL,
    start_time   DATETIME NOT NULL,
    end_time     DATETIME NOT NULL,
    notes        VARCHAR(255) DEFAULT NULL,
    created_by   INT DEFAULT NULL,          -- officer user_id who posted it
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sector_id) REFERENCES sectors(sector_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Epic 3: ISF Billing.
-- See sql/migrations/001_isf_billing.sql for the full commentary — this is
-- the same schema, included here so a brand-new install gets everything
-- from this one file without also needing to run the migration separately.
CREATE TABLE IF NOT EXISTS billing_periods (
    period_id       INT AUTO_INCREMENT PRIMARY KEY,
    period_name     VARCHAR(100) NOT NULL,
    rate_per_ha     DECIMAL(10,2) NOT NULL,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    status          ENUM('open','closed') NOT NULL DEFAULT 'open',
    notes           VARCHAR(255) DEFAULT NULL,
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS isf_bills (
    bill_id         INT AUTO_INCREMENT PRIMARY KEY,
    period_id       INT NOT NULL,
    lot_id          INT NOT NULL,
    user_id         INT NOT NULL,
    land_area_ha    DECIMAL(6,2) NOT NULL,
    rate_per_ha     DECIMAL(10,2) NOT NULL,
    amount_due      DECIMAL(10,2) NOT NULL,
    amount_paid     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    generated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES billing_periods(period_id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES farm_lots(lot_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_period_lot (period_id, lot_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS isf_payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    bill_id         INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_date    DATE NOT NULL,
    recorded_by     INT DEFAULT NULL,
    notes           VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES isf_bills(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_isf_bills_user ON isf_bills(user_id);
CREATE INDEX idx_isf_bills_period ON isf_bills(period_id);
CREATE INDEX idx_isf_bills_status ON isf_bills(status);

-- Auto-assignment audit log: records each time a farm lot's sector was set
-- automatically because its coordinates fell inside a drawn sector polygon
-- (as opposed to an officer manually picking a sector on the farm lot form).
CREATE TABLE IF NOT EXISTS sector_auto_assignments (
    assignment_id       INT AUTO_INCREMENT PRIMARY KEY,
    lot_id              INT NOT NULL,
    previous_sector_id  INT DEFAULT NULL,
    new_sector_id       INT NOT NULL,
    assigned_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by         INT DEFAULT NULL,
    FOREIGN KEY (lot_id) REFERENCES farm_lots(lot_id) ON DELETE CASCADE,
    FOREIGN KEY (previous_sector_id) REFERENCES sectors(sector_id) ON DELETE SET NULL,
    FOREIGN KEY (new_sector_id) REFERENCES sectors(sector_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default password: 12345678
-- Hash generated with PHP password_hash() using PASSWORD_DEFAULT
INSERT INTO users (full_name, email, password_hash, role, status)
VALUES (
    'Default Officer',
    'officer@bambosa.local',
    '$2y$10$74S9DMg5zKVTQTI.5ApgdOwHpAdhwuoNVG4c/h3uWKtkQiNoIUSJi',
    'officer',
    'active'
);
