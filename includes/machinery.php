<?php
/**
 * Machinery Reservation System data access functions.
 * Requires $pdo from config/database.php to already be loaded.
 */

/** All machinery, optionally filtered to only available ones. */
function get_all_machinery(PDO $pdo, bool $onlyAvailable = false): array
{
    $sql = 'SELECT * FROM machinery';
    if ($onlyAvailable) {
        $sql .= ' WHERE is_available = 1';
    }
    $sql .= ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

function get_machinery_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM machinery WHERE machinery_id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_machinery(PDO $pdo, string $name, ?string $description): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO machinery (name, description) VALUES (:name, :description)'
    );
    $stmt->execute(['name' => $name, 'description' => $description ?: null]);
}

function update_machinery(PDO $pdo, int $id, string $name, ?string $description, bool $isAvailable): void
{
    $stmt = $pdo->prepare(
        'UPDATE machinery SET name = :name, description = :description, is_available = :avail
         WHERE machinery_id = :id'
    );
    $stmt->execute([
        'name'        => $name,
        'description' => $description ?: null,
        'avail'       => $isAvailable ? 1 : 0,
        'id'          => $id,
    ]);
}

function delete_machinery(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM machinery WHERE machinery_id = :id');
    $stmt->execute(['id' => $id]);
}

/**
 * All reservations, newest first, joined with machinery name and requester name.
 * Optional status filter.
 */
function get_all_reservations(PDO $pdo, ?string $status = null): array
{
    $sql = 'SELECT r.*, m.name AS machinery_name, u.full_name AS requester_name
            FROM machinery_reservations r
            JOIN machinery m ON m.machinery_id = r.machinery_id
            JOIN users u ON u.user_id = r.user_id';
    $params = [];
    if ($status) {
        $sql .= ' WHERE r.status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY r.start_date DESC, r.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Reservations belonging to one member, newest first. */
function get_reservations_by_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, m.name AS machinery_name
         FROM machinery_reservations r
         JOIN machinery m ON m.machinery_id = r.machinery_id
         WHERE r.user_id = :uid
         ORDER BY r.start_date DESC, r.created_at DESC'
    );
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function get_reservation_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM machinery_reservations WHERE reservation_id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Checks whether a machinery item already has an approved or pending
 * reservation overlapping the given date range. Used to warn/prevent
 * obvious double-booking (simple overlap check, not a full conflict engine).
 */
function has_overlapping_reservation(PDO $pdo, int $machineryId, string $startDate, string $endDate, ?int $excludeReservationId = null): bool
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM machinery_reservations
            WHERE machinery_id = :machinery_id
              AND status IN ('pending','approved')
              AND start_date <= :end_date
              AND end_date >= :start_date";
    $params = [
        'machinery_id' => $machineryId,
        'start_date'   => $startDate,
        'end_date'     => $endDate,
    ];

    if ($excludeReservationId) {
        $sql .= ' AND reservation_id != :exclude_id';
        $params['exclude_id'] = $excludeReservationId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'] > 0;
}

function create_reservation(PDO $pdo, int $machineryId, int $userId, string $startDate, string $endDate, ?string $purpose): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO machinery_reservations (machinery_id, user_id, start_date, end_date, purpose)
         VALUES (:machinery_id, :user_id, :start_date, :end_date, :purpose)'
    );
    $stmt->execute([
        'machinery_id' => $machineryId,
        'user_id'      => $userId,
        'start_date'   => $startDate,
        'end_date'     => $endDate,
        'purpose'      => $purpose ?: null,
    ]);
    return (int) $pdo->lastInsertId();
}

function update_reservation_status(PDO $pdo, int $reservationId, string $status): void
{
    $allowed = ['pending', 'approved', 'rejected', 'cancelled'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid reservation status.');
    }
    $stmt = $pdo->prepare(
        'UPDATE machinery_reservations SET status = :status WHERE reservation_id = :id'
    );
    $stmt->execute(['status' => $status, 'id' => $reservationId]);
}
