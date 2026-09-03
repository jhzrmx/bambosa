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
├── config/
│   ├── database.php          # PDO connection (edit credentials here)
│   └── .htaccess              # Blocks direct browser access
├── includes/
│   ├── base_path_utils.php     # BAMBOSA_BASE_PATH detection + base_url() helper
│   ├── session.php              # session_start() bootstrap (uses base_path_utils.php for cookie path)
│   ├── auth.php                 # require_login(), require_role(), CSRF helpers (uses base_path_utils.php for redirects)
│   ├── members.php              # Member, farm-lot, sector & map data access
│   ├── sector_boundaries.php     # Polygon storage, point-in-polygon test, auto-assignment logic
│   ├── machinery.php             # Machinery + reservation data access
│   ├── schedules.php              # Rotation schedule data access
│   ├── billing.php                 # ISF billing periods, bills, payments data access
│   ├── partials/
│   │   ├── officer_sidebar.php     # Shared officer nav (topbar + drawer + overlay)
│   │   ├── member_sidebar.php       # Shared member nav (topbar + drawer + overlay)
│   │   └── .htaccess
│   └── .htaccess
├── officer/
│   ├── dashboard.php           # Protected — role must be 'officer'
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
│   ├── profile.php              # Edit own name/contact; view (read-only) farm lots
│   ├── billing.php               # View own ISF bills + payment history across all periods
│   ├── machinery.php              # Request a reservation; view own reservation history
│   ├── schedule.php                # View upcoming water rotation for own sector + all sectors
│   └── map.php                      # View own farm lot(s) plotted on a Leaflet map
├── assets/
│   ├── css/style.css           # All styling, including responsive breakpoints + map styles
│   └── js/
│       ├── dashboard.js         # Mobile sidebar drawer toggle (vanilla JS, no deps)
│       ├── sector-boundaries.js  # Leaflet.draw polygon editor + live preview + save (vanilla JS)
│       └── lot-location-picker.js # Click-to-pin map picker for the farm lot form (vanilla JS)
└── sql/
    ├── bambosa_db.sql         # Full schema (fresh install) + seed officer account
    ├── migrations/
    │   ├── 001_isf_billing.sql      # Adds billing_periods/isf_bills/isf_payments to an EXISTING db
    │   ├── 002_sector_polygons.sql   # Adds sectors.boundary_geojson + sector_auto_assignments to an EXISTING db
    │   └── .htaccess
    └── .htaccess
```

## Setup (XAMPP)

**Fresh install (no existing database):**
1. Copy the `bambosa/` folder into `htdocs/`.
2. Start Apache + MySQL in XAMPP.
3. Open phpMyAdmin, import `sql/bambosa_db.sql`. This single file creates
   every table — including ISF billing and sector boundaries — so you don't
   need to run anything from `sql/migrations/` separately.
4. Edit `config/database.php` with your DB username/password (XAMPP
   default is usually `root` with empty password — already set).
5. Visit `http://localhost/bambosa/`.

**Existing install (database created before a given feature was added):**
1. Pull the updated code into your `bambosa/` folder as normal.
2. In phpMyAdmin, open your existing `bambosa_db` database and import
   whichever migration file(s) you haven't run yet, **in numeric order**:
   - `sql/migrations/001_isf_billing.sql` — adds `billing_periods`,
     `isf_bills`, `isf_payments`.
   - `sql/migrations/002_sector_polygons.sql` — adds `boundary_geojson` to
     `sectors` and creates `sector_auto_assignments`.
3. None of this touches data you already have in `users`, `farm_lots`,
   `sectors`, etc. Both migrations are safe to re-run if you're ever unsure
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
  An officer must manually flip their status to `active` in the database
  (or later, via an admin approval screen) before they can log in.
  Officer accounts are **not** self-registrable — provision them directly
  in the database for security.

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

## Epic 2: Member & Farm Management (implemented)

- `farm_lots` table stores one or more land parcels per member: name/label,
  sector, land area (ha), latitude/longitude (for the future QGIS map view),
  and notes.
- **Officers** can: view all members with status filters (all/pending/active/inactive),
  approve pending signups, deactivate/reactivate accounts, and add/edit/delete
  farm lots for any member (`officer/member_view.php`).
- **Members** can: update their own name/contact number, and view (read-only)
  their registered farm lots and total land area (`member/profile.php`).
  Land area and sector are officer-managed only, since they directly drive
  ISF billing in Epic 3 — self-editing would create a billing integrity risk.
- All data access goes through `includes/members.php` (prepared statements only).
- **Location picking:** the farm lot form includes an interactive map
  (`assets/js/lot-location-picker.js`, Leaflet-based) instead of requiring
  an officer to type raw coordinates. Clicking the map drops a pin and
  fills the latitude/longitude fields automatically; dragging an existing
  pin updates them again. The text fields are still there and still
  editable directly — typing into them moves the pin too, so the two stay
  in sync — since coordinates copied from a GPS device or written down in
  the field are still a legitimate way to enter a location. The map
  centers on the lot's existing location when editing, or the average of
  all other farm lots' locations when adding a new one (falling back to a
  wide default view only if no farm lot anywhere has coordinates yet).

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
  It does **not** block submission or auto-block officer approval — the
  officer always makes the final call. This is the "easy" version; a stricter
  conflict-prevention engine can be layered on later if the officer wants
  double-booking hard-blocked instead of just flagged.

## Rotation Scheduling — basic viewer (implemented — simplified version)

- `rotation_schedules` table: one row per water-release window for a sector
  (start/end datetime, notes, which officer posted it).
- **Officers** create/edit/delete schedule entries (`officer/schedules.php`).
- **Members** see the upcoming schedule filtered to their own farm lot's
  sector(s), plus a full reference list of all sectors' upcoming releases
  (`member/schedule.php`).
- This is the straightforward CRUD + listing version described in the
  capstone's Epic 4. It does **not** yet include automatic conflict
  detection between overlapping sector windows — schedules are officer
  judgment calls for now, same as machinery approval above.

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
  by sector, with a legend. Tapping a marker shows the lot name, owner,
  sector, and hectares, plus a link straight to that member's record. If
  any farm lots are missing coordinates, a banner tells the officer how
  many, so incomplete data entry is visible rather than silently skipped.
- `member/map.php` — the same idea scoped to just the logged-in member's
  own farm lot(s), consistent with the read-only pattern already used on
  `member/profile.php`.
- No new database tables were needed — this reads directly from
  `farm_lots.latitude` / `farm_lots.longitude`, which were already being
  captured by the farm-lot form in Epic 2. New data-access functions
  (`get_mapped_farm_lots()`, `count_unmapped_farm_lots()`,
  `get_mapped_farm_lots_by_user()`) live in `includes/members.php`.
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
- [x] Machinery Reservation System (simplified — no auto conflict-blocking)
- [x] Digital Rotation Scheduling — basic CRUD + sector-filtered viewer (simplified — no conflict detection)
- [x] Responsive UI — mobile slide-in sidebar drawer, scrollable tables, responsive forms/nav
- [x] Farm Map view — Leaflet-based web equivalent of the paper's QGIS mapping
- [x] Sector Boundaries & Auto-Assignment — draw sector polygons, point-in-polygon farm lot matching, with confirm-before-overwrite and overlap warnings
- [x] ISF Billing (Epic 3) — billing periods with configurable ₱/ha rate, bill generation, partial-payment tracking with audit trail

All four specific objectives from Chapter 1 are now implemented, along
with sector management, boundary-based auto-assignment, and the
responsive UI work that came up along the way. Possible follow-ups beyond
the original scope, if useful later:
- Conflict detection for overlapping machinery reservations / rotation schedules (currently officer judgment calls)
- Printable/PDF bill or receipt generation
- Importing a real QGIS-exported boundary dataset (GeoJSON/shapefile) to replace or refine the manually-drawn polygons
- Online payment integration (explicitly out of scope per the paper's own delimitations)
