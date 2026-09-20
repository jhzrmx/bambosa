# BAMBOSA Irrigation Management Platform

This build now covers the platform end-to-end: **authentication**,
**Epic 2: Member & Farm Management**, **Sector Management** (CRUD),
a simplified **Machinery Reservation** module, a simplified **Rotation
Scheduling** viewer, **Epic 3: ISF Billing** (billing periods, bill
generation, payment tracking), a **Farm Map view** (web-based equivalent
of the paper's QGIS mapping), and a fully **responsive UI** (mobile-friendly
slide-in sidebar drawer). Every module described in Chapter 1's specific
objectives is now implemented, in simplified form where noted below.

## Folder Structure

```
bambosa/
├── index.php               # Public landing page
├── login.php                # Login form + auth logic
├── signup.php                # Signup form (registers as "member")
├── logout.php                # Destroys session, redirects to login
├── forgot_password.php        # Request a password reset email
├── reset_password.php          # Lands from the emailed reset link; sets new password
├── calendar_data.php          # JSON endpoint the dashboard calendar fetches when navigating months
│                                # (lives here, not in includes/, since includes/.htaccess blocks direct HTTP access)
├── config/
│   ├── database.php          # PDO connection (edit credentials here)
│   ├── smtp.php                # SMTP credentials + settings for outgoing email (edit here)
│   └── .htaccess              # Blocks direct browser access
├── vendor/
│   ├── phpmailer/phpmailer/src/  # PHPMailer 6.12.0 core files (Exception/PHPMailer/SMTP.php) — vendored directly, see VERSION.txt inside
│   └── .htaccess              # Blocks direct browser access
├── includes/
│   ├── base_path_utils.php     # BAMBOSA_BASE_PATH detection + base_url() helper
│   ├── session.php              # session_start() bootstrap (uses base_path_utils.php for cookie path)
│   ├── auth.php                 # require_login(), require_role(), CSRF helpers (uses base_path_utils.php for redirects)
│   ├── members.php              # Member, farm-lot, sector & map data access
│   ├── sector_boundaries.php     # Polygon storage, point-in-polygon test, auto-assignment logic
│   ├── farm_lot_boundaries.php    # Farm lot polygon storage + centroid computation
│   ├── machinery.php             # Machinery + reservation data access
│   ├── schedules.php              # Rotation schedule data access
│   ├── billing.php                 # ISF billing periods, bills, payments data access
│   ├── pagination.php               # Shared search/pagination helpers for officer list pages
│   ├── calendar_events.php           # Builds the dashboard calendar's merged schedule + reservation event list
│   ├── mailer.php                     # PHPMailer wrapper — send_email(), render_email_layout()
│   ├── password_reset.php              # Forgot-password token generation, verification, consumption
│   ├── partials/
│   │   ├── officer_sidebar.php     # Shared officer nav (topbar + drawer + overlay)
│   │   ├── member_sidebar.php       # Shared member nav (topbar + drawer + overlay)
│   │   └── .htaccess
│   └── .htaccess
├── officer/
│   ├── dashboard.php           # Protected — role must be 'officer'
│   ├── profile.php              # Edit own name/contact/password
│   ├── members.php              # List/filter members, approve/deactivate
│   ├── member_view.php           # View one member + manage their farm lots (CRUD, with auto-assign option)
│   ├── update_status.php          # POST handler for approve/deactivate/reactivate
│   ├── sectors.php                 # Sector CRUD (name, description, usage counts)
│   ├── sector_boundaries.php        # Draw/edit sector boundary polygons on a map
│   ├── sector_boundary_save.php      # AJAX: save a polygon, optionally trigger auto-assignment
│   ├── sector_boundary_preview.php    # AJAX: live "N farm lots would match" preview while drawing
│   ├── billing_periods.php             # Open/edit/close/delete billing periods
│   ├── billing_period_view.php          # Generate bills, view/filter bills, record payments
│   ├── machinery.php                     # Manage machinery list (add/edit/delete/availability)
│   ├── reservations.php                   # Approve/reject/cancel member reservation requests
│   ├── schedules.php                       # Create/edit/delete water rotation schedule entries
│   └── map.php                              # All members' farm lots + sector boundaries on a Leaflet map
├── member/
│   ├── dashboard.php           # Protected — role must be 'member'
│   ├── profile.php              # Edit own name/contact/password; view (read-only) farm lots
│   ├── billing.php               # View own ISF bills + payment history across all periods
│   ├── machinery.php              # Request a reservation; view own reservation history
│   ├── schedule.php                # View upcoming water rotation for own sector + all sectors
│   └── map.php                      # View own farm lot(s) plotted on a Leaflet map
├── assets/
│   ├── css/style.css           # All styling, including responsive breakpoints + map styles
│   ├── img/
│   │   ├── favicon.png            # Site favicon (rice-stalk logo), linked on every page
│   │   └── favicon.ico             # Multi-size (16/32/48px) fallback for older browsers
│   └── js/
│       ├── dashboard.js         # Mobile sidebar drawer toggle (vanilla JS, no deps)
│       ├── dashboard-calendar.js # Dashboard calendar widget — month grid, day details, AJAX month nav (vanilla JS)
│       ├── sector-boundaries.js  # Leaflet.draw polygon editor + live preview + save (vanilla JS)
│       └── farm-lot-boundary.js   # Leaflet.draw editor for a single farm lot's boundary (vanilla JS)
├── sql/
│   ├── bambosa_db.sql         # Full schema (fresh install) + seed officer account
│   ├── migrations/
│   │   ├── 001_isf_billing.sql      # Adds billing_periods/isf_bills/isf_payments to an EXISTING db
│   │   ├── 002_sector_polygons.sql   # Adds sectors.boundary_geojson + sector_auto_assignments to an EXISTING db
│   │   ├── 003_farm_lot_polygons.sql  # Adds farm_lots.boundary_geojson to an EXISTING db
│   │   ├── 004_schedule_groups.sql     # Adds rotation_schedules.group_id to an EXISTING db
│   │   ├── 005_password_resets.sql      # Adds the password_resets table to an EXISTING db
│   │   └── .htaccess
│   └── .htaccess
└── serve.bat                  # Windows convenience script — runs `php -S localhost:8000`
                                # from the project root; checks PHP is on PATH first
```

## Setup (XAMPP)

**Requirements:** PHP 7.4 or newer, MySQL/MariaDB. The codebase
intentionally avoids PHP 8-only syntax (`str_contains()`, `match`,
constructor property promotion, union types, etc.) so it also runs on
older PHP 7.4 stacks some school/hosting environments are still on. If a
PHP 8+-only function would otherwise be the natural choice, a small
`if (!function_exists(...))`-guarded 7.4-compatible helper is added next
to where it's needed instead (e.g. `str_has()` in `includes/pagination.php`,
a `strpos()`-based stand-in for `str_contains()`).

**Optional — outgoing email (forgot password, activation notices):**
PHPMailer 6.12.0's core files are already vendored in under
`vendor/phpmailer/phpmailer/src/`, so the only setup step is filling in
real credentials in `config/smtp.php` and setting `SMTP_ENABLED = true`.
Nothing else in the app depends on this — every other feature works
fully without it. See "Outgoing Email" further down for the complete
setup steps and what happens if this is skipped.

**Fresh install (no existing database):**
1. Copy the `bambosa/` folder into `htdocs/`.
2. Start Apache + MySQL in XAMPP.
3. Open phpMyAdmin, import `sql/bambosa_db.sql`. This single file creates
   every table — including ISF billing and sector boundaries — so you don't
   need to run anything from `sql/migrations/` separately.
4. Edit `config/database.php` with your DB username/password (XAMPP
   default is usually `root` with empty password — already set).
5. Visit `http://localhost/bambosa/`.

**Quick local run without Apache (Windows):** double-click `serve.bat` in
the project root. It checks that `php` is available on your `PATH` first
(some bundled Apache+MySQL/MariaDB+PHP stacks, unlike XAMPP, don't add PHP
to `PATH` automatically) and tells you what to do if it isn't found,
otherwise it starts PHP's built-in server (`php -S localhost:8000`) so you
can visit `http://localhost:8000/`. You still need MySQL/MariaDB running
separately and `config/database.php` configured, since the built-in server
only replaces Apache, not the database.

**Existing install (database created before a given feature was added):**
1. Pull the updated code into your `bambosa/` folder as normal.
2. In phpMyAdmin, open your existing `bambosa_db` database and import
   whichever migration file(s) you haven't run yet, **in numeric order**:
   - `sql/migrations/001_isf_billing.sql` — adds `billing_periods`,
     `isf_bills`, `isf_payments`.
   - `sql/migrations/002_sector_polygons.sql` — adds `boundary_geojson` to
     `sectors` and creates `sector_auto_assignments`.
   - `sql/migrations/003_farm_lot_polygons.sql` — adds `boundary_geojson`
     to `farm_lots`.
   - `sql/migrations/004_schedule_groups.sql` — adds `group_id` to
     `rotation_schedules`.
   - `sql/migrations/005_password_resets.sql` — creates the
     `password_resets` table.
3. None of this touches data you already have in `users`, `farm_lots`,
   `sectors`, etc. All migrations are safe to re-run if you're ever unsure
   whether one was already applied (`CREATE TABLE IF NOT EXISTS` throughout,
   plus a column-existence / idempotency check before altering tables or
   adding indexes).

Future schema changes will follow this same pattern: a new
`sql/migrations/NNN_description.sql` file for existing installs, with the
same statements folded into `sql/bambosa_db.sql` so a fresh install always
gets the complete, current schema in one import.

## Works from any subdirectory (or the domain root)

The app does **not** hardcode a leading `/` on any link, form action,
asset URL, or redirect. Instead, every one of those is built with a
`base_url($path)` helper, which resolves against a `BAMBOSA_BASE_PATH`
constant computed once per request from `$_SERVER['SCRIPT_NAME']`. Both
of these live together in `includes/base_path_utils.php`, kept separate
from `session.php` (session bootstrapping) and `auth.php` (login/role
logic) since base-path resolution is really its own concern — it's just
that both of those files happen to depend on it (`session.php` needs it
to scope the session cookie path; `auth.php` needs it for
`require_login()`/`redirect_to_dashboard()`'s redirect URLs).

This means the exact same codebase works unmodified whether it's:
- served from the domain root (`http://localhost/`)
- installed in a subdirectory (`http://localhost/bambosa/`)
- installed several folders deep (`http://localhost/some/nested/path/bambosa/`)

No config value needs to be set for this — it's detected automatically.
The session cookie's `path` is also scoped to the detected base path
(instead of always `/`), which is the technically correct behavior for a
subdirectory install.

**When adding new pages**, always use `base_url()` instead of a hardcoded
leading-slash path:

```php
// Good — works from any install location
<a href="<?= base_url('officer/reports.php') ?>">Reports</a>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
header('Location: ' . base_url('officer/dashboard.php'));

// Bad — breaks if the app isn't served from the domain root
<a href="/officer/reports.php">Reports</a>
header('Location: /officer/dashboard.php');
```

Every new page should `require_once __DIR__ . '/includes/session.php';`
then `require_once __DIR__ . '/includes/auth.php';` (adjusting the path
for pages under `officer/`/`member/`) before calling `base_url()`, exactly
like every existing page already does. You do **not** need to separately
`require_once` `base_path_utils.php` yourself — both `session.php` and
`auth.php` already pull it in, so it's available automatically as soon as
either of those is loaded.

This app currently only has pages one folder deep (`officer/`, `member/`)
or at the root — if a future page goes two folders deep, the base-path
detection logic in `includes/base_path_utils.php` (the officer/member
regex) would need a small update to strip an extra segment.

## Search & pagination on management (CRUD) pages

Every officer list page — Members, Sectors, Machinery, Reservations,
Schedules, Billing Periods, and a Billing Period's Bills — now has a
search box and pagination instead of dumping every row into one long
table:

- **Search** is a simple `?q=` GET parameter, matched server-side with
  a case-insensitive `LIKE '%term%'` against the relevant text columns
  for that list (e.g. member name/email/contact; machinery name/description;
  reservation machinery/requester/purpose). It combines with any existing
  status filter tab (e.g. searching while on the "Pending" members tab
  searches only pending members).
- **Pagination** shows 15 rows per page (`PAGINATION_PER_PAGE` in
  `includes/pagination.php`), with a "Showing X–Y of Z" summary and
  prev/next/page-number links. It's hidden automatically when everything
  fits on one page.
- Both are implemented in `includes/pagination.php`:
  - `get_search_term()` / `get_current_page()` — read and sanitize the
    `?q=` / `?page=` query params.
  - `build_pagination()` — turns a total row count + current page into
    the limit/offset/page-count metadata the view needs.
  - `render_search_box()` / `render_pagination_controls()` — render the
    actual search form and page-link markup, reused identically across
    every list page.
- Every data-access "get all X" function (`get_all_members()`,
  `get_sectors_with_usage()`, `get_all_machinery()`,
  `get_all_reservations()`, `get_all_schedules()`, `get_billing_periods()`,
  `get_bills_for_period()`) now takes optional `$search`, `$limit`, and
  `$offset` parameters, each paired with a `count_*()` function
  (`count_members()`, `count_sectors()`, etc.) that runs the same
  filters without `LIMIT`/`OFFSET` to get the total for the pagination
  summary. Passing `null` for `$limit`/`$offset` returns every matching
  row unpaginated, so anywhere else in the codebase that still calls
  these functions without the new arguments keeps working unchanged
  (e.g. `member/schedule.php` listing a member's own upcoming schedule).
- **All of this goes through prepared statements** — search terms are
  bound as `LIKE` parameters (never string-concatenated into the SQL),
  and `LIMIT`/`OFFSET` values are bound explicitly as
  `PDO::PARAM_INT`. The only dynamic SQL fragments (e.g. appending
  `AND status = :status` only when a filter is active) are hardcoded
  strings chosen by server-side logic, never built from raw user input,
  so there's no SQL injection surface here or elsewhere in the app.
- Search boxes are plain GET forms. Since a GET form's `action` URL
  loses its own query string on submit (the browser only sends the
  form's fields), anything that needs to survive a search — a status
  filter, a billing period's `?id=`, etc. — is passed as a hidden
  input via `render_search_box()`'s `$preserveParams` argument rather
  than baked into the URL.

## Sidebar icons

`includes/partials/officer_sidebar.php` and `includes/partials/member_sidebar.php`
render each nav link with an inline SVG icon (Material Design Icons paths,
`fill="white"`, 24×24 viewBox) instead of emoji/text-only links, so the
sidebar renders consistently across platforms and browsers. To add a new
nav item, follow the existing pattern: an `<svg>` with an `<path>` d`
attribute, followed by a `<span>` with the label text, both inside the
`<a>` tag.

## Favicon

`assets/img/favicon.png` (the rice-stalk logo) and `assets/img/favicon.ico`
(a multi-size 16/32/48px fallback generated from the same image, for
older browsers and OS-level favicon caches that don't support PNG
favicons) are linked in the `<head>` of every page — every file in
`member/`, `officer/`, and the root-level auth pages
(`index.php`, `login.php`, `signup.php`, `forgot_password.php`,
`reset_password.php`). The one exception is `includes/mailer.php`'s
`<head>`, which belongs to an HTML email template, not a browser page —
email clients don't render favicons, so there's nothing to link there.

Both links use `base_url()` like every other asset reference in the app,
so the favicon resolves correctly regardless of which subdirectory (or
domain root) BAMBOSA is installed under — see "Works from any
subdirectory" above.

To replace the logo later: swap out `assets/img/favicon.png` with a new
image (ideally square, at least 144×144 so it stays sharp on high-DPI
displays) and regenerate `favicon.ico` from it — any PNG-to-ICO
conversion tool works, since it's a small offline design asset, not
something that needs regenerating at request-time.

## Direct directory access (`/member/`, `/officer/`)

Both `member/index.php` and `officer/index.php` now exist so that a user
who browses to the bare directory URL (e.g. `http://localhost/bambosa/member/`)
is routed somewhere sensible instead of hitting a server-generated
directory listing or a blank/404 response (depending on server config).
Each just `require_once`s that folder's `dashboard.php`, which in turn
enforces `require_login()` / `require_role()` as normal — so an
unauthenticated visit still correctly redirects to `login.php`, and a
wrong-role visit still redirects to that user's own dashboard.

## Default officer account

The seed SQL inserts an officer row with a **12345678** password hash.
You can also generate a real one:

```bash
php -a
echo password_hash('YourNewPassword123', PASSWORD_DEFAULT);
```

Run that command snippet once then replace the output of
`password_hash` value in the officer row in the database.

## How auth works

- Passwords are hashed with `password_hash()` / verified with `password_verify()`.
- Sessions are hardened (`httponly`, `samesite=Lax`) via `includes/session.php`.
- `includes/auth.php` provides:
  - `require_login()` — kicks unauthenticated users to `/login.php`
  - `require_role('officer'|'member')` — kicks users with the wrong role to their own dashboard
  - `csrf_token()` / `csrf_verify()` — CSRF protection on login/signup forms
- New signups are inserted with `status = 'pending'` and role `member`.
  An officer approves/activates them from `officer/members.php`
  (`officer/update_status.php`) — see Epic 2 below — before they can log
  in. Officer accounts are **not** self-registrable — provision them
  directly in the database for security.
- Forgotten passwords are self-service via `forgot_password.php` /
  `reset_password.php` — see "Outgoing Email" further down for the full
  writeup of how that flow works and what it depends on (SMTP being
  configured).

## Dashboard calendar

Both dashboards (`member/dashboard.php` and `officer/dashboard.php`) show
a month calendar below the stat cards, so an officer or member can see
what's coming up — rotation schedules and machinery reservations — at a
glance instead of clicking into each management page separately.

- **What shows up:** water release windows (`rotation_schedules`) and
  pending/approved machinery reservations (`machinery_reservations`),
  merged into one list per day. Rejected/cancelled reservations are left
  out since they aren't actually happening.
- **Scoped per role:** members only see schedules for their own farm
  lot's sector(s) and their own reservations; officers see every sector's
  schedule and every member's reservations, matching what each role can
  already see elsewhere in the app.
- **Layout:** a two-column card — a bigger month grid on the left, a
  detail panel on the right showing whatever day is selected — deliberately
  sized larger than the stat cards above it (`.dash-calendar` in
  `assets/css/style.css`) since it's meant to be the dashboard's visual
  centerpiece rather than another small tile. On narrow screens the detail
  panel drops below the grid instead of squeezing side-by-side.
- **Multi-day events**: a reservation or schedule window spanning several
  days shows a dot on every day it touches, not just its start day
  (`expand_event_across_days()` in `includes/calendar_events.php`).
- **How it loads:** the current month's events are queried server-side
  and embedded directly in the page (`window.BAMBOSA_CALENDAR_INITIAL` in
  the dashboard's inline `<script>`), so the calendar has content
  immediately on page load. Clicking the prev/next month arrows then
  fetches that month's events as JSON from `calendar_data.php`
  via `fetch()`, so browsing other months doesn't reload the whole page.
  All the grid rendering, month navigation, and day-click handling is
  plain vanilla JS in `assets/js/dashboard-calendar.js` — no calendar
  library, consistent with the rest of the stack.
- `calendar_data.php` only requires the person be logged in (either
  role); it doesn't use `require_role()` like most pages since both roles
  hit the same endpoint, but scopes every query to the session's own
  `user_id`/role internally so a member can never pull another member's
  reservations or an officer-only view through it.
- **Why this file lives at the project root, not in `includes/`:**
  `includes/.htaccess` has `Require all denied`, which blocks *all*
  direct HTTP access to that folder outright — it's meant only for
  library files loaded via `require_once`, never fetched directly by the
  browser. An AJAX endpoint the browser calls with `fetch()` needs to be
  somewhere actually reachable, so `calendar_data.php` sits next to
  `index.php`/`login.php` at the project root instead, and only
  `require_once`s what it needs from `includes/` and `config/` (the same
  pattern every other root-level page already uses).

## Responsive UI / mobile sidebar

All dashboard pages (officer and member) share two partials —
`includes/partials/officer_sidebar.php` and `includes/partials/member_sidebar.php`
— so nav links, active-state highlighting, and the mobile drawer markup
only need to be maintained in one place per role.

- **Desktop (>880px):** sidebar is a fixed-width column, as before.
- **Mobile/tablet (≤880px):** the sidebar becomes a slide-in drawer,
  hidden off-screen by default. A green topbar (`.dash-topbar`) appears
  with a hamburger button; tapping it slides the drawer in over a dark
  overlay (`.dash-overlay`). Tapping the overlay, the ✕ close button, or
  any nav link inside the drawer closes it again.
- The toggle logic lives in `assets/js/dashboard.js` — plain vanilla JS,
  no build step or framework, consistent with the rest of the stack. It
  looks for `#dashSidebar` / `#dashOverlay` and any `[data-drawer-toggle]`
  / `[data-drawer-close]` elements, so adding the drawer to a future page
  is just: include the sidebar partial + `<script src="/assets/js/dashboard.js">`.
- Every `<table class="data-table">` is wrapped in a `.table-scroll` div,
  so wide tables (member lists, farm lots, reservations, schedules) scroll
  horizontally on narrow screens instead of squeezing columns unreadably.
- Forms (`.form-row`), the landing page hero/nav, and the login/signup
  cards all have breakpoints so nothing overflows or overlaps below
  ~480px width.
- To add a new dashboard page later: copy the pattern in any existing
  `officer/*.php` or `member/*.php` file — set `$activeNav`, open
  `<div class="dash-shell">`, `include` the relevant sidebar partial,
  add `<main class="dash-main">`, and include `dashboard.js` before `</body>`.

## Account self-service (profile & password) — both roles

Every logged-in user — officer or member — can update their own name,
contact number, and password without needing another officer to do it
for them:

- **Members**: `member/profile.php` — account info (name, contact) plus
  password change, above the read-only farm-lot table already described
  in Epic 2 above.
- **Officers**: `officer/profile.php` — same account info + password
  change form, linked from a new "My Profile" item in the officer
  sidebar (there previously wasn't one; officers had no self-service
  page at all).
- **Email is intentionally read-only** on both pages — changing it would
  need a re-verification flow (confirm the new address is really theirs)
  that's out of scope for now, so it's shown disabled with a note instead
  of silently allowed.
- **Password changes** require the current password to be entered and
  verified (`change_user_password()` in `includes/members.php`) before a
  new one is accepted — this stops a hijacked/unattended logged-in
  session from being used to lock the real owner out by changing their
  password. Both roles share the same function, since there's nothing
  role-specific about changing your own password. The new password must
  be at least 8 characters (same rule signup.php already enforces) and
  must be entered twice to catch typos.
- Both forms are on the same page but distinguished by a hidden
  `form=password` field, so the two `<form>`s can post to the same
  endpoint (`member/profile.php` / `officer/profile.php`) without
  interfering with each other, and each shows its own success/error
  state independently.
- **What's still officer-only:** role, status (active/inactive/pending),
  and farm lot data are unchanged — those still require another officer
  to edit, exactly as before, since self-editing status or land area
  would be a billing/security integrity risk (see Epic 2 above for why).

## Epic 2: Member & Farm Management (implemented)

- `farm_lots` table stores one or more land parcels per member: name/label,
  sector, land area (ha), a drawn boundary polygon (`boundary_geojson`),
  and notes. `latitude`/`longitude` are also stored, but only as a value
  derived from the polygon — see "Farm Lot Polygons" below.
- **Officers** can: view all members with status filters (all/pending/active/inactive),
  approve pending signups, deactivate/reactivate accounts, and add/edit/delete
  farm lots for any member (`officer/member_view.php`).
- **Members** can: update their own name/contact number, and view (read-only)
  their registered farm lots and total land area (`member/profile.php`).
  Land area and sector are officer-managed only, since they directly drive
  ISF billing in Epic 3 — self-editing would create a billing integrity risk.
- All data access goes through `includes/members.php` (prepared statements only).
- **Location is set by drawing a boundary, not by picking a point.** The
  farm lot form has a single map (`assets/js/farm-lot-boundary.js`,
  Leaflet.draw) for drawing the lot's actual shape as a polygon — see
  "Farm Lot Polygons" below for the full writeup of how that works and why
  there's no separate point-picker or coordinate fields anymore.

## Machinery Reservation System (implemented — simplified version)

- `machinery` table holds the equipment list (name, description, available flag).
- `machinery_reservations` holds member requests against a date range with a
  `pending → approved/rejected` (or `cancelled`) status flow.
- **Officers** manage the equipment list (`officer/machinery.php`) and review/
  approve/reject/cancel requests (`officer/reservations.php`).
- **Members** submit requests and view their own reservation history
  (`member/machinery.php`).
- `has_overlapping_reservation()` in `includes/machinery.php` does a simple
  date-range overlap check and shows the *member* a warning at request time.
  `officer/reservations.php` re-runs the same check per row and shows a
  "⚠ Overlap" badge next to any pending/approved reservation that overlaps
  another pending/approved reservation for the same machine, so the officer
  sees it too, right where they approve/reject — not just at request time.
  It does **not** block submission or auto-block officer approval — the
  officer always makes the final call. This is the "flag, don't block"
  version; a stricter conflict-prevention engine that hard-blocks
  double-booking could be layered on later if ever needed.

## Rotation Scheduling — basic viewer (implemented — simplified version)

- `rotation_schedules` table: one row per water-release window for a sector
  (start/end datetime, notes, which officer posted it).
- **Officers** create/edit/delete schedule entries (`officer/schedules.php`).
- **Members** see the upcoming schedule filtered to their own farm lot's
  sector(s), plus a full reference list of all sectors' upcoming releases
  (`member/schedule.php`).
- This is the straightforward CRUD + listing version described in the
  capstone's Epic 4, extended with the same lightweight conflict-flagging
  approach used for machinery reservations: `has_overlapping_schedule()` in
  `includes/schedules.php` checks whether a sector already has another
  schedule entry overlapping a given time window. `officer/schedules.php`
  shows a "⚠ Overlap" badge on any row that overlaps another entry in the
  same sector, and shows a warning banner after saving one that does.
  As with machinery, this is informational only — schedules are still an
  officer judgment call, since an intentional correction or extension can
  legitimately overlap an existing window.

### Applying the same schedule to multiple sectors at once

Client request: several sectors often need the exact same water-release
window (same day, same start/end time), and re-typing it once per sector
was tedious. `officer/schedules.php` now has an "Also create/apply this
same schedule to other sectors" checkbox on the create/edit form:

- **Unchecked (default):** behaves exactly as before — one sector, one
  schedule entry.
- **Checked:** reveals a scrollable list of every other sector as
  checkboxes. Whichever ones are checked get their own schedule row
  created with the identical start time, end time, and notes as the
  primary sector chosen in the dropdown above. The sector currently
  selected in that dropdown is automatically disabled in the checkbox
  list (can't double-apply the schedule to the same sector twice); this
  is enforced with JS for a responsive UI, and independently by
  `array_unique()` server-side regardless of whether JS ran.
- **Editing a schedule that's part of a group:** the checkbox comes
  pre-checked, and the sectors it already shares this schedule with are
  pre-checked in the list, matching what the client asked for. Editing the
  time/notes and saving propagates the change to every checked sector's
  row. Unchecking a sector that used to share the schedule **ungroups**
  it — that sector's schedule entry stays exactly as it was, it just stops
  being kept in sync with this one going forward. It does not get
  deleted: the assumption is unchecking means "stop linking these," not
  "get rid of that sector's schedule," so it becomes a normal standalone
  entry the officer can still edit or delete directly afterwards if
  removing it entirely really was the goal. Editing back down to a single
  sector (or a schedule that was never grouped) behaves as a normal
  single-sector edit.
- **Data model:** rather than trying to infer "the same schedule" from
  matching timestamps (fragile — two officers could coincidentally create
  identical-looking but unrelated entries), a nullable
  `rotation_schedules.group_id` column explicitly tags rows created/kept
  together as one batch. `NULL` means a standalone entry, exactly like
  every schedule created before this feature existed — nothing about
  existing data changes. See `sql/migrations/004_schedule_groups.sql` for
  an existing database, already included in `sql/bambosa_db.sql` for a
  fresh install.
- **Conflict checking** (the "⚠ Overlap" badge above) runs across every
  sector the schedule will end up covering, not just the primary one, so
  a conflict hiding in one of the "other sectors" isn't missed — and
  correctly excludes each sector's own existing row when editing, so an
  unchanged sibling doesn't flag as "conflicting with itself."
- The schedules table shows a "Shared" badge next to any entry that's
  part of a multi-sector group, so it's clear at a glance which schedules
  aren't standalone.
- New functions in `includes/schedules.php`: `create_schedule_group()`
  (used on create when 2+ sectors are involved),
  `update_schedule_group()` (reconciles an edit against whichever sectors
  end up checked — updates existing rows, inserts new ones, ungroups
  (never deletes) unchecked ones), and `get_sibling_schedule_sector_ids()`
  (what pre-checks the list when editing).

## Sector Management (implemented)

- `officer/sectors.php` — full CRUD for the `sectors` table: create, edit,
  and delete sectors, with duplicate-name checking (case-insensitive).
- The table also shows, per sector, how many farm lots and how many
  rotation schedules currently reference it (`get_sectors_with_usage()` in
  `includes/members.php`), so an officer can see what depends on a sector
  before deleting it.
- Deleting a sector is allowed even if farm lots/schedules reference it,
  but the delete button shows a confirmation dialog naming exactly how many
  of each will be affected — farm lots get unassigned (`sector_id` set to
  NULL) while rotation schedules under that sector are permanently deleted,
  matching the two different foreign-key behaviors already defined in the
  schema (`ON DELETE SET NULL` vs `ON DELETE CASCADE`).
- Sector management is officer-only; members are assigned to a sector via
  their farm lot record (Epic 2) and never manage sectors directly.

## Farm Map view (implemented — web-based equivalent of QGIS mapping)

The capstone paper describes using **QGIS** (desktop GIS software) to
manage member/farm location data. QGIS itself isn't a web technology and
can't be embedded in a browser, so what's built here is the web platform's
own visualization layer over the same underlying data: an interactive map
using **Leaflet.js** (open-source, no API key) with **OpenStreetMap** tiles,
both loaded from a CDN.

- `officer/map.php` — plots every farm lot that has coordinates, color-coded
  by sector, with a legend, plus any drawn farm lot boundary polygons
  (dashed, dark grey) layered underneath for detail. Tapping a marker shows
  the lot name, owner, sector, and hectares, plus a link straight to that
  member's record. If any farm lots are missing coordinates, a banner tells
  the officer how many, so incomplete data entry is visible rather than
  silently skipped.
- `member/map.php` — scoped to just the logged-in member's own farm lot(s),
  consistent with the read-only pattern already used on `member/profile.php`.
  Unlike the officer map, this one shows **only the drawn boundary polygon**
  for a lot that has one — no separate point marker — since the point is
  just that polygon's centroid and would be a redundant second symbol for
  the same shape. A lot with coordinates but no polygon (only possible from
  data pre-dating the polygon feature) still falls back to a plain point
  marker so it isn't hidden entirely.
- No new database tables were needed for the point-based version of this —
  it reads `farm_lots.latitude` / `farm_lots.longitude`, and (for drawn
  shapes) `farm_lots.boundary_geojson` — see "Farm Lot Polygons" below.
  Data-access functions live in `includes/members.php`
  (`get_mapped_farm_lots()`, `count_unmapped_farm_lots()`) and
  `includes/farm_lot_boundaries.php` (`get_farm_lots_with_boundaries()`).
- Sector marker colors are assigned dynamically from a fixed palette based
  on whatever sector names exist in the `sectors` table, so adding a 4th,
  5th, etc. sector via `officer/sectors.php` just works without code changes.
- If a real QGIS-exported dataset (e.g. a GeoJSON or shapefile-derived
  table of farm boundaries) becomes available later, this map is the
  natural place to layer it in — Leaflet supports GeoJSON overlays directly.

## Sector Boundaries & Auto-Assignment (implemented)

Since a real QGIS-exported boundary dataset for Barangay Bikal isn't
available yet, this lets an officer **draw** each sector's boundary
directly on the map instead, using the same fundamental technique a GIS
tool uses for a spatial join: point-in-polygon testing. Farm lots whose
coordinates fall inside a drawn boundary can then be assigned to that
sector automatically.

**How it works:**
- `officer/sector_boundaries.php` — pick a sector from the dropdown, draw
  its boundary as a polygon using **Leaflet.draw** (CDN-loaded, no API
  key), edit/reshape it, then save. Other sectors' saved boundaries are
  shown dimmed on the map for spatial context while you draw. A live
  "Preview Matches" button shows which farm lots would fall inside the
  polygon before you commit anything.
- Boundaries are stored as a JSON array of `[lat, lng]` vertex pairs in a
  new `sectors.boundary_geojson` column — intentionally simple (not full
  GeoJSON Feature wrapping) since that's exactly the vertex list
  Leaflet.draw hands back, and PHP only ever needs raw points for the
  point-in-polygon test.
- The point-in-polygon test itself (`point_in_polygon()` in
  `includes/sector_boundaries.php`) is the standard ray-casting algorithm,
  implemented in PHP as the authoritative version. A live-preview version
  of the same logic (used while actively drawing, before saving) runs
  server-side too, via the `officer/sector_boundary_preview.php` AJAX
  endpoint — there's no separate JS re-implementation of the math to keep
  in sync.
- **Auto-assignment behavior:**
  - Saving a boundary can optionally re-run assignment across **every**
    farm lot with coordinates (checkbox, on by default) — only lots whose
    sector would actually change are updated; already-correct lots are
    left alone.
  - On the farm lot form (`officer/member_view.php`), a per-save checkbox
    lets an officer auto-assign just that one lot's sector from whatever
    boundary its coordinates fall inside.
  - **If auto-assignment would overwrite a sector the officer already
    manually selected**, the form stops and shows an explicit confirmation
    step naming the current vs. matched sector before proceeding — it
    never silently overwrites an existing manual assignment.
  - **Overlapping sector boundaries are allowed.** If a point falls inside
    more than one polygon, the first match is used, and the officer is
    told how many sectors overlapped there, rather than the system
    guessing silently or blocking the save.
  - Every automatic change is logged to `sector_auto_assignments` (lot,
    previous sector, new sector, who triggered the run, when) and shown in
    a "Recent Auto-Assignments" table at the bottom of the boundaries page
    — so an officer can always see what the system changed on its own, as
    opposed to a manual edit.
- `officer/map.php` also renders any drawn boundaries as read-only, dimmed
  polygons alongside the farm lot markers, so the general map view and the
  boundary editor feel like one consistent picture of the association's
  geography.
- All of the drawing/preview/save interactivity is vanilla JavaScript
  (`assets/js/sector-boundaries.js`) talking to two small JSON API
  endpoints — no framework, consistent with the rest of the stack.
- **Default map view:** the map centers on the sector currently being
  edited if it already has a boundary drawn (zoomed to fit that shape),
  or a calm view centered on the average location of all farm lots
  (falling back to a fixed Barangay Bikal center if no lots have
  coordinates yet) when it doesn't. Earlier this instead always zoomed to
  fit every farm lot marker on the map regardless of which sector was
  selected, which could land on an oddly tight or off-center view
  depending on how the lots happened to be scattered — the corrected
  version always shows something sensible and predictable on first load,
  matching the same "calm default center" approach the farm lot boundary
  editor below already used.

## Farm Lot Polygons (implemented — revised)

Individual farm lots are drawn as their actual shape on the map, using
the same drawing tool as sector boundaries, applied one level down to an
individual lot. **A farm lot's location is now defined ONLY by its drawn
boundary polygon** — there is no separate point-picker map, and
latitude/longitude are no longer manually editable fields.

- **`farm_lots.boundary_geojson`** stores the polygon: a JSON array of
  `[lat, lng]` vertex pairs, drawn with Leaflet.draw — same format
  `sectors.boundary_geojson` uses. `sql/migrations/003_farm_lot_polygons.sql`
  adds this column to an existing database; a fresh install already has it
  via `sql/bambosa_db.sql`.
- **`latitude`/`longitude` are purely derived, never directly edited.**
  They exist only so anything that needs a single point — the
  point-in-polygon sector auto-assignment check, and the mapped-lot list
  queries — doesn't need to know about polygons at all. Saving a lot
  computes both from the polygon's **centroid** (`polygon_centroid()` in
  `includes/farm_lot_boundaries.php`, a simple vertex average — precise
  enough at farm-lot scale, no extra math library needed) and writes them
  together with the polygon in the same save. A lot with no polygon
  drawn has `NULL` latitude/longitude — there's no "point but no shape"
  state anymore.
- **One form, one save — no separate "Save Boundary" button.** Earlier
  drafts of this feature had the boundary map save itself via its own
  AJAX call, independent of the rest of the lot's fields, which meant an
  officer could save the lot's name/area without realizing the boundary
  they'd drawn was never actually saved (or vice versa). Now the drawn
  polygon is serialized to a hidden `<input name="boundary_points">`
  field (`assets/js/farm-lot-boundary.js` keeps it in sync as the officer
  draws/edits/clears the shape), and it's submitted and saved together
  with everything else in one normal form POST
  (`officer/member_view.php`). This also means drawing a boundary works
  the same for a brand-new lot as for editing an existing one — there's
  no "save the lot first, then come back to draw it" limitation anymore.
- **Server-side**, `normalize_and_center_boundary()` in
  `includes/farm_lot_boundaries.php` validates the submitted points (0
  points is valid — "no boundary" — but 1 or 2 is rejected as an
  incomplete polygon; needs at least 3) and computes the centroid, and
  `create_farm_lot()`/`update_farm_lot()` in `includes/members.php`
  persist the polygon and derived point together in a single query.
- **Where it's shown:** `officer/map.php` renders farm lot polygons
  (thin, dashed, dark grey) alongside sector boundaries (thicker,
  colored) and still also plots the sector-colored point marker for
  quick visual scanning across many lots at once. `member/map.php`
  instead shows **only the polygon** for a member's own lot(s) — no
  redundant point marker once a shape exists, since the point is just
  the shape's centroid; a lot somehow has a point but no polygon (only
  possible from data predating this feature) still falls back to a
  plain marker so it isn't hidden entirely.
- Both the officer's and member's farm-lot tables (`officer/member_view.php`,
  `member/profile.php`) show a "Drawn" / "Not drawn" badge instead of raw
  coordinates, since the coordinates alone aren't the interesting fact
  anymore — whether a shape exists is.
- **Sector auto-assignment is unaffected** by any of this — it already
  worked purely off whatever `latitude`/`longitude` ended up being on the
  lot row, so it automatically now runs against the polygon's centroid
  with no changes needed to `includes/sector_boundaries.php` at all.
- **Show/hide guides while drawing.** The boundary editor on
  `officer/member_view.php` has two independent checkboxes above the map:
  "Show sector boundaries" and "Show other farm lots" — both overlay
  everyone else's drawn shapes as dimmed, dashed, non-editable reference
  layers (sectors in blue, other lots in purple) so an officer can see
  "am I drawing inside the right sector?" or "does this overlap a
  neighboring lot?" without those shapes getting in the way of the one
  polygon actually being edited. Sector context defaults ON (there's
  usually only a handful, and it's the most immediately useful
  reference); other-lots context defaults OFF (there could be many, and
  showing them all immediately could clutter a small lot's own outline
  while it's still being drawn) — either can be toggled anytime. A small
  legend above the map reflects whichever layers are currently visible.
  The currently-edited lot's own boundary is always excluded from its own
  "other farm lots" context layer, so it's never shown twice.

## Epic 3: ISF Billing (implemented)

**Calculation model.** Real Philippine irrigation associations (per NIA
policy) typically denominate ISF in cavans of palay per hectare, converted
to cash at the prevailing government support price, with rates varying by
system type and season. That level of detail is out of scope for a local
association platform like BAMBOSA — this implementation uses the
simplified cash model the capstone paper itself specifies: **a rate in
pesos per hectare, applied per farm lot, per billing period**, which is
functionally the same calculation NIA uses after their in-kind rate has
already been converted to cash.

- **Billing periods** (`billing_periods` table) represent a cropping season
  or billing cycle. An officer opens one with a name, a ₱/hectare rate, and
  a date range (`officer/billing_periods.php`). Rates are stored per period
  rather than as one global setting, since real ISF rates commonly change
  season to season.
- **Generating bills** (`officer/billing_period_view.php` →
  "Generate / Refresh Bills") creates one bill per registered farm lot:
  `amount_due = land_area_ha × rate_per_ha`. Both values are **snapshotted**
  onto the bill at generation time, so correcting a farm lot's area later
  doesn't silently rewrite historical bills. Re-running generation for the
  same period updates `amount_due` for any lot whose area changed, but
  never touches `amount_paid` — recorded payments are never lost by
  regenerating.
- **Payments** (`isf_payments` table) are logged individually against a
  bill rather than just overwriting a running total, so there's a full
  audit trail (date, amount, who recorded it, optional notes). A bill's
  `status` (`unpaid` / `partial` / `paid`) is derived automatically from
  `amount_paid` vs `amount_due` every time a payment is recorded.
  Overpayment is rejected server-side (`record_payment()` in
  `includes/billing.php`) since there's no credit/refund concept in this
  model.
- **Officers** manage periods, generate/refresh bills, filter bills by
  status, and record payments inline per bill.
- **Members** see all their bills across every period, their current
  outstanding balance, and can expand any bill to see its payment history
  (`member/billing.php`). Members cannot record payments themselves —
  payments are recorded by officers once received, consistent with how a
  real association handles cash collection.
- Closing a period (`status = 'closed'`) blocks further bill generation for
  it, for when an officer wants to lock in a season's billing as final.
- Deleting a billing period cascades to permanently delete every bill and
  payment recorded under it — the delete button warns about this
  explicitly before submitting, since it destroys financial records.
- See **"Running the migration"** under Setup above for how to add these
  tables to a database that already existed before this feature was built.

## Next steps (core objectives from Chapter 1)

- [x] Member & Farm Management module (Epic 2)
- [x] Sector Management — full CRUD with usage counts before delete
- [x] Machinery Reservation System (simplified — overlap flagged to member and officer, not auto-blocked)
- [x] Digital Rotation Scheduling — basic CRUD + sector-filtered viewer (simplified — overlap flagged, not auto-blocked)
- [x] Responsive UI — mobile slide-in sidebar drawer, scrollable tables, responsive forms/nav
- [x] Farm Map view — Leaflet-based web equivalent of the paper's QGIS mapping
- [x] Sector Boundaries & Auto-Assignment — draw sector polygons, point-in-polygon farm lot matching, with confirm-before-overwrite and overlap warnings
- [x] ISF Billing (Epic 3) — billing periods with configurable ₱/ha rate, bill generation, partial-payment tracking with audit trail

All four specific objectives from Chapter 1 are now implemented, along
with sector management, boundary-based auto-assignment, and the
responsive UI work that came up along the way. Possible follow-ups beyond
the original scope, if useful later:
- [x] Conflict detection for overlapping machinery reservations / rotation schedules — both list pages now show a "⚠ Overlap" badge next to any entry that overlaps another pending/approved reservation (same machine) or another schedule (same sector); saving a conflicting schedule also shows a warning. This is flag-only, not a hard block, since the officer still makes the final call — same philosophy as the original member-side reservation warning.
- Printable/PDF bill or receipt generation
- Importing a real QGIS-exported boundary dataset (GeoJSON/shapefile) to replace or refine the manually-drawn polygons
- [x] SMTP email configuration — implemented; see "Outgoing Email (SMTP / Forgot Password / Activation Notice)" below for the full writeup. PHPMailer 6.12.0's core files are already vendored in under `vendor/phpmailer/phpmailer/src/`, so the only remaining step is filling in real provider credentials in `config/smtp.php` and setting `SMTP_ENABLED = true`.
- [x] Store farm lot boundaries as GeoJSON polygons instead of (or alongside) a single lat/lon point — implemented; see "Farm Lot Polygons" above for the full writeup. `farm_lots.latitude`/`longitude` are kept and now recomputed as the drawn polygon's centroid whenever one is saved, so nothing else needed to change to keep working.

## Outgoing Email (SMTP / Forgot Password / Activation Notice)

Two features that previously had no email step at all now send real
email, via **PHPMailer 6.x (core files only)** over plain SMTP:

- **Forgot password** — `forgot_password.php` takes an email address and
  (if it matches a real, non-deactivated account) emails a time-limited,
  single-use reset link. `reset_password.php` is where that link lands,
  and lets the person choose a new password.
- **Account activation notice** — the moment an officer flips a member's
  status from `pending` to `active` (`officer/update_status.php`), the
  member gets an automatic "your account is now active" email. It only
  fires on that specific transition — re-saving an already-active member,
  or any other status change, doesn't re-send it.

### PHPMailer is already vendored in

This project deliberately has **no Composer dependency** anywhere (it's
plain PHP throughout), so PHPMailer's three core files are vendored
directly into the codebase rather than pulled in via a package manager:

```
vendor/phpmailer/phpmailer/src/Exception.php
vendor/phpmailer/phpmailer/src/PHPMailer.php
vendor/phpmailer/phpmailer/src/SMTP.php
```

This is PHPMailer **6.12.0**, official unmodified source, core files
only — no OAuth.php, POP3.php, DSNConfigurator.php, or the language
folder, since BAMBOSA only ever does plain SMTP with a username/password.
See `vendor/phpmailer/phpmailer/src/VERSION.txt` for exactly what's
included and how to upgrade to a newer 6.x release later if needed. The
`vendor/phpmailer/phpmailer/src/...` path deliberately mirrors what
Composer itself would generate, so switching to Composer later (if this
project ever adopts it) wouldn't require changing anything in
`includes/mailer.php`.

There's nothing to install — the only remaining step is filling in real
credentials in `config/smtp.php` and setting `SMTP_ENABLED = true`, per
the "Configuration" section below.

### Configuration — `config/smtp.php`

A new file alongside `config/database.php`, protected by the same
`config/.htaccess` (`Require all denied`) so it can never be fetched
directly over HTTP. Set `SMTP_ENABLED = true` and fill in
`SMTP_HOST`/`SMTP_PORT`/`SMTP_USERNAME`/`SMTP_PASSWORD`/`SMTP_ENCRYPTION`
plus `SMTP_FROM_EMAIL`/`SMTP_FROM_NAME` — the file has worked examples
for Gmail (App Password), Outlook/Microsoft 365, and a typical
cPanel/shared-hosting mailbox in its comments.

### Graceful degradation — nothing breaks if email isn't set up

Both features work correctly with email fully switched off
(`SMTP_ENABLED = false`, the default until you configure it) or
misconfigured (e.g. wrong credentials, unreachable host, or — if the
`vendor/phpmailer` folder is ever deleted or excluded from a deploy — the
PHPMailer files missing entirely):

- **Forgot password** shows the exact same "if that email is registered,
  a reset link has been sent" confirmation message regardless of whether
  the email actually went out, whether the address exists in the system
  at all, or whether SMTP is configured. This isn't a bug — see
  `includes/password_reset.php`'s `request_password_reset()` — it's a
  deliberate anti-enumeration measure: a forgot-password form that
  visibly reacts differently to "that email exists" vs. "it doesn't"
  hands an attacker a way to fish for valid member email addresses.
- **Account activation** always changes the member's status in the
  database and lets them log in immediately — whether or not the
  notification email actually sent is completely irrelevant to that.
- Every failure (missing PHPMailer files, unreachable host, rejected
  credentials, invalid recipient) is caught inside `send_email()` in
  `includes/mailer.php` and logged via `error_log()` with the specific
  reason, but never bubbles up as a visible error, a broken page, or a
  blocked action. An officer/admin troubleshooting "why didn't the email
  arrive" checks the PHP error log, not the end user's screen.

### Security notes

- **Reset tokens are never stored in plaintext.** `password_resets.token_hash`
  stores only the SHA-256 hash of a cryptographically random 32-byte
  token; the raw token exists only in the emailed link and briefly in
  memory. A database leak alone doesn't hand over usable reset links,
  same principle as `password_hash()` for actual account passwords.
- **Tokens are single-use and time-limited** (30 minutes by default,
  `PASSWORD_RESET_TTL_MINUTES` in `includes/password_reset.php`) — once
  used, `used_at` is set and the token is permanently dead, so a leaked
  or reused link can't be replayed. `consume_password_reset()` marks a
  token used inside the same transaction as the actual password change,
  closing the race-condition window where two near-simultaneous requests
  with the same token could otherwise both succeed.
- **Token comparison uses `hash_equals()`**, not `===`, to avoid a timing
  attack that could otherwise be used to guess a valid token hash one
  byte at a time.
- **CSRF protection** on both the forgot-password and reset-password
  forms, consistent with every other form in the app.
- **No forced unencrypted SMTP fallback** — `includes/mailer.php` maps
  `config/smtp.php`'s `SMTP_ENCRYPTION` setting to either STARTTLS or
  implicit TLS explicitly; there's no code path that silently sends
  credentials over an unencrypted connection if the setting is
  missing/invalid.
- New `sql/migrations/005_password_resets.sql` for an existing database
  (already included in `sql/bambosa_db.sql` for a fresh install).

