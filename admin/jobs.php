<?php
$title  = 'Jobs';
$active = 'jobs';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];
$v = ['title' => '', 'branch' => '', 'tags' => '', 'hours' => '', 'distance' => '', 'match_pct' => ''];
$editId = 0;

// ── POST handlers ─────────────────────────────────────────────────────────────
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
        header('Location: jobs.php'); exit;
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
        $editId = (int)($_POST['job_id'] ?? 0);

        if (strlen($v['title'])    < 1 || strlen($v['title'])    > 100) $errors[] = 'Title is required (max 100 chars).';
        if (strlen($v['branch'])   < 1 || strlen($v['branch'])   > 150) $errors[] = 'Branch is required (max 150 chars).';
        if (strlen($v['hours'])    < 1 || strlen($v['hours'])    > 100) $errors[] = 'Hours description is required.';
        if (strlen($v['distance']) < 1 || strlen($v['distance']) > 20)  $errors[] = 'Distance is required (max 20 chars).';
        $matchInt = filter_var($v['match_pct'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($matchInt === false) $errors[] = 'Match % must be a number between 0 and 100.';
        if (strlen($v['tags']) > 255) $v['tags'] = substr($v['tags'], 0, 255);

        if (!$errors) {
            try {
                if ($action === 'add') {
                    db()->prepare('INSERT INTO jobs (title, branch, tags, hours, distance, match_pct) VALUES (?,?,?,?,?,?)')
                       ->execute([$v['title'], $v['branch'], $v['tags'], $v['hours'], $v['distance'], $matchInt]);
                    $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Job added successfully.'];
                } else {
                    db()->prepare('UPDATE jobs SET title=?, branch=?, tags=?, hours=?, distance=?, match_pct=? WHERE id=?')
                       ->execute([$v['title'], $v['branch'], $v['tags'], $v['hours'], $v['distance'], $matchInt, $editId]);
                    $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Job updated successfully.'];
                }
                header('Location: jobs.php'); exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
        // Fall through to render form with errors
    }
}

// ── GET ───────────────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

require __DIR__ . '/includes/admin_header.php';

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

<div class="page-header">
  <h1>Jobs</h1>
</div>

<?php if (!empty($dbErr)): ?>
  <div class="flash err"><?= h($dbErr) ?></div>
<?php else: ?>

<table class="admin-table" style="margin-bottom:28px">
  <thead>
    <tr><th>Title</th><th>Branch</th><th>Hours</th><th>Distance</th><th>Match %</th><th>Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($jobs as $row): ?>
    <tr>
      <td><strong><?= h($row['title']) ?></strong></td>
      <td><?= h($row['branch']) ?></td>
      <td><?= h($row['hours']) ?></td>
      <td><?= h($row['distance']) ?></td>
      <td><?= (int)$row['match_pct'] ?>%</td>
      <td style="white-space:nowrap">
        <button type="button" class="btn outline sm"
                onclick="editJob(this)"
                data-id="<?= h($row['id']) ?>"
                data-title="<?= h($row['title']) ?>"
                data-branch="<?= h($row['branch']) ?>"
                data-tags="<?= h($row['tags']) ?>"
                data-hours="<?= h($row['hours']) ?>"
                data-distance="<?= h($row['distance']) ?>"
                data-match="<?= h($row['match_pct']) ?>">Edit</button>
        <form method="post" action="jobs.php" style="display:inline;margin-left:6px">
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="job_id"  value="<?= (int)$row['id'] ?>">
          <button class="btn danger sm" type="submit"
                  onclick="return confirm('Delete this job and all its applications?')">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$jobs): ?>
    <tr><td colspan="6" style="color:var(--muted);text-align:center;padding:20px">No jobs yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php endif; ?>

<!-- Add / Edit form -->
<div class="admin-form" id="job-form">
  <h3>Add New Job</h3>
  <?php foreach ($errors as $err): ?>
    <div class="flash err" style="margin-bottom:10px"><?= h($err) ?></div>
  <?php endforeach; ?>
  <form method="post" action="jobs.php">
    <input type="hidden" name="action" id="action_field" value="<?= $editId ? 'edit' : 'add' ?>">
    <input type="hidden" name="job_id" id="job_id"       value="<?= $editId ?>">

    <div class="admin-field-row">
      <div class="admin-field">
        <label>Job Title</label>
        <input id="f_title" name="title" placeholder="e.g. Crew Member" value="<?= h($v['title']) ?>" required maxlength="100">
      </div>
      <div class="admin-field">
        <label>Branch</label>
        <input id="f_branch" name="branch" placeholder="e.g. Jollibee – Sta. Rosa" value="<?= h($v['branch']) ?>" required maxlength="150">
      </div>
    </div>

    <div class="admin-field">
      <label>Tags <span style="font-weight:400;color:var(--muted)">(comma-separated)</span></label>
      <input id="f_tags" name="tags" placeholder="e.g. Student Friendly,Part-time" value="<?= h($v['tags']) ?>" maxlength="255">
    </div>

    <div class="admin-field-row">
      <div class="admin-field">
        <label>Hours</label>
        <input id="f_hours" name="hours" placeholder="e.g. Part-time · Flexible Schedule" value="<?= h($v['hours']) ?>" required maxlength="100">
      </div>
      <div class="admin-field">
        <label>Distance</label>
        <input id="f_distance" name="distance" placeholder="e.g. 2.5 km" value="<?= h($v['distance']) ?>" required maxlength="20">
      </div>
    </div>

    <div class="admin-field" style="max-width:160px">
      <label>Match % (0–100)</label>
      <input id="f_match" name="match_pct" type="number" min="0" max="100" placeholder="92" value="<?= h($v['match_pct']) ?>" required>
    </div>

    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn primary" type="submit">Save Job</button>
      <?php if ($editId): ?>
        <a href="jobs.php" class="btn outline">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
