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
    'config/paypal_config.php',
    'includes/functions.php',
    'includes/auth0_functions.php',
    'includes/paypal_functions.php',
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
    echo "BASE_URL: '" . BASE_URL . "'\n";
    echo "SITE_URL: " . SITE_URL . "\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    echo "PAYPAL_RETURN_URL: " . (defined('PAYPAL_RETURN_URL') ? PAYPAL_RETURN_URL : 'NOT DEFINED') . "\n";
    echo "PAYPAL_CANCEL_URL: " . (defined('PAYPAL_CANCEL_URL') ? PAYPAL_CANCEL_URL : 'NOT DEFINED') . "\n";
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
            
            // Check paypal_transactions table
            $result = $conn->query("SHOW TABLES LIKE 'paypal_transactions'");
            echo "paypal_transactions table: " . ($result->num_rows > 0 ? "✅ EXISTS" : "❌ MISSING") . "\n";
            
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
        
        // Check PayPal - try multiple class names
        $paypal_classes = [
            'PayPalCheckoutSdk\Core\PayPalHttpClient',
            'PayPalCheckoutSdk\Orders\OrdersCreateRequest',
            'PayPalHttp\HttpClient'
        ];
        
        foreach ($paypal_classes as $class) {
            echo "$class: " . (class_exists($class) ? "✅ Available" : "❌ Not found") . "\n";
        }
        
        // Check vendor/paypal folder
        $paypal_path = __DIR__ . '/vendor/paypal';
        echo "vendor/paypal folder: " . (is_dir($paypal_path) ? "✅ EXISTS" : "❌ MISSING") . "\n";
        
        if (is_dir($paypal_path)) {
            echo "Contents: " . implode(", ", scandir($paypal_path)) . "\n";
        }
        
    } else {
        echo "Composer Autoload: ❌ vendor/autoload.php not found\n";
    }
} catch (Exception $e) {
    echo "Composer Error: ❌ " . $e->getMessage() . "\n";
}

// 7. Try loading PayPal config
echo "<h2>7. PayPal Config</h2>";
try {
    if (file_exists(__DIR__ . '/config/paypal_config.php')) {
        $paypal_config = require __DIR__ . '/config/paypal_config.php';
        echo "PayPal Config: ✅ Loaded\n";
        echo "Mode: " . ($paypal_config['mode'] ?? 'not set') . "\n";
        echo "Return URL: " . ($paypal_config['return_url'] ?? 'not set') . "\n";
        echo "Cancel URL: " . ($paypal_config['cancel_url'] ?? 'not set') . "\n";
        echo "Currency: " . ($paypal_config['currency'] ?? 'not set') . "\n";
        
        // Check if credentials are set (don't show actual values)
        $mode = $paypal_config['mode'] ?? 'sandbox';
        $creds = $paypal_config[$mode] ?? [];
        echo "Client ID set: " . (!empty($creds['client_id']) ? "✅ YES" : "❌ NO") . "\n";
        echo "Client Secret set: " . (!empty($creds['client_secret']) ? "✅ YES" : "❌ NO") . "\n";
    } else {
        echo "PayPal Config: ❌ File not found\n";
    }
} catch (Exception $e) {
    echo "PayPal Config Error: ❌ " . $e->getMessage() . "\n";
}

// 8. Try loading paypal_functions
echo "<h2>8. PayPal Functions</h2>";
try {
    if (file_exists(__DIR__ . '/includes/paypal_functions.php')) {
        require_once __DIR__ . '/includes/paypal_functions.php';
        echo "paypal_functions.php: ✅ Loaded\n";
        
        // Check if functions exist
        $functions = ['getPayPalClient', 'createPayPalOrder', 'capturePayPalOrder'];
        foreach ($functions as $func) {
            echo "function $func(): " . (function_exists($func) ? "✅ EXISTS" : "❌ NOT FOUND") . "\n";
        }
    } else {
        echo "paypal_functions.php: ❌ File not found\n";
    }
} catch (Exception $e) {
    echo "PayPal Functions Error: ❌ " . $e->getMessage() . "\n";
    echo "Error trace:\n" . $e->getTraceAsString() . "\n";
}

// 9. Apache mod_rewrite
echo "<h2>9. Apache mod_rewrite</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "✅ Enabled" : "❌ Disabled") . "\n";
} else {
    echo "mod_rewrite: ⚠️ Cannot detect (CGI/FPM mode)\n";
}

// 10. Directory permissions
echo "<h2>10. Directory Permissions</h2>";
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
