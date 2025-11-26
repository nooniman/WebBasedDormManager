<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\config\paypal_config.php

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
        'client_id' => 'ooga',
        'client_secret' => 'booga',
    ],
    
    // URLs
    'return_url' => 'http://localhost/dormitory-management-system/tenant/payment_success.php',
    'cancel_url' => 'http://localhost/dormitory-management-system/tenant/payment_cancel.php',
    
    // Currency
    'currency' => 'PHP',
];