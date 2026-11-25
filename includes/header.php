<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';
require_login();
// expects: $title, $active, optional $home=true
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> · JolliHR</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
  <div class="app">
    <?php if (!empty($home)): ?>
      <header class="topbar">
        <div class="brand"><span class="logo"><?= ic('trend') ?></span>
          <div><b>JolliHR</b><small>Your Happy HR Partner</small></div>
        </div>
        <div class="top-actions"><a class="bell" href="#" aria-label="Notifications"><?= ic('bell') ?><i></i></a><a class="avatar sm" href="profile.php" aria-label="Profile">A</a></div>
      </header>
    <?php else: ?>
      <header class="pagebar"><a href="home.php" aria-label="Back"><?= ic('back') ?></a>
        <h1><?= htmlspecialchars($title) ?></h1>
      </header>
    <?php endif; ?>
    <main>