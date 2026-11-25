<?php
// XAMPP defaults. Change here if your MySQL uses a different user/password.
// NOTE: DB_NAME must match the database created in database.sql.
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'jollihr_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Adjust if the project is served from a different subdirectory or vhost root.
define('BASE_PATH', '/Jolli-HR');   // No trailing slash.

function db_options()
{
  return [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ];
}

// Creates the database, tables and seed data if they don't exist yet,
// so the app works even if database.sql was never imported.
// database.sql is the single source of truth for the schema and seed rows.
function db_bootstrap()
{
  $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, db_options());
  $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
  $root->exec('USE `' . DB_NAME . '`');
  $sql = file_get_contents(__DIR__ . '/../database.sql');
  $sql = preg_replace('/^\s*--.*$/m', '', $sql); // drop whole-line comments
  foreach (explode(';', $sql) as $stmt) {
    $stmt = trim($stmt);
    // The database is created/selected above using DB_NAME, so skip those statements.
    if ($stmt === '' || preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $stmt)) continue;
    $root->exec($stmt);
  }
  return $root;
}

function db()
{
  static $pdo = null;
  if (!$pdo) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, db_options());
      // Database exists; make sure all the tables do too.
      $pdo->query('SELECT 1 FROM users, jobs, applications LIMIT 1');
    } catch (PDOException $e) {
      // 1049 = unknown database, 42S02 = table missing
      $missingDb = ($e->errorInfo[1] ?? 0) == 1049;
      $missingTable = ($e->getCode() === '42S02');
      if (!$missingDb && !$missingTable) throw $e;
      $pdo = db_bootstrap();
    }
  }
  return $pdo;
}
