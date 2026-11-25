<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
const DEMO_EMAIL = 'shamea@jollihr.test';
const DEMO_PASS  = 'shamea123';
function require_login()
{
  // Sessions without a user id (e.g. from before the demo account lived in the DB) must log in again.
  if (empty($_SESSION['user']['id'])) {
    unset($_SESSION['user']);
    header('Location: login.php');
    exit;
  }
}

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ordinal')) {
    function ordinal(int $n): string {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }
}

function require_admin(): void
{
    if (empty($_SESSION['user']['is_admin'])) {
        unset($_SESSION['user']);
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}
