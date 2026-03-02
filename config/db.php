<?php
/**
 * Database Configuration
 * PDO-based MySQL connection
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'bot_tele');
define('DB_USER', 'root');
define('DB_PASS', 'ServBay.dev');

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Set Vietnam timezone (UTC+7)
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    
    // Set MySQL timezone to Vietnam time
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    // Log error (in production, don't expose error details)
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

/**
 * Helper function to get database connection
 * @return PDO
 */
function getDB() {
    global $pdo;
    return $pdo;
}
