<?php
require 'includes/auth.php';
require 'includes/icons.php';
require 'includes/db.php';

// Already logged in — no need to be here
if (!empty($_SESSION['user'])) {
    header('Location: home.php');
    exit;
}

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$err    = '';
$done   = false;

// ── Validate the token ────────────────────────────────────────────────────────
$resetRow = null;
if ($token !== '' && preg_match('/^[0-9a-f]{64}$/', $token)) {
    try {
        $st = db()->prepare('
            SELECT pr.id, pr.user_id, pr.expires_at, pr.used
            FROM password_resets pr
            WHERE pr.token = ?
            LIMIT 1
        ');
        $st->execute([$token]);
        $resetRow = $st->fetch();
    } catch (PDOException $e) {
        $err = 'Could not reach the database. Start MySQL first.';
    }
}

$tokenValid = $resetRow
    && !$resetRow['used']
    && new DateTime($resetRow['expires_at']) > new DateTime();

// ── POST: set new password ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm']  ?? '';

    if ($pw === '') {
        $err = 'Password is required.';
    } elseif (strlen($pw) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif (strlen($pw) > 72) {
        $err = 'Password must be 72 characters or less.';
    } elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $err = 'Password must include at least one letter and one number.';
    } elseif ($pw !== $pw2) {
        $err = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([$hash, $resetRow['user_id']]);
            // Mark token used
            db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
               ->execute([$resetRow['id']]);
            $done = true;
        } catch (PDOException $e) {
            $err = 'Could not update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#7a0014">
  <title>Reset Password · JolliHR</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="assets/js/device.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
  <div class="app login">
    <div class="login-top">
      <div class="brand center">
        <span class="logo"><?= ic('trend') ?></span>
        <div><b>JolliHR</b><small>Your Happy HR Partner</small></div>
      </div>
      <h1>Set New Password</h1>
      <p>Choose a password you'll remember.</p>
    </div>

    <div class="card login-card">
      <?php if ($done): ?>
        <!-- ── Success ── -->
        <div class="reset-ok">
          <?= ic('check') ?>
          <h2>Password Updated!</h2>
          <p class="muted">Your password has been changed. You can now log in.</p>
        </div>
        <div style="margin-top:20px">
          <a class="btn big" href="login.php">Log In</a>
        </div>

      <?php elseif (!$tokenValid): ?>
        <!-- ── Invalid / expired token ── -->
        <div class="reset-ok">
          <?= ic('alert') ?>
          <h2>Link Expired</h2>
          <p class="muted">
            This reset link is invalid or has already been used. Reset links expire after 1 hour.
          </p>
        </div>
        <?php if ($err): ?>
          <div class="form-err" style="margin-top:12px"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <div style="margin-top:20px">
          <a class="btn big" href="forgot.php">Request New Link</a>
        </div>
        <div class="divider"><span>Remembered it?</span></div>
        <a class="btn outline big" href="login.php">Back to Login</a>

      <?php else: ?>
        <!-- ── Reset form ── -->
        <h2>New Password</h2>
        <?php if ($err): ?>
          <div class="form-err"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" data-validate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <label class="field">
            <?= ic('lock') ?>
            <input type="password" name="password"
                   placeholder="New password"
                   minlength="8" maxlength="72"
                   pattern="(?=.*[A-Za-z])(?=.*\d).{8,72}"
                   title="8–72 characters with at least one letter and one number"
                   required autofocus>
            <?= pw_toggle() ?>
          </label>
          <small class="field-hint">At least 8 characters, with a letter and a number.</small>
          <label class="field" style="margin-top:8px">
            <?= ic('lock') ?>
            <input type="password" name="confirm"
                   placeholder="Confirm new password"
                   minlength="8" maxlength="72"
                   required>
            <?= pw_toggle() ?>
          </label>
          <button class="btn big" type="submit" style="margin-top:8px">Update Password</button>
        </form>
        <div class="divider"><span>Remembered it?</span></div>
        <a class="btn outline big" href="login.php">Back to Login</a>
      <?php endif; ?>
    </div>

    <footer class="login-foot">JolliHR – Your Happy HR Partner.</footer>
  </div>
  <script src="assets/js/app.js"></script>
</body>
</html>
