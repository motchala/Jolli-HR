<?php
$title  = 'Dashboard';
$active = 'dashboard';
require_once __DIR__ . '/../includes/db.php';
require __DIR__ . '/includes/admin_header.php';

try {
    $totalApplicants = db()->query('SELECT COUNT(DISTINCT user_id) FROM applications')->fetchColumn();
    $pendingDocs     = db()->query('SELECT COUNT(DISTINCT user_id) FROM user_documents WHERE verified = 0')->fetchColumn();
    $inTraining      = db()->query("SELECT COUNT(*) FROM applications WHERE status = 'Training'")->fetchColumn();
    $hired           = db()->query("SELECT COUNT(*) FROM applications WHERE status = 'Hired'")->fetchColumn();
    $recent = db()->query(
        'SELECT a.id, u.first_name, u.last_name, j.title AS job_title, a.status, a.applied_at
         FROM applications a
         JOIN users u ON u.id = a.user_id
         JOIN jobs  j ON j.id = a.job_id
         ORDER BY a.applied_at DESC, a.id DESC LIMIT 10'
    )->fetchAll();
    $dbErr = null;
} catch (PDOException $e) {
    error_log($e->getMessage());
    $dbErr = 'Database error. Please try again.';
}
?>

<div class="page-header">
  <h1>Dashboard</h1>
</div>

<?php if (!empty($dbErr)): ?>
  <div class="flash err"><?= h($dbErr) ?></div>
<?php else: ?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="num"><?= (int)$totalApplicants ?></div>
    <div class="lbl">Total Applicants</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= (int)$pendingDocs ?></div>
    <div class="lbl">Pending Docs</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= (int)$inTraining ?></div>
    <div class="lbl">In Training</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= (int)$hired ?></div>
    <div class="lbl">Hired</div>
  </div>
</div>

<h2>Recent Applications</h2>
<table class="admin-table">
  <thead>
    <tr><th>Applicant</th><th>Job</th><th>Status</th><th>Applied</th></tr>
  </thead>
  <tbody>
  <?php foreach ($recent as $r):
    $pill = match($r['status']) {
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
      <td><?= h($r['job_title']) ?></td>
      <td><span class="status-pill <?= $pill ?>"><?= h($r['status']) ?></span></td>
      <td><?= h((new DateTime($r['applied_at']))->format('M j, Y g:i a')) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$recent): ?>
    <tr><td colspan="4" style="color:var(--muted);text-align:center;padding:20px">No applications yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
