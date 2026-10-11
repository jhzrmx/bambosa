# BAMBOSA Irrigation Management Platform

BAMBOSA is a web platform for running an irrigation association (built
around Barangay Bikal's setup): member and farm-lot records, sector maps,
water-rotation schedules, machinery reservations, and irrigation service
fee (ISF) billing — with separate, role-based workspaces for **admins**,
**officers** and **members**.

It is plain PHP + MySQL/MariaDB with vanilla JavaScript (no framework, no
build step, no Composer), so it runs on a stock XAMPP install.

## Contents

1. [Features at a glance](#1-features-at-a-glance)
2. [Getting started](#2-getting-started)
3. [Project structure](#3-project-structure)
4. [Accounts, roles & access](#4-accounts-roles--access)
5. [Modules](#5-modules)
6. [System configuration & infrastructure](#6-system-configuration--infrastructure)
7. [Security summary](#7-security-summary)
8. [Developer notes](#8-developer-notes)
9. [Database & diagrams](#9-database--diagrams)
10. [Project status](#10-project-status)

---

## 1. Features at a glance

| | Member | Officer | Admin |
|---|:---:|:---:|:---:|
| Self-register via `signup.php` (starts `pending`) | ✅ | ❌ | ❌ |
| Log in, reset a forgotten password by email | ✅ | ✅ | ✅ |
| Edit own name / contact / email / password | ✅ | ✅ | ✅ |
| View own farm lots, ISF bills, schedule, reservations, map | ✅ | — | — |
| Request machinery reservations | ✅ | — | — |
| Approve / deactivate **member** accounts | ❌ | ✅ | ✅ |
| Manage farm lots, sectors & boundaries, schedules, machinery, reservations | ❌ | ✅ | ❌ |
| Manage billing periods, generate bills, record payments | ❌ | ✅ | ❌ |
| Add / edit / delete **any** account (admin, officer, member), change roles & status | ❌ | ❌ | ✅ |
| View every user's login history and look up an IP address | ❌ | ❌ | ✅ |
| Check the PHP / MySQL system clock | ❌ | ❌ | ✅ |
| Dashboard | `member/dashboard.php` | `officer/dashboard.php` | `admin/dashboard.php` |

**Sidebar navigation per role**

- **Admin:** Dashboard · My Profile · User Management · User Login Logs · System Clock
- **Officer:** Dashboard · My Profile · Member Management · Sector Management · Sector Boundaries · Billing & ISF · Rotation Scheduling · Machinery Inventory · Reservation Requests (with a pending-count badge) · Member Map
- **Member:** Dashboard · My Profile & Farm Lot · My Billing / ISF · Rotation Status · Reserve Machinery · Reservation History · My Farm Map

Cross-cutting: responsive UI with a slide-in mobile drawer, search and
pagination on every list page, custom error pages, a configurable timezone
shared by PHP and MySQL, and optional outgoing email (forgot password,
account-activation notice).

---

## 2. Getting started

### Requirements

PHP **7.4 or newer** and MySQL/MariaDB. The code deliberately avoids
PHP 8-only syntax (`str_contains()`, `match`, union types, …); where an
8.x function would be natural, a small 7.4-compatible helper is used
instead (e.g. `str_has()` in `includes/pagination.php`).

### Fresh install (XAMPP)

1. Copy the project folder into `htdocs/` (the examples below assume it is
   named `bambosa`).
2. Start **Apache** and **MySQL** in XAMPP.
3. In phpMyAdmin, import `sql/bambosa_db.sql`. It creates the `bambosa_db`
   database and **every** table, plus the default officer and admin
   accounts — nothing in `sql/migrations/` needs to be run on a fresh install.
4. Check `config/database.php` (XAMPP's default `root` with an empty
   password is already set).
5. *Recommended:* set `APP_TIMEZONE` in `config/timezone.php`
   (e.g. `'Asia/Manila'`) — see [Timezone](#timezone).
6. Open `http://localhost/bambosa/` and log in with a default account
   (below). **Change both default passwords straight away.**

### Quick run without Apache (Windows)

Double-click `serve.bat`. It checks that `php` is on your `PATH`, then
starts PHP's built-in server at `http://localhost:8000/` (using
`error/router.php`, so missing pages and protected folders still get the
branded 404/403 pages). MySQL/MariaDB must still be running separately and
`config/database.php` configured.

### Default accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@bambosa.local` | `12345678` |
| Officer | `officer@bambosa.local` | `12345678` |

Change a password from **My Profile** after logging in, or — as an admin —
from **User Management → Edit** (leave the field blank to keep an
existing password). To generate a hash by hand:

```bash
php -r "echo password_hash('YourNewPassword123', PASSWORD_DEFAULT);"
```

### Upgrading an existing install

Pull the new code, then in phpMyAdmin import any migration you haven't run
yet from `sql/migrations/`, **in numeric order**. They are safe to re-run
(`CREATE TABLE IF NOT EXISTS`, plus existence checks before altering
tables). None of them touches existing rows.

| Migration | Adds |
|---|---|
| `001_isf_billing.sql` | `billing_periods`, `isf_bills`, `isf_payments` |
| `002_sector_polygons.sql` | `sectors.boundary_geojson`, `sector_auto_assignments` |
| `003_farm_lot_polygons.sql` | `farm_lots.boundary_geojson` |
| `004_schedule_groups.sql` | `rotation_schedules.group_id` |
| `005_password_resets.sql` | `password_resets` |
| `006_machinery_groups.sql` | `machinery_groups`, `machinery.group_id` / `damage_level`, `machinery_reservations.group_id` / `rejection_remark` (and makes its `machinery_id` nullable) |
| `007_admin_role.sql` | the `admin` value on `users.role` and the default admin account |

Future schema changes follow the same pattern: a new
`sql/migrations/NNN_description.sql` for existing installs, with the same
statements folded into `sql/bambosa_db.sql` so a fresh install always gets
the complete, current schema in one import.

### Configuration files

All live in `config/`, which is blocked from direct web access by
`config/.htaccess` (`Require all denied`).

| File | Purpose | Default |
|---|---|---|
| `database.php` | PDO connection (host, database, credentials) | XAMPP defaults |
| `timezone.php` | `APP_TIMEZONE` — one timezone for PHP and MySQL | empty = not configured, nothing is changed |
| `smtp.php` | SMTP settings for outgoing email | `SMTP_ENABLED = false` |

---

## 3. Project structure

```
bambosa/
├── index.php                  # Public landing page
├── login.php                  # Login form + auth logic (records login_logs)
├── signup.php                 # Member signup (role "member", status "pending")
├── logout.php                 # Ends the session
├── forgot_password.php        # Request a password-reset email
├── reset_password.php         # Landing page for the emailed reset link
├── calendar_data.php          # JSON endpoint for the dashboard calendar's month navigation
│                              #   (at the root because includes/.htaccess blocks direct HTTP access)
├── .htaccess                  # ErrorDocument rules → error/*.html
├── serve.bat                  # Windows helper: php -S localhost:8000 with error/router.php
├── README.md
│
├── admin/                     # role = admin
│   ├── index.php              # Directory entry point → dashboard.php
│   ├── dashboard.php          # Account counts + quick actions
│   ├── profile.php            # Own name / contact / email / password
│   ├── users.php              # List, filter and search ALL accounts; edit / delete links
│   ├── user_edit.php          # Add an account / edit any other account
│   ├── user_delete.php        # POST handler: delete an account
│   ├── login_logs.php         # User Login Logs (search + pagination)
│   ├── ip.php                 # IP details: map + location / ISP info for ?address=…
│   └── system_clock.php       # PHP vs MySQL timezone and time
│
├── officer/                   # role = officer
│   ├── index.php              # Directory entry point → dashboard.php
│   ├── dashboard.php          # Stat cards + calendar
│   ├── profile.php            # Own name / contact / email / password
│   ├── members.php            # List / filter members; approve, deactivate
│   ├── member_view.php        # One member + their farm lots (CRUD, boundary drawing)
│   ├── update_status.php      # POST handler: approve / deactivate / reactivate a member
│   ├── sectors.php            # Sector CRUD
│   ├── sector_boundaries.php  # Draw / edit sector boundaries on a map
│   ├── sector_boundary_save.php     # AJAX: save a boundary (+ optional auto-assignment)
│   ├── sector_boundary_preview.php  # AJAX: live "N farm lots would match" preview
│   ├── billing_periods.php    # Open / edit / close / delete billing periods
│   ├── billing_period_view.php # Generate bills, filter bills, record payments
│   ├── machinery.php          # Machinery inventory (availability, group, condition)
│   ├── machinery_groups.php   # Pools of interchangeable equipment
│   ├── reservations.php       # Approve / reject / cancel reservation requests
│   ├── reservation_assign.php # Pick a unit to assign + approve a pool request
│   ├── schedules.php          # Water-rotation schedule CRUD
│   └── map.php                # Member Map: all lots + sector boundaries
│
├── member/                    # role = member
│   ├── index.php              # Directory entry point → dashboard.php
│   ├── dashboard.php          # Stat cards + calendar
│   ├── profile.php            # Own details + password; read-only farm lots
│   ├── billing.php            # Own ISF bills and payment history
│   ├── machinery.php          # Request a reservation
│   ├── machinery_history.php  # Own reservations + equipment condition
│   ├── schedule.php           # Upcoming water rotation (own sector + all)
│   └── map.php                # Own farm lots on a map
│
├── includes/                  # Library code (blocked from direct HTTP access)
│   ├── base_path_utils.php    # BAMBOSA_BASE_PATH detection + base_url()
│   ├── session.php            # Session bootstrap; applies the PHP timezone
│   ├── auth.php               # Login/role checks, CSRF helpers, session ↔ DB sync
│   ├── pagination.php        # Shared search + pagination helpers
│   ├── members.php            # Users, farm lots, sectors, map queries
│   ├── admin_users.php        # Account management for admins + safety guards
│   ├── login_logs.php         # Read-only login_logs queries
│   ├── ip_lookup.php          # ip-api.com lookup (validation, 24 h cache)
│   ├── timezone.php           # Applies config/timezone.php to PHP + MySQL
│   ├── sector_boundaries.php  # Boundary storage, point-in-polygon, auto-assignment
│   ├── farm_lot_boundaries.php # Lot polygon storage + centroid
│   ├── machinery.php          # Machinery, groups, reservations
│   ├── schedules.php          # Rotation schedules
│   ├── billing.php            # Billing periods, bills, payments
│   ├── calendar_events.php    # Merged schedule + reservation events for the calendar
│   ├── mailer.php             # PHPMailer wrapper: send_email(), render_email_layout()
│   ├── password_reset.php     # Reset-token generation / verification / consumption
│   └── partials/              # admin_, officer_ and member_sidebar.php (nav + mobile drawer)
│
├── config/                    # database.php · timezone.php · smtp.php (+ .htaccess)
├── vendor/phpmailer/phpmailer/src/  # PHPMailer 6.12.0 core files, vendored (+ .htaccess)
├── error/                     # 400 · 401 · 403 · 404 · 500 · 503 .html + router.php
├── assets/
│   ├── css/style.css          # All styling, responsive breakpoints, map styles
│   ├── img/                   # favicon.png, favicon.ico
│   └── js/                    # dashboard.js · dashboard-calendar.js ·
│                              #   sector-boundaries.js · farm-lot-boundary.js
├── sql/
│   ├── bambosa_db.sql         # Full schema + seed accounts (fresh install)
│   └── migrations/            # 001 … 007 for existing installs (+ .htaccess)
└── docs/diagrams/             # erd.mermaid · dfd_level0.mermaid · dfd_level1.mermaid · README.md
```

`includes/`, `config/`, `vendor/` and `sql/` each carry a
`.htaccess` with `Require all denied`.

---

## 4. Accounts, roles & access

### Authentication & sessions

- Passwords are stored with `password_hash()` and checked with
  `password_verify()`. Sessions are hardened (`httponly`, `samesite=Lax`,
  cookie path scoped to the install folder) in `includes/session.php`.
- `includes/auth.php` provides `require_login()`,
  `require_role('admin'|'officer'|'member')` (a wrong-role visit is sent to
  that user's own dashboard), and `csrf_token()` / `csrf_verify()`.
- **Changes take effect immediately.** `sync_session_with_db()` (run once
  per request from `is_logged_in()`) re-checks the logged-in user against
  the database: a deleted or non-`active` account is logged out on its
  next request, and a changed role updates straight away.
- New signups are saved as role `member`, status `pending`, and cannot log
  in until an officer or admin sets them to `active`.
  **Officer and admin accounts cannot self-register** — an admin creates
  them in User Management.
- Forgotten passwords are self-service through `forgot_password.php` /
  `reset_password.php` (see [Outgoing email](#outgoing-email)).
- Every successful login is written to `login_logs` (user, time, IP).
- Direct folder URLs such as `/bambosa/member/` work: each folder's
  `index.php` simply loads its `dashboard.php`, which applies the normal
  login and role checks.

### Admin

Admins hold the "master key" for **accounts** (day-to-day association
records stay with officers).

- **User Management** (`admin/users.php`) — every account in one list with
  role tabs, status tabs, search and pagination.
- **Add / edit** (`admin/user_edit.php`) — name, email, contact number,
  role, status, and a password (required for a new account; leave blank
  when editing to keep it). Email must be valid and unique; passwords need
  at least 8 characters.
- **Delete** (`admin/user_delete.php`, POST + CSRF) — permanent. Database
  cascades also remove a member's farm lots, reservations, ISF bills and
  payments, login logs and reset tokens; records an officer merely
  created (schedules, billing periods, recorded payments, auto-assignment
  log entries) are kept with the author cleared. The confirmation dialog
  warns about the member cascade.
- **Safety rules** (`user_change_guard_error()`): an admin cannot delete,
  demote or deactivate **their own** account from User Management (they
  edit themselves via My Profile), and the **last active admin** can never
  be deleted, demoted or deactivated — so the system cannot be locked out.
- **User Login Logs** (`admin/login_logs.php`) — every successful login by
  any role, newest first: date/time, name, email, role, IP. Search matches
  name, email, role, IP or the timestamp text (e.g. `2026-10`); 15 rows per
  page. Deleting an account also removes its log rows.
- **IP address details** (`admin/ip.php?address=…`) — each IP in the logs
  is a link: a map on the left (above the details on phones) and country,
  region, city, ZIP, coordinates, timezone and local time there, currency,
  ISP, organization, AS number/name, reverse DNS, and Yes/No flags for
  mobile network, proxy/VPN and hosting. Data comes from the free
  [ip-api.com](https://ip-api.com/docs) API, queried **server-side** by
  `includes/ip_lookup.php`:
  - Local/private addresses (`127.0.0.1`, `::1`, `192.168.x.x`, …) have no
    public location and are not sent to the API — on a local install most
    logins will be one of these.
  - The free endpoint is **HTTP only** (only the IP travels, unencrypted),
    limited to about 45 requests/minute and meant for non-commercial use.
    Results are cached for 24 hours in the system temp folder. For
    HTTPS/commercial use, switch to a paid key (`IP_API_BASE`).
  - Geolocation is approximate (city/ISP level), so the map shows a point
    plus a ~5 km circle. The address is validated with `filter_var()` and
    all output is HTML-escaped.
- **System Clock** (`admin/system_clock.php`) — PHP's and MySQL's timezone
  and current time side by side, with a warning if they disagree (see
  [Timezone](#timezone)).

### Officer

Officers run the association's records: members and farm lots, sectors and
boundaries, rotation schedules, machinery and reservations, and ISF
billing — see [Modules](#5-modules). They can approve and
deactivate **member** accounts from Member Management;
`officer/update_status.php` refuses to touch admin or officer accounts.
Role, status and land data can only be changed by an officer or admin, never
by the member (self-editing land area or status would be a billing and
security risk).

### Member

Members see their own data only: profile and read-only farm lots, ISF
bills and payment history, the rotation schedule, a farm map, machinery
reservations and their history. Members cannot record payments or edit
land area/sector.

### Account self-service (all roles)

Every role has a profile page (`admin/`, `officer/` and `member/profile.php`):

- **Name and contact number** can be edited freely.
- **Email** is editable. It is the login name and the password-reset
  address, so changing it requires the **current password**; it must be a
  valid address not used by another account. No confirmation link is sent
  to the new address.
- **Password changes** require the current password, at least 8 new
  characters, entered twice. This stops an unattended session from being
  used to lock the real owner out. All roles share `change_user_password()`.
- The account form and the password form post to the same endpoint and are
  told apart by a hidden `form=password` field, so each shows its own
  success/error state.

---

## 5. Modules

### Member & farm management

- `farm_lots` holds one or more parcels per member: name, sector, land
  area (ha), a drawn boundary (`boundary_geojson`) and notes.
- **Officers** filter members (all / pending / active / inactive), approve
  signups, deactivate or reactivate accounts, and add/edit/delete farm lots
  for any member (`officer/member_view.php`).
- **Members** update their own name and contact number and see their lots
  and total area read-only (`member/profile.php`). Land area and sector are
  officer-managed because they drive billing.
- All data access goes through `includes/members.php` using prepared
  statements.

### Sector management

- `officer/sectors.php` is full CRUD with case-insensitive duplicate-name
  checking. Each row shows how many farm lots and rotation schedules use
  that sector (`get_sectors_with_usage()`).
- Deleting a sector is allowed; the confirmation names how many records are
  affected. Farm lots are **unassigned** (`ON DELETE SET NULL`) while that
  sector's schedules are **deleted** (`ON DELETE CASCADE`).

### Farm lot polygons

A farm lot's location is defined **only by its drawn boundary**.

- `farm_lots.boundary_geojson` is a JSON array of `[lat, lng]` vertices
  drawn with Leaflet.draw (same format as sector boundaries).
- `latitude` / `longitude` are **derived, never edited**: saving a lot
  computes them as the polygon's centroid (`polygon_centroid()` in
  `includes/farm_lot_boundaries.php`) and stores them together with the
  polygon. A lot with no polygon has `NULL` coordinates. Anything needing a
  single point — chiefly sector auto-assignment — uses the centroid.
- **One form, one save.** The drawn polygon is serialised into a hidden
  `boundary_points` field by `assets/js/farm-lot-boundary.js` and saved
  with the lot's other fields in one POST (`officer/member_view.php`), for
  new lots and existing ones alike.
- `normalize_and_center_boundary()` validates the points: none = "no
  boundary" (valid); one or two = rejected; three or more = accepted.
- While drawing, two checkboxes overlay other shapes as dimmed, dashed,
  non-editable guides: **Show sector boundaries** (on by default) and
  **Show other farm lots** (off by default). The lot being edited is never
  shown twice.
- Lot tables show a "Drawn" / "Not drawn" badge instead of raw coordinates.

### Sector boundaries & auto-assignment

Officers draw each sector's boundary on a map; farm lots whose centroid
falls inside can then be assigned to that sector automatically
(point-in-polygon, the same technique a GIS spatial join uses).

- `officer/sector_boundaries.php` — pick a sector, draw/reshape its polygon
  with Leaflet.draw, and save. Other sectors' boundaries show dimmed for
  context, and **Preview Matches** lists the lots that would fall inside
  before anything is saved. Farm lots appear as their own boundary shapes,
  filled with their sector's colour (a checkbox toggles them).
- Boundaries are stored in `sectors.boundary_geojson` as the same simple
  `[lat, lng]` vertex list. `point_in_polygon()` (ray casting) lives in
  `includes/sector_boundaries.php`; the live preview calls the same PHP via
  `officer/sector_boundary_preview.php`, so there is no second copy of the
  maths in JavaScript. Save goes through `sector_boundary_save.php`.
- **Auto-assignment rules**
  - Saving a boundary can re-run assignment across every lot that has
    coordinates (checkbox, on by default); only lots whose sector would
    change are updated.
  - The lot form can auto-assign a single lot from the boundary its
    centroid falls in.
  - If that would overwrite a sector an officer picked manually, an explicit
    confirmation step shows current vs matched sector first.
  - Overlapping boundaries are allowed: the first match wins and the
    officer is told how many sectors overlapped.
  - Every automatic change is logged in `sector_auto_assignments` (lot,
    previous and new sector, who ran it, when) and listed in "Recent
    Auto-Assignments" on the boundaries page.
- The map opens on the sector being edited (if it has a boundary), otherwise
  on the average position of all lots, falling back to a fixed Barangay
  Bikal centre.
- Lots with no sector are **not billed** (see [ISF billing](#isf-billing)).

### Farm maps

Maps use **Leaflet.js** with **OpenStreetMap** tiles from a CDN (no API
key). QGIS, which the capstone paper mentions, cannot run in a browser;
these maps are the web platform's own visualisation of the same data.

| Page | Shows |
|---|---|
| `officer/map.php` (Member Map) | Every lot **with a boundary** as its shape, filled with its sector's colour (legend), and sector boundaries as dashed outlines beneath. Clicking a lot opens a popup (name, owner, sector, hectares) with a **View member** link. A banner counts lots that have no boundary yet. |
| `member/map.php` (My Farm Map) | Only the member's own lots, as boundary shapes; says how many lots still lack a boundary. |
| `officer/sector_boundaries.php` | The sector editor, with lots drawn as boundary shapes. |

On all three, **only the boundary is drawn** — the stored centroid is never
plotted as a pin, and a lot without a boundary does not appear. Sector
colours come from a fixed palette keyed to the sector names, so new
sectors need no code changes. Leaflet also supports GeoJSON overlays, so
an imported QGIS dataset could be layered in later.

### Rotation scheduling

- `rotation_schedules` stores one water-release window per sector (start,
  end, notes, posting officer). Officers create/edit/delete entries
  (`officer/schedules.php`); members see the upcoming schedule for their
  own sector(s) plus every sector's upcoming releases
  (`member/schedule.php`).
- **Overlap flagging:** `has_overlapping_schedule()` marks any entry that
  overlaps another in the same sector with a "⚠ Overlap" badge, and shows a
  warning after saving one. It is informational only — an intentional
  correction or extension may legitimately overlap.
- **Apply one schedule to several sectors:** a checkbox on the create/edit
  form reveals the other sectors; each checked sector gets its own row with
  identical times and notes. The sector chosen in the main dropdown is
  disabled in the list (also enforced server-side with `array_unique()`).
  - A nullable `rotation_schedules.group_id` tags rows created together;
    `NULL` means standalone. Grouped rows show a "Shared" badge.
  - Editing a grouped schedule pre-checks its sibling sectors and
    propagates the change to every checked one. **Unchecking ungroups, never
    deletes** — that sector's entry stays as a normal standalone schedule.
  - Overlap checks cover every sector the schedule will end up in, excluding
    each sector's own existing row.
  - Functions: `create_schedule_group()`, `update_schedule_group()`,
    `get_sibling_schedule_sector_ids()` in `includes/schedules.php`.

### Machinery reservations

- **Tables:** `machinery_groups` (pools of interchangeable equipment, e.g.
  "Hand Tractors"), `machinery` (optional `group_id`; `damage_level`
  0–100), and `machinery_reservations` with a `pending →
  approved / rejected` (or `cancelled`) flow.
- **Members reserve a pool, not a specific unit.** `member/machinery.php`
  lists **Equipment pools** ("Hand Tractors — 2 units available") and
  **Standalone Machinery** (ungrouped machines, reserved directly as
  always). A pool is hidden when none of its units is marked available.
- **Officers assign the unit at approval time.** "Assign & Approve"
  (`officer/reservations.php` → `officer/reservation_assign.php`) lists the
  units that are available **and** free of overlapping pending/approved
  reservations for those dates; the chosen unit becomes the reservation's
  `machinery_id`.
- **No stock = no approval.** If a pool is fully booked, the list shows a
  "No stock available" badge and the only action is **Reject** with a
  remark. `assign_and_approve_reservation()` re-checks availability at
  submission, in case another request was approved first.
- **Rejection remarks are required** and shown to the member on
  `member/machinery_history.php`. Rejected rows have an **Edit Remark**
  action (`update_rejection_remark()`): it only changes the remark, never
  the status; the remark stays required and is limited to 255 characters.
- **Condition tracking:** `damage_level` is set with a slider on the
  officer's machinery form and shown as a colour bar (green < 34 %,
  amber 34–66 %, red ≥ 67 % — `damage_severity_class()`) in the officer
  list, the assignment page and an "Equipment Condition" table that
  members can read before requesting.
- **Overlap detection:** `has_overlapping_reservation()` (specific machine)
  and `get_available_machinery_in_group()` (pools). At request time this is
  a soft heads-up — the request still goes in as pending; at approval time
  of a fully-booked pool it is a hard block.
- **Navigation:** the officer sidebar has **Machinery Inventory** and a
  separate **Reservation Requests** item with a red badge for pending
  requests (hidden at zero, "99+" cap), computed inside
  `officer_sidebar.php`. The member sidebar has **Reserve Machinery** and
  **Reservation History**; the history page's two tables search and page
  independently (`?q=`/`?page=` and `?cq=`/`?cpage=`).

### ISF billing

**Calculation model.** A **rate in pesos per hectare, applied per farm lot,
per billing period** — the simplified cash model the capstone paper
specifies (equivalent to NIA's cavan-per-hectare rate once converted to
cash).

- **Billing periods** (`billing_periods`) are a season or cycle with a name,
  a ₱/ha rate and a date range (`officer/billing_periods.php`). Rates are
  stored per period because they change from season to season. Closing a
  period blocks further bill generation; deleting one permanently removes
  every bill and payment under it (the delete button warns first).
- **Generating bills** (`officer/billing_period_view.php` → *Generate /
  Refresh Bills*) creates one bill per farm lot **that is inside a sector**
  (`farm_lots.sector_id IS NOT NULL`): `amount_due = land_area_ha ×
  rate_per_ha`. Lots in no sector are **not billed**; the page says how many
  are skipped.
  - Area and rate are **snapshotted** on the bill, so later edits to a lot
    don't rewrite history. Regenerating refreshes `amount_due` but never
    touches `amount_paid`.
  - Regenerating also cleans up after sector changes: a bill for a lot that
    is no longer in a sector is removed if nothing has been paid on it, or
    **kept and flagged for review** if payments exist. Closed periods are
    never changed.
- **Payments** (`isf_payments`) are logged individually — date, amount,
  who recorded it, optional notes — giving a full audit trail. A bill's
  `status` (`unpaid` / `partial` / `paid`) is derived from `amount_paid`
  vs `amount_due`. Overpayment is rejected (`record_payment()` in
  `includes/billing.php`).
- **Recording a payment keeps your place.** The *Record Payment* link carries
  the current status filter, search term and **page number**, and ends in
  `#payment-form-<bill_id>`, so the page scrolls to the inline form (clear
  of the mobile top bar) and focuses the amount field. The form posts back
  to the same URL, so after saving you return to the same page of the same
  list; validation errors show inside the form with your input kept.
- **Members** see all their bills across periods, their outstanding balance,
  and each bill's payment history (`member/billing.php`). Only officers
  record payments, as cash is collected by the association.

### Dashboard calendar

The officer and member dashboards show a month calendar under the stat
cards (admins have no calendar).

- **Content:** water-release windows (`rotation_schedules`) and
  pending/approved reservations, merged per day. Rejected and cancelled
  reservations are left out. Multi-day events mark every day they touch
  (`expand_event_across_days()` in `includes/calendar_events.php`).
- **Scope:** members see their own sector(s)' schedules and their own
  reservations; officers see everything.
- **Layout:** a large month grid with a day-detail panel beside it (below it
  on narrow screens), styled by `.dash-calendar`.
- **Loading:** the current month is rendered server-side and embedded in
  the page (`window.BAMBOSA_CALENDAR_INITIAL`); the prev/next arrows fetch
  other months as JSON from `calendar_data.php`. The grid, navigation and
  day clicks are vanilla JS in `assets/js/dashboard-calendar.js`.
- `calendar_data.php` answers officers and members only (401 when logged
  out, 403 for admins) and scopes every query to the session user.

---

## 6. System configuration & infrastructure

### Timezone

PHP and MySQL keep separate timezones that often disagree (PHP defaults to
UTC unless `php.ini` says otherwise; MySQL follows the server OS). Anything
that mixes them goes subtly wrong — e.g. a password-reset link is stamped
with PHP's clock but checked against MySQL's `NOW()`, and schedule times
are compared with `NOW()`. `config/timezone.php` fixes that with one line:

```php
const APP_TIMEZONE = '';   // '' = not configured; e.g. 'Asia/Manila'
```

- **Not configured → nothing changes.** An empty value, a missing file, or
  an invalid identifier (logged to the PHP error log) leaves both PHP and
  MySQL exactly as the server set them. A typo can never half-apply.
- **Configured → both are synced.** `includes/session.php` (first on every
  page) calls `date_default_timezone_set()`, and `config/database.php` runs
  `SET time_zone` on each connection (`includes/timezone.php`). If MySQL has
  no timezone tables (common on a fresh XAMPP), the equivalent offset such
  as `+08:00` is used automatically.
- **Verify it** on the admin **System Clock** page.
- **Existing data:** `DATETIME` columns (e.g. schedule start/end) keep the
  clock time as entered. `TIMESTAMP` columns (`created_at`, `login_time`, …)
  are stored as UTC and displayed in the connection's zone, so setting or
  changing the timezone changes how existing values display. Choose it once,
  early.

### Outgoing email

Two features send email through **PHPMailer 6.12.0** (core files only,
vendored in `vendor/phpmailer/phpmailer/src/`; no Composer, no OAuth/POP3):

- **Forgot password** — `forgot_password.php` emails a time-limited,
  single-use reset link to a real, non-deactivated account;
  `reset_password.php` is where it lands.
- **Account-activation notice** — sent when an **officer** approves a
  `pending` member in Member Management (`officer/update_status.php`). It
  fires only on that `pending → active` change. *(Changing a member's
  status through the admin's User Management does not send it.)*

**Setup:** fill in `config/smtp.php` (host, port, username, password,
encryption, from-address — it includes worked examples for Gmail with an App
Password, Outlook/Microsoft 365 and cPanel mailboxes) and set
`SMTP_ENABLED = true`. Everything else works without email.

**Graceful degradation.** With email off (the default) or misconfigured —
including a missing `vendor/phpmailer` folder — nothing breaks:

- The forgot-password page always shows the same "if that email is
  registered, a link has been sent" message, whether or not the address
  exists or the email went out. This is deliberate anti-enumeration, so the
  form can't be used to fish for valid addresses.
- Account activation always succeeds in the database; the notice is
  best-effort.
- Every failure is caught in `send_email()` (`includes/mailer.php`) and
  written to `error_log()`; it never surfaces as a broken page. Check the PHP
  error log to see why an email didn't arrive.

**Reset-token security**
- Only the SHA-256 hash of a random 32-byte token is stored
  (`password_resets.token_hash`); the raw token exists only in the emailed
  link.
- Tokens are single-use and expire after 30 minutes
  (`PASSWORD_RESET_TTL_MINUTES`). `consume_password_reset()` marks a token
  used in the same transaction as the password change, so two simultaneous
  requests can't both succeed. Comparison uses `hash_equals()`.
- SMTP encryption is mapped explicitly to STARTTLS or implicit TLS; there is
  no silent fallback to an unencrypted connection.

### Custom error pages

Six branded static pages live in `error/`: `400.html` (Bad Request),
`401.html` (Unauthorized), `403.html` (Forbidden), `404.html` (Not Found),
`500.html` (Internal Server Error) and `503.html` (Service Unavailable).

- **Self-contained:** each file has its own inline CSS and icon, so it
  renders from any URL depth and even when the app or database is down. They
  are responsive and marked `noindex`.
- **Wiring (Apache/XAMPP):** the root `.htaccess` maps each status to its
  page with `ErrorDocument`, which also covers the 403s from the
  `Require all denied` folders. The paths include the install folder
  (`/bambosa/error/404.html`) — **this is the only place the folder name is
  needed**. If you rename or move the project, find-and-replace `/bambosa`
  in those six lines (use `/error/404.html` etc. at the web root). Keep them
  as local paths, not full URLs, or Apache redirects instead of returning the
  real status code.
- **Buttons:** *Back to Home* on every page, plus *Go Back* (400/404),
  *Log In* (401/403) or *Try Again* (500/503). A few lines of JS find the
  project root: a URL inside `admin/`, `officer/`, `member/`, `error/` or
  `assets/` gives it directly; otherwise the page checks which parent
  folder really contains `error/400.html` (a quick `HEAD` request). Without
  JavaScript the buttons fall back to `/`. The 404 page also shows the
  requested address.
- **Built-in server:** `php -S` ignores `.htaccess`, so `serve.bat` starts it
  with `error/router.php`, which returns the 404 page for missing files, 400
  for malformed paths and 403 for `.htaccess` files and `Require all denied`
  folders, like Apache.
- **When they appear:** 404/403/400 happen automatically; 500 on a fatal PHP
  error with `display_errors` off (keep it off outside local development);
  503 when Apache is overloaded or in maintenance. The app redirects logged-out
  users to the login page itself, so 401 only appears if the web server
  returns it.
- Preview any page at `http://localhost/bambosa/error/404.html`.

### Works from any subdirectory

No link, form action, asset URL or redirect hardcodes a leading `/`. Every
one is built with `base_url($path)`, which resolves against a
`BAMBOSA_BASE_PATH` constant detected once per request from
`$_SERVER['SCRIPT_NAME']` (both live in `includes/base_path_utils.php`).
The same code therefore runs from the domain root, `/bambosa/`, or a deeply
nested folder with no configuration, and the session cookie path follows the
detected base.

```php
// Good                                              // Bad
<a href="<?= base_url('officer/reports.php') ?>">    <a href="/officer/reports.php">
header('Location: ' . base_url('officer/dashboard.php'));   header('Location: /officer/dashboard.php');
```

Every page starts with `require_once` of `includes/session.php` and
`includes/auth.php` (adjust the relative path for pages in `admin/`,
`officer/` or `member/`); `base_path_utils.php` is pulled in by both.

The detection strips exactly one trailing `admin`, `officer` or `member`
folder. **Adding another role folder means adding it to the
`(admin|officer|member)` regex in `base_path_utils.php`**, or every link,
asset URL and redirect on its pages breaks. Pages two folders deep would
need an extra segment stripped as well.

### Search & pagination

Every list page — officer Members, Sectors, Machinery, Reservations,
Schedules, Billing Periods and a period's Bills; admin User Management and
User Login Logs; member Reservation History — has a search box and
pagination.

- **Search** is a `?q=` GET parameter matched server-side with a
  case-insensitive `LIKE '%term%'` on the relevant text columns, and it
  combines with any status/role filter tab.
- **Pagination** shows **15 rows per page** (`PAGINATION_PER_PAGE` in
  `includes/pagination.php`) with "Showing X–Y of Z" and prev/next/page
  links, hidden when everything fits on one page.
- **Helpers** in `includes/pagination.php`: `get_search_term()`,
  `get_current_page()`, `build_pagination()`, `render_search_box()`,
  `render_pagination_controls()` (the last two accept a parameter-name
  override so one page can host two independent tables).
- Each "get all X" data function takes optional `$search`, `$limit` and
  `$offset`, paired with a `count_*()` function; passing `null` returns every
  row, so callers that need an unpaginated list keep working.
- All search and `LIMIT/OFFSET` values are bound as prepared-statement
  parameters. The only dynamic SQL fragments are hardcoded strings chosen by
  server-side logic.
- Search boxes are plain GET forms, which drop the action URL's own query
  string on submit, so anything that must survive a search (a filter, a
  billing period's `?id=`) is passed through `render_search_box()`'s
  `$preserveParams`.

### Responsive UI

- The three sidebar partials (`admin_`, `officer_`, `member_sidebar.php` in
  `includes/partials/`) hold the nav links, active-state highlighting and
  mobile drawer markup in one place per role. Each nav item is an inline
  Material Design Icons `<svg>` (`fill="white"`, 24×24) followed by a
  `<span>` label, so icons look the same on every platform.
- **Desktop (> 880 px):** fixed-width sidebar. **Mobile/tablet (≤ 880 px):**
  the sidebar becomes a slide-in drawer opened by the hamburger button in a
  green top bar; the overlay, the ✕ button, or any nav link closes it.
  Logic is vanilla JS in `assets/js/dashboard.js`.
- Every `<table class="data-table">` sits in a `.table-scroll` wrapper so
  wide tables scroll sideways on small screens; forms (`.form-row`), the
  landing page and the login/signup cards have breakpoints down to ~480 px.
- **Favicon:** `assets/img/favicon.png` (rice-stalk logo) and
  `favicon.ico` (16/32/48 px fallback) are linked in the `<head>` of every
  page — admin, officer, member and the root auth pages — via `base_url()`.
  (Error pages embed theirs inline; the email template has none, since email
  clients ignore favicons.) To change it, replace the PNG (square, ≥ 144 px)
  and regenerate the `.ico`.

---

## 7. Security summary

- **Passwords:** `password_hash()` / `password_verify()`; minimum 8
  characters for new passwords; the current password is required to change
  your own password or email.
- **Sessions:** `httponly`, `samesite=Lax`, scoped cookie path; the session
  is re-validated against the database every request, so deleting,
  deactivating or re-roling an account applies immediately.
- **Authorisation:** every protected page enforces login and role
  (`require_role()`, or the equivalent check in the AJAX endpoints). Officers
  cannot change admin or officer accounts. Admins cannot delete, demote or
  deactivate themselves, and the last active admin is protected.
- **CSRF:** tokens on login, signup, forgot/reset password, profile,
  account-management and billing forms.
- **SQL injection:** prepared statements throughout; search terms and
  `LIMIT/OFFSET` are bound parameters.
- **XSS:** output is HTML-escaped; external API data (IP lookup) is escaped
  and inserted into the map popup with `textContent`.
- **Protected folders:** `config/`, `includes/`, `vendor/` and `sql/` are
  `Require all denied`, with matching behaviour under `serve.bat`.
- **Account enumeration:** the forgot-password response never reveals
  whether an address exists.
- **Reset tokens:** hashed at rest, single-use, 30-minute expiry,
  `hash_equals()` comparison.
- **Email failures** are logged, never shown to users.
- **Third-party calls:** the only outbound request carrying user-related
  data is the admin IP lookup (the IP address only, over HTTP on ip-api's
  free tier); map tiles and Leaflet come from public CDNs.

---

## 8. Developer notes

**Adding a page**
1. Start with `require_once` of `includes/session.php`,
   `includes/auth.php` and (if you touch the database)
   `config/database.php`.
2. Call `require_role('admin'|'officer'|'member')`.
3. Build every URL with `base_url()`.
4. Protect POST forms with `csrf_token()` / `csrf_verify()`; use prepared
   statements; escape output with `htmlspecialchars()`.
5. Set `$activeNav`, open `<div class="dash-shell">`, include the role's
   sidebar partial, wrap content in `<main class="dash-main">`, and load
   `assets/js/dashboard.js` before `</body>`. Wrap tables in `.table-scroll`.
6. Add the link to the sidebar partial (icon `<svg>` + `<span>`).
7. For list pages, use the helpers in `includes/pagination.php`.
8. Update this README and, if data or flows changed, the diagrams.

**Schema changes:** add `sql/migrations/NNN_description.sql` (idempotent)
and fold the same change into `sql/bambosa_db.sql`.

**PHP compatibility:** keep to PHP 7.4 syntax (see
[Requirements](#requirements)).

---

## 9. Database & diagrams

Thirteen tables, all in `sql/bambosa_db.sql`:

| Table | Purpose |
|---|---|
| `users` | All accounts — role `admin` / `officer` / `member`, status `active` / `inactive` / `pending` |
| `login_logs` | One row per successful login (user, time, IP) |
| `password_resets` | Hashed, single-use password-reset tokens |
| `sectors` | Irrigation sectors, with optional `boundary_geojson` |
| `farm_lots` | Members' parcels: sector, area, `boundary_geojson`, derived `latitude`/`longitude` |
| `sector_auto_assignments` | Audit log of automatic sector assignments |
| `machinery_groups` | Pools of interchangeable equipment |
| `machinery` | Individual machines (optional group, availability, `damage_level`) |
| `machinery_reservations` | Reservation requests, status flow and rejection remarks |
| `rotation_schedules` | Water-release windows per sector (`group_id` ties shared entries together) |
| `billing_periods` | Billing seasons with a ₱/ha rate |
| `isf_bills` | One bill per lot per period (area and rate snapshotted) |
| `isf_payments` | Individual payments against a bill |

**Deleting a user** cascades to their farm lots, reservations, ISF bills and
payments, login logs and reset tokens. Records a user merely created
(schedules, billing periods, recorded payments, auto-assignment log
entries) are kept with the author set to `NULL`.

Mermaid sources live in `docs/diagrams/` — plain text so they can be
updated alongside the code (see its `README.md` for viewing/editing):

- `erd.mermaid` — Entity-Relationship Diagram of the schema above.
- `dfd_level0.mermaid` — context diagram: Member, Officer, Admin, the SMTP
  server, OpenStreetMap tiles and ip-api.com around the whole system.
- `dfd_level1.mermaid` — the 8 major processes (auth, farm lots, sectors,
  machinery, scheduling, billing, dashboard/calendar, and admin account
  administration with login logs and IP lookup) mapped to their tables.

---

## 10. Project status

All of the capstone's specific objectives are implemented (simplified
where noted):

| Module | Status |
|---|---|
| Authentication, roles (admin / officer / member), self-service profiles | ✅ |
| Member & farm management | ✅ |
| Sector management, boundaries & auto-assignment | ✅ |
| Farm lot polygons and farm maps (web equivalent of QGIS mapping) | ✅ |
| Rotation scheduling (flag-only overlap detection, multi-sector apply) | ✅ |
| Machinery reservations (pools, stock-aware approval, condition tracking) | ✅ |
| ISF billing (periods, bills for lots inside sectors, payments with audit trail) | ✅ |
| Dashboard calendar | ✅ |
| Admin: user management, login logs, IP lookup, system clock | ✅ |
| Responsive UI, custom error pages, timezone sync, optional SMTP email | ✅ |

Possible follow-ups beyond the original scope: printable/PDF bills and
receipts; importing a real QGIS-exported boundary dataset (GeoJSON or
shapefile-derived) to refine the drawn polygons; an activation email when an
admin (not just an officer) activates a member; recording failed login
attempts.
