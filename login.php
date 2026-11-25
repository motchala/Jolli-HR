<?php
require 'includes/auth.php';
require 'includes/icons.php';
require 'includes/db.php';
if (!empty($_SESSION['user'])) {
  header('Location: ' . (!empty($_SESSION['user']['is_admin']) ? 'admin/index.php' : 'home.php'));
  exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass = $_POST['password'] ?? '';
  try {
    $st = db()->prepare('SELECT id, first_name, last_name, password_hash, is_admin FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user'] = [
          'id'       => (int)$u['id'],
          'name'     => $u['first_name'] . ' ' . $u['last_name'],
          'first'    => $u['first_name'],
          'is_admin' => (bool)$u['is_admin'],
      ];
      header('Location: ' . ($u['is_admin'] ? 'admin/index.php' : 'home.php'));
      exit;
    }
    $err = 'Incorrect email or password. Try again.';
  } catch (PDOException $e) {
    $err = 'Could not reach the database. Start MySQL and import database.sql first.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in · JolliHR</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-body">
  <div class="app login">
    <div class="login-top">
      <div class="brand center"><span class="logo"><?= ic('trend') ?></span>
        <div><b>JolliHR</b><small>Your Happy HR Partner</small></div>
      </div>
      <h1>Welcome to JolliHR!</h1>
      <p>Your journey as a Jollibee working student starts here.</p>
    </div>
    <form class="card login-card" method="post" autocomplete="on">
      <h2>Student Login</h2>
      <?php if ($err): ?><div class="form-err"><?= $err ?></div><?php endif; ?>
      <label class="field"><?= ic('doc') ?><input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></label>
      <label class="field"><?= ic('user') ?><input type="password" name="password" placeholder="Password" required><button type="button" class="pw-toggle" data-pw>Show</button></label>
      <div class="row between"><label class="remember"><input type="checkbox"> Remember Me</label><a class="link" href="forgot.php">Forgot Password?</a></div>
      <button class="btn big" type="submit">Log In</button>
      <div class="divider"><span>Don't have an account yet?</span></div>
      <a class="btn outline big" href="signup.php">Create Account</a>
      <p class="hint">Demo: <?= DEMO_EMAIL ?> / <?= DEMO_PASS ?></p>
    </form>
    <footer class="login-foot">JolliHR – Your Happy HR Partner.</footer>
  </div>
  <script src="assets/js/app.js"></script>
</body>

</html>