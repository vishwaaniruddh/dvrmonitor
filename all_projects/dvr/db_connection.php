<?php
function get_database_connection() {
    try {
        $host = 'localhost';
        $dbname = 'esurv';
        $username = 'reporting';
        $password = 'reporting';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, $username, $password, $options);
        
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        throw new Exception("Database connection failed. Please check the error log for details.");
    }
}

// Function to log errors to a file
function log_error($message) {
    $log_file = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Create tables if they don't exist
try {
    $db = get_database_connection();
    
    // Create dvr_list table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS dvr_list (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(50) NOT NULL,
        port INT DEFAULT 80,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(50) NOT NULL,
        dvr_type VARCHAR(20) NOT NULL,
        location VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    
    // Create dvr_activity table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS dvr_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL,
        dvr_time DATETIME,
        login_time DATETIME,
        system_time DATETIME,
        total_cameras INT,
        storage_type VARCHAR(50),
        storage_status VARCHAR(50),
        storage_capacity VARCHAR(50),
        storage_free VARCHAR(50),
        recording_from DATETIME,
        recording_to DATETIME,
        camera_status JSON,
        dvr_type VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    
} catch (Exception $e) {
    error_log("Table Creation Error: " . $e->getMessage());
} 