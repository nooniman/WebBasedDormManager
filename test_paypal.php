<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\test_paypal.php
// DELETE THIS FILE AFTER TESTING!

require_once 'vendor/autoload.php';
require_once 'config/paypal_config.php';

$config = require 'config/paypal_config.php';

echo "<h2>PayPal Configuration Test</h2>";
echo "<pre>";
echo "Mode: " . $config['mode'] . "\n";
echo "Client ID: " . substr($config[$config['mode']]['client_id'], 0, 30) . "...\n";
echo "Secret Length: " . strlen($config[$config['mode']]['client_secret']) . " characters\n";
echo "</pre>";

// Test API connection
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

try {
    $environment = new SandboxEnvironment(
        $config['sandbox']['client_id'],
        $config['sandbox']['client_secret']
    );
    $client = new PayPalHttpClient($environment);
    
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => 'PHP',
                'value' => '10.00'
            ]
        ]],
        'application_context' => [
            'return_url' => 'http://localhost/test',
            'cancel_url' => 'http://localhost/test',
            'shipping_preference' => 'NO_SHIPPING'
        ]
    ];
    
    $response = $client->execute($request);
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724;'>✅ SUCCESS! PayPal connection works!</h3>";
    echo "<p>Order ID: " . $response->result->id . "</p>";
    echo "<p>Status: " . $response->result->status . "</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3 style='color: #721c24;'>❌ ERROR: PayPal connection failed!</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='https://developer.paypal.com/dashboard/applications/sandbox' target='_blank'>PayPal Developer Dashboard</a></li>";
    echo "<li>Make sure you're viewing <strong>Sandbox</strong> credentials (not Live)</li>";
    echo "<li>Copy the <strong>Client ID</strong> exactly as shown</li>";
    echo "<li>Click 'Show' next to Secret, then copy the <strong>Secret</strong> exactly</li>";
    echo "<li>Paste both values into <code>config/paypal_config.php</code></li>";
    echo "<li>Make sure there are no extra spaces or line breaks</li>";
    echo "</ol>";
}
?>