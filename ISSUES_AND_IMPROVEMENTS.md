# US Tape Time Tracker — Known Issues & Improvement Plan

Living reference doc. Update checkboxes as items get fixed. Grouped by priority:
**P0 = security/data-integrity, fix before real use. P1 = correctness/reliability. P2 = polish/features.**

---

## P0 — Security

- [ ] **Plain-text password comparison.** `manager_login.php:25` does `$user['password'] !== $password` with no hashing. README claims `password_hash`/`password_verify` is used — it isn't, for manager/admin login. Need to hash on create (`admin.php`) and verify with `password_verify()` on login.
- [ ] **PINs are plain text, not hashed.** `clock_handler.php` queries `WHERE pin = ?` directly. Only 100,000 possible 5-digit PINs and no rate limiting/lockout — brute-forceable from the clock-in screen. Decide: hash PINs (matches README's original claim) and/or add attempt throttling.
- [ ] **No auth check on `create_user.php`.** Anyone can POST to it and create an employee (with a known/guessable PIN) — no `session_start()`, no role check at all. Either delete this file (superseded by `admin.php`'s create flow) or lock it down the same way `admin.php` is.
- [ ] **IDOR on `dashboard.php`, `profile.php`, `employee_shifts.php`.** All three take `employee_id` or `email` as a plain GET parameter with no session/ownership check — anyone can view (and on `employee_shifts.php`, indirectly edit via `update_shift.php`) any employee's hours by changing the URL. Need to either require login + restrict to self, or require manager/admin session for the manager-facing pages.
- [ ] **No permission check in `update_shift.php`.** It calls `session_start()` and logs `$_SESSION['user_id']` to the audit table, but never verifies the session actually has `can_edit_others` before applying the edit. Add the same role-flag check `manager_dashboard.php` and `admin.php` already use.
- [ ] **No CSRF protection anywhere.** Clock in/out, shift edits, employee create/deactivate — all plain POST forms with no token. Add CSRF tokens at least to the manager/admin-side state-changing forms.
- [ ] **Geolocation is trust-on-input.** `lat`/`lng` come straight from the client with no server-side sanity check (range, plausibility vs. IP, etc). Fine as a UX nicety, but don't treat it as a location *control* without validation.

## P0 — Data integrity / schema

- [ ] **Schema drift: `EMPLOYEES.password` doesn't exist in `db.sql`** but `manager_login.php` and `admin.php` both reference it (`admin.php` even defensively runs `SHOW COLUMNS ... LIKE 'password'` first). Reconcile: add the column to `db.sql` officially, migrate, and remove the defensive existence-check once it's guaranteed to be there.
- [ ] **`EMPLOYEES.pin` has no `UNIQUE` constraint in `db.sql`** (the ERD shows one, the live schema doesn't). `generateUniquePin()` checks-then-inserts, which is a TOCTOU race without a DB-level backstop — two near-simultaneous employee creations could get the same PIN. Add `UNIQUE` to the column.
- [ ] **No server-side validation on manually edited shift times** in `update_shift.php` — only an HTML `pattern` attribute on the client. Nothing stops `clock_out` before `clock_in`, garbage strings, or wildly implausible durations from being saved. Validate server-side before the UPDATE.
- [ ] **ERD (`TEMP ERD Diagrams.png`) describes a target schema, not the current one** — `PAY_RATES` table, `clock_in_note`/`clock_out_note`, `approved_by_id`/`approved_at`/`approval_note`, `edited_by_id`/`edited_at`/`edit_reason` on `WORK_TIMES` are all planned but not in `db.sql`. Either treat the ERD as the migration backlog and chip away at it, or update the ERD to reflect reality so it stops being misleading.

## P1 — Correctness / reliability

- [ ] `dashboard.php` reads `$_GET['employee_id']` with no existence check — missing/invalid id produces a raw PHP warning instead of a clean error.
- [ ] `cron_anomaly.php` only echoes alerts to stdout. If this is meant to run on a schedule, it needs an actual notification path (email/Slack/log file) or it will alert no one.
- [ ] Audit log capture in `update_shift.php` assumes `$_SESSION['user_id']` is set — fine today since the page is (nominally) session-gated, but once the permission check above is added, make sure the audit insert still can't run with a null `changed_by`.
- [x] ~~"Export to Excel" buttons on `manager_dashboard.php` and `employee_shifts.php` are non-functional stubs~~ — **Done (2026-09-01).** Added `export_shifts.php`: CSV download (no third-party libraries, per the intranet-only constraint), gated behind the same `can_edit_others` session check as `manager_dashboard.php`. Wired both buttons to it, carrying the page's current `from`/`to`/`search`/`employee_id` filters. Tested locally end-to-end (unauthenticated block, full export, single-employee export, search-filtered export) — see note below.
- [ ] **Confirmed live**: while testing the export against real local data, one shift row came back with **negative hours** (clock-out before clock-in, employee_id 3, 2026-04-20 17:51 → 17:46). This is direct evidence for the "no server-side validation on edited shift times" item above — it's not a hypothetical, bad data already exists. Prioritize the `update_shift.php` validation fix.

- [ ] **Timezone mismatch.** `clock_handler.php` stores punches with `UTC_TIMESTAMP()`, but `employee_shifts.php` / `export_shifts.php` display the raw value and build date-filter boundaries from PHP's local `date()`. Managers see UTC times (4–5h ahead of Eastern), manual edits entered in local time get mixed with UTC rows, and shifts near midnight can land in the wrong day's filter. Pick one convention (store UTC, convert for display/input with `America/New_York`) and apply it everywhere.
- [ ] **Location is never captured.** `clock_handler.php` accepts `lat`/`lng`, but `index.php` never sends them (no geolocation JS), so those columns are always NULL. Note that browsers only allow geolocation on HTTPS or `localhost`, so a plain-HTTP intranet kiosk will need HTTPS to use it.
- [ ] **Saving an open shift crashes (confirmed 2026-09-28).** On `employee_shifts.php`, pressing Save Changes on a shift whose Clock Out is blank sends `clock_out = ''` to `update_shift.php`, and MySQL rejects it (`Incorrect datetime value: ''`), so the page shows a PHP fatal error. Managers therefore cannot approve or add a note to a shift that is still in progress. Convert an empty clock-out to `NULL` before the UPDATE.
- [ ] **Invalid edited time text crashes the page (confirmed 2026-09-28).** A clock-in value like `garbage` (possible if browser validation is bypassed) produces a PHP fatal error instead of a friendly message. Part of the server-side validation item above.
- [ ] **Only the clicked row is saved (confirmed 2026-09-28).** The shifts table is one form, but `update_shift.php` only reads the row whose Save Changes button was pressed. Edits typed into other rows are discarded without warning. Either save all changed rows, or warn the manager about unsaved edits.
- [ ] **No sign-out button.** Manager sessions end only when the browser closes or after ~24 minutes idle (PHP's default `session.gc_maxlifetime`). On a shared PC the next person can use the manager pages until then. Add a `logout.php` that destroys the session and link it from the dashboard.

## P2 — Polish / planned features (from README's own roadmap)

- [ ] Admin dashboard — partially done (`admin.php`), but no editing of existing employee details (name/email/role) after creation, only activate/deactivate.
- [ ] Shift approval is a single checkbox with no note field, no per-approval audit trail (`approved_by`/`approved_at` — see ERD gap above).
- [ ] Payroll reporting — not started; likely depends on the `PAY_RATES` table being added first.
- [ ] UI styling — currently inline styles per-page; consider a shared stylesheet once the page count grows further.

## Feature log

- [x] **Manager shift notes (2026-09-15).** Added `WORK_TIMES.manager_note` (VARCHAR 255, nullable) — a free-text field managers can set per shift (e.g. "late", "left early") from `employee_shifts.php`, right next to the Edited/Action columns. Flows into the audit log (old/new note captured on every edit) and into the CSV export as a new "Note" column. Added to `db.sql` at the same time as the live DB, specifically to avoid repeating the `password`-column schema drift noted above — **if this app is ever deployed anywhere besides this machine, that column needs to be added there too** (`ALTER TABLE WORK_TIMES ADD COLUMN manager_note VARCHAR(255) NULL AFTER is_edited;`).

## Notes

- Repo has a `.git` folder inside `US-Tape---Time-Tracking-Application/`, not at the workspace root — keep that in mind when running git commands from the top-level folder.
- `config.php` ships with local dev credentials (`root` / empty password) committed to the repo. Fine for local MySQL, but confirm this file (or a prod variant of it) is never the one used against a real deployment, and that real credentials never get committed here.
