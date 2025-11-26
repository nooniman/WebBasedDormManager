<?php
/**
 * Database Configuration File
 * 
 * Uses environment detection for automatic credential selection.
 */

// Include environment configuration
require_once __DIR__ . '/environment.php';

// Database credentials are now defined in environment.php
// DB_HOST, DB_USER, DB_PASS, DB_NAME

// Create database connection
function getDatabaseConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// Global database connection
$conn = getDatabaseConnection();
?>