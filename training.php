<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();

$uid = (int)$_SESSION['user']['id'];

// ── POST: mark a module complete ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    $moduleId = filter_var($_POST['module_id'] ?? '', FILTER_VALIDATE_INT);
    if ($moduleId) {
        // Confirm the module exists before writing
        $chk = db()->prepare('SELECT id FROM training_modules WHERE id = ?');
        $chk->execute([$moduleId]);
        if ($chk->fetch()) {
            db()->prepare('INSERT INTO user_training (user_id, module_id, status, completed_at)
                VALUES (?, ?, "completed", NOW())
                ON DUPLICATE KEY UPDATE status = "completed", completed_at = NOW()')
              ->execute([$uid, $moduleId]);
        }
    }
    header('Location: training.php');
    exit;
}

// ── GET: load modules + this user's progress ──────────────────────────────────
$st = db()->prepare('
    SELECT m.id, m.name, m.description, m.display_order,
           COALESCE(ut.status, "not_started") AS status,
           ut.completed_at
    FROM training_modules m
    LEFT JOIN user_training ut ON ut.module_id = m.id AND ut.user_id = ?
    ORDER BY m.display_order
');
$st->execute([$uid]);
$modules = $st->fetchAll();

$total     = count($modules);
$completed = count(array_filter($modules, fn($m) => $m['status'] === 'completed'));
$pct       = $total ? round($completed / $total * 100) : 0;

// Pill class + label map
function statusPill(string $s): array {
    return match($s) {
        'completed'   => ['ok',   'Completed'],
        'in_progress' => ['warn', 'In Progress'],
        default       => ['gray', 'Not Started'],
    };
}

$title  = 'Training & Development';
$active = 'training';
require 'includes/header.php';
?>

<div class="seg" data-tabs>
  <button class="on" data-tab="mine">My Training</button>
  <button data-tab="certs">Certificates</button>
</div>

<!-- ── My Training ──────────────────────────────────────────────────────── -->
<div data-panel="mine">
  <section class="card">
    <h3><?= ic('target', 'red') ?> Pre-Employment Training</h3>
    <p class="muted">Complete these modules before your first shift.</p>

    <div class="row between prog">
      <b><?= $completed ?>/<?= $total ?> Completed</b>
      <b><?= $pct ?>%</b>
    </div>
    <div class="bar"><i style="width:<?= $pct ?>%"></i></div>

    <?php foreach ($modules as $m):
      [$pillCls, $pillLabel] = statusPill($m['status']);
    ?>
      <div class="module">
        <span class="mod-ic"><?= ic('book') ?></span>
        <div class="grow">
          <b><?= htmlspecialchars($m['name']) ?></b>
          <p class="muted"><?= htmlspecialchars($m['description']) ?></p>
        </div>
        <span class="pill <?= $pillCls ?>"><?= $pillLabel ?></span>
        <?php if ($m['status'] !== 'completed'): ?>
          <form method="post" action="training.php" style="display:inline">
            <input type="hidden" name="action"    value="complete">
            <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
            <button class="btn sm" type="submit">Mark Complete</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h3><?= ic('doc', 'red') ?> Training Assessment</h3>
    <div class="row between">
      <div>
        <b class="big-t">Customer Service Quiz</b>
        <p class="muted">Score: 88%</p>
      </div>
      <span class="pill ok">Passed</span>
    </div>
  </section>
</div>

<!-- ── Certificates ─────────────────────────────────────────────────────── -->
<div data-panel="certs" hidden>
  <?php
  $certModules = array_filter($modules, fn($m) => $m['status'] === 'completed');
  if ($certModules):
    foreach ($certModules as $m):
  ?>
    <section class="card cert">
      <span class="cert-ic"><?= ic('award') ?></span>
      <div class="grow">
        <b class="big-t"><?= htmlspecialchars($m['name']) ?> Certificate</b>
        <p class="muted sm-t">Issued by JolliHR</p>
      </div>
      <span class="tick"><?= ic('check') ?></span>
    </section>
  <?php
    endforeach;
  else:
  ?>
    <section class="card">
      <p class="muted">Complete a training module to earn your first certificate.</p>
    </section>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
