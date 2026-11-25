# Technical Design Document

## Overview

This document describes the technical design for making three currently-static JolliHR pages — `profile.php`, `training.php`, and `schedule.php` — fully database-backed. No new frameworks are introduced. The design follows the conventions already established in `jobs.php` and `home.php`: PDO via the `db()` helper, POST-redirect-GET for mutations, `htmlspecialchars` output escaping, and schema defined in `database.sql` with `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE` so the auto-bootstrap in `db.php` picks up the new tables without manual imports.

---

## High-Level Architecture

```
Browser
  │  GET / POST
  ▼
profile.php / training.php / schedule.php
  │  require_once
  ├── includes/auth.php   (session, require_login)
  ├── includes/db.php     (db() PDO singleton)
  ├── includes/header.php
  ├── includes/footer.php
  └── includes/icons.php

  db() ──► MySQL jollihr_db
              ├── users              (existing)
              ├── jobs               (existing)
              ├── applications       (existing)
              ├── user_profiles      (NEW)
              ├── user_documents     (NEW)
              ├── training_modules   (NEW)
              ├── user_training      (NEW)
              ├── class_slots        (NEW)
              ├── work_shifts        (NEW)
              └── mood_entries       (NEW)
```

All pages use the same **pattern**:

1. `require_once 'includes/auth.php'` + `require_login()` guard at top.
2. Handle POST at top (validate → write → redirect).
3. Query DB for page data.
4. `require 'includes/header.php'` to open the HTML shell.
5. Render HTML with `htmlspecialchars` on every echoed value.
6. `require 'includes/footer.php'` to close.

---

## Database Schema

All DDL goes in `database.sql` using `CREATE TABLE IF NOT EXISTS`. Foreign keys cascade on delete. All string columns use `utf8mb4_unicode_ci`.

### `user_profiles`
Stores per-user profile preferences. One row per user.

```sql
CREATE TABLE IF NOT EXISTS user_profiles (
  user_id            INT UNSIGNED NOT NULL,
  university         VARCHAR(150) NOT NULL DEFAULT '',
  programme          VARCHAR(100) NOT NULL DEFAULT '',
  year_level         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  available_days     VARCHAR(50)  NOT NULL DEFAULT '',  -- comma-separated: 'Mon,Tue,Thu'
  preferred_shift    VARCHAR(50)  NOT NULL DEFAULT '',
  max_hours_per_week TINYINT UNSIGNED NOT NULL DEFAULT 20,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### `user_documents`
Tracks required onboarding documents per user.

```sql
CREATE TABLE IF NOT EXISTS user_documents (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  document_name VARCHAR(100) NOT NULL,
  verified      TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### `training_modules`
Master list of modules (shared, not per-user).

```sql
CREATE TABLE IF NOT EXISTS training_modules (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NOT NULL DEFAULT '',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
);
```

### `user_training`
Per-user progress record for each module.

```sql
CREATE TABLE IF NOT EXISTS user_training (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  module_id    INT UNSIGNED NOT NULL,
  status       ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ut (user_id, module_id),
  CONSTRAINT fk_ut_user   FOREIGN KEY (user_id)   REFERENCES users (id)             ON DELETE CASCADE,
  CONSTRAINT fk_ut_module FOREIGN KEY (module_id) REFERENCES training_modules (id)  ON DELETE CASCADE
);
```

### `class_slots`
Recurring weekly class blocks per user.

```sql
CREATE TABLE IF NOT EXISTS class_slots (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,  -- 0=Mon … 6=Sun
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  label       VARCHAR(100) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### `work_shifts`
Available and selected work shifts per user.

```sql
CREATE TABLE IF NOT EXISTS work_shifts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  shift_name  VARCHAR(100) NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,  -- 0=Mon … 6=Sun
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  branch      VARCHAR(150) NOT NULL DEFAULT '',
  status      ENUM('available','selected') NOT NULL DEFAULT 'available',
  PRIMARY KEY (id),
  CONSTRAINT fk_ws_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### `mood_entries`
One row per user per ISO week.

```sql
CREATE TABLE IF NOT EXISTS mood_entries (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id  INT UNSIGNED NOT NULL,
  mood     ENUM('Great','Okay','Tired') NOT NULL,
  iso_year SMALLINT UNSIGNED NOT NULL,
  iso_week TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_me (user_id, iso_year, iso_week),
  CONSTRAINT fk_me_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

---

## Seed Data

All seed rows use `INSERT IGNORE` so re-importing is safe.

**Training modules** (global, 5 rows):
- 1 Orientation · "Welcome to the team" · order 1
- 2 Food Safety · "Safe food, happy customers" · order 2
- 3 Customer Service · "Creating great experiences" · order 3
- 4 POS System · "Basic transactions" · order 4
- 5 Workplace Safety · "A safer workplace for all" · order 5

**Demo account** (user_id resolved by sub-select on `shamea@jollihr.test`):
- `user_profiles`: PUP Biñan Campus / BSBA / 4th year / Mon,Tue,Thu,Fri,Sat / Morning / 20 hours
- `user_documents`: COR (verified), Valid ID (verified), NBI Clearance (unverified), Medical Certificate (unverified)
- `user_training`: modules 1–3 completed, 4 in_progress, 5 not_started
- `work_shifts`: Morning Shift Mon (07:00–11:00, available), Afternoon Shift Tue (14:00–18:00, available), Evening Shift Wed (17:00–21:00, available)

No seed rows for `class_slots` or `mood_entries`; those are user-generated.

---

## Page Designs

### `profile.php`

**GET flow:**

```php
$uid = $_SESSION['user']['id'];

// 1. User row (name already in session; re-fetch for freshness)
$user = db()->prepare('SELECT first_name, last_name, email FROM users WHERE id = ?');
$user->execute([$uid]);
$user = $user->fetch();

// 2. Profile row (LEFT JOIN so missing row returns NULLs, not an error)
$prof = db()->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
$prof->execute([$uid]);
$prof = $prof->fetch() ?: [];   // empty array = show placeholders

// 3. Documents
$docs = db()->prepare('SELECT document_name, verified FROM user_documents WHERE user_id = ? ORDER BY id');
$docs->execute([$uid]);
$docs = $docs->fetchAll();

// 4. Mood for current ISO week
[$isoY, $isoW] = explode('-', (new DateTime())->format('o-W'));
$mood = db()->prepare('SELECT mood FROM mood_entries WHERE user_id = ? AND iso_year = ? AND iso_week = ?');
$mood->execute([$uid, $isoY, $isoW]);
$currentMood = $mood->fetchColumn() ?: '';
```

**POST — preference update (`action=prefs`):**

Validate: `available_days` non-empty array from checkboxes; `max_hours_per_week` integer 1–40; `preferred_shift` non-empty string ≤ 50 chars.

```php
db()->prepare('INSERT INTO user_profiles
  (user_id, available_days, preferred_shift, max_hours_per_week)
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    available_days = VALUES(available_days),
    preferred_shift = VALUES(preferred_shift),
    max_hours_per_week = VALUES(max_hours_per_week)')
  ->execute([$uid, implode(',', $days), $shift, $maxH]);
header('Location: profile.php?saved=1');
exit;
```

**POST — mood (`action=mood`):**

Validate mood is one of `['Great','Okay','Tired']`.

```php
db()->prepare('INSERT INTO mood_entries (user_id, mood, iso_year, iso_week) VALUES (?,?,?,?)
  ON DUPLICATE KEY UPDATE mood = VALUES(mood)')
  ->execute([$uid, $mood, $isoY, $isoW]);
header('Location: profile.php#well');
exit;
```

Both POSTs are on separate `<form>` elements with a hidden `name="action"` field so the handler can branch.

The avatar initials (`AR` hardcoded today) become `<?= strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1)) ?>`.

---

### `training.php`

**GET flow:**

```php
$uid = $_SESSION['user']['id'];

// All modules joined with this user's progress (LEFT JOIN so modules with no row appear as not_started)
$rows = db()->prepare('
  SELECT m.id, m.name, m.description, m.display_order,
         COALESCE(ut.status, "not_started") AS status,
         ut.completed_at
  FROM training_modules m
  LEFT JOIN user_training ut ON ut.module_id = m.id AND ut.user_id = ?
  ORDER BY m.display_order
');
$rows->execute([$uid]);
$modules = $rows->fetchAll();

$total     = count($modules);
$completed = count(array_filter($modules, fn($m) => $m['status'] === 'completed'));
$pct       = $total ? round($completed / $total * 100) : 0;
```

Status → pill class mapping: `completed` → `ok`, `in_progress` → `warn`, `not_started` → `gray`.

**POST — mark complete (`action=complete`):**

```php
$moduleId = filter_var($_POST['module_id'] ?? '', FILTER_VALIDATE_INT);
// Validate module exists
db()->prepare('INSERT INTO user_training (user_id, module_id, status, completed_at)
  VALUES (?, ?, "completed", NOW())
  ON DUPLICATE KEY UPDATE status = "completed", completed_at = NOW()')
  ->execute([$uid, $moduleId]);
header('Location: training.php');
exit;
```

The "mark complete" button only renders when `$m['status'] !== 'completed'`.

**Certificates panel:** loop over `$modules` and show a certificate card only where `$m['status'] === 'completed'`.

**Assessment card** remains static (quiz data is out of scope for this feature).

---

### `schedule.php`

**GET flow:**

```php
$uid = $_SESSION['user']['id'];

// Class slots indexed by day_of_week
$cs = db()->prepare('SELECT day_of_week, start_time, end_time, label FROM class_slots WHERE user_id = ? ORDER BY day_of_week, start_time');
$cs->execute([$uid]);
$classByDay = [];
foreach ($cs->fetchAll() as $r) $classByDay[$r['day_of_week']][] = $r;

// Work shifts
$ws = db()->prepare('SELECT id, shift_name, day_of_week, start_time, end_time, branch, status FROM work_shifts WHERE user_id = ? ORDER BY day_of_week, start_time');
$ws->execute([$uid]);
$shifts = $ws->fetchAll();
$shiftByDay = [];
foreach ($shifts as $r) $shiftByDay[$r['day_of_week']][] = $r;

// Conflict detection (PHP-side, small dataset)
$conflicts = [];
foreach ($shifts as $s) {
  foreach (($classByDay[$s['day_of_week']] ?? []) as $c) {
    if ($s['start_time'] < $c['end_time'] && $s['end_time'] > $c['start_time']) {
      $conflicts[] = ['class' => $c, 'shift' => $s];
    }
  }
}
```

**Day-cell coloring logic** (in the weekly calendar loop for days 0–6):
- Has conflict on that day → `conflict`
- Has work shift only → `work`
- Has class slot only → `class`
- Neither → `free`

**POST — add class slot (`action=add_class`):**

Validate: `day_of_week` integer 0–6; `start_time` and `end_time` valid `H:i` strings; `start_time < end_time`; `label` ≤ 100 chars (may be empty).

```php
db()->prepare('INSERT INTO class_slots (user_id, day_of_week, start_time, end_time, label) VALUES (?,?,?,?,?)')
  ->execute([$uid, $day, $start, $end, $label]);
header('Location: schedule.php');
exit;
```

**POST — select shift (`action=select_shift`):**

```php
$shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
// Ownership check before update
$st = db()->prepare('SELECT user_id FROM work_shifts WHERE id = ?');
$st->execute([$shiftId]);
$owner = $st->fetchColumn();
if ((int)$owner !== $uid) { http_response_code(403); exit; }

db()->prepare('UPDATE work_shifts SET status = "selected" WHERE id = ?')
  ->execute([$shiftId]);
header('Location: schedule.php');
exit;
```

**POST — request shift swap (`action=swap`):**

For now, record a simple note (out-of-scope for full swap logic). Toggle the `status` back to `available` as an approximation, or simply redirect with a flash notice `?swapped=1`.

---

## Component Interaction Summary

| Trigger | Handler | DB write | Redirect |
|---|---|---|---|
| Profile prefs form submit | `profile.php` POST `action=prefs` | `INSERT … ON DUPLICATE KEY UPDATE user_profiles` | `profile.php?saved=1` |
| Mood button click (JS → form submit) | `profile.php` POST `action=mood` | `INSERT … ON DUPLICATE KEY UPDATE mood_entries` | `profile.php#well` |
| Mark module complete | `training.php` POST `action=complete` | `INSERT … ON DUPLICATE KEY UPDATE user_training` | `training.php` |
| Add class slot | `schedule.php` POST `action=add_class` | `INSERT class_slots` | `schedule.php` |
| Select shift | `schedule.php` POST `action=select_shift` | `UPDATE work_shifts` | `schedule.php` |
| Request swap | `schedule.php` POST `action=swap` | `UPDATE work_shifts status=available` | `schedule.php?swapped=1` |

---

## Security Notes

- Every rendered user-supplied value is wrapped in `htmlspecialchars()`.
- Every DB write uses a PDO prepared statement with `?` placeholders.
- Shift ownership is verified server-side before any `UPDATE`.
- Mood and status enums are whitelisted in PHP before hitting the DB.
- No new session handling beyond the existing `require_login()` guard.

---

## Out of Scope

- File upload for documents (the upload button remains a UI stub).
- Full shift-swap request workflow (a real swap table with notifications).
- University COR parsing / verification.
- Admin-side views or HR manager pages.
- Push notifications or real-time updates.
