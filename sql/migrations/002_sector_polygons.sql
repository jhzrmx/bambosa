-- Migration: 002_sector_polygons.sql
-- Adds polygon boundary storage to sectors, so officers can draw a sector's
-- boundary on the map instead of only assigning sectors manually per farm
-- lot. Farm lots whose coordinates fall inside a sector's polygon can then
-- be auto-assigned to it (point-in-polygon test).
--
-- Run this against an EXISTING bambosa_db database. If setting up a brand
-- new database instead, just import sql/bambosa_db.sql, which already
-- includes this schema.
--
-- Safe to re-run: uses IF NOT EXISTS / a column-existence check before
-- altering, since ADD COLUMN has no native IF NOT EXISTS in MySQL.

USE bambosa_db;

-- ---------------------------------------------------------------------
-- Add boundary_geojson to sectors: the polygon's vertices, stored as a
-- JSON array of [lat, lng] pairs, e.g. [[13.748,122.943],[13.750,122.945],...]
-- NULL means the sector has no drawn boundary yet (falls back to manual
-- assignment only, same as before this migration).
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'sectors' AND column_name = 'boundary_geojson'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE sectors ADD COLUMN boundary_geojson JSON DEFAULT NULL AFTER description',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Audit log of auto-assignment actions, so officers can see what the
-- system changed automatically (as opposed to a manual sector edit on a
-- farm lot). Each row records one lot's sector change caused by polygon
-- assignment, including which polygon matched and what the lot's sector
-- was before.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sector_auto_assignments (
    assignment_id   INT AUTO_INCREMENT PRIMARY KEY,
    lot_id          INT NOT NULL,
    previous_sector_id INT DEFAULT NULL,
    new_sector_id   INT NOT NULL,
    assigned_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by     INT DEFAULT NULL,     -- officer who triggered the run (draw/save or manual re-run)
    FOREIGN KEY (lot_id) REFERENCES farm_lots(lot_id) ON DELETE CASCADE,
    FOREIGN KEY (previous_sector_id) REFERENCES sectors(sector_id) ON DELETE SET NULL,
    FOREIGN KEY (new_sector_id) REFERENCES sectors(sector_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
