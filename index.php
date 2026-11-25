<?php require 'includes/auth.php';
header('Location: ' . (empty($_SESSION['user']) ? 'login.php' : 'home.php'));
