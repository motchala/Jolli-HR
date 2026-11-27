<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/icons.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · JolliHR Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page admin-<?= h($active) ?>">
<header class="admin-topbar">
  <div class="admin-brand">
    <div class="admin-logo">J</div>
    <div>
      <b>JolliHR</b>
      <small>Admin Panel</small>
    </div>
  </div>
  <nav>
    <a href="index.php"      class="<?= $active === 'dashboard'  ? 'on' : '' ?>">Dashboard</a>
    <a href="applicants.php" class="<?= $active === 'applicants' ? 'on' : '' ?>">Applicants</a>
    <a href="jobs.php"       class="<?= $active === 'jobs'       ? 'on' : '' ?>">Jobs</a>
    <a href="../logout.php"  class="logout">Logout</a>
  </nav>
</header>
<div class="admin-wrap">
  <main class="admin-main">