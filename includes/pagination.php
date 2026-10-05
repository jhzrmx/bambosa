<?php
/**
 * Small shared helper for paginating officer list pages.
 * Keeps the "how many rows, what page am I on, how many pages total"
 * math in one place instead of repeating it on every list page.
 */

const PAGINATION_PER_PAGE = 15;

/**
 * PHP 7.4-compatible substring check (str_contains() is PHP 8.0+, and this
 * codebase targets PHP 7.4+). Behaves the same: true if $needle occurs
 * anywhere in $haystack, including the empty-string case.
 */
if (!function_exists('str_has')) {
    function str_has(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/**
 * Reads and sanitizes the current page number from a query param.
 * Always returns an integer >= 1.
 */
function get_current_page(string $param = 'page'): int
{
    $page = (int) ($_GET[$param] ?? 1);
    return $page > 0 ? $page : 1;
}

/**
 * Reads and trims a search-term query param.
 * Returns null (not an empty string) when there is no search term, so
 * callers can pass it straight into a `?search : null` data-access
 * function signature without an extra check.
 */
function get_search_term(string $param = 'q'): ?string
{
    $term = trim((string) ($_GET[$param] ?? ''));
    return $term !== '' ? $term : null;
}

/**
 * Builds the small metadata array views need to render page numbers /
 * prev-next links: current page, total pages, total rows, and the
 * LIMIT/OFFSET to hand to the data-access layer.
 */
function build_pagination(int $totalRows, int $currentPage, int $perPage = PAGINATION_PER_PAGE): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $currentPage = min(max(1, $currentPage), $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_rows'   => $totalRows,
        'per_page'     => $perPage,
        'total_pages'  => $totalPages,
        'current_page' => $currentPage,
        'offset'       => $offset,
    ];
}

/**
 * Renders a "Showing X-Y of Z" + prev/next/page-number nav.
 * $baseUrl should already be run through base_url() and include any
 * filters/search that must be preserved (e.g. 'officer/members.php?status=active&q=juan').
 * Page links are appended as &{$pageParam}=N (or ?{$pageParam}=N if
 * $baseUrl has no query string yet).
 *
 * $pageParam: override the query param name used for page links. Only
 * needed when a single page hosts two independent paginated tables (e.g.
 * member/machinery_history.php's reservation history AND equipment
 * condition tables) — paginating one must not disturb the other's
 * current page. Must match the $param passed to get_current_page() for
 * the pagination this is rendering.
 */
function render_pagination_controls(array $pagination, string $baseUrl, string $pageParam = 'page'): void
{
    if ($pagination['total_pages'] <= 1 && $pagination['total_rows'] <= $pagination['per_page']) {
        return;
    }

    $sep = str_has($baseUrl, '?') ? '&' : '?';
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];

    $firstRow = $pagination['total_rows'] === 0 ? 0 : $pagination['offset'] + 1;
    $lastRow = min($pagination['offset'] + $pagination['per_page'], $pagination['total_rows']);
    ?>
    <div class="pagination">
      <div class="pagination-summary">
        Showing <?= $firstRow ?>&ndash;<?= $lastRow ?> of <?= $pagination['total_rows'] ?>
      </div>
      <div class="pagination-links">
        <?php if ($current > 1): ?>
          <a href="<?= htmlspecialchars($baseUrl . $sep . $pageParam . '=' . ($current - 1)) ?>" class="page-link">&larr; Prev</a>
        <?php else: ?>
          <span class="page-link disabled">&larr; Prev</span>
        <?php endif; ?>

        <?php
        // Show up to 5 page numbers centered on the current page.
        $windowStart = max(1, $current - 2);
        $windowEnd = min($total, $windowStart + 4);
        $windowStart = max(1, $windowEnd - 4);
        ?>

        <?php if ($windowStart > 1): ?>
          <a href="<?= htmlspecialchars($baseUrl . $sep . $pageParam . '=1') ?>" class="page-link">1</a>
          <?php if ($windowStart > 2): ?><span class="page-ellipsis">&hellip;</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
          <a href="<?= htmlspecialchars($baseUrl . $sep . $pageParam . '=' . $p) ?>" class="page-link <?= $p === $current ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($windowEnd < $total): ?>
          <?php if ($windowEnd < $total - 1): ?><span class="page-ellipsis">&hellip;</span><?php endif; ?>
          <a href="<?= htmlspecialchars($baseUrl . $sep . $pageParam . '=' . $total) ?>" class="page-link"><?= $total ?></a>
        <?php endif; ?>

        <?php if ($current < $total): ?>
          <a href="<?= htmlspecialchars($baseUrl . $sep . $pageParam . '=' . ($current + 1)) ?>" class="page-link">Next &rarr;</a>
        <?php else: ?>
          <span class="page-link disabled">Next &rarr;</span>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

/**
 * Renders a simple GET search box that preserves any other query params
 * currently on the page (e.g. a status filter tab, a record id) via hidden
 * inputs, and resets to page 1 on every new search.
 *
 * IMPORTANT: $actionUrl must be the bare page URL with NO query string —
 * browsers drop any query string already on a GET form's action attribute
 * when submitting, keeping only the form's own fields. Anything that needs
 * to survive the search (status filter, ?id=, etc.) must be passed via
 * $preserveParams so it's rendered as a hidden field instead.
 *
 * $preserveParams: extra query params (name => value) to preserve as hidden
 * fields, e.g. ['status' => $statusFilter, 'id' => $periodId].
 *
 * $searchParam: override the query param name this search box submits as.
 * Only needed when a single page hosts two independent searchable tables
 * (e.g. member/machinery_history.php's reservation history AND equipment
 * condition tables) — searching one must not disturb the other's current
 * search term. Must match the $param passed to get_search_term() for the
 * search this is rendering, and the page-reset link below is built to
 * match (clearing this box's term, not the other table's).
 */
function render_search_box(string $actionUrl, ?string $currentTerm, string $placeholder, array $preserveParams = [], string $searchParam = 'q'): void
{
    // Defensive: if a caller accidentally passes a URL with a query string,
    // move it into $preserveParams instead of silently losing it on submit.
    $queryPos = strpos($actionUrl, '?');
    if ($queryPos !== false) {
        parse_str(substr($actionUrl, $queryPos + 1), $existingParams);
        $preserveParams = $existingParams + $preserveParams;
        $actionUrl = substr($actionUrl, 0, $queryPos);
    }

    // Build the "clear" link (bare action URL + preserved params, no search term).
    $clearParams = [];
    foreach ($preserveParams as $name => $value) {
        if ($value !== null && $value !== '') {
            $clearParams[$name] = $value;
        }
    }
    $clearUrl = $actionUrl . ($clearParams ? '?' . http_build_query($clearParams) : '');
    ?>
    <form method="GET" action="<?= htmlspecialchars($actionUrl) ?>" class="search-form">
      <?php foreach ($preserveParams as $name => $value): ?>
        <?php if ($value !== null && $value !== ''): ?>
          <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <input type="search" name="<?= htmlspecialchars($searchParam) ?>" value="<?= htmlspecialchars($currentTerm ?? '') ?>" placeholder="<?= htmlspecialchars($placeholder) ?>" class="search-input">
      <button type="submit" class="search-btn">Search</button>
      <?php if ($currentTerm): ?>
        <a href="<?= htmlspecialchars($clearUrl) ?>" class="search-clear">Clear</a>
      <?php endif; ?>
    </form>
    <?php
}
