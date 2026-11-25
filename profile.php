<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();

$uid  = (int)$_SESSION['user']['id'];
$errs = [];
$saved = false;

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Preference update ────────────────────────────────────────────────────
    if ($action === 'prefs') {
        $days  = array_filter(array_map('trim', (array)($_POST['days'] ?? [])));
        $shift = trim($_POST['preferred_shift'] ?? '');
        $maxH  = filter_var($_POST['max_hours'] ?? '', FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1, 'max_range' => 40]]);

        if (!$days)         $errs['days']  = 'Select at least one available day.';
        if ($shift === '')  $errs['shift'] = 'Please choose a preferred shift.';
        if ($shift !== '' && strlen($shift) > 50) $errs['shift'] = 'Shift name is too long.';
        if ($maxH === false) $errs['max_hours'] = 'Max hours must be a number between 1 and 40.';

        if (!$errs) {
            db()->prepare('INSERT INTO user_profiles
                (user_id, available_days, preferred_shift, max_hours_per_week)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  available_days     = VALUES(available_days),
                  preferred_shift    = VALUES(preferred_shift),
                  max_hours_per_week = VALUES(max_hours_per_week)')
              ->execute([$uid, implode(',', $days), $shift, (int)$maxH]);
            header('Location: profile.php?saved=1');
            exit;
        }
    }

    // ── Mood check-in ────────────────────────────────────────────────────────
    if ($action === 'mood') {
        $allowed = ['Great', 'Okay', 'Tired'];
        $mood    = $_POST['mood'] ?? '';
        if (in_array($mood, $allowed, true)) {
            [$isoY, $isoW] = explode('-', (new DateTime())->format('o-W'));
            db()->prepare('INSERT INTO mood_entries (user_id, mood, iso_year, iso_week)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE mood = VALUES(mood)')
              ->execute([$uid, $mood, (int)$isoY, (int)$isoW]);
        }
        header('Location: profile.php#well');
        exit;
    }
}

// ── GET: load page data ───────────────────────────────────────────────────────
$saved = isset($_GET['saved']);

// Real user name
$st = db()->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch();

// Profile row (may not exist for brand-new accounts)
$st = db()->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
$st->execute([$uid]);
$prof = $st->fetch() ?: [];

// Documents
$st = db()->prepare('SELECT document_name, verified FROM user_documents WHERE user_id = ? ORDER BY id');
$st->execute([$uid]);
$docs = $st->fetchAll();

// Current mood
[$isoY, $isoW] = explode('-', (new DateTime())->format('o-W'));
$st = db()->prepare('SELECT mood FROM mood_entries WHERE user_id = ? AND iso_year = ? AND iso_week = ?');
$st->execute([$uid, (int)$isoY, (int)$isoW]);
$currentMood = $st->fetchColumn() ?: '';

// Computed values with fallbacks
$firstName  = $user['first_name'] ?? $_SESSION['user']['first'];
$lastName   = $user['last_name']  ?? '';
$fullName   = trim("$firstName $lastName") ?: $_SESSION['user']['name'];
$initials   = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: 'U';
$university = $prof['university']         ?? 'Not set';
$programme  = $prof['programme']          ?? 'Not set';
$yearLevel  = $prof['year_level']         ?? '';
$yearLabel  = $yearLevel ? ordinal((int)$yearLevel) . ' Year' : 'Not set';
$availDays  = $prof['available_days']     ?? '';
$prefShift  = $prof['preferred_shift']    ?? 'Not set';
$maxHours   = $prof['max_hours_per_week'] ?? 20;


$title  = 'My Profile';
$active = 'profile';
require 'includes/header.php';
?>

<?php if ($saved): ?>
  <div class="flash ok"><?= ic('check') ?> Preferences saved.</div>
<?php endif; ?>

<section class="card profile-head">
  <div class="avatar"><?= htmlspecialchars($initials) ?></div>
  <div>
    <b class="big-t"><?= htmlspecialchars($fullName) ?></b>
    <p class="muted"><?= htmlspecialchars($programme) ?> | <?= htmlspecialchars($yearLabel) ?></p>
    <div class="tags">
      <span><?= htmlspecialchars($university) ?></span>
      <span class="vf"><?= ic('check') ?> Verified</span>
    </div>
  </div>
</section>

<div class="tabs" data-tabs>
  <button class="on" data-tab="info">Personal Info</button>
  <button data-tab="docs">Documents</button>
  <button data-tab="well">Well-being</button>
</div>

<!-- ── Personal Info ────────────────────────────────────────────────────── -->
<div data-panel="info">
  <section class="card">
    <div class="row between">
      <div>
        <h3><?= ic('bank', 'red') ?> University Information</h3>
        <b class="big-t"><?= htmlspecialchars($university) ?></b>
        <p class="muted"><?= htmlspecialchars($programme) ?> – <?= htmlspecialchars($yearLabel) ?></p>
      </div>
      <span class="pill ok">Connected</span>
    </div>
  </section>

  <section class="card">
    <h3><?= ic('cal', 'red') ?> Availability &amp; Preferences</h3>

    <?php if ($errs): ?>
      <div class="form-err">
        <?php foreach ($errs as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="profile.php#info">
      <input type="hidden" name="action" value="prefs">

      <div class="pref-edit">
        <label><b><?= ic('cal', 'red') ?> Available Days</b></label>
        <div class="day-checks">
          <?php
          $selDays = $availDays ? array_map('trim', explode(',', $availDays)) : [];
          foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d):
            $chk = in_array($d, $selDays, true) ? ' checked' : '';
          ?>
            <label class="day-chk"><input type="checkbox" name="days[]" value="<?= $d ?>"<?= $chk ?>><?= $d ?></label>
          <?php endforeach; ?>
        </div>
        <?php if (!$selDays && !$errs): ?>
          <p class="muted sm-t">No days set yet.</p>
        <?php endif; ?>
      </div>

      <div class="pref-edit">
        <label for="pref-shift"><b><?= ic('clock', 'red') ?> Preferred Shift</b></label>
        <select id="pref-shift" name="preferred_shift" class="field-sel">
          <?php foreach (['Morning','Afternoon','Evening','Flexible'] as $opt):
            $sel = ($prefShift === $opt || $prefShift === "$opt / Afternoon") ? ' selected' : '';
          ?>
            <option value="<?= $opt ?>"<?= $sel ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pref-edit">
        <label for="pref-hours"><b><?= ic('target', 'red') ?> Max Hours per Week</b></label>
        <input id="pref-hours" type="number" name="max_hours" min="1" max="40"
               value="<?= (int)$maxHours ?>" class="field-num">
      </div>

      <button class="btn" type="submit">Save Preferences</button>
    </form>

    <?php if ($availDays): ?>
      <div class="pref"><?= ic('cal', 'red') ?><div><b>Available Days</b><p class="muted"><?= htmlspecialchars($availDays) ?></p></div></div>
      <div class="pref"><?= ic('clock', 'red') ?><div><b>Preferred Shift</b><p class="muted"><?= htmlspecialchars($prefShift) ?></p></div></div>
      <div class="pref"><?= ic('target', 'red') ?><div><b>Max Hours per Week</b><p class="muted"><?= (int)$maxHours ?> hours</p></div></div>
    <?php endif; ?>
  </section>

  <a class="btn outline big" href="logout.php">Log Out</a>
</div>

<!-- ── Documents ────────────────────────────────────────────────────────── -->
<div data-panel="docs" hidden>
  <section class="card">
    <h3><?= ic('doc', 'red') ?> Document Requirements</h3>
    <?php if ($docs): ?>
      <?php foreach ($docs as $doc): ?>
        <div class="doc">
          <?= ic('doc', 'red') ?>
          <span class="grow"><?= htmlspecialchars($doc['document_name']) ?></span>
          <?php if (!$doc['verified']): ?>
            <button class="up" aria-label="Upload"><?= ic('up') ?></button>
          <?php endif; ?>
          <span class="pill <?= $doc['verified'] ? 'ok' : 'warn' ?>">
            <?= $doc['verified'] ? 'Verified' : 'Pending' ?>
          </span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="muted">No documents on record.</p>
    <?php endif; ?>
  </section>
</div>

<!-- ── Well-being ───────────────────────────────────────────────────────── -->
<div data-panel="well" hidden>
  <section class="card">
    <h3><?= ic('heart', 'red') ?> Weekly Check-in</h3>
    <p class="muted">How are you feeling this week?</p>
    <form method="post" action="profile.php" id="mood-form">
      <input type="hidden" name="action" value="mood">
      <input type="hidden" name="mood" id="mood-val" value="">
      <div class="mood" data-mood>
        <?php foreach (['Great', 'Okay', 'Tired'] as $opt):
          $on = ($currentMood === $opt) ? ' class="on"' : '';
        ?>
          <button type="button"<?= $on ?>
            onclick="document.getElementById('mood-val').value='<?= $opt ?>';document.getElementById('mood-form').submit()">
            <?= htmlspecialchars($opt) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </form>
    <p class="muted sm-t">
      You've worked <?= (int)$maxHours ?> of your <?= (int)$maxHours ?>-hour weekly limit.
    </p>
  </section>
</div>

<?php require 'includes/footer.php'; ?>
