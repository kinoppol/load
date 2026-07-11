# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ระบบเบิกค่าตอบแทนการสอนเกินภาระงาน** — a Thai-language web application for managing teaching overload compensation claims at Sattahip Technical College. Teachers submit weekly claim records for hours taught beyond their normal load (18 periods/week); department staff and directors approve them; accounting processes payment.

Runs on WampServer (Apache + PHP 8+ + MariaDB) at `http://localhost/load`.

## Environment Setup

1. Import the database: `mysql -u root < sql/schema.sql` — this creates the `teaching_compensation` database and loads seed data.
2. Edit `config/config.php` to adjust DB credentials if needed (defaults: `root` / no password).
3. On first run, visit `/load/install.php` to run the web installer. After installation, `install.lock` prevents re-running it.
4. Default login credentials (all password `1234`): `admin`, `director`, `curriculum`, `accounting`, `teacher1`–`teacher8`.

## Architecture

### Request Flow

- **Entry point**: `index.php` — requires auth, outputs the SPA shell (sidebar + topbar + empty `#page-content` div), and injects `APP_CONFIG` (role, userId, baseUrl) as a JS global.
- **Frontend router**: `assets/js/app.js` — single-file vanilla JS SPA. The `pages` object maps page IDs to render functions. `navigate(page)` is called from the sidebar nav and replaces `#page-content` innerHTML.
- **API layer**: `api/*.php` — each file handles one resource via `$_SERVER['REQUEST_METHOD']` and a POST `action` param. All return `{success, data, message}` JSON. Auth is enforced at the top of every API file via `Auth::requireApi()` and `Auth::requireRole()`.

### Backend Patterns

- **`DB` class** (`includes/db.php`): singleton PDO wrapper. Use `DB::fetch()` for single row, `DB::fetchAll()` for lists, `DB::insert()` for INSERT + last ID, `DB::exec()` for UPDATE/DELETE row count, `DB::query()` for raw PDOStatement.
- **`Auth` class** (`includes/auth.php`): session management. Call `Auth::requireLogin()` at top of page files, `Auth::requireApi()` at top of API files. Role check: `Auth::can('admin', 'director')`.
- **`get_input()`** (`includes/functions.php`): reads JSON body or falls back to `$_POST` — use this in all API POST handlers.
- **`json_ok()` / `json_err()`**: response helpers that also call `exit`.

### Role Model

Five roles with progressive access:
- `teacher` — sees only their own claims and attendance
- `curriculum` — manages claims, rules, periods, attendance for all teachers
- `director` — approves/rejects claims and makeup/substitute requests
- `accounting` — views periods and reports, marks payments
- `admin` — full access including user management

### Key Business Logic

- `calculate_amount()` in `includes/functions.php`: computes compensation. Over-periods = `min(total_periods - normal_load, max_claimable)`. Amount = over_periods × rate if students ≥ min_students, otherwise per-head rate.
- Compensation rules (`compensation_rules` table) are per-semester: normal load (default 18), max claimable (default 10), min students (default 25), per-head rate, holiday handling.
- Teaching rates (`teaching_rates`) are per-semester and per education level: pvch (ปวช.), pvs (ปวส.), degree.
- `claim_periods` divides a semester into billing windows (งวด); status flows `open → locked → paid`.
- `claim_records` status flows: `draft → pending → approved/rejected → paid`.

### Frontend Patterns

- All API calls go through `api(url, opts)` and `post(url, body)` helpers (thin wrappers around `fetch`).
- `APP_CONFIG.baseUrl` is always prepended to API URLs.
- `can(...roles)` mirrors server-side role checks for showing/hiding UI elements.
- Page render functions in `app.js` are defined in a `pages` object keyed by the nav IDs: `dashboard`, `claims`, `rules`, `periods`, `attendance`, `makeup`, `reports`, `institution`, `users`.
- UI components: `toast(msg, type)` for notifications, `showModal(html)` / `closeModal()` for dialogs.
- Theme (`light`/`dark`/`system`) is stored in `localStorage` key `tc_theme` and applied as `data-theme` on `#app`.

### File Uploads

File uploads go to `/uploads/` (max 5 MB, defined in `config/config.php`). The directory is git-tracked via `.gitkeep` but its contents are gitignored.
