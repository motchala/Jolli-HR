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

<div class="page-header">
  <h1>Applicants</h1>
</div>

<!-- Status filter chips -->
<div class="filter-row">
  <a href="applicants.php" class="<?= $filter === '' ? 'on' : '' ?>">All</a>
  <?php foreach ($allowed as $s): ?>
    <a href="applicants.php?status=<?= urlencode($s) ?>" class="<?= $filter === $s ? 'on' : '' ?>"><?= h($s) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!empty($dbErr)): ?>
  <div class="flash err"><?= h($dbErr) ?></div>
<?php else: ?>

<table class="admin-table">
  <thead>
    <tr><th>Applicant</th><th>Email</th><th>Job</th><th>Status</th><th>Applied</th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r):
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
      <td><?= h($r['email']) ?></td>
      <td><?= h($r['title']) ?></td>
      <td><span class="status-pill <?= $pill ?>"><?= h($r['status']) ?></span></td>
      <td><?= h((new DateTime($r['applied_at']))->format('M j, Y')) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="5" style="color:var(--muted);text-align:center;padding:20px">No applications found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
