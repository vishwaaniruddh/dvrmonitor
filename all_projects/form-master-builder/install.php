<?php
require_once 'config/database.php';

try {
    // Create forms table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `forms` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `structure` LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create submissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `form_submissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `form_id` INT NOT NULL,
        `data` LONGTEXT NOT NULL,
        `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`form_id`) REFERENCES `forms`(`id`)
    )");

    echo "Database tables created successfully!";
} catch(PDOException $e) {
    die("Installation failed: " . $e->getMessage());
}
?>