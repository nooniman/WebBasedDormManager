<?php
/**
 * Database Installation Script
 * 
 * Run this file once to set up the database automatically.
 * Access: http://localhost/dormitory-management-system/database/install.php
 */

// Include database configuration
require_once '../config/database.php';

// Read the SQL schema file
$schema_file = __DIR__ . '/schema.sql';

if (!file_exists($schema_file)) {
    die("Error: Schema file not found!");
}

$sql = file_get_contents($schema_file);

// Split SQL into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Database Installation</h1>";

// Close existing connection
if (isset($conn)) {
    $conn->close();
}

// Connect without selecting database first
$install_conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($install_conn->connect_error) {
    echo "<p class='error'>Connection failed: " . $install_conn->connect_error . "</p>";
    echo "</body></html>";
    exit;
}

echo "<p class='info'>Connected to MySQL server successfully!</p>";

// Execute each statement
$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty(trim($statement))) continue;
    
    if ($install_conn->query($statement) === TRUE) {
        $success_count++;
    } else {
        $error_count++;
        echo "<p class='error'>Error executing statement: " . $install_conn->error . "</p>";
        echo "<pre>" . htmlspecialchars(substr($statement, 0, 200)) . "...</pre>";
    }
}

echo "<h2>Installation Summary</h2>";
echo "<p class='success'>Successfully executed: $success_count statements</p>";

if ($error_count > 0) {
    echo "<p class='error'>Failed statements: $error_count</p>";
} else {
    echo "<p class='success'>✓ Database installed successfully!</p>";
    echo "<h3>Default Login Credentials:</h3>";
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>Administrator:</strong></p>";
    echo "<p>Email: admin@dormitory.com<br>Password: Admin123!</p>";
    echo "</div>";
    echo "<div style='background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>Sample Tenant:</strong></p>";
    echo "<p>Email: tenant@example.com<br>Password: Tenant123!</p>";
    echo "</div>";
    echo "<p style='color: red;'><strong>Important:</strong> Change these passwords immediately after first login!</p>";
    echo "<p><a href='../login.php' style='display: inline-block; background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
    // Delete this installation file for security
    echo "<hr>";
    echo "<p class='info'>For security reasons, you should delete this installation file:</p>";
    echo "<code>database/install.php</code>";
}

$install_conn->close();

echo "</body></html>";
?>
