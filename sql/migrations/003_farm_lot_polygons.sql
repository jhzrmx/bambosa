-- Migration: 003_farm_lot_polygons.sql
-- Adds polygon boundary storage to farm_lots, so an officer can draw a farm
-- lot's actual shape on the map instead of only dropping a single point
-- pin. This mirrors what 002_sector_polygons.sql already did for sectors.
--
-- The existing latitude/longitude columns are NOT removed. When a polygon
-- is saved, latitude/longitude are recomputed as the polygon's centroid,
-- so every existing feature that only needs a single point (mapped-lot
-- lists, the point-in-polygon sector auto-assignment, the member's
-- read-only farm map) keeps working completely unchanged. Lots with no
-- polygon drawn keep behaving exactly as before: a manually-entered or
-- picked lat/lng point, nothing else required.
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
    WHERE table_schema = DATABASE() AND table_name = 'farm_lots' AND column_name = 'boundary_geojson'
);
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE farm_lots ADD COLUMN boundary_geojson JSON DEFAULT NULL AFTER longitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
