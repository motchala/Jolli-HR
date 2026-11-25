<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();

$uid  = (int)$_SESSION['user']['id'];
$errs = [];

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add class slot ───────────────────────────────────────────────────────
    if ($action === 'add_class') {
        $day   = filter_var($_POST['day_of_week'] ?? '', FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 0, 'max_range' => 6]]);
        $start = trim($_POST['start_time'] ?? '');
        $end   = trim($_POST['end_time']   ?? '');
        $label = substr(trim($_POST['label'] ?? ''), 0, 100);

        if ($day === false)   $errs['day']   = 'Please choose a valid day.';
        if (!preg_match('/^\d{2}:\d{2}$/', $start)) $errs['start'] = 'Enter a valid start time.';
        if (!preg_match('/^\d{2}:\d{2}$/', $end))   $errs['end']   = 'Enter a valid end time.';
        if (!isset($errs['start']) && !isset($errs['end']) && $start >= $end)
            $errs['time'] = 'Start time must be earlier than end time.';

        if (!$errs) {
            db()->prepare('INSERT INTO class_slots (user_id, day_of_week, start_time, end_time, label)
                VALUES (?, ?, ?, ?, ?)')
              ->execute([$uid, $day, $start . ':00', $end . ':00', $label]);
            header('Location: schedule.php');
            exit;
        }
    }

    // ── Select shift ─────────────────────────────────────────────────────────
    if ($action === 'select_shift') {
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        if ($shiftId) {
            $st = db()->prepare('SELECT user_id FROM work_shifts WHERE id = ?');
            $st->execute([$shiftId]);
            $owner = $st->fetchColumn();
            if ((int)$owner !== $uid) { http_response_code(403); exit; }
            db()->prepare('UPDATE work_shifts SET status = "selected" WHERE id = ?')
              ->execute([$shiftId]);
        }
        header('Location: schedule.php');
        exit;
    }

    // ── Request shift swap ───────────────────────────────────────────────────
    if ($action === 'swap') {
        $shiftId = filter_var($_POST['shift_id'] ?? '', FILTER_VALIDATE_INT);
        if ($shiftId) {
            $st = db()->prepare('SELECT user_id FROM work_shifts WHERE id = ?');
            $st->execute([$shiftId]);
            $owner = $st->fetchColumn();
            if ((int)$owner !== $uid) { http_response_code(403); exit; }
            db()->prepare('UPDATE work_shifts SET status = "available" WHERE id = ?')
              ->execute([$shiftId]);
        }
        header('Location: schedule.php?swapped=1');
        exit;
    }
}

// ── GET: load data ────────────────────────────────────────────────────────────
$swapped = isset($_GET['swapped']);

// Class slots indexed by day
$st = db()->prepare('SELECT id, day_of_week, start_time, end_time, label
    FROM class_slots WHERE user_id = ? ORDER BY day_of_week, start_time');
$st->execute([$uid]);
$classByDay = [];
foreach ($st->fetchAll() as $r) {
    $classByDay[(int)$r['day_of_week']][] = $r;
}

// Work shifts
$st = db()->prepare('SELECT id, shift_name, day_of_week, start_time, end_time, branch, status
    FROM work_shifts WHERE user_id = ? ORDER BY day_of_week, start_time');
$st->execute([$uid]);
$shifts = $st->fetchAll();
$shiftByDay = [];
foreach ($shifts as $r) {
    $shiftByDay[(int)$r['day_of_week']][] = $r;
}

// Conflict detection
$conflicts = [];
foreach ($shifts as $s) {
    foreach (($classByDay[(int)$s['day_of_week']] ?? []) as $c) {
        if ($s['start_time'] < $c['end_time'] && $s['end_time'] > $c['start_time']) {
            $conflicts[] = ['class' => $c, 'shift' => $s];
        }
    }
}

// Day label + dot class for weekly calendar
$dayLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
function dayDotClass(int $d, array $classByDay, array $shiftByDay, array $conflicts): string {
    $hasConflict = false;
    foreach ($conflicts as $con) {
        if ((int)$con['shift']['day_of_week'] === $d) { $hasConflict = true; break; }
    }
    if ($hasConflict)                  return 'conflict';
    if (!empty($shiftByDay[$d]))       return 'work';
    if (!empty($classByDay[$d]))       return 'class';
    return 'free';
}

// Pill helpers
function shiftPill(string $s): array {
    return $s === 'selected' ? ['ok', 'Selected'] : ['', 'Available'];
}

$title  = 'My Schedule';
$active = 'schedule';
require 'includes/header.php';
?>

<?php if ($swapped): ?>
  <div class="flash ok"><?= ic('check') ?> Shift swap requested.</div>
<?php endif; ?>

<div class="seg" data-tabs>
  <button class="on" data-tab="mine">My Schedule</button>
  <button data-tab="match">Shift Matching</button>
</div>

<!-- ── My Schedule ──────────────────────────────────────────────────────── -->
<div data-panel="mine">

  <section class="card">
    <h3><?= ic('cal', 'red') ?> Weekly Schedule</h3>
    <div class="week seven">
      <?php foreach ($dayLabels as $i => $d): ?>
        <div>
          <small><?= $d ?></small>
          <b><?= $i + 6 ?></b>
          <i class="dot <?= dayDotClass($i, $classByDay, $shiftByDay, $conflicts) ?>"></i>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="legend">
      <span><i class="dot class"></i>Class</span>
      <span><i class="dot work"></i>Work Shift</span>
      <span><i class="dot conflict"></i>Conflict</span>
      <span><i class="dot free"></i>Free</span>
    </div>
  </section>

  <!-- Add class slot form -->
  <section class="card">
    <h3><?= ic('book', 'red') ?> Add Class Slot</h3>
    <?php if ($errs): ?>
      <div class="form-err">
        <?php foreach ($errs as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" action="schedule.php" class="add-class-form">
      <input type="hidden" name="action" value="add_class">
      <div class="row gap">
        <select name="day_of_week" class="field-sel" required>
          <option value="">Day</option>
          <?php foreach ($dayLabels as $i => $d): ?>
            <option value="<?= $i ?>"><?= $d ?></option>
          <?php endforeach; ?>
        </select>
        <input type="time" name="start_time" placeholder="Start" class="field-time" required>
        <input type="time" name="end_time"   placeholder="End"   class="field-time" required>
      </div>
      <input type="text" name="label" maxlength="100" placeholder="Subject / label (optional)" class="field-txt">
      <button class="btn" type="submit">Add Class</button>
    </form>
  </section>

  <!-- Conflict alert -->
  <?php if ($conflicts): ?>
    <?php foreach ($conflicts as $con):
      $c = $con['class'];
      $s = $con['shift'];
      $dayName = $dayLabels[(int)$s['day_of_week']] ?? '';
    ?>
    <section class="card alert">
      <h3><?= ic('alert') ?> Schedule Conflict Detected</h3>
      <p>Your class on <?= htmlspecialchars($dayName) ?>
         (<?= htmlspecialchars(substr($c['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($c['end_time'],0,5)) ?>)
         <?= $c['label'] ? '– ' . htmlspecialchars($c['label']) : '' ?>
         overlaps with <?= htmlspecialchars($s['shift_name']) ?>
         (<?= htmlspecialchars(substr($s['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($s['end_time'],0,5)) ?>).</p>
      <div class="row gap">
        <button class="btn outline grow">View Details</button>
        <form method="post" action="schedule.php" style="flex:1">
          <input type="hidden" name="action"   value="swap">
          <input type="hidden" name="shift_id" value="<?= (int)$s['id'] ?>">
          <button class="btn grow" type="submit">Request Shift Swap</button>
        </form>
      </div>
    </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Recommended shifts -->
  <?php if ($shifts): ?>
  <section class="card">
    <h3><?= ic('star', 'red') ?> My Work Shifts</h3>
    <p class="muted sm-t">Your scheduled shifts</p>
    <?php foreach ($shifts as $s):
      [$pillCls, $pillLabel] = shiftPill($s['status']);
      $dayName = $dayLabels[(int)$s['day_of_week']] ?? '';
      $hasConflictOnShift = false;
      foreach ($conflicts as $con) {
          if ((int)$con['shift']['id'] === (int)$s['id']) { $hasConflictOnShift = true; break; }
      }
      $compatLabel = $hasConflictOnShift ? 'Conflict' : 'Compatible';
      $compatCls   = $hasConflictOnShift ? 'warn'     : 'ok';
    ?>
      <div class="shift">
        <?= ic('clock', 'red') ?>
        <div class="grow">
          <b><?= htmlspecialchars($s['shift_name']) ?></b>
          <p><?= htmlspecialchars(substr($s['start_time'],0,5)) ?> – <?= htmlspecialchars(substr($s['end_time'],0,5)) ?></p>
          <small class="muted"><?= htmlspecialchars($s['branch']) ?></small>
        </div>
        <div class="col end">
          <span class="pill <?= $compatCls ?>"><?= $compatLabel ?></span>
          <?php if ($s['status'] === 'available'): ?>
            <form method="post" action="schedule.php">
              <input type="hidden" name="action"   value="select_shift">
              <input type="hidden" name="shift_id" value="<?= (int)$s['id'] ?>">
              <button class="btn sm" type="submit">Select</button>
            </form>
          <?php else: ?>
            <span class="pill ok">Selected</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
  <?php else: ?>
  <section class="card">
    <p class="muted">No work shifts assigned yet.</p>
  </section>
  <?php endif; ?>

</div>

<!-- ── Shift Matching ───────────────────────────────────────────────────── -->
<div data-panel="match" hidden>
  <?php if ($shifts): ?>
  <section class="card">
    <h3><?= ic('star', 'red') ?> Shift Matching</h3>
    <p class="muted sm-t">AI matched for you</p>
    <?php foreach ($shifts as $s):
      [$pillCls, $pillLabel] = shiftPill($s['status']);
      $dayName = $dayLabels[(int)$s['day_of_week']] ?? '';
      $hasConflictOnShift = false;
      foreach ($conflicts as $con) {
          if ((int)$con['shift']['id'] === (int)$s['id']) { $hasConflictOnShift = true; break; }
      }
      $compatLabel = $hasConflictOnShift ? 'Conflict' : 'Compatible';
      $compatCls   = $hasConflictOnShift ? 'warn'     : 'ok';
    ?>
      <div class="shift">
        <?= ic('clock', 'red') ?>
        <div class="grow">
          <b><?= htmlspecialchars($s['shift_name']) ?></b>
          <p><?= htmlspecialchars(substr($s['start_time'],0,5)) ?> – <?= htmlspecialchars(substr($s['end_time'],0,5)) ?></p>
          <small class="muted"><?= htmlspecialchars($s['branch']) ?></small>
        </div>
        <div class="col end">
          <span class="pill <?= $compatCls ?>"><?= $compatLabel ?></span>
          <?php if ($s['status'] === 'available'): ?>
            <form method="post" action="schedule.php">
              <input type="hidden" name="action"   value="select_shift">
              <input type="hidden" name="shift_id" value="<?= (int)$s['id'] ?>">
              <button class="btn sm" type="submit">Select</button>
            </form>
          <?php else: ?>
            <span class="pill ok">Selected</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
  <?php else: ?>
  <section class="card">
    <p class="muted">No shifts available for matching yet.</p>
  </section>
  <?php endif; ?>

  <?php if ($conflicts): ?>
    <?php foreach ($conflicts as $con):
      $c = $con['class'];
      $s = $con['shift'];
      $dayName = $dayLabels[(int)$s['day_of_week']] ?? '';
    ?>
    <section class="card alert">
      <h3><?= ic('alert') ?> Schedule Conflict Detected</h3>
      <p>Your class on <?= htmlspecialchars($dayName) ?>
         (<?= htmlspecialchars(substr($c['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($c['end_time'],0,5)) ?>)
         overlaps with <?= htmlspecialchars($s['shift_name']) ?>
         (<?= htmlspecialchars(substr($s['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($s['end_time'],0,5)) ?>).</p>
      <div class="row gap">
        <button class="btn outline grow">View Details</button>
        <form method="post" action="schedule.php" style="flex:1">
          <input type="hidden" name="action"   value="swap">
          <input type="hidden" name="shift_id" value="<?= (int)$s['id'] ?>">
          <button class="btn grow" type="submit">Request Shift Swap</button>
        </form>
      </div>
    </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
