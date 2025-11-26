<?php
/**
 * Environment Configuration
 * 
 * Automatically detects localhost vs production (Hostinger) environment
 * and sets appropriate paths, URLs, and database credentials.
 */

// Detect environment based on server name
function detectEnvironment() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    
    // Check for localhost indicators
    $localhost_indicators = ['localhost', '127.0.0.1', '::1'];
    
    foreach ($localhost_indicators as $indicator) {
        if (strpos($host, $indicator) !== false || strpos($server_name, $indicator) !== false) {
            return 'development';
        }
    }
    
    return 'production';
}

// Get current environment
define('ENVIRONMENT', detectEnvironment());

// Environment-specific configuration
if (ENVIRONMENT === 'development') {
    // ==========================================
    // LOCALHOST (XAMPP) CONFIGURATION
    // ==========================================
    
    // Database credentials
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'dormitory_db');
    
    // Base URL (with subfolder for XAMPP)
    define('BASE_URL', '/dormitory-management-system');
    define('SITE_URL', 'http://localhost/dormitory-management-system');
    
    // Clean URLs enabled (works with .htaccess RewriteBase)
    define('CLEAN_URLS', true);
    
} else {
    // ==========================================
    // PRODUCTION (HOSTINGER) CONFIGURATION
    // ==========================================
    
    // Database credentials
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u168857261_admin');
    define('DB_PASS', '1234!@#$Qwert');
    define('DB_NAME', 'u168857261_dormitory_db');
    
    // Base URL (root level on Hostinger)
    define('BASE_URL', '');
    define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
    
    // Clean URLs enabled on production
    define('CLEAN_URLS', true);
}

// ==========================================
// COMMON CONFIGURATION
// ==========================================

// Asset paths
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('PROFILES_URL', BASE_URL . '/profiles');

// Section paths
define('ADMIN_URL', BASE_URL . '/admin');
define('TENANT_URL', BASE_URL . '/tenant');
define('PUBLIC_URL', BASE_URL . '/public');

// Auth paths (clean URLs - no .php extension)
define('LOGIN_URL', BASE_URL . '/login');
define('LOGOUT_URL', BASE_URL . '/logout');
define('CALLBACK_URL', SITE_URL . '/callback');

// PayPal callback URLs (keep .php for external callbacks)
define('PAYPAL_RETURN_URL', SITE_URL . '/tenant/payment_success.php');
define('PAYPAL_CANCEL_URL', SITE_URL . '/tenant/payment_cancel.php');

/**
 * Generate URL with proper base path
 * 
 * @param string $path Relative path (e.g., '/admin/dashboard.php')
 * @return string Full URL path
 */
function url($path = '') {
    // Remove leading slash if present to avoid double slashes
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Generate full site URL (with protocol)
 * 
 * @param string $path Relative path
 * @return string Full URL with protocol
 */
function site_url($path = '') {
    $path = ltrim($path, '/');
    return SITE_URL . ($path ? '/' . $path : '');
}

/**
 * Generate asset URL
 * 
 * @param string $path Asset path relative to assets folder
 * @return string Full asset URL
 */
function asset($path = '') {
    $path = ltrim($path, '/');
    return ASSETS_URL . '/' . $path;
}

/**
 * Generate upload URL
 * 
 * @param string $filename Upload filename
 * @return string Full upload URL
 */
function upload_url($filename = '') {
    return UPLOADS_URL . '/' . $filename;
}

/**
 * Generate profile picture URL
 * 
 * @param string $filename Profile picture filename
 * @return string Full profile URL
 */
function profile_url($filename = '') {
    return PROFILES_URL . '/' . $filename;
}

/**
 * Redirect to a URL using the proper base path
 * 
 * @param string $path Path to redirect to
 */
function redirect_to($path) {
    header('Location: ' . url($path));
    exit();
}

/**
 * Get clean URL if enabled, otherwise return standard URL
 * 
 * @param string $path Standard path with .php extension
 * @return string Clean or standard URL
 */
function clean_url($path) {
    if (CLEAN_URLS) {
        // Remove .php extension for clean URLs
        $path = preg_replace('/\.php$/', '', $path);
        // Remove index from end
        $path = preg_replace('/\/index$/', '', $path);
    }
    return url($path);
}

// Debug mode (only in development)
define('DEBUG_MODE', ENVIRONMENT === 'development');

// Error reporting based on environment
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Log environment detection (for debugging)
if (DEBUG_MODE && php_sapi_name() !== 'cli') {
    error_log('[DMS] Environment: ' . ENVIRONMENT);
    error_log('[DMS] Base URL: ' . BASE_URL);
    error_log('[DMS] Site URL: ' . SITE_URL);
}
?>
