<?php
/**
 * Member & Farm Management data access functions (Epic 2).
 * Requires $pdo from config/database.php to already be loaded.
 */

/** Get all sectors for dropdowns, ordered by name. */
function get_sectors(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM sectors ORDER BY sector_name')->fetchAll();
}

/**
 * Get all sectors along with a count of farm lots and rotation schedules
 * currently assigned to each — used on the sector management page so
 * officers can see at a glance what depends on a sector before deleting it.
 */
function get_sectors_with_usage(PDO $pdo): array
{
    return $pdo->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM farm_lots fl WHERE fl.sector_id = s.sector_id) AS lot_count,
                (SELECT COUNT(*) FROM rotation_schedules rs WHERE rs.sector_id = s.sector_id) AS schedule_count
         FROM sectors s
         ORDER BY s.sector_name"
    )->fetchAll();
}

/** Get a single sector by ID. */
function get_sector_by_id(PDO $pdo, int $sectorId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sectors WHERE sector_id = :id');
    $stmt->execute(['id' => $sectorId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Create a new sector. Returns the new sector_id. Throws on duplicate name. */
function create_sector(PDO $pdo, string $name, ?string $description): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO sectors (sector_name, description) VALUES (:name, :description)'
    );
    $stmt->execute(['name' => $name, 'description' => $description ?: null]);
    return (int) $pdo->lastInsertId();
}

/** Update an existing sector's name/description. Throws on duplicate name. */
function update_sector(PDO $pdo, int $sectorId, string $name, ?string $description): void
{
    $stmt = $pdo->prepare(
        'UPDATE sectors SET sector_name = :name, description = :description WHERE sector_id = :id'
    );
    $stmt->execute(['name' => $name, 'description' => $description ?: null, 'id' => $sectorId]);
}

/**
 * Delete a sector. Farm lots referencing it have their sector_id set to
 * NULL automatically (ON DELETE SET NULL); rotation schedules referencing
 * it are deleted automatically (ON DELETE CASCADE) since a schedule can't
 * exist without a sector. The officer-facing page warns about both effects
 * before this is called.
 */
function delete_sector(PDO $pdo, int $sectorId): void
{
    $stmt = $pdo->prepare('DELETE FROM sectors WHERE sector_id = :id');
    $stmt->execute(['id' => $sectorId]);
}

/** True if another sector already uses this name (case-insensitive), excluding the given ID when editing. */
function sector_name_exists(PDO $pdo, string $name, ?int $excludeId = null): bool
{
    $sql = 'SELECT COUNT(*) AS cnt FROM sectors WHERE LOWER(sector_name) = LOWER(:name)';
    $params = ['name' => $name];
    if ($excludeId !== null) {
        $sql .= ' AND sector_id != :exclude_id';
        $params['exclude_id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'] > 0;
}

/** Get all members (role=member), optionally including a status filter. */
function get_all_members(PDO $pdo, ?string $status = null): array
{
    if ($status) {
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE role = 'member' AND status = :status ORDER BY full_name"
        );
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    return $pdo->query(
        "SELECT * FROM users WHERE role = 'member' ORDER BY full_name"
    )->fetchAll();
}

/** Get a single user by ID (any role). */
function get_user_by_id(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Get all farm lots belonging to a user, with sector name joined in. */
function get_farm_lots_by_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT fl.*, s.sector_name
         FROM farm_lots fl
         LEFT JOIN sectors s ON s.sector_id = fl.sector_id
         WHERE fl.user_id = :uid
         ORDER BY fl.created_at DESC'
    );
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

/** Get a single farm lot by ID. */
function get_farm_lot(PDO $pdo, int $lotId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM farm_lots WHERE lot_id = :id');
    $stmt->execute(['id' => $lotId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Total land area (ha) registered to a member — used later for ISF billing. */
function get_total_land_area(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(land_area_ha), 0) AS total FROM farm_lots WHERE user_id = :uid'
    );
    $stmt->execute(['uid' => $userId]);
    return (float) $stmt->fetch()['total'];
}

/** Create a new farm lot record. Returns the new lot_id. */
function create_farm_lot(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO farm_lots (user_id, sector_id, lot_name, land_area_ha, latitude, longitude, notes)
         VALUES (:user_id, :sector_id, :lot_name, :land_area_ha, :latitude, :longitude, :notes)'
    );
    $stmt->execute([
        'user_id'      => $data['user_id'],
        'sector_id'    => $data['sector_id'] ?: null,
        'lot_name'     => $data['lot_name'],
        'land_area_ha' => $data['land_area_ha'],
        'latitude'     => $data['latitude'] ?: null,
        'longitude'    => $data['longitude'] ?: null,
        'notes'        => $data['notes'] ?: null,
    ]);
    return (int) $pdo->lastInsertId();
}

/** Update an existing farm lot record. */
function update_farm_lot(PDO $pdo, int $lotId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE farm_lots SET
            sector_id = :sector_id,
            lot_name = :lot_name,
            land_area_ha = :land_area_ha,
            latitude = :latitude,
            longitude = :longitude,
            notes = :notes
         WHERE lot_id = :lot_id'
    );
    $stmt->execute([
        'sector_id'    => $data['sector_id'] ?: null,
        'lot_name'     => $data['lot_name'],
        'land_area_ha' => $data['land_area_ha'],
        'latitude'     => $data['latitude'] ?: null,
        'longitude'    => $data['longitude'] ?: null,
        'notes'        => $data['notes'] ?: null,
        'lot_id'       => $lotId,
    ]);
}

/** Delete a farm lot record. */
function delete_farm_lot(PDO $pdo, int $lotId): void
{
    $stmt = $pdo->prepare('DELETE FROM farm_lots WHERE lot_id = :id');
    $stmt->execute(['id' => $lotId]);
}

/** Update a user's own editable profile fields (name, contact — NOT role/status/land data). */
function update_user_profile(PDO $pdo, int $userId, string $fullName, ?string $contactNumber): void
{
    $stmt = $pdo->prepare(
        'UPDATE users SET full_name = :full_name, contact_number = :contact_number WHERE user_id = :id'
    );
    $stmt->execute([
        'full_name'      => $fullName,
        'contact_number' => $contactNumber ?: null,
        'id'             => $userId,
    ]);
}

/** Officer action: change a member's account status (approve, deactivate, reactivate). */
function update_user_status(PDO $pdo, int $userId, string $status): void
{
    $allowed = ['active', 'inactive', 'pending'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid status value.');
    }

    $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE user_id = :id');
    $stmt->execute(['status' => $status, 'id' => $userId]);
}

/**
 * Map view (QGIS-style member mapping): all farm lots that HAVE coordinates,
 * joined with owner name and sector name, for plotting on the officer map.
 */
function get_mapped_farm_lots(PDO $pdo): array
{
    return $pdo->query(
        "SELECT fl.*, s.sector_name, u.full_name AS owner_name, u.status AS owner_status
         FROM farm_lots fl
         LEFT JOIN sectors s ON s.sector_id = fl.sector_id
         JOIN users u ON u.user_id = fl.user_id
         WHERE fl.latitude IS NOT NULL AND fl.longitude IS NOT NULL
         ORDER BY fl.lot_name"
    )->fetchAll();
}

/** Count of farm lots missing coordinates — flagged to the officer as incomplete data entry. */
function count_unmapped_farm_lots(PDO $pdo): int
{
    $row = $pdo->query(
        "SELECT COUNT(*) AS cnt FROM farm_lots WHERE latitude IS NULL OR longitude IS NULL"
    )->fetch();
    return (int) $row['cnt'];
}

/** Farm lots for one member that HAVE coordinates, joined with sector name, for the member's own map. */
function get_mapped_farm_lots_by_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT fl.*, s.sector_name
         FROM farm_lots fl
         LEFT JOIN sectors s ON s.sector_id = fl.sector_id
         WHERE fl.user_id = :uid AND fl.latitude IS NOT NULL AND fl.longitude IS NOT NULL
         ORDER BY fl.lot_name"
    );
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}
