<?php $title = 'Home';
$active = 'home';
$home   = true;
require 'includes/header.php';
require_once 'includes/db.php';

$uid = (int)$_SESSION['user']['id'];

// ── Application status stepper ───────────────────────────────────────────────
$stages = ['Applied', 'Docs Verified', 'Training', 'Hired'];
$st = db()->prepare('SELECT status, applied_at FROM applications WHERE user_id = ? ORDER BY applied_at DESC, id DESC LIMIT 1');
$st->execute([$uid]);
$app = $st->fetch();
$cur       = $app ? array_search($app['status'], $stages, true) : false;
$appliedOn = $app ? (new DateTime($app['applied_at']))->format('M j') : '';

// ── Home schedule widget: real class + work data for the current week ─────────
// Day indices: 0=Mon … 6=Sun. We show Mon–Fri (0–4) on home, same as before.
$st = db()->prepare('SELECT day_of_week FROM class_slots WHERE user_id = ?');
$st->execute([$uid]);
$classDays = array_flip($st->fetchAll(PDO::FETCH_COLUMN));

$st = db()->prepare('SELECT day_of_week FROM work_shifts WHERE user_id = ?');
$st->execute([$uid]);
$shiftDays = array_flip($st->fetchAll(PDO::FETCH_COLUMN));

// Conflict days: a day that has both a class slot and a work shift that overlap
$st = db()->prepare('
    SELECT DISTINCT cs.day_of_week
    FROM class_slots cs
    JOIN work_shifts ws ON ws.user_id = cs.user_id
                       AND ws.day_of_week = cs.day_of_week
                       AND ws.start_time  < cs.end_time
                       AND ws.end_time    > cs.start_time
    WHERE cs.user_id = ?
');
$st->execute([$uid]);
$conflictDays = array_flip($st->fetchAll(PDO::FETCH_COLUMN));

// Map day index → dot class
function homeDotClass(int $d, array $classDays, array $shiftDays, array $conflictDays): string {
    if (isset($conflictDays[$d])) return 'conflict';
    if (isset($shiftDays[$d]))    return 'work';
    if (isset($classDays[$d]))    return 'class';
    return 'free';
}

// Day number to show in the cell: use the actual ISO date for the current week
$monday    = new DateTime();
$monday->modify('Monday this week');
$weekCells = [];
$dayNames  = ['Mon','Tue','Wed','Thu','Fri'];
for ($i = 0; $i < 5; $i++) {
    $date = clone $monday;
    $date->modify("+{$i} days");
    $weekCells[] = [
        'label' => $dayNames[$i],
        'num'   => (int)$date->format('j'),
        'dot'   => homeDotClass($i, $classDays, $shiftDays, $conflictDays),
    ];
}

// ── Recommended job: highest match_pct the user hasn't applied to yet ────────
$st = db()->prepare('
    SELECT j.id, j.title, j.branch, j.tags, j.match_pct
    FROM jobs j
    WHERE j.id NOT IN (
        SELECT job_id FROM applications WHERE user_id = ?
    )
    ORDER BY j.match_pct DESC
    LIMIT 1
');
$st->execute([$uid]);
$recJob = $st->fetch();
?>

<section class="hero">
  <h2>Hi, <?= htmlspecialchars($_SESSION['user']['first']) ?>!</h2>
  <p>Keep going! Your goals are within reach.</p>
</section>

<!-- Application status -->
<section class="card">
  <h3><?= ic('doc', 'red') ?> Application Status</h3>
  <ol class="steps">
    <?php foreach ($stages as $i => $label):
      if ($cur === false) {
        $cls = '';
        $sub = $i === 0 ? 'Not yet applied' : 'Pending';
      } elseif ($i < $cur || ($i === $cur && $cur === count($stages) - 1)) {
        $cls = 'done';
        $sub = '&nbsp;';
      } elseif ($i === $cur) {
        $cls = 'now';
        $sub = 'In Progress';
      } else {
        $cls = '';
        $sub = 'Pending';
      }
      if ($i === 0 && $app) $sub = htmlspecialchars($appliedOn);
    ?>
      <li<?= $cls ? " class=\"$cls\"" : '' ?>>
        <i><?= $cls === 'done' ? ic('check') : $i + 1 ?></i>
        <b><?= $label ?></b>
        <small><?= $sub ?></small>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<!-- University verification -->
<section class="card">
  <h3><?= ic('bank', 'red') ?> University Verification</h3>
  <a class="uni-row" href="profile.php">
    <?= ic('bank') ?>
    <div>
      <b>PUP Biñan Campus</b>
      <span class="ok"><?= ic('check') ?> Connected</span>
    </div>
    <em>›</em>
  </a>
</section>

<!-- Recommended job (real data) -->
<section class="card">
  <div class="row between">
    <h3><?= ic('star', 'red') ?> Recommended for You</h3>
    <a class="link" href="jobs.php">View All</a>
  </div>
  <?php if ($recJob):
    $tags = array_filter(array_map('trim', explode(',', $recJob['tags'])));
    $pct  = (int)$recJob['match_pct'];
    // Ring colour: green ≥80, amber 60–79, red <60
    $ringColor = $pct >= 80 ? 'var(--green)' : ($pct >= 60 ? 'var(--amber)' : 'var(--red)');
  ?>
    <div class="row between rec">
      <div>
        <b class="big-t"><?= htmlspecialchars($recJob['title']) ?></b>
        <p class="muted"><?= htmlspecialchars($recJob['branch']) ?></p>
        <div class="tags">
          <?php foreach ($tags as $tag): ?>
            <span><?= htmlspecialchars($tag) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="ring" style="background:conic-gradient(<?= $ringColor ?> <?= $pct ?>%,#e3efe6 0)">
        <span style="color:<?= $ringColor ?>"><?= $pct ?>%</span>
      </div>
    </div>
    <div style="margin-top:12px">
      <a class="btn sm" href="jobs.php">Apply Now</a>
    </div>
  <?php else: ?>
    <p class="muted">You've applied to all available jobs. Check back soon!</p>
  <?php endif; ?>
</section>

<!-- Schedule widget (real data) -->
<section class="card">
  <div class="row between">
    <h3><?= ic('cal', 'red') ?> My Schedule (This Week)</h3>
    <a class="link" href="schedule.php">View Calendar</a>
  </div>
  <?php if (!$classDays && !$shiftDays): ?>
    <p class="muted sm-t">No schedule set yet. <a class="link" href="schedule.php">Add your classes</a>.</p>
  <?php else: ?>
    <div class="week">
      <?php foreach ($weekCells as $cell): ?>
        <div>
          <small><?= $cell['label'] ?></small>
          <b><?= $cell['num'] ?></b>
          <i class="dot <?= $cell['dot'] ?>"></i>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($conflictDays): ?>
      <p class="muted sm-t" style="margin-top:8px;color:#e0141f">
        <?= ic('alert') ?> Conflict detected — <a class="link" href="schedule.php">view details</a>.
      </p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
