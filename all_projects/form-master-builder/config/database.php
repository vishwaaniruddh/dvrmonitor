<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'esurv2');
define('DB_USER', 'reporting');
define('DB_PASS', 'reporting');

// Create connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>