<?php
require 'includes/auth.php';
require 'includes/icons.php';
require 'includes/db.php';

// Already logged in — no need to be here
if (!empty($_SESSION['user'])) {
    header('Location: home.php');
    exit;
}

$err     = '';
$resetUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($email === '') {
        $err = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Enter a valid email address.';
    } else {
        try {
            $st = db()->prepare('SELECT id FROM users WHERE email = ?');
            $st->execute([$email]);
            $user = $st->fetch();

            if ($user) {
                // Expire any existing unused tokens for this user
                db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
                   ->execute([$user['id']]);

                // Generate a secure random token
                $token     = bin2hex(random_bytes(32)); // 64 hex chars
                $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

                db()->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
                   ->execute([$user['id'], $token, $expiresAt]);

                // Build the reset URL — shown on-screen since there is no mail server
                $base     = rtrim(BASE_PATH, '/');
                $resetUrl = "http://{$_SERVER['HTTP_HOST']}{$base}/reset.php?token={$token}";
            }
            // If email not found we still show the same "check your inbox" message
            // to avoid leaking which addresses are registered.
        } catch (PDOException $e) {
            $err = 'Could not reach the database. Start MySQL first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password · JolliHR</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
  <div class="app login">
    <div class="login-top">
      <div class="brand center">
        <span class="logo"><?= ic('trend') ?></span>
        <div><b>JolliHR</b><small>Your Happy HR Partner</small></div>
      </div>
      <h1>Reset Password</h1>
      <p>Enter your email and we'll generate a reset link.</p>
    </div>

    <div class="card login-card">
      <?php if ($resetUrl): ?>
        <!-- Success state -->
        <div class="reset-ok">
          <?= ic('check') ?>
          <h2>Link Ready</h2>
          <p class="muted">Copy the link below and open it to set a new password. It expires in <b>1 hour</b>.</p>
        </div>
        <div class="reset-link-box">
          <input type="text" id="reset-link" value="<?= htmlspecialchars($resetUrl) ?>" readonly>
          <button class="btn sm" onclick="copyLink()">Copy</button>
        </div>
        <p class="hint" id="copy-msg"></p>
        <div style="margin-top:16px">
          <a class="btn outline big" href="<?= htmlspecialchars($resetUrl) ?>">Open Link Now</a>
        </div>
      <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$err): ?>
        <!-- Email not found — same message to avoid enumeration -->
        <div class="reset-ok">
          <?= ic('check') ?>
          <h2>Check Your Email</h2>
          <p class="muted">If that address is registered, a reset link has been sent.</p>
        </div>
        <div style="margin-top:16px">
          <a class="btn outline big" href="login.php">Back to Login</a>
        </div>
      <?php else: ?>
        <!-- Form state -->
        <h2>Forgot Password?</h2>
        <?php if ($err): ?>
          <div class="form-err"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
          <label class="field">
            <?= ic('doc') ?>
            <input type="email" name="email"
                   placeholder="Your registered email address"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
          </label>
          <button class="btn big" type="submit">Send Reset Link</button>
        </form>
        <div class="divider"><span>Remembered it?</span></div>
        <a class="btn outline big" href="login.php">Back to Login</a>
      <?php endif; ?>
    </div>

    <footer class="login-foot">JolliHR – Your Happy HR Partner.</footer>
  </div>
  <script>
    function copyLink() {
      var el = document.getElementById('reset-link');
      el.select(); el.setSelectionRange(0, 999);
      navigator.clipboard.writeText(el.value).then(function() {
        document.getElementById('copy-msg').textContent = 'Copied!';
      }).catch(function() {
        document.execCommand('copy');
        document.getElementById('copy-msg').textContent = 'Copied!';
      });
    }
  </script>
</body>
</html>
