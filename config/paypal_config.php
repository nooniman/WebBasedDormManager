<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\config\paypal_config.php

// Include environment for dynamic URLs
require_once __DIR__ . '/environment.php';

return [
    // Set to 'sandbox' for testing, 'live' for production
    'mode' => 'sandbox',
    
    // Sandbox credentials - TRIMMED to remove any hidden characters
    'sandbox' => [
        'client_id' => trim('AVXiHU3rf2C4z90D4FdWbNNOPbmCqqRUEEeYezO0zFbMqxBkVRw_nS6umVX1XTgihfw61yhcPfUL5s53'),
        'client_secret' => trim('EF0ab1amGbaMHUMoOTBGDgKhRx7IxKW9-C7PPkRn-RgwH7cOQtzZYTf7WCAPfQkZgjAodwXhjlGTcd-w'),
    ],
    
    // Live credentials
    'live' => [
        'client_id' => 'your-live-client-id',
        'client_secret' => 'your-live-client-secret',
    ],
    
    // Dynamic URLs based on environment
    'return_url' => PAYPAL_RETURN_URL,
    'cancel_url' => PAYPAL_CANCEL_URL,
    
    // Currency
    'currency' => 'PHP',
];