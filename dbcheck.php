<?php
// Temporary diagnostic — delete after fixing
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

echo '<pre>';
echo 'DB_HOST: ' . DB_HOST . "\n";
echo 'DB_NAME: ' . DB_NAME . "\n";
echo 'DB_USER: ' . DB_USER . "\n";
echo 'HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n\n";

try {
    $pdo = db();
    echo "✅ Connected OK\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n";
    foreach ($tables as $t) echo "  - $t\n";
    echo "\nUsers:\n";
    $users = $pdo->query("SELECT id, email, is_admin FROM users")->fetchAll();
    foreach ($users as $u) echo "  id={$u['id']} email={$u['email']} is_admin={$u['is_admin']}\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo '</pre>';
