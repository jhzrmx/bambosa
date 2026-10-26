<?php
/**
 * Member export (officer side): query + CSV / XLSX writers.
 * Used by officer/members_export.php. Requires $pdo to be loaded and
 * includes/members.php (for get_sectors()). PHP 7.4-compatible.
 *
 * XLSX is produced by the vendored SimpleXLSXGen library
 * (vendor/shuchkin/simplexlsxgen, MIT). That library treats cell text that
 * contains "<tags>" as formatting and turns numeric-looking text (a phone
 * number like 09171234567) into a number, so every member-supplied value is
 * passed through SimpleXLSXGen::raw() ("\0" prefix) — stored exactly as
 * typed, as plain text, never interpreted as a formula or markup.
 */

const MEMBER_EXPORT_STATUSES = ['active', 'inactive', 'pending'];

/** Path of the vendored XLSX library, or null if it is not installed. */
function xlsx_library_path(): ?string
{
    $path = __DIR__ . '/../vendor/shuchkin/simplexlsxgen/src/SimpleXLSXGen.php';
    return is_file($path) ? $path : null;
}

/**
 * Rows for the export, one per member ($layout 'member') or one per farm
 * lot ($layout 'lot').
 *
 * $sector: null = every sector (and members with no lots at all),
 *          0    = only lots with no sector assigned,
 *          >0   = only lots in that sector.
 * With a sector chosen, a member appears only if they have a matching lot,
 * and the lot count / area / sectors in the 'member' layout are limited to
 * that sector.
 * $status: null or one of MEMBER_EXPORT_STATUSES.
 */
function get_members_for_export(PDO $pdo, string $layout, ?int $sector, ?string $status): array
{
    $where  = ["u.role = 'member'"];
    $params = [];

    if ($status !== null) {
        $where[] = 'u.status = :status';
        $params['status'] = $status;
    }
    if ($sector === 0) {
        $where[] = 'fl.lot_id IS NOT NULL AND fl.sector_id IS NULL';
    } elseif ($sector !== null) {
        $where[] = 'fl.sector_id = :sector';
        $params['sector'] = $sector;
    }
    $whereSql = implode(' AND ', $where);

    if ($layout === 'lot') {
        $sql = "SELECT u.full_name, u.email, u.contact_number, u.status, u.created_at,
                       fl.lot_name, s.sector_name, fl.land_area_ha,
                       fl.latitude, fl.longitude, fl.notes
                FROM users u
                LEFT JOIN farm_lots fl ON fl.user_id = u.user_id
                LEFT JOIN sectors s ON s.sector_id = fl.sector_id
                WHERE $whereSql
                ORDER BY u.full_name, fl.lot_name";
    } else {
        $sql = "SELECT u.user_id, u.full_name, u.email, u.contact_number, u.status, u.created_at,
                       COUNT(fl.lot_id) AS lot_count,
                       COALESCE(SUM(fl.land_area_ha), 0) AS total_area,
                       GROUP_CONCAT(DISTINCT s.sector_name ORDER BY s.sector_name SEPARATOR ', ') AS sectors
                FROM users u
                LEFT JOIN farm_lots fl ON fl.user_id = u.user_id
                LEFT JOIN sectors s ON s.sector_id = fl.sector_id
                WHERE $whereSql
                GROUP BY u.user_id, u.full_name, u.email, u.contact_number, u.status, u.created_at
                ORDER BY u.full_name";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Column headings and widths (in characters) for a layout. */
function member_export_columns(string $layout): array
{
    $common = [
        ['Name', 28], ['Email', 32], ['Contact Number', 18], ['Status', 11], ['Date Registered', 16],
    ];
    if ($layout === 'lot') {
        return array_merge($common, [
            ['Lot Name', 20], ['Sector', 20], ['Land Area (ha)', 15],
            ['Latitude', 13], ['Longitude', 13], ['Lot Notes', 36],
        ]);
    }
    return array_merge($common, [
        ['No. of Lots', 12], ['Total Land Area (ha)', 21], ['Sectors', 32],
    ]);
}

/**
 * Plain values for one DB row, in column order. Numbers stay numbers
 * (land area as a float, lot count as an int); missing values are ''.
 */
function member_export_values(array $r, string $layout): array
{
    $vals = [
        (string) $r['full_name'],
        (string) $r['email'],
        (string) ($r['contact_number'] ?? ''),
        ucfirst((string) $r['status']),
        substr((string) $r['created_at'], 0, 10),
    ];
    if ($layout === 'lot') {
        $hasLot = $r['lot_name'] !== null;
        $vals[] = (string) ($r['lot_name'] ?? '');
        $vals[] = (string) ($r['sector_name'] ?? ($hasLot ? 'Unassigned' : ''));
        $vals[] = $hasLot ? (float) $r['land_area_ha'] : '';
        $vals[] = $r['latitude']  !== null ? (string) $r['latitude']  : '';
        $vals[] = $r['longitude'] !== null ? (string) $r['longitude'] : '';
        $vals[] = (string) ($r['notes'] ?? '');
    } else {
        $vals[] = (int) $r['lot_count'];
        $vals[] = (float) $r['total_area'];
        $vals[] = (string) ($r['sectors'] ?? '');
    }
    return $vals;
}

/**
 * CSV spreadsheet-formula guard: text starting with = + - @ (or a tab /
 * carriage return) would be run as a formula when the CSV is opened in
 * Excel, so it gets a leading apostrophe. Phone-style values such as
 * "+639171234567" are left alone.
 */
function csv_safe_cell($value)
{
    if (!is_string($value) || $value === '') {
        return $value;
    }
    if (preg_match('/^[=+\-@\t\r]/', $value) && !preg_match('/^[+\-]?[0-9][0-9 ().\-]*$/', $value)) {
        return "'" . $value;
    }
    return $value;
}

/** Streams the rows as a UTF-8 CSV download (with BOM so Excel reads accents). */
function output_members_csv(array $rows, string $layout, string $filename): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_column(member_export_columns($layout), 0), ',', '"', '\\');
    foreach ($rows as $r) {
        $vals = array_map('csv_safe_cell', member_export_values($r, $layout));
        foreach ($vals as $i => $v) {
            if (is_float($v)) {
                $vals[$i] = number_format($v, 2, '.', '');
            }
        }
        fputcsv($out, $vals, ',', '"', '\\');
    }
    fclose($out);
}

/**
 * One centered, merged title line for the top of the sheet. $text is
 * escaped and passed as formatted raw text (the documented
 * <raw> pattern), so a sector name containing "<" cannot break the file.
 */
function xlsx_title_cell(string $text, string $styleAttrs, bool $bold, bool $italic): string
{
    $inner = '<center><raw>' . htmlspecialchars($text, ENT_QUOTES) . '</raw></center>';
    $inner = '<style ' . $styleAttrs . '>' . $inner . '</style>';
    if ($italic) {
        $inner = '<i>' . $inner . '</i>';
    }
    return $bold ? '<b>' . $inner . '</b>' : $inner;
}

/**
 * Streams the rows as an XLSX download. Minimal styling: a centered
 * letterhead (association name, address, report title, filters + date),
 * then a green bold header row with white text, frozen panes, auto-filter,
 * column widths, and land area shown with two decimals.
 * $filterSummary is the human-readable filter line, e.g. "Sector: Sector 1 | Status: All".
 * Returns false if the library is missing.
 */
function output_members_xlsx(array $rows, string $layout, string $filename, string $sheetTitle, string $filterSummary = ''): bool
{
    $lib = xlsx_library_path();
    if ($lib === null) {
        return false;
    }
    require_once $lib;

    $columns = member_export_columns($layout);

    $colCount = count($columns);
    $pad = function (string $first) use ($colCount): array {
        return array_merge([$first], array_fill(0, $colCount - 1, ''));
    };
    $reportTitle = $layout === 'lot' ? 'Member Masterlist (per Farm Lot)' : 'Member Masterlist';
    $infoLine    = trim($filterSummary . ($filterSummary !== '' ? '  |  ' : '') . 'Generated: ' . date('M j, Y g:i A'));

    $letterhead = [
        $pad(xlsx_title_cell('BAMBOSA Irrigation Community', 'color="#1F7A4D" font-size="16"', true, false)),
        $pad(xlsx_title_cell('Barangay Bikal, Libmanan, Camarines Sur', 'font-size="11"', false, false)),
        $pad(xlsx_title_cell($reportTitle, 'font-size="13"', true, false)),
        $pad(xlsx_title_cell($infoLine, 'color="#666666" font-size="10"', false, true)),
    ];
    $letterheadRows = count($letterhead);

    $header = [];
    foreach ($columns as $c) {
        $header[] = '<b><style bgcolor="#1F7A4D" color="#FFFFFF"><middle><center>' . $c[0] . '</center></middle></style></b>';
    }
    $data = $letterhead;
    $data[] = $header;

    foreach ($rows as $r) {
        $line = [];
        foreach (member_export_values($r, $layout) as $v) {
            if (is_float($v)) {
                $line[] = '<style nf="0.00">' . number_format($v, 2, '.', '') . '</style>';
            } elseif (is_int($v)) {
                $line[] = $v;
            } else {
                $line[] = \Shuchkin\SimpleXLSXGen::raw($v);   // plain text, never parsed
            }
        }
        $data[] = $line;
    }

    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($data, $sheetTitle);
    $xlsx->setAuthor('BAMBOSA')
         ->setCompany('BAMBOSA - Brgy Bikal')
         ->freezePanes('A' . ($letterheadRows + 2))
         ->autoFilter('A' . ($letterheadRows + 1) . ':' . \Shuchkin\SimpleXLSXGen::coord2cell($colCount - 1, count($data) - 1));
    for ($i = 1; $i <= $letterheadRows; $i++) {
        $xlsx->mergeCells('A' . $i . ':' . \Shuchkin\SimpleXLSXGen::coord2cell($colCount - 1, $i - 1));
    }
    foreach ($columns as $i => $c) {
        $xlsx->setColWidth($i + 1, $c[1]);
    }
    return $xlsx->downloadAs($filename . '.xlsx');
}
