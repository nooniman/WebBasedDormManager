<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;

function get_auth0_instance() {
    $config = require __DIR__ . '/../config/auth0_config.php';
    
    // Ensure scope is an array
    $scope = $config['scope'];
    if (is_string($scope)) {
        $scope = explode(' ', $scope);
    }
    
    $configuration = new SdkConfiguration(
        domain: $config['domain'],
        clientId: $config['clientId'],
        clientSecret: $config['clientSecret'],
        redirectUri: $config['redirectUri'],
        cookieSecret: $config['cookieSecret'],
        scope: $scope,
        usePkce: true // Enable PKCE for better security
    );
    
    return new Auth0($configuration);
}

function sync_auth0_user($auth0_user, $conn) {
    $email = $auth0_user['email'];
    $first_name = $auth0_user['given_name'] ?? '';
    $last_name = $auth0_user['family_name'] ?? '';
    $auth0_id = $auth0_user['sub'];
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing user
        $user = $result->fetch_assoc();
        $update_stmt = $conn->prepare("UPDATE users SET auth0_id = ?, last_login = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $auth0_id, $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
        return $user;
    } else {
        // Create new user
        $random_password = hash_password(bin2hex(random_bytes(16)));
        $insert_stmt = $conn->prepare(
            "INSERT INTO users (email, password, first_name, last_name, auth0_id, role, is_active, email_verified, created_at) 
             VALUES (?, ?, ?, ?, ?, 'tenant', 1, 1, NOW())"
        );
        $insert_stmt->bind_param("sssss", $email, $random_password, $first_name, $last_name, $auth0_id);
        $insert_stmt->execute();
        $user_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        return ['id' => $user_id, 'role' => 'tenant'];
    }
    
    $stmt->close();
}