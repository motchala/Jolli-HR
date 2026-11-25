# Technical Design — `admin-panel`

## Overview

The admin panel is a self-contained subdirectory (`admin/`) within the existing JolliHR project. It reuses every existing include (`includes/db.php`, `includes/auth.php`, `includes/icons.php`) and the existing CSS assets, adding only an `admin/includes/` layer for the admin-specific header/footer and a thin `assets/css/admin.css` for layout overrides. The database gains one column (`users.is_admin`) and one new ENUM value (`applications.status = 'Rejected'`). All admin pages follow the POST-redirect-GET pattern already established in `jobs.php`. No new PHP framework, router, or templating engine is introduced.

---

## Technology Stack (locked)

- **Language:** PHP 8+
- **Database:** MySQL via the existing `db()` PDO singleton (`includes/db.php`)
- **CSS:** Vanilla CSS — existing `assets/css/style.css` plus a new `assets/css/admin.css`
- **JavaScript:** Vanilla JS — a new `assets/js/admin.js` for edit-job population; one inline `confirm()` call for the job-delete button; the existing `assets/js/app.js` is **not modified**
- **Server:** XAMPP / Apache, no `.htaccess` rewrites required

---

## Database Changes

### 1. `users.is_admin` column

Added in `database.sql` using `ALTER TABLE … ADD COLUMN IF NOT EXISTS` so that re-importing the file on an existing database is a no-op:

```sql
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0;
```

This is placed after the `users` `CREATE TABLE` block. All existing rows default to `0`; no data migration is needed.

### 2. `applications.status` ENUM extended with `'Rejected'`

The existing ENUM is `('Applied','Docs Verified','Training','Hired')`. It gains `'Rejected'` as a fifth value:

```sql
ALTER TABLE applications
  MODIFY COLUMN status
    ENUM('Applied','Docs Verified','Training','Hired','Rejected')
    NOT NULL DEFAULT 'Applied';
```

This is a non-destructive change; existing rows are unaffected. It is placed directly after the `applications` `CREATE TABLE` block.

**Note on `db_bootstrap()` and idempotency:** `ADD COLUMN IF NOT EXISTS` is natively idempotent — MySQL will skip the statement if the column exists. `MODIFY COLUMN` works differently: it re-applies the **full** ENUM definition every time `db_bootstrap()` runs. This is safe as long as `database.sql` always lists every intended ENUM value in the `MODIFY COLUMN` statement. If the ENUM is ever extended again, that later `MODIFY COLUMN` must include all previous values (`Applied`, `Docs Verified`, `Training`, `Hired`, `Rejected`, plus the new value) or existing rows with removed values will be silently set to the column's default. These two mechanisms are **not interchangeable**: do not assume `MODIFY COLUMN` is safe the same way `IF NOT EXISTS` is safe.

### 3. Demo admin seed

```sql
INSERT IGNORE INTO users (first_name, last_name, email, password_hash, is_admin) VALUES
  ('Admin', 'HR', 'admin@jollihr.test',
   '$2y$10$cZS4ryhi8HBg3.5hfVjWUePbmJUH812ExaMedtbAJIZQZLTUmZJYS', 1);
```

The hash above is a real bcrypt hash (cost 10) of the password `admin123`, generated at design time via `password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10])`. It is embedded as a literal string consistent with how the demo student account is seeded. Verify with `password_verify('admin123', '<hash>')` during QA.

---

## Auth Changes (`includes/auth.php`)

### Guard `session_start()` with `session_status()`

The existing `session_start()` call at file scope in `auth.php` is unguarded. It must be changed to:

```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

This makes `auth.php` safe to `require_once` from any depth or order. Without this guard, a page that includes `auth.php` directly **and** includes `admin_header.php` (which also includes `auth.php`) will trigger a PHP `E_NOTICE` and risk corrupting session state. The `require_once` deduplication mechanism prevents the double-include today, but the guard makes the intent explicit and survives future refactors.

### Session shape after login

`login.php` already reads `id`, `first_name`, `last_name` from the `users` row. It is extended to also select `is_admin`:

```php
$st = db()->prepare(
    'SELECT id, first_name, last_name, password_hash, is_admin FROM users WHERE email = ?'
);
```

On success the session write becomes:

```php
$_SESSION['user'] = [
    'id'       => (int)$u['id'],
    'name'     => $u['first_name'] . ' ' . $u['last_name'],
    'first'    => $u['first_name'],
    'is_admin' => (bool)$u['is_admin'],   // ← new
];
```

The redirect target is chosen by the new field:

```php
header('Location: ' . ($u['is_admin'] ? 'admin/index.php' : 'home.php'));
```

**Early-exit guard for already-logged-in users:** `login.php` has a top-of-file guard that sends any user with an active session elsewhere before showing the login form. This guard must also branch on `is_admin`, otherwise an admin who is already logged in and visits `login.php` (via browser history or a bookmark) is sent to `home.php` — the student dashboard — instead of the admin panel:

```php
if (!empty($_SESSION['user'])) {
    header('Location: ' . (!empty($_SESSION['user']['is_admin']) ? 'admin/index.php' : 'home.php'));
    exit;
}
```

This change is in the same location as the POST success redirect change (`login.php` lines 5–8).

### `require_admin()` function and redirect path

Added to `includes/auth.php` (the same file as `require_login()` to keep auth in one place).

The redirect uses a **root-relative URL** so it is not fragile to call-depth. Define a base-path constant in `includes/db.php` (the bootstrap file, already included by all pages):

```php
// In includes/db.php, near the top:
// Adjust if the project is served from a different subdirectory or vhost root.
define('BASE_PATH', '/Jolli-HR');   // No trailing slash.
```

Then `require_admin()` becomes:

```php
function require_admin(): void
{
    if (empty($_SESSION['user']['is_admin'])) {
        unset($_SESSION['user']);
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}
```

Using `BASE_PATH` rather than a bare `../login.php` means the redirect is correct regardless of whether the calling page lives at `admin/index.php`, `admin/includes/something.php`, or any other depth. Any future change to the project root path requires updating only the constant in `db.php`.

### `h()` helper — single definition, function_exists guard

The existing `jobs.php` defines `h()` at file scope. Each admin page needs it too. To prevent fatal `Cannot redeclare function h()` errors if two files in the same request both define it, **define `h()` once in `includes/auth.php`**, guarded by `function_exists`:

```php
if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
```

`auth.php` is included by every page (student and admin alike via `admin_header.php`), so this single definition covers all pages.

**Required change to `jobs.php`:** Remove the bare `function h($s)` definition at line 26 entirely. `auth.php` (which `jobs.php` already includes first via `require_once 'includes/auth.php'`) now provides `h()`. Do not add a `function_exists` guard to `jobs.php` and leave the bare definition in place — because `jobs.php` includes `auth.php` first, PHP will reach the unguarded `function h()` in `jobs.php` after `auth.php` has already defined it, and will trigger a fatal `Cannot redeclare function h()` on the student jobs page.

---

## File Structure

```
admin/
  index.php              — Dashboard
  applicants.php         — Applicant list (with status filter)
  applicant.php          — Applicant detail (status stepper + doc verify)
  jobs.php               — Job list + add/edit/delete
  includes/
    admin_header.php     — HTML head + top nav bar
    admin_footer.php     — Close HTML + JS
assets/
  css/
    admin.css            — Admin-only layout overrides (new)
  js/
    admin.js             — Edit-job form population (new)
includes/
  auth.php               — Extended: session_start() guard, is_admin in session, require_admin(), h()
  db.php                 — Extended: BASE_PATH constant
database.sql             — Extended: ALTER TABLE statements + admin seed
```

No changes to any existing student-facing PHP files except `login.php` (the `SELECT` query and redirect logic) and the two includes noted above.

---

## Admin Layout (`admin/includes/`)

### `admin_header.php`

Each admin page sets `$title` and `$active` before including this file (same convention as `includes/header.php`). The file:

1. `require_once __DIR__ . '/../../includes/auth.php';` — triggers `session_start()` (guarded), loads `h()`, and loads `require_admin()`.
2. `require_once __DIR__ . '/../../includes/icons.php';`
3. Calls `require_admin()`.
4. Emits a full `<!DOCTYPE html>` opening, `<head>` with links to Outfit font, `../../assets/css/style.css`, and `../../assets/css/admin.css`.
5. Renders an `<header class="admin-topbar">` containing the JolliHR brand mark and a `<nav>` with four links. **All `href` values are URL-relative to the pages at `admin/*.php`** (not filesystem-relative to `admin/includes/` where this file lives — `require_once` paths use `__DIR__`, but `href` values in HTML are resolved by the browser against the page's URL):
   - Dashboard: `href="index.php"` (sibling)
   - Applicants: `href="applicants.php"` (sibling)
   - Jobs: `href="jobs.php"` (sibling)
   - Logout: `href="../logout.php"` (one level up to root)
   
   The active link receives `class="on"` based on `$active`.
6. Opens `<div class="admin-wrap"><main class="admin-main">`.

### `admin_footer.php`

Closes `</main></div>` and closes `</body></html>`. Per FR-6.3, it includes **both** `app.js` and `admin.js`:

```html
<script src="../../assets/js/app.js"></script>
<script src="../../assets/js/admin.js"></script>
</body>
</html>
```

`app.js` is included to satisfy FR-6.3. Because `app.js` only hooks onto DOM elements absent from admin pages (`[data-tabs]`, `[data-chips]`, `.job`, the mood check-in widget, the signup form), it has no effect there — its selectors return empty NodeLists and all event bindings silently do nothing. `admin.js` must come after `app.js` so that `editJob()` is available when the admin jobs page renders.

### `assets/css/admin.css`

Adds styles for:
- `.admin-topbar` — horizontal flex bar, full-width, white background, red brand, with nav links to the right.
- `.admin-wrap` — removes the student `max-width: 430px` constraint; uses `max-width: 1024px` centered.
- `.admin-main` — `padding: 24px`.
- `.admin-table` — `width: 100%; border-collapse: collapse` table with alternating row shading, using existing `--line` colour token.
- `.admin-table th` — header cells styled with `--pink` background.
- `.admin-table td a` — inherits `--red` link colour.
- `.status-pill` — reuses existing `.pill` modifier classes (`.ok`, `.warn`, `.gray`) mapped to pipeline statuses.
- `.admin-form` — a constrained-width form card using the existing `.card` style.
- `.flash.err` — error flash styling, since `style.css` provides only `.flash` (layout) and `.flash.ok` (green success), with no error variant:

```css
.flash.err {
    background: #ffe8e6;
    color: #c4141f;
}
```

The file introduces no new colour variables beyond the above; all other values come from the tokens already in `style.css`. The `.form-ok` class is **not** added here — see the Flash Message Mechanism section for why the existing `.flash`/`.flash.ok`/`.flash.err` classes are used instead.

---

## Flash Message Mechanism

No existing page uses a flash pattern. This section defines it once; both `applicant.php` and `jobs.php` follow it exactly.

### Writing a flash (call before the redirect in every POST handler):

```php
$_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Status updated.'];
header('Location: applicant.php?id=' . $appId);
exit;
```

For errors:

```php
$_SESSION['flash'] = ['type' => 'err', 'msg' => 'Database error. Please try again.'];
header('Location: applicant.php?id=' . $appId);
exit;
```

### Reading and clearing (at the very top of the GET path, before any output or query):

```php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
```

### Rendering in HTML (place immediately after `<main>` opens):

```php
<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>">
    <?= h($flash['msg']) ?>
  </div>
<?php endif; ?>
```

`style.css` already provides `.flash` (base layout: flex, padding, border-radius, font-size) and `.flash.ok` (green success: `#dcf5e3` background, green text). `admin.css` adds `.flash.err` (red error: `#ffe8e6` background, `#c4141f` text — see Admin Layout section). When `type` is `'err'`, the classes `flash err` are applied, giving the red error styling. When `type` is `'ok'`, the classes `flash ok` are applied, giving the green success styling. The `.form-ok` class does not need to be added to `admin.css`.

Note: `.form-err` remains available in `style.css` for inline validation errors rendered on forms before a redirect (a distinct use case from post-redirect flash messages). Do not conflate the two.

---

## Page Designs

### `admin/index.php` — Dashboard

**Data queries (read-only):**

```sql
-- Card 1: total applicants (distinct users who have applied)
SELECT COUNT(DISTINCT user_id) FROM applications;

-- Card 2: pending docs (applicants who have at least one unverified document)
SELECT COUNT(DISTINCT user_id) FROM user_documents WHERE verified = 0;

-- Card 3: in training
SELECT COUNT(*) FROM applications WHERE status = 'Training';

-- Card 4: hired
SELECT COUNT(*) FROM applications WHERE status = 'Hired';

-- Recent applications (last 10)
SELECT a.id, u.first_name, u.last_name, j.title AS job_title,
       a.status, a.applied_at
FROM applications a
JOIN users u ON u.id = a.user_id
JOIN jobs  j ON j.id = a.job_id
ORDER BY a.applied_at DESC, a.id DESC
LIMIT 10;
```

**Rendering:** Four `.card` summary divs in a CSS grid row, then a `.admin-table` for recent applications. Each row links to `applicant.php?id={a.id}`.

**Error handling:** If any query throws `PDOException`, the page catches it, logs the message to `error_log()`, and renders a `.form-err` notice. The page does not expose PDO error details to the browser.

---

### `admin/applicants.php` — Applicant List

**Input:** `$_GET['status']` — optional string. Valid values: `''`, `Applied`, `Docs Verified`, `Training`, `Hired`, `Rejected`. Any other value is silently treated as `''` (all).

**Validation:** Whitelist the value against the known ENUM values before interpolating into SQL. Invalid values fall back to "all".

**Data query:**

```php
$allowed = ['Applied', 'Docs Verified', 'Training', 'Hired', 'Rejected'];
$filter  = in_array($_GET['status'] ?? '', $allowed, true) ? $_GET['status'] : '';

if ($filter) {
    $st = db()->prepare(
        'SELECT a.id, u.first_name, u.last_name, u.email, j.title,
                a.status, a.applied_at
         FROM applications a
         JOIN users u ON u.id = a.user_id
         JOIN jobs  j ON j.id = a.job_id
         WHERE a.status = ?
         ORDER BY a.applied_at DESC'
    );
    $st->execute([$filter]);
} else {
    $st = db()->query(
        'SELECT a.id, u.first_name, u.last_name, u.email, j.title,
                a.status, a.applied_at
         FROM applications a
         JOIN users u ON u.id = a.user_id
         JOIN jobs  j ON j.id = a.job_id
         ORDER BY a.applied_at DESC'
    );
}
$rows = $st->fetchAll();
```

**Rendering:** A `<select>` filter at the top submits via `onchange="this.form.submit()"` inside a `<form method="get">`. The table shows all columns; status cells use a `.status-pill` span. Each row's name links to `applicant.php?id={a.id}`.

**Error handling:** `PDOException` → `error_log()` + `.form-err` notice displayed in place of the table.

---

### `admin/applicant.php?id=X` — Applicant Detail

**Input:** `$_GET['id']` — required, cast to `(int)`. If `0`, redirect to `applicants.php`.

**Flash read (top of GET path, before any query):**

```php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
```

**POST actions (all follow PRG):**

| `$_POST['action']` | Effect |
|--------------------|--------|
| `advance_status`   | Advance `applications.status` to the next ENUM stage |
| `reject`           | Set `applications.status = 'Rejected'` |
| `toggle_doc`       | Flip `user_documents.verified` for `$_POST['doc_id']` |

**Advance-status logic (complete POST handler branch):**

```php
$pipeline = ['Applied', 'Docs Verified', 'Training', 'Hired'];
$idx = array_search($app['status'], $pipeline, true);
if ($idx === false || $idx >= count($pipeline) - 1) {
    // Already at Hired, already Rejected, or unknown status — cannot advance.
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Status cannot be advanced further.'];
    header('Location: applicant.php?id=' . $appId);
    exit;
}
$next = $pipeline[$idx + 1];
db()->prepare('UPDATE applications SET status = ? WHERE id = ?')
    ->execute([$next, $appId]);
$_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Status updated to ' . h($next) . '.'];
header('Location: applicant.php?id=' . $appId);
exit;
```

**Toggle-doc logic:**

```php
$docId = filter_var($_POST['doc_id'] ?? '', FILTER_VALIDATE_INT);
if ($docId) {
    // The AND user_id = ? clause is a scope guard: a crafted POST cannot
    // touch documents belonging to a different applicant.
    db()->prepare(
        'UPDATE user_documents SET verified = 1 - verified
         WHERE id = ? AND user_id = ?'
    )->execute([$docId, $app['user_id']]);
    $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Document status updated.'];
} else {
    // doc_id missing or not a valid integer — write an error flash so the
    // user gets feedback rather than a silent no-op redirect.
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Invalid document.'];
}
header('Location: applicant.php?id=' . $appId);
exit;
```

**Data queries (GET path):**

```sql
-- Load application + applicant user
SELECT a.id, a.user_id, a.status, a.applied_at,
       u.first_name, u.last_name, u.email,
       j.title AS job_title
FROM applications a
JOIN users u ON u.id = a.user_id
JOIN jobs  j ON j.id = a.job_id
WHERE a.id = ?;

-- Load profile
SELECT * FROM user_profiles WHERE user_id = ?;

-- Load documents
SELECT id, document_name, verified FROM user_documents WHERE user_id = ?;
```

**Error handling:**
- `$_GET['id']` invalid or no matching row → redirect to `applicants.php` (not a 404, keeps it simple).
- POST `advance_status`/`reject`/`toggle_doc` PDOException → `error_log()`, write flash error to `$_SESSION['flash']`, redirect to same page. The flash write always precedes the redirect so the GET path can read and display it.

---

### `admin/jobs.php` — Jobs Management

**Three POST actions (all PRG):**

| `$_POST['action']` | Effect |
|--------------------|--------|
| `add`              | INSERT new job |
| `edit`             | UPDATE existing job by `$_POST['job_id']` |
| `delete`           | DELETE job by `$_POST['job_id']` |

**Flash read (top of GET path):**

```php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
```

**Input validation for `add` and `edit`:**

| Field       | Type    | Required | Constraints                        | PHP check                                                                 | On fail              |
|-------------|---------|----------|------------------------------------|---------------------------------------------------------------------------|----------------------|
| `title`     | string  | yes      | 1–100 chars after trim             | `strlen($v) >= 1 && strlen($v) <= 100`                                   | Re-show form + error |
| `branch`    | string  | yes      | 1–150 chars after trim             | `strlen($v) >= 1 && strlen($v) <= 150`                                   | Re-show form + error |
| `tags`      | string  | no       | ≤255 chars after trim; default `''`| `strlen($v) <= 255`                                                       | Silently truncate to 255 chars (acceptable for demo scale — tags are display-only labels; no business logic depends on their full value) |
| `hours`     | string  | yes      | 1–100 chars after trim             | `strlen($v) >= 1 && strlen($v) <= 100`                                   | Re-show form + error |
| `distance`  | string  | yes      | 1–20 chars after trim              | `strlen($v) >= 1 && strlen($v) <= 20`                                    | Re-show form + error |
| `match_pct` | int     | yes      | 0–100 inclusive                    | `filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]])` | Re-show form + error |

Use `FILTER_VALIDATE_INT` with explicit `min_range`/`max_range` for `match_pct`, not `is_numeric`. A value of `200` is a valid integer but fails the business rule; `is_numeric` would not catch it.

**Delete:** No field validation — `$_POST['job_id']` is cast to `(int)`. An `onclick="return confirm('Delete this job and all its applications?')"` on the button gives the user one chance to cancel client-side. Server-side, the DELETE executes regardless (the confirm is cosmetic). The cascade constraint handles linked applications.

**Data query:**

```sql
SELECT id, title, branch, tags, hours, distance, match_pct FROM jobs ORDER BY id;
```

**Edit flow:** The Add/Edit form lives at the bottom of the page. When the user clicks "Edit" on a job row, the edit button is:

```html
<button type="button"
        onclick="editJob(this)"
        data-id="<?= h($row['id']) ?>"
        data-title="<?= h($row['title']) ?>"
        data-branch="<?= h($row['branch']) ?>"
        data-tags="<?= h($row['tags']) ?>"
        data-hours="<?= h($row['hours']) ?>"
        data-distance="<?= h($row['distance']) ?>"
        data-match="<?= h($row['match_pct']) ?>">Edit</button>
```

The `editJob()` function lives in **`assets/js/admin.js`** (new file, not `app.js`, which is student-facing). It is linked from `admin_footer.php` via `<script src="../../assets/js/admin.js"></script>`. The function:

```js
function editJob(btn) {
    document.getElementById('job_id').value      = btn.dataset.id;
    document.getElementById('f_title').value     = btn.dataset.title;
    document.getElementById('f_branch').value    = btn.dataset.branch;
    document.getElementById('f_tags').value      = btn.dataset.tags;
    document.getElementById('f_hours').value     = btn.dataset.hours;
    document.getElementById('f_distance').value  = btn.dataset.distance;
    document.getElementById('f_match').value     = btn.dataset.match;
    document.getElementById('action_field').value = 'edit';
    document.getElementById('job-form').scrollIntoView({ behavior: 'smooth' });
}
```

The form inputs carry the `id` attributes shown above (`f_title`, `f_branch`, etc.); the hidden action input has `id="action_field"`. On POST with `action=edit`, if `job_id` resolves to a real row, the UPDATE executes; otherwise it is a no-op and the redirect still fires.

**Error handling:** `PDOException` on insert/update/delete → `error_log()`, write flash error to `$_SESSION['flash']`, redirect to `jobs.php`. The flash is written before the redirect so the GET path reads and displays it.

---

## Security

- `require_admin()` is the first executable statement in every admin page, before any database call or output.
- All `$_GET` and `$_POST` values used in queries are either passed as PDO parameters or whitelisted against a fixed array before use. No string interpolation into SQL.
- All values rendered into HTML are wrapped in `h()`. The `h()` helper is defined once in `includes/auth.php` with a `function_exists` guard, so it is available on every page without risk of redeclaration.
- The `toggle_doc` scope guard (`AND user_id = ?`) prevents horizontal privilege escalation across applicants.
- CSRF tokens are not implemented in the existing codebase; this feature matches that posture for consistency. CSRF protection is noted as a future hardening item.

---

## Testability

**Unit-testable logic (no HTTP, no DB):**
- Advance-status array walk (given a status string, returns the next stage or no-op).
- Input validation rules for job fields.
- `h()` escaping helper.
- `filter_var` range check for `match_pct`.

**Integration-testable via browser / XAMPP:**
- Full PRG cycle for each POST action (add/edit/delete job, advance status, reject, toggle doc).
- Flash message appears after redirect and is cleared on next load.
- Status filter on applicant list.
- `require_admin()` redirect when session is absent or non-admin.
- Dashboard counts match direct SQL queries.
- `session_start()` guard: including `auth.php` twice in one request does not trigger a PHP notice.

The page-level structure (one file per page, no dependency injection container) means integration testing via a real browser is the primary test path, consistent with the rest of the project.

---

## Edge Cases

| Scenario | Behaviour |
|---|---|
| Admin visits `admin/applicant.php?id=0` or a non-existent ID | Redirect to `admin/applicants.php` |
| `toggle_doc` POST with missing or non-integer `doc_id` | Flash "Invalid document." (type `err`), redirect back to same page — no silent no-op |
| Advance-status POST on a `Hired` or `Rejected` application | Flash "Status cannot be advanced further." (type `err`), redirect back to same page |
| Delete a job that has active applications | Cascade deletes the applications; students see no matching application row on `home.php` |
| Re-importing `database.sql` on existing DB | `ADD COLUMN IF NOT EXISTS` skips the column if it exists; `MODIFY COLUMN` re-applies the full ENUM definition (safe if definition is complete); seed rows use `INSERT IGNORE` |
| Applicant has no `user_profiles` row | Profile section renders "—" for each field (the join returns NULL; `h($row['university'] ?? '—')`) |
| Applicant has no documents | Document list renders "No documents on file" |
| Student logs in and manually navigates to `admin/index.php` | `require_admin()` unsets the session and redirects to `BASE_PATH . '/login.php'` |
| Two files in same request both include `auth.php` | `session_start()` guard prevents double-call; `function_exists` guard on `h()` prevents fatal redeclaration |

---

## Review Finding Responses

This section documents how findings from all three design reviews were addressed.

### First Review (Revision 0 → 1)

| Finding | Severity | Action |
|---------|----------|--------|
| **F1** — `session_start()` will fire twice | HIGH | Addressed. Added `session_status() === PHP_SESSION_NONE` guard to `auth.php` in the "Guard `session_start()`" section. |
| **F2** — `require_admin()` relative redirect path fragile | HIGH | Addressed. Replaced `../login.php` with a `BASE_PATH` constant defined in `db.php`; `require_admin()` now uses `header('Location: ' . BASE_PATH . '/login.php')`. |
| **F3** — `MODIFY COLUMN` claimed idempotent like `IF NOT EXISTS` | HIGH | Addressed. The "Note on `db_bootstrap()`" section now clearly distinguishes the two mechanisms and spells out the risk of incomplete ENUM lists in future changes. |
| **F4** — Flash message mechanism unspecified | MEDIUM | Addressed. Added a dedicated "Flash Message Mechanism" section with concrete write, read-and-clear, and render snippets. |
| **F5** — Logout href resolves to wrong location | MEDIUM | Addressed. Added explicit note in `admin_header.php` section that all `href` values are URL-relative to `admin/*.php`, not filesystem-relative to `admin/includes/`. |
| **F6** — Edit-job JavaScript unspecified | MEDIUM | Addressed. Specified `assets/js/admin.js` as the file, defined `dataset` attribute names, and provided the full `editJob()` function body in the `admin/jobs.php` section. |
| **F7** — `h()` per-page redeclaration risk | MEDIUM | Addressed. `h()` is now defined once in `includes/auth.php` with a `function_exists` guard. Per-page definitions are removed. |
| **F8** — Advance-status no-op missing flash in POST handler spec | MEDIUM | Addressed. The POST handler spec for `advance_status` now includes the full branch: flash write, `Location` header, and `exit` for the no-op case. |
| **F9** — Placeholder hash in seed SQL | NIT | Addressed. Generated and embedded the actual bcrypt hash (`$2y$10$cZS4ryhi8HBg3.5hfVjWUePbmJUH812ExaMedtbAJIZQZLTUmZJYS`) for `admin123`. |
| **F10** — Nav hrefs resolve to `admin/admin/*.php` | NIT | Addressed. Nav hrefs changed to sibling-relative (`index.php`, `applicants.php`, `jobs.php`, `../logout.php`). |
| **F11** — `match_pct` validation lacks explicit PHP check | NIT | Addressed. Validation table now shows the exact `filter_var` call with `min_range`/`max_range` and explicitly warns against `is_numeric`. |

### Second Review (Revision 1 → 2)

| Finding | Severity | Action |
|---------|----------|--------|
| **F1** — Already-logged-in admin redirected to student `home.php` | HIGH | Addressed. The early-exit guard at the top of `login.php` now branches on `is_admin`, sending admins to `admin/index.php` and non-admins to `home.php`. Added the fix to the "Session shape after login" section with the exact code change. |
| **F2** — `h()` in `jobs.php` has no `function_exists` guard; design ambiguity creates fatal error path | HIGH | Addressed. Removed the ambiguous "both are acceptable" language. The design now explicitly prescribes removing the bare `function h($s)` definition from `jobs.php` entirely, and explains why the guard-only approach fails at runtime (execution order). |
| **F3** — Flash CSS class conflicts with existing `.flash`/`.flash.ok` | MEDIUM | Addressed. The flash rendering snippet now uses `<div class="flash <?= $flash['type'] === 'ok' ? 'ok' : '' ?>">`, matching the existing `.flash`/`.flash.ok` classes in `style.css`. `.form-ok` is not added to `admin.css`. The `admin.css` description is updated accordingly. |
| **F4** — `toggle_doc` POST handler: no flash on invalid/missing `doc_id` | MEDIUM | Addressed. Added `else` branch to the `toggle_doc` handler that writes `$_SESSION['flash'] = ['type' => 'err', 'msg' => 'Invalid document.']` before the redirect. Added the case to the edge cases table. |
| **F5** — `form-err` base-class description incorrectly states `border-left` | NIT | Superseded by F3: since the flash rendering now uses `.flash`/`.flash.ok` rather than `form-err`/`form-ok`, the incorrect description in the old rendering explanation is gone. `.form-err` retains a brief mention only as an inline validation tool (distinct use case), with no incorrect property claims. |
| **F6** — `BASE_PATH` hardcoded value undocumented assumption | NIT | Addressed. Added `// Adjust if the project is served from a different subdirectory or vhost root.` comment to the `BASE_PATH` constant definition in the design. |

### Third Review (Revision 2 → 3)

| Finding | Severity | Action |
|---------|----------|--------|
| **F1** — `.flash` error state renders invisibly | HIGH | Addressed (Option A). Added `.flash.err { background: #ffe8e6; color: #c4141f; }` to the `admin.css` section. Updated the flash render snippet to emit `class="flash err"` for the error case instead of `class="flash "`. Updated the Flash Message Mechanism description to remove the incorrect claim that `.flash` base provides red background/text. |
| **F2** — Design contradicts FR-6.3 on `app.js` inclusion | MEDIUM | Addressed. Updated `admin_footer.php` section to include both `app.js` and `admin.js`, per FR-6.3. Added an explanation that `app.js` is harmless on admin pages because all its DOM hooks target student-facing elements absent from admin views. |
| **F3** — `tags` silent truncation silently loses user data | NIT | Addressed by justification. The on-fail column now states the rationale: "acceptable for demo scale — tags are display-only labels; no business logic depends on their full value." |
