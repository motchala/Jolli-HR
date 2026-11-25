# Code Review — `admin-panel`

## Evidence Collected

Files read: `database.sql`, `includes/auth.php`, `includes/db.php`, `login.php`, `jobs.php` (student-facing), `admin/includes/admin_header.php`, `admin/includes/admin_footer.php`, `admin/index.php`, `admin/applicants.php`, `admin/applicant.php`, `admin/jobs.php`, `assets/js/admin.js` (confirmed present), `assets/css/admin.css` (confirmed present), `requirements.md`, `design.md`, `plan.md`.

No git repository exists; commit-message test evidence is unavailable. Static analysis was used as the primary verification path.

XAMPP `php.ini` confirms `output_buffering=4096`.

---

## Check Results

### 1. All output escaped with `htmlspecialchars`

All user-sourced values rendered to HTML are wrapped in `h()` (which calls `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')`). The function is defined once in `includes/auth.php` with a `function_exists` guard and is therefore available on every page.

Examined outputs in each file:
- `admin/index.php`: names, job titles, statuses, timestamps — all via `h()`. Dashboard counts cast to `(int)`. ✓
- `admin/applicants.php`: names, emails, titles, statuses, applied-at — all via `h()`. ✓
- `admin/applicant.php`: all profile fields, document names, statuses, flash message — all via `h()`. The two inline string literals `'Verified'`/`'Unverified'` and `'Unverify'`/`'Verify'` are developer-defined constants, not user data. ✓
- `admin/jobs.php`: all job fields in table and in `data-*` attributes for `editJob()` — all via `h()`. `match_pct` in the table cell uses `(int)` cast. ✓
- `admin/includes/admin_header.php`: `$title` is passed through `h()`; nav `class=` attribute outputs only the string literals `'on'` or `''` based on comparing `$active` to developer strings. ✓
- `login.php` line 56: `<?= $err ?>` is unescaped. The `$err` variable is set only to two developer-defined string literals (lines 28, 30) and never echoes user input, so there is no XSS path. This is a pre-existing pattern unchanged by this feature.

### 2. All DB writes use parameterized PDO queries

Every INSERT, UPDATE, and DELETE in the admin pages binds values via `?` parameters:
- `admin/applicant.php`: `UPDATE applications SET status = ? WHERE id = ?` ✓; `UPDATE user_documents SET verified = 1 - verified WHERE id = ? AND user_id = ?` ✓; the `reject` branch uses `UPDATE applications SET status = 'Rejected' WHERE id = ?` with a literal `'Rejected'` value (not user input). ✓
- `admin/jobs.php`: INSERT and UPDATE both use `?` for all six fields plus `job_id`. DELETE uses `?` for `job_id`. ✓
- `login.php`: SELECT uses `?` for email. ✓
- SELECT queries in `applicants.php` with the status filter use `?` when a filter is active; the value is whitelisted against `$allowed` before use anyway. ✓

No string interpolation into SQL was found anywhere in the new or modified files.

### 3. POST-redirect-GET pattern on all form submissions

- `admin/applicant.php`: all three POST actions (`advance_status`, `reject`, `toggle_doc`) end with `header('Location: applicant.php?id=' . $appId); exit;`. ✓
- `admin/jobs.php`: `delete`, `add` (success), and `edit` (success) all end with `header('Location: jobs.php'); exit;`. Validation failure on `add`/`edit` falls through to re-render the form with inline errors — this is the specified behavior per the requirements ("re-display the form with an inline error message") and design, not a PRG violation. ✓
- `login.php`: success redirects to `admin/index.php` or `home.php`. ✓

### 4. `require_admin()` called at top of every admin page

- `admin/applicant.php` line 6: explicit `require_admin()` call before POST handler and before any output. ✓
- `admin/jobs.php` line 6: explicit `require_admin()` call before POST handler and before any output. ✓
- `admin/index.php` line 5: includes `admin_header.php` which calls `require_admin()` at its line 5. No POST handler; guard fires before any query or output. ✓
- `admin/applicants.php` line 5: includes `admin_header.php` which calls `require_admin()` at its line 5. No POST handler; guard fires before any query or output. ✓
- `admin/includes/admin_header.php` line 5: `require_admin()` called after including `auth.php` and before emitting any HTML. ✓

### 5. Admin pages inaccessible to non-admin users

`require_admin()` in `includes/auth.php` checks `empty($_SESSION['user']['is_admin'])`. On failure it unsets the session and redirects to `BASE_PATH . '/login.php'` (`/Jolli-HR/login.php`). `BASE_PATH` is defined in `includes/db.php` which is included before `auth.php` on every admin page. The redirect is root-relative and works from any admin page depth. ✓

The already-logged-in guard in `login.php` (lines 4–7) branches on `is_admin`, sending admins to `admin/index.php` and non-admins to `home.php`. ✓

### 6. `is_admin` column and demo admin seed in `database.sql`

```sql
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0;
```
Present, placed after the `users` CREATE TABLE block. Uses `IF NOT EXISTS` for idempotency. ✓

```sql
INSERT IGNORE INTO users (first_name, last_name, email, password_hash, is_admin) VALUES
  ('Admin', 'HR', 'admin@jollihr.test',
   '$2y$10$cZS4ryhi8HBg3.5hfVjWUePbmJUH812ExaMedtbAJIZQZLTUmZJYS', 1);
```
Present, uses `INSERT IGNORE` for idempotency, `is_admin = 1`. Hash matches the design specification for `admin123`. ✓

The `applications.status` ENUM is extended to include `'Rejected'` via `MODIFY COLUMN`. ✓

### 7. All features from requirements present

| Requirement | Status |
|---|---|
| FR-1.1 `is_admin` column | ✓ |
| FR-1.2 Demo admin seed | ✓ |
| FR-1.3 Session stores `is_admin`, redirect to `admin/index.php` | ✓ |
| FR-1.4 `require_admin()` in `includes/auth.php` | ✓ |
| FR-1.5 All admin pages call `require_admin()` before output | ✓ |
| FR-2.1 Four summary cards on dashboard | ✓ |
| FR-2.2 Recent Applications list (10, DESC) | ✓ |
| FR-2.3 Each row links to applicant detail | ✓ |
| FR-3.1 Applicant list table with all columns | ✓ |
| FR-3.2 Status filter with `?status=` query param | ✓ |
| FR-3.3 Each row links to applicant detail | ✓ |
| FR-4.1 Applicant profile fields displayed | ✓ |
| FR-4.2 Document list with toggle (PRG) | ✓ |
| FR-4.3 Status stepper (advance/reject, PRG) | ✓ |
| FR-4.4 Rejected hides advance/reject form | ✓ |
| FR-4.5 Flash messages on status/doc change | ✓ |
| FR-5.1 Jobs list with all columns | ✓ |
| FR-5.2 Add job form (POST, PRG) | ✓ |
| FR-5.3 Edit job form (pre-fills via JS, POST, PRG) | ✓ |
| FR-5.4 Delete job (POST, PRG, JS confirm) | ✓ |
| FR-5.5 Delete cascades to applications | ✓ (cascade defined in schema) |
| FR-6.1 Admin pages use top-nav layout | ✓ |
| FR-6.2 Top nav: Dashboard, Applicants, Jobs, Logout | ✓ |
| FR-6.3 Admin footer closes HTML and includes both JS files | ✓ |
| FR-6.4 Stylesheets via relative path from `admin/*.php` | ✓ (`../../assets/css/`) |
| NFR-1 All output escaped, writes parameterized, `require_admin()` on all pages | ✓ |
| NFR-2 PRG pattern consistent | ✓ |
| NFR-3 `ADD COLUMN IF NOT EXISTS` | ✓ |

---

## Notes (Non-Blocking)

**`admin/applicant.php` GET-path redirect after `admin_header.php` output (line 73 → line 88–90):** If a valid but non-existent application ID is requested, `admin_header.php` emits ~1,178 bytes of HTML before the redirect fires. XAMPP's default `output_buffering=4096` keeps this in the buffer, so the `header()` call succeeds before the buffer flushes. This is XAMPP-specific behavior and matches the project's stated deployment environment. It is a minor structural concern but not a reachable correctness failure on the target platform.

**No git repository / no commit-message test evidence:** The plan specifies browser-verification steps for each implementation item. No commit history is available to read. Static analysis confirms the implementation matches the design specifications.

---

## Verdict

No blockers found. The implementation meets all functional and non-functional requirements in the approved specifications.
