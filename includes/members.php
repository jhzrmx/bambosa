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
 *
 * $search: optional case-insensitive match against sector name/description.
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_sectors_with_usage(PDO $pdo, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    $sql = "SELECT s.*,
                (SELECT COUNT(*) FROM farm_lots fl WHERE fl.sector_id = s.sector_id) AS lot_count,
                (SELECT COUNT(*) FROM rotation_schedules rs WHERE rs.sector_id = s.sector_id) AS schedule_count
            FROM sectors s";
    $params = [];

    if ($search) {
        $sql .= ' WHERE s.sector_name LIKE :search1 OR s.description LIKE :search2';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY s.sector_name';

    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    if ($limit !== null && $offset !== null) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Count of sectors matching an optional search term (for pagination totals). */
function count_sectors(PDO $pdo, ?string $search = null): int
{
    $sql = 'SELECT COUNT(*) AS cnt FROM sectors';
    $params = [];
    if ($search) {
        $sql .= ' WHERE sector_name LIKE :search1 OR description LIKE :search2';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'];
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

/**
 * Get all members (role=member), optionally filtered by status and/or a
 * search term (matched against name, email, contact number), optionally
 * paginated.
 *
 * $search: optional case-insensitive match against name/email/contact.
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_all_members(PDO $pdo, ?string $status = null, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    $sql = "SELECT * FROM users WHERE role = 'member'";
    $params = [];

    if ($status) {
        $sql .= ' AND status = :status';
        $params['status'] = $status;
    }

    if ($search) {
        $sql .= ' AND (full_name LIKE :search1 OR email LIKE :search2 OR contact_number LIKE :search3)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
        $params['search3'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY full_name';

    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    if ($limit !== null && $offset !== null) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Count of members matching an optional status/search filter (for pagination totals). */
function count_members(PDO $pdo, ?string $status = null, ?string $search = null): int
{
    $sql = "SELECT COUNT(*) AS cnt FROM users WHERE role = 'member'";
    $params = [];

    if ($status) {
        $sql .= ' AND status = :status';
        $params['status'] = $status;
    }

    if ($search) {
        $sql .= ' AND (full_name LIKE :search1 OR email LIKE :search2 OR contact_number LIKE :search3)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
        $params['search3'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'];
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

/**
 * Create a new farm lot record. Returns the new lot_id.
 *
 * $data['latitude']/['longitude']/['boundary_geojson'] are expected to
 * already be fully resolved by the caller (see
 * normalize_and_center_boundary() in includes/farm_lot_boundaries.php) —
 * this function just persists whatever it's given, it doesn't compute a
 * centroid or validate polygon shape itself.
 */
function create_farm_lot(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO farm_lots (user_id, sector_id, lot_name, land_area_ha, latitude, longitude, boundary_geojson, notes)
         VALUES (:user_id, :sector_id, :lot_name, :land_area_ha, :latitude, :longitude, :boundary_geojson, :notes)'
    );
    $stmt->execute([
        'user_id'          => $data['user_id'],
        'sector_id'        => $data['sector_id'] ?: null,
        'lot_name'         => $data['lot_name'],
        'land_area_ha'     => $data['land_area_ha'],
        'latitude'         => $data['latitude'] ?? null,
        'longitude'        => $data['longitude'] ?? null,
        'boundary_geojson' => $data['boundary_geojson'] ?? null,
        'notes'            => $data['notes'] ?: null,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing farm lot record — including its boundary polygon and
 * derived latitude/longitude, all saved together in this one call. See
 * create_farm_lot()'s note above about $data['latitude']/['longitude']/
 * ['boundary_geojson'] already being resolved before this is called.
 */
function update_farm_lot(PDO $pdo, int $lotId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE farm_lots SET
            sector_id = :sector_id,
            lot_name = :lot_name,
            land_area_ha = :land_area_ha,
            latitude = :latitude,
            longitude = :longitude,
            boundary_geojson = :boundary_geojson,
            notes = :notes
         WHERE lot_id = :lot_id'
    );
    $stmt->execute([
        'sector_id'        => $data['sector_id'] ?: null,
        'lot_name'         => $data['lot_name'],
        'land_area_ha'     => $data['land_area_ha'],
        'latitude'         => $data['latitude'] ?? null,
        'longitude'        => $data['longitude'] ?? null,
        'boundary_geojson' => $data['boundary_geojson'] ?? null,
        'notes'            => $data['notes'] ?: null,
        'lot_id'           => $lotId,
    ]);
}

/** Delete a farm lot record. */
function delete_farm_lot(PDO $pdo, int $lotId): void
{
    $stmt = $pdo->prepare('DELETE FROM farm_lots WHERE lot_id = :id');
    $stmt->execute(['id' => $lotId]);
}

/**
 * Update a user's own editable profile fields (name, contact, optionally email —
 * NOT role/status/land data). Pass $email = null to leave the email untouched.
 *
 * Throws PDOException (SQLSTATE 23000) if the email collides with another
 * account's UNIQUE email; callers should check email_in_use() first for a
 * friendly message and treat this as the race-condition fallback.
 */
function update_user_profile(PDO $pdo, int $userId, string $fullName, ?string $contactNumber, ?string $email = null): void
{
    $sql = 'UPDATE users SET full_name = :full_name, contact_number = :contact_number'
         . ($email !== null ? ', email = :email' : '')
         . ' WHERE user_id = :id';
    $params = [
        'full_name'      => $fullName,
        'contact_number' => $contactNumber ?: null,
        'id'             => $userId,
    ];
    if ($email !== null) {
        $params['email'] = $email;
    }
    $pdo->prepare($sql)->execute($params);
}

/** True if another account (not $excludeUserId) already uses this email. */
function email_in_use(PDO $pdo, string $email, int $excludeUserId = 0): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email AND user_id <> :id LIMIT 1');
    $stmt->execute(['email' => $email, 'id' => $excludeUserId]);
    return (bool) $stmt->fetchColumn();
}

/** Checks a user's password without changing anything (used to confirm sensitive edits). */
function verify_user_password(PDO $pdo, int $userId, string $password): bool
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    return $row && password_verify($password, $row['password_hash']);
}

/**
 * Changes a user's own password after verifying their current one.
 *
 * Used by the Account Security pages (admin/, officer/ and member/security.php) — any logged-in
 * user can change their own password this way (there's no "reset someone
 * else's password" officer action; that's a separate, bigger feature).
 *
 * Returns true and updates the hash if $currentPassword matches what's on
 * file; returns false (no change made) if it doesn't, so the caller can
 * show "current password is incorrect" without a extra lookup.
 */
function change_user_password(PDO $pdo, int $userId, string $currentPassword, string $newPassword): bool
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        return false;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id');
    $update->execute(['hash' => $newHash, 'id' => $userId]);
    return true;
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

/**
 * A reasonable default map center for the farm-lot location picker: the
 * average latitude/longitude across every farm lot that already has
 * coordinates. Returns null if no lots have coordinates yet, in which
 * case the caller should fall back to a generic wide view instead of
 * guessing a specific town center with no data to anchor it.
 */
function get_average_farm_lot_location(PDO $pdo): ?array
{
    $row = $pdo->query(
        "SELECT AVG(latitude) AS avg_lat, AVG(longitude) AS avg_lng, COUNT(*) AS cnt
         FROM farm_lots WHERE latitude IS NOT NULL AND longitude IS NOT NULL"
    )->fetch();

    if (!$row || (int) $row['cnt'] === 0) {
        return null;
    }

    return ['lat' => (float) $row['avg_lat'], 'lng' => (float) $row['avg_lng']];
}

