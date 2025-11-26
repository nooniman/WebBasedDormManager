<?php
/**
 * Tenant Authentication Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include environment for URL constants
require_once __DIR__ . '/../config/environment.php';

// Check if user is logged in and is a tenant
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tenant') {
    header("Location: " . LOGIN_URL);
    exit();
}

// Regenerate session ID
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>