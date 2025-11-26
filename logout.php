<?php
session_start();
require_once 'config/environment.php';
require_once 'includes/auth0_functions.php';

$auth_method = $_SESSION['auth_method'] ?? 'standard';

// Destroy all session data
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// If logged in via Auth0, logout from Auth0 as well
if ($auth_method === 'auth0') {
    $auth0 = get_auth0_instance();
    $auth0->logout(SITE_URL . '/login.php');
} else {
    header("Location: " . LOGIN_URL);
}
exit();