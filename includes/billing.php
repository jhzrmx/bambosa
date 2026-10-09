<?php
/**
 * ISF Billing data access functions (Epic 3).
 * Requires $pdo from config/database.php to already be loaded.
 *
 * Calculation model: a billing period defines a cash rate per hectare
 * (commonly varies per cropping season in practice). Generating a period
 * creates one bill per farm lot = land_area_ha * rate_per_ha, snapshotting
 * both values on the bill so historical bills stay accurate even if a
 * farm lot's recorded area is corrected later. Payments are logged
 * individually against a bill so partial payments have an audit trail;
 * a bill's status (unpaid/partial/paid) is derived from amount_paid vs
 * amount_due and kept in sync whenever a payment is recorded.
 *
 * Only farm lots that are INSIDE a sector (farm_lots.sector_id IS NOT NULL)
 * are billed. A lot that isn't assigned to any sector — not within a drawn
 * sector boundary and not manually assigned — is skipped.
 */

/**
 * All billing periods, newest first, optionally filtered by a search term
 * (matched against period name and notes), optionally paginated.
 *
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_billing_periods(PDO $pdo, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    $sql = 'SELECT * FROM billing_periods WHERE 1=1';
    $params = [];

    if ($search) {
        $sql .= ' AND (period_name LIKE :search1 OR notes LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY start_date DESC';

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

/** Count of billing periods matching an optional search filter (for pagination totals). */
function count_billing_periods(PDO $pdo, ?string $search = null): int
{
    $sql = 'SELECT COUNT(*) AS cnt FROM billing_periods WHERE 1=1';
    $params = [];

    if ($search) {
        $sql .= ' AND (period_name LIKE :search1 OR notes LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'];
}

function get_billing_period(PDO $pdo, int $periodId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM billing_periods WHERE period_id = :id');
    $stmt->execute(['id' => $periodId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_billing_period(PDO $pdo, string $name, float $ratePerHa, string $startDate, string $endDate, ?string $notes, ?int $createdBy): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO billing_periods (period_name, rate_per_ha, start_date, end_date, notes, created_by)
         VALUES (:name, :rate, :start_date, :end_date, :notes, :created_by)'
    );
    $stmt->execute([
        'name'       => $name,
        'rate'       => $ratePerHa,
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'notes'      => $notes ?: null,
        'created_by' => $createdBy,
    ]);
    return (int) $pdo->lastInsertId();
}

function update_billing_period(PDO $pdo, int $periodId, string $name, float $ratePerHa, string $startDate, string $endDate, ?string $notes): void
{
    $stmt = $pdo->prepare(
        'UPDATE billing_periods SET period_name = :name, rate_per_ha = :rate,
            start_date = :start_date, end_date = :end_date, notes = :notes
         WHERE period_id = :id'
    );
    $stmt->execute([
        'name'       => $name,
        'rate'       => $ratePerHa,
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'notes'      => $notes ?: null,
        'id'         => $periodId,
    ]);
}

function set_billing_period_status(PDO $pdo, int $periodId, string $status): void
{
    if (!in_array($status, ['open', 'closed'], true)) {
        throw new InvalidArgumentException('Invalid billing period status.');
    }
    $stmt = $pdo->prepare('UPDATE billing_periods SET status = :status WHERE period_id = :id');
    $stmt->execute(['status' => $status, 'id' => $periodId]);
}

function delete_billing_period(PDO $pdo, int $periodId): void
{
    // ON DELETE CASCADE on isf_bills (and isf_payments via bills) means this
    // also removes every bill and payment ever recorded under this period.
    // The officer-facing page shows a confirm() dialog warning about this
    // cascade before submitting, since it destroys financial records.
    $stmt = $pdo->prepare('DELETE FROM billing_periods WHERE period_id = :id');
    $stmt->execute(['id' => $periodId]);
}

/**
 * Generates (or regenerates) bills for every farm lot that is inside a
 * sector (farm_lots.sector_id IS NOT NULL) for a given billing period,
 * using that period's rate_per_ha. Lots with no sector are NOT billed.
 *
 * If a bill already exists for a lot in this period (unique key on
 * period_id+lot_id), its land_area_ha, rate_per_ha, and amount_due are
 * refreshed to current values — but amount_paid/status are left untouched
 * so recorded payments are never lost by re-generating.
 *
 * Clean-up on regeneration: if a lot that was billed earlier is no longer
 * in a sector (its sector was removed/deleted, or it was unassigned), its
 * bill in this period is removed — but ONLY if nothing has been paid on it.
 * A bill that already has payments is kept (it's a financial record) and
 * reported in $summary['kept_with_payments'] so an officer can review it.
 *
 * Returns the number of bills created or updated. Optionally fills
 * $summary with:
 *   'skipped'            lots not billed because they're in no sector
 *   'removed'            stale unpaid bills deleted by this run
 *   'kept_with_payments' bills for no-sector lots kept because they have payments
 */
function generate_bills_for_period(PDO $pdo, int $periodId, ?array &$summary = null): int
{
    $period = get_billing_period($pdo, $periodId);
    if (!$period) {
        throw new InvalidArgumentException('Billing period not found.');
    }
    if ($period['status'] !== 'open') {
        throw new RuntimeException('Cannot generate bills for a closed billing period.');
    }

    $rate = (float) $period['rate_per_ha'];

    $lots = $pdo->query(
        'SELECT lot_id, user_id, land_area_ha FROM farm_lots WHERE sector_id IS NOT NULL'
    )->fetchAll();

    $upsert = $pdo->prepare(
        'INSERT INTO isf_bills (period_id, lot_id, user_id, land_area_ha, rate_per_ha, amount_due)
         VALUES (:period_id, :lot_id, :user_id, :land_area_ha, :rate_per_ha, :amount_due)
         ON DUPLICATE KEY UPDATE
            land_area_ha = VALUES(land_area_ha),
            rate_per_ha  = VALUES(rate_per_ha),
            amount_due   = VALUES(amount_due)'
    );

    $pdo->beginTransaction();
    try {
        // Remove bills for lots that are no longer in any sector, unless a
        // payment has been recorded against them.
        $removeStale = $pdo->prepare(
            'DELETE b FROM isf_bills b
             JOIN farm_lots fl ON fl.lot_id = b.lot_id
             WHERE b.period_id = :period_id
               AND fl.sector_id IS NULL
               AND b.amount_paid <= 0
               AND NOT EXISTS (SELECT 1 FROM isf_payments pay WHERE pay.bill_id = b.bill_id)'
        );
        $removeStale->execute(['period_id' => $periodId]);
        $removed = $removeStale->rowCount();

        $count = 0;
        foreach ($lots as $lot) {
            $area = (float) $lot['land_area_ha'];
            $amountDue = round($area * $rate, 2);

            $upsert->execute([
                'period_id'    => $periodId,
                'lot_id'       => $lot['lot_id'],
                'user_id'      => $lot['user_id'],
                'land_area_ha' => $area,
                'rate_per_ha'  => $rate,
                'amount_due'   => $amountDue,
            ]);
            $count++;
        }

        // Re-sync status for any bill whose amount_due changed (e.g. a farm
        // lot's area was edited after the previous generation), in case that
        // pushes a previously-"paid" bill back to "partial".
        recalculate_all_bill_statuses_for_period($pdo, $periodId);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $keptStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM isf_bills b
         JOIN farm_lots fl ON fl.lot_id = b.lot_id
         WHERE b.period_id = :period_id AND fl.sector_id IS NULL'
    );
    $keptStmt->execute(['period_id' => $periodId]);

    $summary = [
        'skipped'            => count_farm_lots_without_sector($pdo),
        'removed'            => (int) $removed,
        'kept_with_payments' => (int) $keptStmt->fetchColumn(),
    ];

    return $count;
}

/** Number of farm lots not assigned to any sector (these are not billed). */
function count_farm_lots_without_sector(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM farm_lots WHERE sector_id IS NULL')->fetchColumn();
}

/** Recomputes status for every bill in a period based on amount_paid vs amount_due. */
function recalculate_all_bill_statuses_for_period(PDO $pdo, int $periodId): void
{
    $pdo->prepare(
        "UPDATE isf_bills SET status = CASE
            WHEN amount_paid <= 0 THEN 'unpaid'
            WHEN amount_paid >= amount_due THEN 'paid'
            ELSE 'partial'
         END
         WHERE period_id = :period_id"
    )->execute(['period_id' => $periodId]);
}

/**
 * Bills for a given billing period, optionally filtered by status and/or a
 * search term (matched against member name and lot name), optionally
 * paginated.
 *
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_bills_for_period(PDO $pdo, int $periodId, ?string $status = null, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    $sql = "SELECT b.*, u.full_name AS owner_name, fl.lot_name, s.sector_name
            FROM isf_bills b
            JOIN users u ON u.user_id = b.user_id
            JOIN farm_lots fl ON fl.lot_id = b.lot_id
            LEFT JOIN sectors s ON s.sector_id = fl.sector_id
            WHERE b.period_id = :period_id";
    $params = ['period_id' => $periodId];

    if ($status) {
        $sql .= ' AND b.status = :status';
        $params['status'] = $status;
    }

    if ($search) {
        $sql .= ' AND (u.full_name LIKE :search1 OR fl.lot_name LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY u.full_name, fl.lot_name';

    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === 'period_id') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    if ($limit !== null && $offset !== null) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Count of bills in a period matching an optional status/search filter (for pagination totals). */
function count_bills_for_period(PDO $pdo, int $periodId, ?string $status = null, ?string $search = null): int
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM isf_bills b
            JOIN users u ON u.user_id = b.user_id
            JOIN farm_lots fl ON fl.lot_id = b.lot_id
            WHERE b.period_id = :period_id";
    $params = ['period_id' => $periodId];

    if ($status) {
        $sql .= ' AND b.status = :status';
        $params['status'] = $status;
    }

    if ($search) {
        $sql .= ' AND (u.full_name LIKE :search1 OR fl.lot_name LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === 'period_id') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    return (int) $stmt->fetch()['cnt'];
}

/** All bills for one member across all periods, newest period first. */
function get_bills_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT b.*, bp.period_name, bp.start_date, bp.end_date, fl.lot_name, s.sector_name
         FROM isf_bills b
         JOIN billing_periods bp ON bp.period_id = b.period_id
         JOIN farm_lots fl ON fl.lot_id = b.lot_id
         LEFT JOIN sectors s ON s.sector_id = fl.sector_id
         WHERE b.user_id = :uid
         ORDER BY bp.start_date DESC, fl.lot_name"
    );
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function get_bill_by_id(PDO $pdo, int $billId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT b.*, u.full_name AS owner_name, fl.lot_name, bp.period_name, bp.status AS period_status
         FROM isf_bills b
         JOIN users u ON u.user_id = b.user_id
         JOIN farm_lots fl ON fl.lot_id = b.lot_id
         JOIN billing_periods bp ON bp.period_id = b.period_id
         WHERE b.bill_id = :id"
    );
    $stmt->execute(['id' => $billId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Payment history for one bill, most recent first. */
function get_payments_for_bill(PDO $pdo, int $billId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, u.full_name AS recorded_by_name
         FROM isf_payments p
         LEFT JOIN users u ON u.user_id = p.recorded_by
         WHERE p.bill_id = :bill_id
         ORDER BY p.payment_date DESC, p.created_at DESC"
    );
    $stmt->execute(['bill_id' => $billId]);
    return $stmt->fetchAll();
}

/**
 * Records a payment against a bill and updates the bill's running total
 * and status accordingly. Rejects payments that would push amount_paid
 * past amount_due, since that's almost certainly a data-entry error for
 * a fixed-fee bill (there's no overpayment/credit concept in this model).
 */
function record_payment(PDO $pdo, int $billId, float $amount, string $paymentDate, ?string $notes, ?int $recordedBy): void
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }

    $bill = get_bill_by_id($pdo, $billId);
    if (!$bill) {
        throw new InvalidArgumentException('Bill not found.');
    }

    $newTotal = (float) $bill['amount_paid'] + $amount;
    if ($newTotal > (float) $bill['amount_due'] + 0.01) { // small epsilon for float rounding
        throw new RuntimeException('Payment would exceed the amount due on this bill.');
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO isf_payments (bill_id, amount, payment_date, recorded_by, notes)
             VALUES (:bill_id, :amount, :payment_date, :recorded_by, :notes)'
        );
        $insert->execute([
            'bill_id'      => $billId,
            'amount'       => $amount,
            'payment_date' => $paymentDate,
            'recorded_by'  => $recordedBy,
            'notes'        => $notes ?: null,
        ]);

        $status = $newTotal <= 0 ? 'unpaid' : ($newTotal >= (float) $bill['amount_due'] ? 'paid' : 'partial');

        $update = $pdo->prepare(
            'UPDATE isf_bills SET amount_paid = :paid, status = :status WHERE bill_id = :id'
        );
        $update->execute(['paid' => $newTotal, 'status' => $status, 'id' => $billId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Summary totals for a billing period: total due, total collected, count by status. */
function get_period_summary(PDO $pdo, int $periodId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS bill_count,
            COALESCE(SUM(amount_due), 0) AS total_due,
            COALESCE(SUM(amount_paid), 0) AS total_collected,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count
         FROM isf_bills WHERE period_id = :period_id"
    );
    $stmt->execute(['period_id' => $periodId]);
    return $stmt->fetch();
}

/** A member's total outstanding balance across all bills (any period, any status but paid). */
function get_outstanding_balance_for_user(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_due - amount_paid), 0) AS balance
         FROM isf_bills WHERE user_id = :uid AND status != 'paid'"
    );
    $stmt->execute(['uid' => $userId]);
    return (float) $stmt->fetch()['balance'];
}
