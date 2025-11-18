<?php
session_start();
require_once 'includes/auth0_functions.php';

try {
    $auth0 = get_auth0_instance();
    $loginUrl = $auth0->login();
    
    echo "<h2>Auth0 Configuration Test</h2>";
    echo "<p><strong>Login URL:</strong> <a href='" . htmlspecialchars($loginUrl) . "'>" . htmlspecialchars($loginUrl) . "</a></p>";
    echo "<p>Click the link above to test Auth0 login</p>";
    
} catch (Exception $e) {
    // Log the error message and stack trace to the server log
    error_log("Auth0 Test Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "<h2>Error</h2>";
    echo "<p>An unexpected error occurred. Please contact the administrator.</p>";
}