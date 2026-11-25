<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();
$uid = (int)$_SESSION['user']['id'];

// Apply: record an 'Applied' row for the logged-in user, then redirect (so a refresh doesn't resubmit).
// The unique key on (user_id, job_id) makes a repeat apply a harmless no-op.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $jobId = filter_var($_POST['job_id'] ?? '', FILTER_VALIDATE_INT);
  if ($jobId) {
    db()->prepare("INSERT IGNORE INTO applications (user_id, job_id, status) VALUES (?, ?, 'Applied')")
      ->execute([$uid, $jobId]);
  }
  header('Location: jobs.php');
  exit;
}

$title = 'Job Opportunities';
$active = 'jobs';
require 'includes/header.php';
$jobs = db()->query('SELECT id, title, branch, tags, hours, distance, match_pct FROM jobs ORDER BY id')->fetchAll();
$st = db()->prepare('SELECT job_id FROM applications WHERE user_id = ?');
$st->execute([$uid]);
$applied = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
?>
<div class="row gap"><label class="search"><?= ic('search') ?><input id="q" type="search" placeholder="Search jobs, location, or keywords…"></label><button class="icon-btn" aria-label="Filters"><?= ic('sliders') ?></button></div>
<div class="chips" data-chips>
  <button class="on" data-f="all">All</button><button data-f="part">Part-time</button><button data-f="near">Near Me</button><button data-f="student">Student Friendly</button>
</div>
<section class="banner">
  <h2>Grow Your Skills with Jollibee</h2>
  <p>Flexible work. Brighter future.</p>
</section>
<?php foreach ($jobs as $j):
  $tags = array_filter(array_map('trim', explode(',', $j['tags'])));
  // Filter chips: part-time / student friendly come from the row, "near" = within 3.5 km.
  $f = implode(' ', array_filter([
    stripos($j['hours'], 'Part-time') === 0 ? 'part' : '',
    in_array('Student Friendly', $tags, true) ? 'student' : '',
    (float)$j['distance'] <= 3.5 ? 'near' : '',
  ]));
  $isApplied = isset($applied[$j['id']]);
?>
  <article class="card job" data-tags="<?= h($f) ?>" data-text="<?= h(strtolower("{$j['title']} {$j['branch']} " . implode(' ', $tags))) ?>">
    <div class="row top"><span class="job-ic"><?= ic('store') ?></span>
      <div class="grow"><b class="big-t"><?= h($j['title']) ?></b>
        <p class="muted"><?= h($j['branch']) ?></p>
        <p class="meta"><?= ic('cal') ?> <?= h($j['hours']) ?></p>
        <p class="meta"><?= ic('pin') ?> <?= h($j['distance']) ?></p>
      </div>
      <span class="match"><?= (int)$j['match_pct'] ?>% Match</span>
    </div>
    <div class="row between end">
      <div class="tags"><?php foreach ($tags as $g) echo '<span>' . h($g) . '</span>'; ?></div>
      <?php if ($isApplied): ?>
        <button class="btn sm done" type="button" disabled>Applied</button>
      <?php else: ?>
        <button class="btn sm" type="submit" form="apply-<?= (int)$j['id'] ?>">Apply</button>
      <?php endif; ?>
    </div>
    <?php if (!$isApplied): ?>
      <form id="apply-<?= (int)$j['id'] ?>" method="post" action="jobs.php" hidden><input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>"></form>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php require 'includes/footer.php'; ?>
