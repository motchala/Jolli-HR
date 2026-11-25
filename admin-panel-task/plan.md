# Implementation Plan — `admin-panel`

Each item leaves the codebase in a buildable, browsable state. Items are ordered by dependency; each item notes what it depends on.

---

- [ ] 1. **Extend `database.sql` with schema changes and admin seed**

  Append three SQL blocks immediately after their respective `CREATE TABLE` blocks:

  1. After the `users` `CREATE TABLE` block — add the `is_admin` column:
     ```sql
     ALTER TABLE users
       ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0;
     ```
  2. After the `applications` `CREATE TABLE` block — extend the ENUM:
     ```sql
     ALTER TABLE applications
       MODIFY COLUMN status
         ENUM('Applied','Docs Verified','Training','Hired','Rejected')
         NOT NULL DEFAULT 'Applied';
     ```
  3. After the existing demo-account `INSERT IGNORE` for `shamea@jollihr.test` — add the admin seed:
     ```sql
     INSERT IGNORE INTO users (first_name, last_name, email, password_hash, is_admin) VALUES
       ('Admin', 'HR', 'admin@jollihr.test',
        '$2y$10$cZS4ryhi8HBg3.5hfVjWUePbmJUH812ExaMedtbAJIZQZLTUmZJYS', 1);
     ```
     (This hash is bcrypt cost-10 of `admin123`. Verify: `password_verify('admin123', '<hash>')` returns `true`.)

  File: `database.sql`

  Verify: Open phpMyAdmin → Import `database.sql` on an **existing** `jollihr_db` instance → no errors. Run `SELECT email, is_admin FROM users;` and confirm `admin@jollihr.test` has `is_admin = 1`. Run `SHOW COLUMNS FROM applications LIKE 'status';` and confirm the ENUM includes `'Rejected'`. Re-import a second time and confirm no duplicate-key or column-exists errors.

---

- [ ] 2. **Add `BASE_PATH` constant to `includes/db.php`**

  Insert one line near the top of `includes/db.php`, after the DB credential constants, before `db_options()`:

  ```php
  // Adjust if the project is served from a different subdirectory or vhost root.
  define('BASE_PATH', '/Jolli-HR');   // No trailing slash.
  ```

  File: `includes/db.php`

  Verify: Load any existing student page (e.g. `http://localhost/Jolli-HR/home.php`) in the browser — no PHP fatal or notice errors. The constant will be used by `require_admin()` in the next item.

---

- [ ] 3. **Extend `includes/auth.php`: session guard, `h()` helper, `require_admin()`**

  Three changes to `includes/auth.php` (the file is 14 lines; replace the whole file):

  1. **Guard `session_start()`** — replace the bare `session_start();` at line 2 with:
     ```php
     if (session_status() === PHP_SESSION_NONE) {
         session_start();
     }
     ```
  2. **Define `h()` once** — append after the existing `require_login()` closing brace:
     ```php
     if (!function_exists('h')) {
         function h(string $s): string {
             return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
         }
     }
     ```
  3. **Add `require_admin()`** — append after `h()`:
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

  File: `includes/auth.php`

  Verify: Load `http://localhost/Jolli-HR/home.php` as a student (logged in) — no errors, page renders normally. Confirm `session_start()` is not called twice by checking the PHP error log for `E_NOTICE: session already started`.

---

- [ ] 4. **Remove the bare `h()` definition from `jobs.php`** *(depends on item 3)*

  Delete the `function h($s)` block at approximately line 26 of `jobs.php` (the five lines starting `function h($s)`). `auth.php`, which `jobs.php` already includes first, now provides `h()` with a `function_exists` guard. Leaving the bare definition in place would cause a fatal `Cannot redeclare function h()`.

  File: `jobs.php`

  Verify: Load `http://localhost/Jolli-HR/jobs.php` while logged in as the demo student — job list renders, no PHP fatal error.

---

- [ ] 5. **Extend `login.php`: select `is_admin`, branch redirect, and fix already-logged-in guard** *(depends on items 1, 2, 3)*

  Three changes to `login.php`:

  1. **Update the SELECT** — change:
     ```php
     $st = db()->prepare('SELECT id, first_name, last_name, password_hash FROM users WHERE email = ?');
     ```
     to:
     ```php
     $st = db()->prepare('SELECT id, first_name, last_name, password_hash, is_admin FROM users WHERE email = ?');
     ```

  2. **Store `is_admin` in session and branch the redirect** — replace the session-write and redirect on success:
     ```php
     $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['first_name'] . ' ' . $u['last_name'], 'first' => $u['first_name']];
     header('Location: home.php');
     ```
     with:
     ```php
     $_SESSION['user'] = [
         'id'       => (int)$u['id'],
         'name'     => $u['first_name'] . ' ' . $u['last_name'],
         'first'    => $u['first_name'],
         'is_admin' => (bool)$u['is_admin'],
     ];
     header('Location: ' . ($u['is_admin'] ? 'admin/index.php' : 'home.php'));
     ```

  3. **Fix the already-logged-in guard** — replace the guard at the top (lines 5–7):
     ```php
     if (!empty($_SESSION['user'])) {
         header('Location: home.php');
         exit;
     }
     ```
     with:
     ```php
     if (!empty($_SESSION['user'])) {
         header('Location: ' . (!empty($_SESSION['user']['is_admin']) ? 'admin/index.php' : 'home.php'));
         exit;
     }
     ```

  File: `login.php`

  Verify:
  - Log in as `shamea@jollihr.test` / `shamea123` → lands on `home.php`.
  - Log in as `admin@jollihr.test` / `admin123` → lands on `admin/index.php` (which will 404 until item 7, but the redirect header is correct — confirm with browser dev-tools Network tab).
  - While logged in as admin, visit `http://localhost/Jolli-HR/login.php` → redirects to `admin/index.php`, not `home.php`.

---

- [ ] 6. **Create `assets/css/admin.css`**

  Create a new file `assets/css/admin.css` with the following styles. All colour values come from existing `:root` tokens in `style.css` where available:

  ```css
  /* Admin layout overrides */
  .admin-topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 24px;
      height: 60px;
      background: #fff;
      border-bottom: 1px solid var(--line);
  }

  .admin-topbar .brand b { color: var(--red); }

  .admin-topbar nav { display: flex; gap: 8px; }

  .admin-topbar nav a {
      padding: 8px 14px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 14px;
      color: var(--muted);
  }

  .admin-topbar nav a.on,
  .admin-topbar nav a:hover { color: var(--red); background: var(--pink); }

  .admin-wrap {
      max-width: 1024px;
      margin: 0 auto;
      padding: 0 24px;
  }

  .admin-main { padding: 24px 0; }

  /* Admin table */
  .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
  }

  .admin-table th,
  .admin-table td {
      padding: 10px 12px;
      text-align: left;
      border-bottom: 1px solid var(--line);
  }

  .admin-table th {
      background: var(--pink);
      font-weight: 600;
  }

  .admin-table tbody tr:nth-child(even) { background: #fdf6f5; }

  .admin-table td a { color: var(--red); }

  /* Status pills for pipeline statuses */
  .status-pill {
      font-size: 12px;
      font-weight: 600;
      padding: 3px 11px;
      border-radius: 999px;
      white-space: nowrap;
  }

  .status-pill.ok   { background: #dcf5e3; color: var(--green); }
  .status-pill.warn { background: #fdecc8; color: #c9780a; }
  .status-pill.gray { background: #f1ebea; color: var(--muted); }
  .status-pill.red  { background: #ffe8e6; color: #c4141f; }

  /* Admin form card */
  .admin-form {
      max-width: 560px;
      background: #fff;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(120, 20, 30, .07);
      border: 1px solid #f6eae9;
  }

  .admin-form h3 { margin-bottom: 14px; }

  /* Flash error variant (style.css has .flash and .flash.ok; we add .flash.err) */
  .flash.err {
      background: #ffe8e6;
      color: #c4141f;
  }

  /* Summary cards row on dashboard */
  .stat-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      margin-bottom: 20px;
  }

  .stat-card {
      background: #fff;
      border-radius: 16px;
      padding: 16px 20px;
      box-shadow: 0 2px 10px rgba(120, 20, 30, .07);
      border: 1px solid #f6eae9;
  }

  .stat-card .num {
      font-size: 32px;
      font-weight: 700;
      color: var(--red);
      line-height: 1.1;
  }

  .stat-card .lbl {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px;
  }
  ```

  File: `assets/css/admin.css`

  Verify: File is valid CSS (no parse errors reported in browser dev-tools when linked). Visual check deferred to item 7.

---

- [ ] 7. **Create `assets/js/admin.js`**

  Create a new file `assets/js/admin.js` containing the `editJob()` function used by the Edit buttons on `admin/jobs.php`:

  ```js
  function editJob(btn) {
      document.getElementById('job_id').value       = btn.dataset.id;
      document.getElementById('f_title').value      = btn.dataset.title;
      document.getElementById('f_branch').value     = btn.dataset.branch;
      document.getElementById('f_tags').value       = btn.dataset.tags;
      document.getElementById('f_hours').value      = btn.dataset.hours;
      document.getElementById('f_distance').value   = btn.dataset.distance;
      document.getElementById('f_match').value      = btn.dataset.match;
      document.getElementById('action_field').value = 'edit';
      document.getElementById('job-form').scrollIntoView({ behavior: 'smooth' });
  }
  ```

  File: `assets/js/admin.js`

  Verify: File is syntactically valid JS. Functional check deferred to item 11.

---

- [ ] 8. **Create `admin/includes/admin_header.php` and `admin/includes/admin_footer.php`** *(depends on items 2, 3, 6, 7)*

  Create the `admin/` and `admin/includes/` directories (they do not exist yet).

  **`admin/includes/admin_header.php`** — each admin page sets `$title` and `$active` before including this file (same convention as `includes/header.php`):

  ```php
  <?php
  require_once __DIR__ . '/../../includes/auth.php';
  require_once __DIR__ . '/../../includes/icons.php';
  require_admin();
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> · JolliHR Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
  </head>
  <body>
  <header class="admin-topbar">
    <div class="brand">
      <span class="logo"><?= ic('trend') ?></span>
      <div><b>JolliHR</b><small>Admin Panel</small></div>
    </div>
    <nav>
      <a href="index.php"      class="<?= $active === 'dashboard'  ? 'on' : '' ?>">Dashboard</a>
      <a href="applicants.php" class="<?= $active === 'applicants' ? 'on' : '' ?>">Applicants</a>
      <a href="jobs.php"       class="<?= $active === 'jobs'       ? 'on' : '' ?>">Jobs</a>
      <a href="../logout.php">Logout</a>
    </nav>
  </header>
  <div class="admin-wrap"><main class="admin-main">
  ```

  Note: `href` values are URL-relative to the pages at `admin/*.php`, not to `admin/includes/` where this file lives on disk.

  **`admin/includes/admin_footer.php`**:

  ```php
  </main></div>
  <script src="../../assets/js/app.js"></script>
  <script src="../../assets/js/admin.js"></script>
  </body>
  </html>
  ```

  Files: `admin/includes/admin_header.php`, `admin/includes/admin_footer.php`

  Verify: Deferred to item 9 (first page that uses these files).

---

- [ ] 9. **Create `admin/index.php` — Dashboard** *(depends on items 1, 5, 8)*

  Create `admin/index.php`. The page sets `$title = 'Dashboard'` and `$active = 'dashboard'`, includes `admin_header.php`, runs five read-only queries, and renders four stat cards plus a recent-applications table.

  ```php
  <?php
  $title  = 'Dashboard';
  $active = 'dashboard';
  require_once __DIR__ . '/../includes/db.php';
  require __DIR__ . '/includes/admin_header.php';

  try {
      $totalApplicants = db()->query(
          'SELECT COUNT(DISTINCT user_id) FROM applications'
      )->fetchColumn();

      $pendingDocs = db()->query(
          'SELECT COUNT(DISTINCT user_id) FROM user_documents WHERE verified = 0'
      )->fetchColumn();

      $inTraining = db()->query(
          "SELECT COUNT(*) FROM applications WHERE status = 'Training'"
      )->fetchColumn();

      $hired = db()->query(
          "SELECT COUNT(*) FROM applications WHERE status = 'Hired'"
      )->fetchColumn();

      $recent = db()->query(
          'SELECT a.id, u.first_name, u.last_name, j.title AS job_title,
                  a.status, a.applied_at
           FROM applications a
           JOIN users u ON u.id = a.user_id
           JOIN jobs  j ON j.id = a.job_id
           ORDER BY a.applied_at DESC, a.id DESC
           LIMIT 10'
      )->fetchAll();

      $dbErr = null;
  } catch (PDOException $e) {
      error_log($e->getMessage());
      $dbErr = 'Database error. Please try again.';
  }
  ?>
  <h1 style="margin-bottom:20px">Dashboard</h1>

  <?php if ($dbErr): ?>
    <div class="form-err"><?= h($dbErr) ?></div>
  <?php else: ?>

  <div class="stat-cards">
    <div class="stat-card"><div class="num"><?= (int)$totalApplicants ?></div><div class="lbl">Total Applicants</div></div>
    <div class="stat-card"><div class="num"><?= (int)$pendingDocs ?></div><div class="lbl">Pending Docs</div></div>
    <div class="stat-card"><div class="num"><?= (int)$inTraining ?></div><div class="lbl">In Training</div></div>
    <div class="stat-card"><div class="num"><?= (int)$hired ?></div><div class="lbl">Hired</div></div>
  </div>

  <h2 style="margin-bottom:12px;font-size:17px">Recent Applications</h2>
  <table class="admin-table">
    <thead>
      <tr><th>Applicant</th><th>Job</th><th>Status</th><th>Applied</th></tr>
    </thead>
    <tbody>
    <?php foreach ($recent as $r):
      $pillClass = match($r['status']) {
          'Hired'        => 'ok',
          'Training'     => 'warn',
          'Docs Verified'=> 'warn',
          'Applied'      => 'gray',
          'Rejected'     => 'red',
          default        => 'gray',
      };
    ?>
      <tr>
        <td><a href="applicant.php?id=<?= (int)$r['id'] ?>"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></a></td>
        <td><?= h($r['job_title']) ?></td>
        <td><span class="status-pill <?= $pillClass ?>"><?= h($r['status']) ?></span></td>
        <td><?= h($r['applied_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="4" style="color:var(--muted)">No applications yet.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <?php endif; ?>
  <?php require __DIR__ . '/includes/admin_footer.php'; ?>
  ```

  File: `admin/index.php`

  Verify:
  - Visit `http://localhost/Jolli-HR/admin/index.php` while **not** logged in → redirects to `http://localhost/Jolli-HR/login.php`.
  - Log in as `admin@jollihr.test` / `admin123` → redirects to `admin/index.php`, page loads with four stat cards and the recent-applications table.
  - Confirm card counts are non-negative integers.
  - Log in as `shamea@jollihr.test`, then manually navigate to `http://localhost/Jolli-HR/admin/index.php` → `require_admin()` redirects to `login.php`.

---

- [ ] 10. **Create `admin/applicants.php` — Applicant List** *(depends on items 1, 8)*

  Create `admin/applicants.php`. Implements the status-filter GET flow with whitelisted `$_GET['status']`.

  ```php
  <?php
  $title  = 'Applicants';
  $active = 'applicants';
  require_once __DIR__ . '/../includes/db.php';
  require __DIR__ . '/includes/admin_header.php';

  $allowed = ['Applied', 'Docs Verified', 'Training', 'Hired', 'Rejected'];
  $filter  = in_array($_GET['status'] ?? '', $allowed, true) ? $_GET['status'] : '';

  try {
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
      $rows  = $st->fetchAll();
      $dbErr = null;
  } catch (PDOException $e) {
      error_log($e->getMessage());
      $dbErr = 'Database error. Please try again.';
      $rows  = [];
  }
  ?>
  <h1 style="margin-bottom:16px">Applicants</h1>

  <form method="get" style="margin-bottom:16px">
    <select name="status" onchange="this.form.submit()" style="padding:8px 12px;border-radius:8px;border:1px solid var(--line)">
      <option value="" <?= $filter === '' ? 'selected' : '' ?>>All Statuses</option>
      <?php foreach ($allowed as $s): ?>
        <option value="<?= h($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= h($s) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($dbErr): ?>
    <div class="form-err"><?= h($dbErr) ?></div>
  <?php else: ?>
  <table class="admin-table">
    <thead>
      <tr><th>Applicant</th><th>Email</th><th>Job</th><th>Status</th><th>Applied</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $pillClass = match($r['status']) {
          'Hired'         => 'ok',
          'Training'      => 'warn',
          'Docs Verified' => 'warn',
          'Applied'       => 'gray',
          'Rejected'      => 'red',
          default         => 'gray',
      };
    ?>
      <tr>
        <td><a href="applicant.php?id=<?= (int)$r['id'] ?>"><?= h($r['first_name'] . ' ' . $r['last_name']) ?></a></td>
        <td><?= h($r['email']) ?></td>
        <td><?= h($r['title']) ?></td>
        <td><span class="status-pill <?= $pillClass ?>"><?= h($r['status']) ?></span></td>
        <td><?= h($r['applied_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" style="color:var(--muted)">No applications found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php require __DIR__ . '/includes/admin_footer.php'; ?>
  ```

  File: `admin/applicants.php`

  Verify:
  - Visit `admin/applicants.php` → all applications listed, no errors.
  - Change the dropdown to "Applied" → page reloads and shows only Applied rows.
  - Change to "Rejected" → shows only Rejected rows (or empty list if none exist yet).
  - Confirm each applicant name is a link to `admin/applicant.php?id=...`.

---

- [ ] 11. **Create `admin/applicant.php` — Applicant Detail** *(depends on items 1, 8)*

  Create `admin/applicant.php`. Handles three POST actions (`advance_status`, `reject`, `toggle_doc`) all via PRG, then the GET display path.

  ```php
  <?php
  $title  = 'Applicant';
  $active = 'applicants';
  require_once __DIR__ . '/../includes/db.php';

  $appId = (int)($_GET['id'] ?? 0);
  if (!$appId) {
      header('Location: applicants.php');
      exit;
  }

  // ── POST handlers (PRG) ──────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // Load the application first so we know user_id and current status.
      $stA = db()->prepare(
          'SELECT a.id, a.user_id, a.status FROM applications a WHERE a.id = ?'
      );
      $stA->execute([$appId]);
      $appRow = $stA->fetch();

      if (!$appRow) {
          header('Location: applicants.php');
          exit;
      }

      $action = $_POST['action'] ?? '';

      try {
          if ($action === 'advance_status') {
              $pipeline = ['Applied', 'Docs Verified', 'Training', 'Hired'];
              $idx = array_search($appRow['status'], $pipeline, true);
              if ($idx === false || $idx >= count($pipeline) - 1) {
                  $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Status cannot be advanced further.'];
              } else {
                  $next = $pipeline[$idx + 1];
                  db()->prepare('UPDATE applications SET status = ? WHERE id = ?')
                      ->execute([$next, $appId]);
                  $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Status updated to ' . h($next) . '.'];
              }

          } elseif ($action === 'reject') {
              db()->prepare("UPDATE applications SET status = 'Rejected' WHERE id = ?")
                  ->execute([$appId]);
              $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Application rejected.'];

          } elseif ($action === 'toggle_doc') {
              $docId = filter_var($_POST['doc_id'] ?? '', FILTER_VALIDATE_INT);
              if ($docId) {
                  db()->prepare(
                      'UPDATE user_documents SET verified = 1 - verified
                       WHERE id = ? AND user_id = ?'
                  )->execute([$docId, $appRow['user_id']]);
                  $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Document status updated.'];
              } else {
                  $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Invalid document.'];
              }
          }
      } catch (PDOException $e) {
          error_log($e->getMessage());
          $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Database error. Please try again.'];
      }

      header('Location: applicant.php?id=' . $appId);
      exit;
  }

  // ── GET path ─────────────────────────────────────────────────────────────────
  $flash = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);

  require __DIR__ . '/includes/admin_header.php';

  try {
      $stA = db()->prepare(
          'SELECT a.id, a.user_id, a.status, a.applied_at,
                  u.first_name, u.last_name, u.email,
                  j.title AS job_title
           FROM applications a
           JOIN users u ON u.id = a.user_id
           JOIN jobs  j ON j.id = a.job_id
           WHERE a.id = ?'
      );
      $stA->execute([$appId]);
      $app = $stA->fetch();

      if (!$app) {
          header('Location: applicants.php');
          exit;
      }

      $stP = db()->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
      $stP->execute([$app['user_id']]);
      $profile = $stP->fetch() ?: [];

      $stD = db()->prepare('SELECT id, document_name, verified FROM user_documents WHERE user_id = ?');
      $stD->execute([$app['user_id']]);
      $docs = $stD->fetchAll();

      $dbErr = null;
  } catch (PDOException $e) {
      error_log($e->getMessage());
      $dbErr = 'Database error. Please try again.';
      $app   = null;
      $profile = [];
      $docs    = [];
  }
  ?>
  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if ($dbErr): ?>
    <div class="form-err"><?= h($dbErr) ?></div>
  <?php elseif ($app): ?>

  <h1 style="margin-bottom:20px"><?= h($app['first_name'] . ' ' . $app['last_name']) ?></h1>

  <!-- Applicant info -->
  <div class="card admin-form" style="max-width:100%">
    <h3>Application Info</h3>
    <table class="admin-table">
      <tr><th>Job</th><td><?= h($app['job_title']) ?></td></tr>
      <tr><th>Email</th><td><?= h($app['email']) ?></td></tr>
      <tr><th>Applied</th><td><?= h($app['applied_at']) ?></td></tr>
      <tr><th>Status</th><td><?= h($app['status']) ?></td></tr>
      <tr><th>University</th><td><?= h($profile['university'] ?? '—') ?></td></tr>
      <tr><th>Programme</th><td><?= h($profile['programme'] ?? '—') ?></td></tr>
      <tr><th>Year Level</th><td><?= h($profile['year_level'] ?? '—') ?></td></tr>
      <tr><th>Available Days</th><td><?= h($profile['available_days'] ?? '—') ?></td></tr>
      <tr><th>Preferred Shift</th><td><?= h($profile['preferred_shift'] ?? '—') ?></td></tr>
      <tr><th>Max Hours/Week</th><td><?= h($profile['max_hours_per_week'] ?? '—') ?></td></tr>
    </table>
  </div>

  <!-- Documents -->
  <div class="card admin-form" style="max-width:100%">
    <h3>Documents</h3>
    <?php if ($docs): ?>
    <table class="admin-table">
      <thead><tr><th>Document</th><th>Verified</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($docs as $doc): ?>
        <tr>
          <td><?= h($doc['document_name']) ?></td>
          <td><?= $doc['verified'] ? '<span class="status-pill ok">Verified</span>' : '<span class="status-pill gray">Unverified</span>' ?></td>
          <td>
            <form method="post" action="applicant.php?id=<?= $appId ?>">
              <input type="hidden" name="action"  value="toggle_doc">
              <input type="hidden" name="doc_id"  value="<?= (int)$doc['id'] ?>">
              <button class="btn sm" type="submit"><?= $doc['verified'] ? 'Unverify' : 'Verify' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="muted">No documents on file.</p>
    <?php endif; ?>
  </div>

  <!-- Status actions -->
  <div class="card admin-form" style="max-width:100%">
    <h3>Pipeline Actions</h3>
    <?php if ($app['status'] === 'Rejected'): ?>
      <span class="status-pill red">Rejected</span>
    <?php else: ?>
      <?php
        $pipeline = ['Applied', 'Docs Verified', 'Training', 'Hired'];
        $idx = array_search($app['status'], $pipeline, true);
        $canAdvance = ($idx !== false && $idx < count($pipeline) - 1);
      ?>
      <?php if ($canAdvance): ?>
        <form method="post" action="applicant.php?id=<?= $appId ?>" style="display:inline">
          <input type="hidden" name="action" value="advance_status">
          <button class="btn sm" type="submit">Advance to <?= h($pipeline[$idx + 1]) ?></button>
        </form>
      <?php else: ?>
        <span class="status-pill ok">Hired</span>
      <?php endif; ?>
      <form method="post" action="applicant.php?id=<?= $appId ?>" style="display:inline;margin-left:10px">
        <input type="hidden" name="action" value="reject">
        <button class="btn sm outline" type="submit" onclick="return confirm('Reject this application?')">Reject</button>
      </form>
    <?php endif; ?>
  </div>

  <?php endif; ?>
  <?php require __DIR__ . '/includes/admin_footer.php'; ?>
  ```

  File: `admin/applicant.php`

  Verify:
  - Visit `admin/applicant.php?id=0` → redirects to `admin/applicants.php`.
  - Visit `admin/applicant.php?id=1` (a valid application ID) → page loads with applicant info, documents, and pipeline actions.
  - Click "Verify" on a document → page reloads with green flash "Document status updated." and the document shows "Verified".
  - Click "Advance" on an Applied application → reloads with "Status updated to Docs Verified." flash.
  - Click "Reject" and confirm → reloads with "Application rejected." flash; Advance/Reject form is gone, "Rejected" pill is shown.
  - Click "Advance" on a Hired application → reloads with "Status cannot be advanced further." error flash.
  - Verify flash clears on the following page load (does not reappear on refresh).

---

- [ ] 12. **Create `admin/jobs.php` — Jobs Management** *(depends on items 1, 7, 8)*

  Create `admin/jobs.php`. Handles three POST actions (`add`, `edit`, `delete`) via PRG, then the GET display of jobs table and add/edit form.

  ```php
  <?php
  $title  = 'Jobs';
  $active = 'jobs';
  require_once __DIR__ . '/../includes/db.php';

  // ── POST handlers (PRG) ──────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $action = $_POST['action'] ?? '';

      if ($action === 'delete') {
          $jobId = (int)($_POST['job_id'] ?? 0);
          if ($jobId) {
              try {
                  db()->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
                  $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Job deleted.'];
              } catch (PDOException $e) {
                  error_log($e->getMessage());
                  $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Database error. Please try again.'];
              }
          }
          header('Location: jobs.php');
          exit;
      }

      if ($action === 'add' || $action === 'edit') {
          $v = [
              'title'     => trim($_POST['title']     ?? ''),
              'branch'    => trim($_POST['branch']    ?? ''),
              'tags'      => trim($_POST['tags']      ?? ''),
              'hours'     => trim($_POST['hours']     ?? ''),
              'distance'  => trim($_POST['distance']  ?? ''),
              'match_pct' => $_POST['match_pct']      ?? '',
          ];

          $errors = [];
          if (strlen($v['title'])    < 1 || strlen($v['title'])    > 100) $errors[] = 'Title is required (1–100 chars).';
          if (strlen($v['branch'])   < 1 || strlen($v['branch'])   > 150) $errors[] = 'Branch is required (1–150 chars).';
          if (strlen($v['hours'])    < 1 || strlen($v['hours'])    > 100) $errors[] = 'Hours is required (1–100 chars).';
          if (strlen($v['distance']) < 1 || strlen($v['distance']) > 20)  $errors[] = 'Distance is required (1–20 chars).';
          $matchInt = filter_var($v['match_pct'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
          if ($matchInt === false)  $errors[] = 'Match % must be an integer 0–100.';
          // Tags: silently truncate to 255 chars (display-only, no business logic)
          if (strlen($v['tags']) > 255) $v['tags'] = substr($v['tags'], 0, 255);

          if (!$errors) {
              try {
                  if ($action === 'add') {
                      db()->prepare(
                          'INSERT INTO jobs (title, branch, tags, hours, distance, match_pct)
                           VALUES (?, ?, ?, ?, ?, ?)'
                      )->execute([$v['title'], $v['branch'], $v['tags'], $v['hours'], $v['distance'], $matchInt]);
                      $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Job added.'];
                  } else {
                      $jobId = (int)($_POST['job_id'] ?? 0);
                      db()->prepare(
                          'UPDATE jobs SET title=?, branch=?, tags=?, hours=?, distance=?, match_pct=? WHERE id=?'
                      )->execute([$v['title'], $v['branch'], $v['tags'], $v['hours'], $v['distance'], $matchInt, $jobId]);
                      $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Job updated.'];
                  }
                  header('Location: jobs.php');
                  exit;
              } catch (PDOException $e) {
                  error_log($e->getMessage());
                  $errors[] = 'Database error. Please try again.';
              }
          }

          // Validation failure: fall through to GET rendering with $errors and $v populated.
      }
  }

  // ── GET path ─────────────────────────────────────────────────────────────────
  $flash = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);

  require __DIR__ . '/includes/admin_header.php';

  // Repopulate form on validation failure (kept from POST branch above)
  $errors ??= [];
  $v      ??= ['title' => '', 'branch' => '', 'tags' => '', 'hours' => '', 'distance' => '', 'match_pct' => ''];

  try {
      $jobs  = db()->query('SELECT id, title, branch, tags, hours, distance, match_pct FROM jobs ORDER BY id')->fetchAll();
      $dbErr = null;
  } catch (PDOException $e) {
      error_log($e->getMessage());
      $dbErr = 'Database error. Please try again.';
      $jobs  = [];
  }
  ?>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <h1 style="margin-bottom:16px">Jobs</h1>

  <?php if ($dbErr): ?>
    <div class="form-err"><?= h($dbErr) ?></div>
  <?php else: ?>
  <table class="admin-table" style="margin-bottom:24px">
    <thead>
      <tr><th>Title</th><th>Branch</th><th>Hours</th><th>Distance</th><th>Match %</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($jobs as $row): ?>
      <tr>
        <td><?= h($row['title']) ?></td>
        <td><?= h($row['branch']) ?></td>
        <td><?= h($row['hours']) ?></td>
        <td><?= h($row['distance']) ?></td>
        <td><?= (int)$row['match_pct'] ?></td>
        <td>
          <button type="button" class="btn sm outline"
                  onclick="editJob(this)"
                  data-id="<?= h($row['id']) ?>"
                  data-title="<?= h($row['title']) ?>"
                  data-branch="<?= h($row['branch']) ?>"
                  data-tags="<?= h($row['tags']) ?>"
                  data-hours="<?= h($row['hours']) ?>"
                  data-distance="<?= h($row['distance']) ?>"
                  data-match="<?= h($row['match_pct']) ?>">Edit</button>
          <form method="post" action="jobs.php" style="display:inline">
            <input type="hidden" name="action"  value="delete">
            <input type="hidden" name="job_id"  value="<?= (int)$row['id'] ?>">
            <button class="btn sm" type="submit"
                    onclick="return confirm('Delete this job and all its applications?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$jobs): ?><tr><td colspan="6" style="color:var(--muted)">No jobs yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Add / Edit form -->
  <div class="admin-form" id="job-form">
    <h3>Add / Edit Job</h3>
    <?php foreach ($errors as $err): ?>
      <div class="form-err"><?= h($err) ?></div>
    <?php endforeach; ?>
    <form method="post" action="jobs.php">
      <input type="hidden" name="action"  id="action_field" value="add">
      <input type="hidden" name="job_id"  id="job_id"       value="">
      <div class="col">
        <label class="field"><input id="f_title"    name="title"     placeholder="Title"          value="<?= h($v['title']) ?>"     required></label>
        <label class="field"><input id="f_branch"   name="branch"    placeholder="Branch"         value="<?= h($v['branch']) ?>"    required></label>
        <label class="field"><input id="f_tags"     name="tags"      placeholder="Tags (comma-separated)" value="<?= h($v['tags']) ?>"></label>
        <label class="field"><input id="f_hours"    name="hours"     placeholder="Hours (e.g. Part-time · Flexible)" value="<?= h($v['hours']) ?>"    required></label>
        <label class="field"><input id="f_distance" name="distance"  placeholder="Distance (e.g. 2.5 km)"           value="<?= h($v['distance']) ?>" required></label>
        <label class="field"><input id="f_match"    name="match_pct" placeholder="Match % (0–100)"                  value="<?= h($v['match_pct']) ?>" type="number" min="0" max="100" required></label>
        <button class="btn" type="submit">Save Job</button>
      </div>
    </form>
  </div>

  <?php require __DIR__ . '/includes/admin_footer.php'; ?>
  ```

  File: `admin/jobs.php`

  Verify:
  - Visit `admin/jobs.php` → all four seeded jobs listed; Add/Edit form below.
  - Fill in the form with valid data and click "Save Job" → page reloads with "Job added." flash and new row in table.
  - Click "Edit" on a job row → form scrolls into view pre-filled with that job's data, action changes to "edit".
  - Edit a field, click "Save Job" → page reloads with "Job updated." flash and updated values in the table.
  - Click "Delete" on a job and confirm → page reloads with "Job deleted." flash and row is gone.
  - Submit the form with a blank title → page reloads with the validation error "Title is required (1–100 chars)" and no row is added.
  - Confirm the student `jobs.php` still works (no errors from the removed `h()` definition).

---

## Digest

### Files touched

| File | Block / change | Covering test |
|---|---|---|
| `database.sql` | Append `ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin…` after users CREATE TABLE | Re-import SQL, run `SELECT email, is_admin FROM users` |
| `database.sql` | Append `ALTER TABLE applications MODIFY COLUMN status ENUM(…,'Rejected')…` after applications CREATE TABLE | Run `SHOW COLUMNS FROM applications LIKE 'status'` |
| `database.sql` | Append admin `INSERT IGNORE INTO users … is_admin = 1` after Shamea seed | Run `SELECT email, is_admin FROM users` |
| `includes/db.php` | Add `define('BASE_PATH', '/Jolli-HR')` before `db_options()` | Load any student page, no PHP fatal |
| `includes/auth.php` | Replace bare `session_start()` with guarded form; add `h()` with `function_exists`; add `require_admin()` | Load student pages — no double-session notice; load admin page without login → redirects |
| `jobs.php` | Remove `function h($s)` block (~line 26) | Load `http://localhost/Jolli-HR/jobs.php` while logged in — no fatal error |
| `login.php` | Update SELECT to include `is_admin`; store `is_admin` in session; branch redirect on login; branch already-logged-in guard | Login as admin → lands on `admin/index.php`; login as student → `home.php` |
| `assets/css/admin.css` | New file: `.admin-topbar`, `.admin-wrap`, `.admin-main`, `.admin-table`, `.status-pill`, `.admin-form`, `.flash.err`, `.stat-cards`, `.stat-card` | Inspect in browser dev-tools — no CSS parse errors |
| `assets/js/admin.js` | New file: `editJob()` function | Edit-job button on `admin/jobs.php` pre-fills form correctly |
| `admin/includes/admin_header.php` | New file: `require_admin()`, HTML head, admin topbar, opens `admin-wrap` | Load any `admin/*.php` page |
| `admin/includes/admin_footer.php` | New file: closes HTML, includes `app.js` and `admin.js` | Load any `admin/*.php` page |
| `admin/index.php` | New file: five read-only queries, four stat cards, recent-applications table | Log in as admin; dashboard loads with correct counts |
| `admin/applicants.php` | New file: status-filter GET, applicant list table | Status filter works; each row links to applicant detail |
| `admin/applicant.php` | New file: `advance_status`, `reject`, `toggle_doc` POST handlers + GET display | Full PRG cycle for each action; flash clears on next load |
| `admin/jobs.php` | New file: `add`, `edit`, `delete` POST handlers + GET display with inline validation | Full PRG cycle; server-side validation rejects blank title |

**Build command:** None (PHP/XAMPP — no compile step).

**Test command:** Manual browser testing against `http://localhost/Jolli-HR/` with XAMPP Apache + MySQL running and `jollihr_db` created by importing `database.sql`.

**Tests passing today:** All existing student pages (`home.php`, `jobs.php`, `profile.php`, `schedule.php`, `training.php`) load without error. The plan preserves this — only `auth.php`, `db.php`, and `jobs.php` are modified in existing files, and each modification is additive or is a targeted removal of one function that is now provided centrally.
