<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth0_functions.php';

try {
    $auth0 = get_auth0_instance();
    $auth0->exchange();
    
    $user_info = $auth0->getUser();
    
    if ($user_info) {
        // Sync user with database
        $user = sync_auth0_user($user_info, $conn);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user_info['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = ($user_info['given_name'] ?? '') . ' ' . ($user_info['family_name'] ?? '');
        $_SESSION['auth_method'] = 'auth0';
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            redirect('admin/dashboard');
        } else {
            redirect('tenant/portal');
        }
    } else {
        $_SESSION['error'] = 'Authentication failed';
        redirect('login');
    }
} catch (Exception $e) {
    error_log('Auth0 Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Authentication error occurred';
    redirect('login');
}