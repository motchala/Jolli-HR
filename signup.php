<?php
require 'includes/auth.php';
require 'includes/icons.php';
require 'includes/db.php';
if (!empty($_SESSION['user'])) {
  header('Location: home.php');
  exit;
}
$err = '';
$errs = [];
$first = trim($_POST['first'] ?? '');
$last = trim($_POST['last'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
const NAME_RE = "/^\\p{L}[\\p{L} .'’-]*$/u";
function name_error($v, $label)
{
  $n = preg_match_all('/./su', $v);
  if ($v === '') return "$label is required.";
  if ($n < 2) return "$label must be at least 2 characters.";
  if ($n > 50) return "$label must be 50 characters or less.";
  if (!preg_match(NAME_RE, $v)) return "$label can only contain letters, spaces, hyphens and apostrophes.";
  return '';
}
function fe($k)
{
  global $errs;
  return isset($errs[$k]) ? '<small class="field-err">' . htmlspecialchars($errs[$k]) . '</small>' : '';
}
function bad($k)
{
  global $errs;
  return isset($errs[$k]) ? ' bad' : '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pw = $_POST['password'] ?? '';
  $pw2 = $_POST['confirm'] ?? '';
  if ($e = name_error($first, 'First name')) $errs['first'] = $e;
  if ($e = name_error($last, 'Last name')) $errs['last'] = $e;
  if ($email === '') $errs['email'] = 'Email address is required.';
  elseif (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs['email'] = 'Enter a valid email address (e.g. name@jollihr.com).';
  elseif (!preg_match('/(@jollihr\.com|\.test)$/', $email)) $errs['email'] = 'Email must end with @jollihr.com or .test';
  if ($pw === '') $errs['password'] = 'Password is required.';
  elseif (strlen($pw) < 8) $errs['password'] = 'Password must be at least 8 characters.';
  elseif (strlen($pw) > 72) $errs['password'] = 'Password must be 72 characters or less.';
  elseif (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $errs['password'] = 'Password must include at least one letter and one number.';
  if ($pw2 === '') $errs['confirm'] = 'Please confirm your password.';
  elseif ($pw !== $pw2) $errs['confirm'] = 'Passwords do not match.';
  if (!$errs && $email === DEMO_EMAIL) $errs['email'] = 'An account with this email already exists.';
  if (!$errs) {
    try {
      $st = db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
      $st->execute([$first, $last, $email, password_hash($pw, PASSWORD_DEFAULT)]);
      session_regenerate_id(true);
      $_SESSION['user'] = ['id' => (int)db()->lastInsertId(), 'name' => "$first $last", 'first' => $first];
      header('Location: home.php');
      exit;
    } catch (PDOException $e) {
      if ($e->getCode() === '23000') $errs['email'] = 'An account with this email already exists.';
      else $err = 'Could not reach the database. Start MySQL and import database.sql first.';
    }
  }
}
$namePat = "[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ .'’\\-]*";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account · JolliHR</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-body">
  <div class="app login">
    <div class="login-top">
      <div class="brand center"><span class="logo"><?= ic('trend') ?></span>
        <div><b>JolliHR</b><small>Your Happy HR Partner</small></div>
      </div>
      <h1>Join JolliHR!</h1>
      <p>Create your working student account.</p>
    </div>
    <form class="card login-card" method="post" autocomplete="on" data-validate>
      <h2>Create Account</h2>
      <?php if ($err): ?><div class="form-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <div class="field-row">
        <div><label class="field<?= bad('first') ?>"><input type="text" name="first" placeholder="First Name" minlength="2" maxlength="50" pattern="<?= htmlspecialchars($namePat) ?>" title="Letters, spaces, hyphens and apostrophes only" value="<?= htmlspecialchars($first) ?>" required></label><?= fe('first') ?></div>
        <div><label class="field<?= bad('last') ?>"><input type="text" name="last" placeholder="Last Name" minlength="2" maxlength="50" pattern="<?= htmlspecialchars($namePat) ?>" title="Letters, spaces, hyphens and apostrophes only" value="<?= htmlspecialchars($last) ?>" required></label><?= fe('last') ?></div>
      </div>
      <label class="field<?= bad('email') ?>"><?= ic('doc') ?><input type="email" name="email" placeholder="Email Address" maxlength="190" pattern=".+(@jollihr\.com|\.test)" title="Email must end with @jollihr.com or .test" value="<?= htmlspecialchars($email) ?>" required></label><?= fe('email') ?>
      <label class="field<?= bad('password') ?>"><?= ic('user') ?><input type="password" name="password" placeholder="Password" minlength="8" maxlength="72" pattern="(?=.*[A-Za-z])(?=.*\d).{8,72}" title="8–72 characters with at least one letter and one number" required><button type="button" class="pw-toggle" data-pw>Show</button></label><?= isset($errs['password']) ? fe('password') : '<small class="field-hint">At least 8 characters, with a letter and a number.</small>' ?>
      <label class="field<?= bad('confirm') ?>"><?= ic('user') ?><input type="password" name="confirm" placeholder="Confirm Password" minlength="8" maxlength="72" required><button type="button" class="pw-toggle" data-pw>Show</button></label><?= fe('confirm') ?>
      <button class="btn big" type="submit">Create Account</button>
      <div class="divider"><span>Already have an account?</span></div>
      <a class="btn outline big" href="login.php">Log In</a>
    </form>
    <footer class="login-foot">JolliHR – Your Happy HR Partner.</footer>
  </div>
  <script src="assets/js/app.js"></script>
</body>

</html>