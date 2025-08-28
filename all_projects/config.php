<?php
$host = 'localhost';
$db   = 'esurv2';
$user = 'reporting';
$pass = 'reporting';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = $con = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Function to get alert counts
function getAlertCounts($pdo) {
    $stmt = $pdo->query("SELECT alerttype, count(1) as count FROM `ai_alerts` GROUP BY alerttype");
    return $stmt->fetchAll();
}

// Function to get total alerts
function getTotalAlerts($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM `ai_alerts`");
    return $stmt->fetch()['total'];
}
?>