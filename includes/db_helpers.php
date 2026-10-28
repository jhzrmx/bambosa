<?php
/**
 * Small PDO helpers shared by the includes/*.php data-access files, so the
 * "bind the params, bind LIMIT/OFFSET, execute, fetch" block (and the
 * search-term LIKE clause) is written once instead of in every list/count
 * function. PHP 7.4-compatible.
 *
 * Note: every named placeholder must be unique within a query (native
 * prepared statements can't reuse one), which db_like_clause() takes care
 * of for search terms.
 */

/** Binds each param with its PDO type: ints as INT, everything else as STR. */
function db_bind_all(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/**
 * Runs a SELECT and returns every row. `LIMIT :limit OFFSET :offset` is
 * appended only when both $limit and $offset are given; leave both null to
 * get every matching row.
 */
function db_fetch_all(PDO $pdo, string $sql, array $params = [], ?int $limit = null, ?int $offset = null): array
{
    $paged = $limit !== null && $offset !== null;
    if ($paged) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    db_bind_all($stmt, $params);
    if ($paged) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Runs a `SELECT COUNT(*) AS cnt ...` query and returns the number. */
function db_count(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    db_bind_all($stmt, $params);
    $stmt->execute();
    return (int) $stmt->fetch()['cnt'];
}

/**
 * Builds the search clause "(col1 LIKE :search1 OR col2 LIKE :search2 ...)"
 * for a search term, with its bound params. Returns ['', []] when there is
 * no term, so callers can skip it. Note "0" is a valid term.
 *
 * @param string[] $columns columns (with table alias if needed) to match
 * @return array [string $clause, array $params]
 */
function db_like_clause(array $columns, ?string $term, string $prefix = 'search'): array
{
    if ($term === null || $term === '') {
        return ['', []];
    }
    $parts  = [];
    $params = [];
    foreach (array_values($columns) as $i => $column) {
        $name = $prefix . ($i + 1);
        $parts[]       = $column . ' LIKE :' . $name;
        $params[$name] = '%' . $term . '%';
    }
    return ['(' . implode(' OR ', $parts) . ')', $params];
}
