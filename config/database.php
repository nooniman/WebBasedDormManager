<?php
/**
 * Database Configuration File
 * 
 * This file contains database connection settings.
 * DO NOT commit this file to version control with actual credentials.
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dormitory_db');

// Create database connection
function getDatabaseConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset to utf8mb4 for proper character encoding
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        // Log error securely (don't expose to users)
        error_log($e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// Global database connection
$conn = getDatabaseConnection();
?>
