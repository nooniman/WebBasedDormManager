<?php
/**
 * Common Functions
 * 
 * This file contains utility functions used throughout the application.
 */

/**
 * Sanitize input data to prevent XSS attacks
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Hash password securely
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie path for subdirectory
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/environment.php';
        }
        $cookie_path = BASE_URL ?: '/';
        session_set_cookie_params(['path' => $cookie_path]);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie path for subdirectory
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/environment.php';
        }
        $cookie_path = BASE_URL ?: '/';
        session_set_cookie_params(['path' => $cookie_path]);
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect to a specific page
 * Automatically prepends BASE_URL for relative paths
 */
function redirect($url) {
    // Include environment if not already loaded
    if (!defined('BASE_URL')) {
        require_once __DIR__ . '/../config/environment.php';
    }
    
    // If URL doesn't start with http/https or /, prepend BASE_URL
    if (!preg_match('#^(https?://|/)#', $url)) {
        $url = BASE_URL . '/' . ltrim($url, '/');
    }
    
    header("Location: " . $url);
    exit();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie path for subdirectory
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/environment.php';
        }
        $cookie_path = BASE_URL ?: '/';
        session_set_cookie_params(['path' => $cookie_path]);
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Format date for display
 */
function format_date($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Format currency
 */
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Upload file with validation
 */
function upload_file($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880, $subfolder = '') {
    $upload_dir = __DIR__ . '/../uploads/';
    
    // Add subfolder if specified
    if (!empty($subfolder)) {
        $upload_dir .= rtrim($subfolder, '/') . '/';
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable'];
    }
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];
        $error_code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        return ['success' => false, 'message' => $error_messages[$error_code] ?? 'File upload failed'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds limit (' . round($max_size / 1048576, 1) . 'MB)'];
    }
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file type
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    // Generate unique filename
    $new_filename = uniqid('profile_', true) . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return path relative to uploads folder
        $relative_path = (!empty($subfolder) ? rtrim($subfolder, '/') . '/' : '') . $new_filename;
        return ['success' => true, 'filename' => $new_filename, 'path' => $relative_path];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

/**
 * Display flash message
 */
function set_flash_message($message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Convert timestamp to human-readable elapsed time
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
