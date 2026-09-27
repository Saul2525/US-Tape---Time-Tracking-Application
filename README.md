# US Tape – Time Tracking Application

A lightweight, intranet-only employee time clock built with plain PHP and MySQL/MariaDB. Employees clock in and out with a 5-digit PIN at a shared kiosk. Managers review, correct and approve shifts and export them to Excel. Admins manage employee accounts.

> **Status: working prototype, not production-ready yet.** The core flows work end to end, but several security and data-integrity items must be fixed before real payroll use. See [ISSUES_AND_IMPROVEMENTS.md](ISSUES_AND_IMPROVEMENTS.md) (P0 items first).

---

## Handoff documentation

| Document | What it covers |
|---|---|
| **README.md** (this file) | What the app does, how the code is laid out, how to set it up and run it |
| [docs/XAMPP_Guide.pdf](docs/XAMPP_Guide.pdf) | What XAMPP is, why it is the recommended way to host this app, install, deployment, hardening, backups and troubleshooting |
| [docs/Git_Version_Control_Guide.pdf](docs/Git_Version_Control_Guide.pdf) | What Git and version control are, how this repository is organized (branches, history, CI), and the day-to-day workflow for the next team |
| [ISSUES_AND_IMPROVEMENTS.md](ISSUES_AND_IMPROVEMENTS.md) | Living list of known bugs, security gaps and planned work, grouped by priority |
| `TEMP ERD Diagrams.png` | **Target** database design. It includes tables and columns that do not exist yet (see "Database" below) |

---

## Features

**Employees (no login, kiosk page)**
- Clock in / clock out by typing a 5-digit PIN on `index.php`. The same PIN toggles: if the employee has an open shift they are clocked out, otherwise they are clocked in.
- The IP address is recorded on every punch.

**Managers (email + password login)**
- Manager dashboard listing active employees, with name search.
- Per-employee shift view with a date-range filter and total hours for the range.
- Edit clock-in/clock-out times and approve shifts. Every edit marks the shift as `Edited` and writes a before/after record to `AUDIT_LOG`.
- **Export to Excel**: downloads a CSV (opens in Excel) of the shifts matching the current filters, either for all employees or a single employee.

**Admins (managers with the `can_manage_users` permission)**
- Create employees and managers. PINs are auto-generated and unique, or can be set manually.
- Activate / deactivate accounts. Deactivated employees can no longer clock in. Admins cannot deactivate themselves.

**Background**
- `cron_anomaly.php` lists shifts that have been open for more than 10 hours (forgotten clock-outs). It prints to the console only and has no notification path yet.

---

## Tech stack

| Layer | Technology |
|---|---|
| Web server | Apache (via XAMPP) or PHP's built-in dev server |
| Language | PHP 8.2+ (no framework, no Composer dependencies on `main`) |
| Database | MariaDB 10.4+ (via XAMPP) or MySQL 8 |
| DB access | PDO with prepared statements |
| Front end | Server-rendered HTML with inline CSS. No JavaScript build step |
| Version control | Git + GitHub ([Saul2525/US-Tape---Time-Tracking-Application](https://github.com/Saul2525/US-Tape---Time-Tracking-Application)) |
| CI (branch `ci-testing` only) | GitHub Actions + PHPUnit 13 |

The app was built for US Tape's intranet-only requirement, confirmed with US Tape IT on 2026-04-14. Nothing is loaded from the internet (no CDNs, no third-party libraries at runtime).

---

## Project structure

```
timeclock/
├── index.php                  Employee kiosk: PIN entry (start page)
├── clock_handler.php          Handles the PIN POST, toggles clock in/out
├── manager_login.php          Manager/admin login (email + password)
├── manager_dashboard.php      Employee list + search + export button
├── employee_shifts.php        One employee's shifts: filter, edit, approve
├── update_shift.php           Saves a shift edit and writes AUDIT_LOG
├── export_shifts.php          CSV export (Excel) respecting current filters
├── admin.php                  Create / activate / deactivate employees
├── cron_anomaly.php           CLI script: flags shifts open > 10 hours
├── config.php                 DB connection (NOT in Git, create it yourself)
├── db.sql                     Base schema + seed roles
├── dashboard.php              Legacy: last-7-days hours by ?employee_id=
├── profile.php                Legacy: total/approved hours by ?email=
├── create_user.php            Legacy: unauthenticated create endpoint
├── ISSUES_AND_IMPROVEMENTS.md Known issues / roadmap
├── TEMP ERD Diagrams.png      Target ERD (future schema)
└── docs/                      Handoff PDFs (XAMPP, Git)
```

`dashboard.php`, `profile.php` and `create_user.php` are early pages that nothing links to anymore. They have no access control. `admin.php` replaces `create_user.php`. Remove all three before deployment, or lock them down (see ISSUES P0). The PHPUnit tests on the `ci-testing` branch currently test `create_user.php`, so move those tests to `admin.php` first.

### Page flow

```
index.php ──PIN──▶ clock_handler.php ──(3s)──▶ index.php
    │
    └─"Manager Sign In"─▶ manager_login.php ─▶ manager_dashboard.php
                                                  ├─▶ employee_shifts.php ─Save─▶ update_shift.php
                                                  ├─▶ export_shifts.php (CSV download)
                                                  └─▶ admin.php  (admins only)
```

---

## Roles and permissions

Roles live in the `ROLES` table. Pages check the permission flags, not the role names:

| role_id | Role | can_edit_others | can_approve | can_manage_users | Can use |
|---|---|---|---|---|---|
| 1 | Employee | 0 | 0 | 0 | Kiosk only |
| 2 | Manager | 1 | 1 | 0 | Dashboard, shifts, export |
| 3 | Admin | 1 | 1 | 1 | Everything, including `admin.php` |

`manager_login.php` also hardcodes `role_id` 2 or 3 as the roles that can log in.

---

## Database

Four tables (`db.sql`):

- **ROLES**: role name + permission flags (seeded with Employee / Manager / Admin).
- **EMPLOYEES**: `employee_id`, `first_name`, `last_name`, `email` (unique), `pin`, `role_id` → ROLES, `is_active`, `created_at`.
- **WORK_TIMES**: one row per shift. `clock_in_time`, `clock_out_time` (NULL while the shift is open), `clock_in/out_lat/lng`, `clock_in/out_ip`, `approved`, `is_edited`.
- **AUDIT_LOG**: one row per manager edit. `work_time_id`, `employee_id`, `changed_by`, `old_values` / `new_values` (JSON), `change_reason`, `action_timestamp`.

### ⚠️ `db.sql` is behind the working database

The development database was changed by hand and `db.sql` was never updated. A database built only from `db.sql` **cannot log in any manager**, because `manager_login.php` and `admin.php` need a `password` column. After importing `db.sql`, run:

```sql
USE timeclock;
ALTER TABLE EMPLOYEES ADD COLUMN password VARCHAR(255) NULL;
ALTER TABLE EMPLOYEES MODIFY pin CHAR(5) NOT NULL;
ALTER TABLE EMPLOYEES ADD UNIQUE (pin);
```

Folding these changes into `db.sql` is tracked in ISSUES_AND_IMPROVEMENTS.md.

The ERD image shows a *future* schema (`PAY_RATES`, approval and edit metadata columns, notes). Those tables and columns do not exist yet.

---

## Setup and running locally

### Option A: XAMPP (recommended, and how it should be deployed)

Full step-by-step walkthrough, including hardening and backups: **[docs/XAMPP_Guide.pdf](docs/XAMPP_Guide.pdf)**. Short version:

1. Install XAMPP (PHP 8.2+) and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy the `timeclock` folder into XAMPP's `htdocs` folder (`C:\xampp\htdocs\timeclock` on Windows, `/Applications/XAMPP/htdocs/timeclock` on macOS).
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database named `timeclock` (collation `utf8mb4_general_ci`).
4. Select it, then **Import** `db.sql`. Next, run the migration SQL from the "Database" section above in the **SQL** tab.
5. Create `config.php` (see below).
6. Create the first admin account (see below).
7. Browse to `http://localhost/timeclock/`.

### Option B: PHP built-in server + standalone MySQL (current dev setup)

This is how the app has been developed so far on the team's Macs (Homebrew MySQL on port 3306):

```bash
brew services start mysql
mysql -u root -e "CREATE DATABASE IF NOT EXISTS timeclock"
mysql -u root timeclock < db.sql            # then run the migration SQL above
cd "/path/to/US Tape Time Tracking Application/timeclock"
php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000`. The built-in server is for development only. It handles one request at a time and should never serve the shop floor.

### `config.php` (required, not in Git)

`config.php` is in `.gitignore` so database passwords never get committed. After cloning, create it next to `index.php`:

```php
<?php
$host = "127.0.0.1";
$port = 3306;          // XAMPP default is 3306 (see note below)
$db   = "timeclock";
$user = "root";
$pass = "";            // set this once the MySQL root password is changed

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
```

> **Port note:** The current local `config.php` hardcodes port `3306` in the DSN. On the original dev Mac, Homebrew MySQL already uses 3306, so XAMPP's MariaDB was moved to **3307**. If you run XAMPP next to another MySQL, set `$port` to match `port=` in XAMPP's `my.cnf` / `my.ini`.

For production, use a dedicated MySQL user with a strong password, not `root` with an empty password.

### Creating the first admin

`admin.php` requires an admin to be logged in, so the first account has to be inserted by hand (phpMyAdmin → SQL tab):

```sql
INSERT INTO EMPLOYEES (first_name, last_name, email, pin, password, role_id, is_active)
VALUES ('First', 'Admin', 'admin@ustape.local', '10000', 'ChangeMe!123', 3, 1);
```

Log in at `/manager_login.php` with that email and password, open **Employee Management**, and create everyone else from there.

> Manager passwords are currently stored and compared in **plain text** (ISSUES P0). Do not reuse a real password until that is fixed with `password_hash()` / `password_verify()`.

### Local sample data

The original dev database has three test employees with the kiosk PINs `21850`, `09924` and `95425`. These exist only in that local database, not in `db.sql`. Never reuse them on a real deployment.

---

## Using the app

1. **Employee:** open the kiosk page, type your PIN, press Submit. You'll see "Clocked IN" or "Clocked OUT", then the page returns to the keypad after 3 seconds.
2. **Manager:** click **Manager Sign In**, log in, pick an employee → **View Shifts**. Set a date range, adjust times in `YYYY-MM-DD HH:MM` format, tick **Approved**, then click **Save Changes** on that row. Each row saves on its own.
3. **Export:** **Export to Excel** on either page downloads `shifts_<from>_to_<to>.csv` with the filters that page is showing. Columns: Employee, Email, Clock In, Clock Out, Hours, Approved, Edited.
4. **Admin:** **Employee Management** on the dashboard (visible to admins only).
5. **Forgotten clock-outs:** `php cron_anomaly.php`. Schedule it with Windows Task Scheduler or cron once it can send alerts.

---

## Known limitations (read before go-live)

The full, prioritized list is in [ISSUES_AND_IMPROVEMENTS.md](ISSUES_AND_IMPROVEMENTS.md). The biggest ones:

- **Security:** plain-text manager passwords and PINs; no PIN rate-limiting; `update_shift.php` does not check permissions; the legacy pages have no access control; no CSRF tokens.
- **Times are stored and shown in UTC.** Punches use `UTC_TIMESTAMP()`, but the pages show the raw value and the date filters use server-local dates. On Eastern time, displayed times run 4–5 hours ahead, and shifts near midnight can fall in the wrong day's filter.
- **Location is never captured.** The `*_lat` / `*_lng` columns exist and `clock_handler.php` accepts them, but `index.php` does not send them, so they are always NULL.
- **No server-side validation of edited times.** At least one negative-hours shift already exists in dev data.
- **Schema drift** between `db.sql` and the working database (see above).

---

## Testing and CI

Automated tests live on the **`ci-testing`** branch and have not been merged into `main` yet:

- `composer.json` / `phpunit.xml`: PHPUnit 13 (dev-only dependency; `vendor/` is gitignored).
- `tests/ClockHandlerTest.php`, `tests/CreateUserTest.php`, `test_helpers/BaseWebTest.php`: HTTP-level tests that POST to a running server with cURL.
- `.github/workflows/ci.yml`: on every push to `ci-testing` and every pull request into `main`, GitHub Actions starts MySQL 8, imports `db.sql`, writes a CI `config.php`, starts `php -S`, and runs `composer test`.

To run locally on that branch: `git checkout ci-testing && composer install`, start MySQL and `php -S 127.0.0.1:8000`, then run `composer test`.

---

## Version control

The Git repository is the `timeclock/` folder. The parent `US Tape Time Tracking Application/` folder is **not** a repository, so run Git commands from inside `timeclock/`. Branches, history, the CI branch and the recommended workflow are all covered in **[docs/Git_Version_Control_Guide.pdf](docs/Git_Version_Control_Guide.pdf)**.

Quick start:

```bash
git clone git@github.com:Saul2525/US-Tape---Time-Tracking-Application.git timeclock
cd timeclock
git checkout -b feature/short-description
# ...make changes...
git add <files> && git commit -m "fix: describe the change"
git push -u origin feature/short-description   # then open a Pull Request into main
```

---

## Authors

Developed by Gabriel Kagwanja, Chris Anderson and Saul Toribio for US Tape.
