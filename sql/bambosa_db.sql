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
    role           ENUM('admin','officer','member') NOT NULL DEFAULT 'member',
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

-- Forgot-password email verification links (see includes/password_reset.php).
-- A row is created each time someone requests a reset; only the token's
-- SHA-256 HASH is stored, never the raw token itself — same principle as
-- never storing a plaintext password. The raw token only ever exists in
-- the emailed link and briefly in memory while processing a request.
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    token_hash   CHAR(64) NOT NULL,          -- SHA-256 hex digest of the raw token
    expires_at   DATETIME NOT NULL,
    used_at      DATETIME DEFAULT NULL,      -- set once the link is used, so it can't be replayed
    requested_ip VARCHAR(45) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_password_resets_token_hash ON password_resets(token_hash);
CREATE INDEX idx_password_resets_user_id ON password_resets(user_id);

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
    -- Polygon vertices as a JSON array of [lat, lng] pairs, drawn by an
    -- officer on the map — same format as sectors.boundary_geojson below.
    -- NULL means the lot only has a single point (latitude/longitude above),
    -- not a drawn shape. When a polygon IS saved, latitude/longitude are
    -- recomputed as the polygon's centroid, so every existing feature that
    -- only needs a single map pin (mapped-lot lists, point-in-polygon
    -- sector auto-assignment, the member's read-only map) keeps working
    -- unchanged without needing to know about polygons at all.
    boundary_geojson JSON DEFAULT NULL,
    notes        VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (sector_id) REFERENCES sectors(sector_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Epic 3 (simplified for now): Machinery Reservation System
-- Officers maintain the equipment list; members submit reservation requests;
-- officers approve/reject/cancel.
--
-- Machinery groups (added alongside individual machines — see client
-- feedback in the "Machinery Reservation" section of the README) let
-- several interchangeable units of the same kind of equipment (e.g. three
-- separate hand tractors) be reserved as one pool instead of one specific
-- unit. A member requests against the GROUP; an officer picks which
-- physical unit to actually hand over at approval time. This avoids the
-- old friction where a member had to guess a specific machine, get
-- rejected because that one unit was busy, and resubmit against a
-- different one in the same group even though the group as a whole had
-- availability the whole time.
CREATE TABLE IF NOT EXISTS machinery_groups (
    group_id     INT AUTO_INCREMENT PRIMARY KEY,
    group_name   VARCHAR(100) NOT NULL UNIQUE,
    description  VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machinery (
    machinery_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id     INT DEFAULT NULL,               -- NULL = standalone machine, not part of any group
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  VARCHAR(255) DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,   -- officer can mark under-repair/out of service
    damage_level TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0 (pristine) to 100 (fully damaged) — officer-set condition slider
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES machinery_groups(group_id) ON DELETE SET NULL,
    CONSTRAINT chk_machinery_damage_level CHECK (damage_level BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machinery_reservations (
    reservation_id    INT AUTO_INCREMENT PRIMARY KEY,
    -- A request is made against a GROUP (group_id) when the machine belongs
    -- to one, or directly against a specific machine (machinery_id) when it
    -- doesn't. Exactly one of the two is filled in at request time — see
    -- the CHECK constraint below. machinery_id is filled in (or changed)
    -- once an officer assigns a specific physical unit at approval time,
    -- which is also true for a standalone (non-grouped) request: it's
    -- already the specific unit, so approval just confirms it.
    machinery_id       INT DEFAULT NULL,
    group_id           INT DEFAULT NULL,
    user_id            INT NOT NULL,           -- member who requested it
    start_date         DATE NOT NULL,
    end_date           DATE NOT NULL,
    purpose            VARCHAR(255) DEFAULT NULL,
    status             ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    rejection_remark   VARCHAR(255) DEFAULT NULL,   -- required officer note when status = 'rejected'
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (machinery_id) REFERENCES machinery(machinery_id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES machinery_groups(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_reservation_target CHECK (
        (machinery_id IS NOT NULL AND group_id IS NULL) OR
        (machinery_id IS NULL AND group_id IS NOT NULL) OR
        (machinery_id IS NOT NULL AND group_id IS NOT NULL)
    )
) ENGINE=InnoDB;

INSERT INTO machinery_groups (group_name, description) VALUES
    ('Hand Tractors', 'Kubota-type hand tractors for tilling')
ON DUPLICATE KEY UPDATE group_name = group_name;

SET @hand_tractor_group_id = (SELECT group_id FROM machinery_groups WHERE group_name = 'Hand Tractors');

INSERT INTO machinery (name, description, group_id) VALUES
    ('Hand Tractor #1', 'Kubota hand tractor for tilling', @hand_tractor_group_id),
    ('Water Pump #1', 'Gasoline-powered irrigation pump', NULL),
    ('Thresher #1', 'Mobile rice thresher', NULL)
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
    -- Ties together multiple rows created as "the same schedule, applied to
    -- several sectors at once" (see officer/schedules.php's "create the
    -- same schedule for other sectors too" option). NULL means this
    -- schedule entry stands alone, not part of a multi-sector batch.
    -- Not a foreign key since it doesn't reference another table — it's
    -- just a shared tag across sibling rows in this same table.
    group_id     INT DEFAULT NULL,
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
CREATE INDEX idx_rotation_schedules_group_id ON rotation_schedules(group_id);

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

-- Default admin account (can add/edit/delete officer and member accounts).
-- Password: 12345678 — CHANGE IT after first login via My Profile.
INSERT INTO users (full_name, email, password_hash, role, status)
VALUES (
    'Default Admin',
    'admin@bambosa.local',
    '$2y$10$74S9DMg5zKVTQTI.5ApgdOwHpAdhwuoNVG4c/h3uWKtkQiNoIUSJi',
    'admin',
    'active'
);
