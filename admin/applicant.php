<?php
$title  = 'Applicant Detail';
$active = 'applicants';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$appId = (int)($_GET['id'] ?? 0);
if (!$appId) { header('Location: applicants.php'); exit; }

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stA = db()->prepare('SELECT id, user_id, status FROM applications WHERE id = ?');
    $stA->execute([$appId]);
    $appRow = $stA->fetch();
    if (!$appRow) { header('Location: applicants.php'); exit; }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'advance_status') {
            $pipeline = ['Applied', 'Docs Verified', 'Training', 'Hired'];
            $idx = array_search($appRow['status'], $pipeline, true);
            if ($idx === false || $idx >= count($pipeline) - 1) {
                $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Status cannot be advanced further.'];
            } else {
                $next = $pipeline[$idx + 1];
                db()->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$next, $appId]);
                $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Status updated to ' . $next . '.'];
            }
        } elseif ($action === 'reject') {
            db()->prepare("UPDATE applications SET status = 'Rejected' WHERE id = ?")->execute([$appId]);
            $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Application rejected.'];
        } elseif ($action === 'toggle_doc') {
            $docId = filter_var($_POST['doc_id'] ?? '', FILTER_VALIDATE_INT);
            if ($docId) {
                db()->prepare('UPDATE user_documents SET verified = 1 - verified WHERE id = ? AND user_id = ?')
                   ->execute([$docId, $appRow['user_id']]);
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

// ── GET ───────────────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

require __DIR__ . '/includes/admin_header.php';

try {
    $stA = db()->prepare(
        'SELECT a.id, a.user_id, a.status, a.applied_at,
                u.first_name, u.last_name, u.email, j.title AS job_title
         FROM applications a
         JOIN users u ON u.id = a.user_id
         JOIN jobs  j ON j.id = a.job_id
         WHERE a.id = ?'
    );
    $stA->execute([$appId]);
    $app = $stA->fetch();
    if (!$app) { header('Location: applicants.php'); exit; }

    $stP = db()->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
    $stP->execute([$app['user_id']]);
    $profile = $stP->fetch() ?: [];

    $stD = db()->prepare('SELECT id, document_name, verified FROM user_documents WHERE user_id = ? ORDER BY id');
    $stD->execute([$app['user_id']]);
    $docs = $stD->fetchAll();
    $dbErr = null;
} catch (PDOException $e) {
    error_log($e->getMessage());
    $dbErr = 'Database error.';
    $app = null; $profile = []; $docs = [];
}

$pipeline = ['Applied', 'Docs Verified', 'Training', 'Hired'];
$curIdx   = $app ? array_search($app['status'], $pipeline, true) : false;
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>
<?php if ($dbErr): ?>
  <div class="flash err"><?= h($dbErr) ?></div>
<?php elseif ($app): ?>

<div class="page-header">
  <h1><?= h($app['first_name'] . ' ' . $app['last_name']) ?></h1>
  <a href="applicants.php" class="btn outline sm">← Back</a>
</div>

<!-- Pipeline stepper -->
<div class="detail-card">
  <h3>Application Pipeline</h3>
  <div class="pipeline">
    <?php foreach ($pipeline as $i => $stage):
      if ($app['status'] === 'Rejected') {
        $cls = $i === 0 ? 'done' : '';
      } elseif ($curIdx === false) {
        $cls = '';
      } elseif ($i < $curIdx || ($i === $curIdx && $curIdx === count($pipeline) - 1)) {
        $cls = 'done';
      } elseif ($i === $curIdx) {
        $cls = 'now';
      } else {
        $cls = '';
      }
    ?>
      <div class="pipeline-step <?= $cls ?>">
        <div class="dot"><?= $cls === 'done' ? '✓' : ($i + 1) ?></div>
        <b><?= h($stage) ?></b>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($app['status'] === 'Rejected'): ?>
    <span class="status-pill red" style="margin-top:14px;display:inline-block">Rejected</span>
  <?php endif; ?>
</div>

<!-- Actions -->
<?php if ($app['status'] !== 'Rejected' && $app['status'] !== 'Hired'): ?>
<div class="detail-card">
  <h3>Actions</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php if ($curIdx !== false && $curIdx < count($pipeline) - 1): ?>
      <form method="post" action="applicant.php?id=<?= $appId ?>">
        <input type="hidden" name="action" value="advance_status">
        <button class="btn primary sm" type="submit">
          Advance to <?= h($pipeline[$curIdx + 1]) ?> →
        </button>
      </form>
    <?php endif; ?>
    <form method="post" action="applicant.php?id=<?= $appId ?>">
      <input type="hidden" name="action" value="reject">
      <button class="btn danger sm" type="submit"
              onclick="return confirm('Reject this application?')">Reject</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Applicant info -->
<div class="detail-card">
  <h3>Applicant Info</h3>
  <div class="detail-row"><span class="lbl">Job Applied</span><span class="val"><?= h($app['job_title']) ?></span></div>
  <div class="detail-row"><span class="lbl">Email</span><span class="val"><?= h($app['email']) ?></span></div>
  <div class="detail-row"><span class="lbl">Applied</span><span class="val"><?= h((new DateTime($app['applied_at']))->format('M j, Y g:i a')) ?></span></div>
  <div class="detail-row"><span class="lbl">University</span><span class="val"><?= h($profile['university'] ?? '—') ?></span></div>
  <div class="detail-row"><span class="lbl">Programme</span><span class="val"><?= h($profile['programme'] ?? '—') ?></span></div>
  <div class="detail-row"><span class="lbl">Year Level</span><span class="val"><?= h(($profile['year_level'] ?? '') ? ordinal((int)$profile['year_level']) . ' Year' : '—') ?></span></div>
  <div class="detail-row"><span class="lbl">Available Days</span><span class="val"><?= h($profile['available_days'] ?? '—') ?></span></div>
  <div class="detail-row"><span class="lbl">Preferred Shift</span><span class="val"><?= h($profile['preferred_shift'] ?? '—') ?></span></div>
  <div class="detail-row"><span class="lbl">Max Hours/Week</span><span class="val"><?= h(($profile['max_hours_per_week'] ?? '') ?: '—') ?></span></div>
</div>

<!-- Documents -->
<div class="detail-card">
  <h3>Documents</h3>
  <?php if ($docs): ?>
    <?php foreach ($docs as $doc): ?>
      <div class="doc-row">
        <span class="doc-name"><?= h($doc['document_name']) ?></span>
        <span class="status-pill <?= $doc['verified'] ? 'ok' : 'gray' ?>">
          <?= $doc['verified'] ? 'Verified' : 'Pending' ?>
        </span>
        <form method="post" action="applicant.php?id=<?= $appId ?>">
          <input type="hidden" name="action" value="toggle_doc">
          <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
          <button class="btn <?= $doc['verified'] ? 'outline' : 'primary' ?> sm" type="submit">
            <?= $doc['verified'] ? 'Unverify' : 'Verify' ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:var(--muted);font-size:14px">No documents on file.</p>
  <?php endif; ?>
</div>

<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
