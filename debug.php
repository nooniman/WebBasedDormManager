<?php
/**
 * Debug file for Hostinger deployment
 * DELETE THIS FILE AFTER DEBUGGING!
 */

// Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Hostinger Debug Report</h1>";
echo "<pre>";

// 1. PHP Version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "\n";

// 2. Server Info
echo "<h2>2. Server Info</h2>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'not set') . "\n";

// 3. Check if key files exist
echo "<h2>3. File Check</h2>";
$files_to_check = [
    'config/environment.php',
    'config/database.php',
    'config/auth0_config.php',
    'includes/functions.php',
    'includes/auth0_functions.php',
    'vendor/autoload.php',
    'login.php',
    '.htaccess'
];

foreach ($files_to_check as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    echo "$file: " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
}

// 4. Try to load environment
echo "<h2>4. Environment Detection</h2>";
try {
    require_once __DIR__ . '/config/environment.php';
    echo "ENVIRONMENT: " . ENVIRONMENT . "\n";
    echo "BASE_URL: " . BASE_URL . "\n";
    echo "SITE_URL: " . SITE_URL . "\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    echo "Environment loaded: ✅ SUCCESS\n";
} catch (Exception $e) {
    echo "Environment Error: ❌ " . $e->getMessage() . "\n";
}

// 5. Try database connection
echo "<h2>5. Database Connection</h2>";
try {
    if (defined('DB_HOST')) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            echo "Database: ❌ Connection failed: " . $conn->connect_error . "\n";
        } else {
            echo "Database: ✅ Connected successfully\n";
            $conn->close();
        }
    } else {
        echo "Database: ❌ DB constants not defined\n";
    }
} catch (Exception $e) {
    echo "Database Error: ❌ " . $e->getMessage() . "\n";
}

// 6. Check Composer autoload
echo "<h2>6. Composer Autoload</h2>";
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "Composer Autoload: ✅ Loaded\n";
        
        // Check Auth0
        if (class_exists('Auth0\SDK\Auth0')) {
            echo "Auth0 SDK: ✅ Available\n";
        } else {
            echo "Auth0 SDK: ❌ Class not found\n";
        }
        
        // Check PayPal
        if (class_exists('PayPalCheckoutSdk\Core\PayPalHttpClient')) {
            echo "PayPal SDK: ✅ Available\n";
        } else {
            echo "PayPal SDK: ❌ Class not found\n";
        }
    } else {
        echo "Composer Autoload: ❌ vendor/autoload.php not found\n";
        echo "Run 'composer install' on the server or upload the vendor folder\n";
    }
} catch (Exception $e) {
    echo "Composer Error: ❌ " . $e->getMessage() . "\n";
}

// 7. Check .htaccess mod_rewrite
echo "<h2>7. Apache mod_rewrite</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "✅ Enabled" : "❌ Disabled") . "\n";
} else {
    echo "mod_rewrite: ⚠️ Cannot detect (CGI/FPM mode)\n";
}

// 8. Directory permissions
echo "<h2>8. Directory Permissions</h2>";
$dirs_to_check = ['uploads', 'profiles'];
foreach ($dirs_to_check as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        echo "$dir: " . (is_writable($path) ? "✅ Writable" : "❌ Not writable") . "\n";
    } else {
        echo "$dir: ❌ Directory not found\n";
    }
}

echo "</pre>";

echo "<h2>⚠️ IMPORTANT</h2>";
echo "<p style='color: red; font-weight: bold;'>DELETE THIS FILE (debug.php) AFTER DEBUGGING!</p>";
?>
