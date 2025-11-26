<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\includes\paypal_functions.php

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalHttp\HttpException;

/**
 * Get PayPal HTTP Client
 */
function getPayPalClient() {
    $config = require __DIR__ . '/../config/paypal_config.php';
    
    $mode = $config['mode'];
    $clientId = $config[$mode]['client_id'];
    $clientSecret = $config[$mode]['client_secret'];
    
    // Debug: Log credentials (remove in production!)
    error_log("PayPal Mode: " . $mode);
    error_log("PayPal Client ID (first 20 chars): " . substr($clientId, 0, 20) . "...");
    
    if ($mode === 'sandbox') {
        $environment = new SandboxEnvironment($clientId, $clientSecret);
    } else {
        $environment = new ProductionEnvironment($clientId, $clientSecret);
    }
    
    return new PayPalHttpClient($environment);
}

/**
 * Create a PayPal Order
 */
function createPayPalOrder($amount, $description, $reference_id, $return_url, $cancel_url) {
    $client = getPayPalClient();
    $config = require __DIR__ . '/../config/paypal_config.php';
    
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $reference_id,
            'description' => $description,
            'amount' => [
                'currency_code' => $config['currency'],
                'value' => number_format($amount, 2, '.', '')
            ]
        ]],
        'application_context' => [
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
            'brand_name' => 'Dormitory Management System',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'shipping_preference' => 'NO_SHIPPING'
        ]
    ];
    
    try {
        $response = $client->execute($request);
        return [
            'success' => true,
            'order_id' => $response->result->id,
            'status' => $response->result->status,
            'links' => $response->result->links
        ];
    } catch (HttpException $e) {
        // PayPal API error - get detailed message
        $errorMessage = $e->getMessage();
        $statusCode = $e->statusCode;
        error_log("PayPal API Error (HTTP $statusCode): " . $errorMessage);
        
        return [
            'success' => false,
            'error' => $errorMessage,
            'status_code' => $statusCode
        ];
    } catch (Exception $e) {
        error_log('PayPal Create Order Error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Capture a PayPal Order (after user approves)
 */
function capturePayPalOrder($order_id) {
    $client = getPayPalClient();
    
    $request = new OrdersCaptureRequest($order_id);
    $request->prefer('return=representation');
    
    try {
        $response = $client->execute($request);
        $result = $response->result;
        
        return [
            'success' => true,
            'order_id' => $result->id,
            'status' => $result->status,
            'payer' => [
                'email' => $result->payer->email_address ?? null,
                'payer_id' => $result->payer->payer_id ?? null,
                'name' => ($result->payer->name->given_name ?? '') . ' ' . ($result->payer->name->surname ?? '')
            ],
            'capture' => [
                'id' => $result->purchase_units[0]->payments->captures[0]->id ?? null,
                'amount' => $result->purchase_units[0]->payments->captures[0]->amount->value ?? null,
                'currency' => $result->purchase_units[0]->payments->captures[0]->amount->currency_code ?? null
            ]
        ];
    } catch (Exception $e) {
        error_log('PayPal Capture Order Error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get PayPal Order Details
 */
function getPayPalOrder($order_id) {
    $client = getPayPalClient();
    
    $request = new OrdersGetRequest($order_id);
    
    try {
        $response = $client->execute($request);
        return [
            'success' => true,
            'order' => $response->result
        ];
    } catch (Exception $e) {
        error_log('PayPal Get Order Error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get approval URL from order links
 */
function getApprovalUrl($links) {
    foreach ($links as $link) {
        if ($link->rel === 'approve') {
            return $link->href;
        }
    }
    return null;
}

/**
 * Store pending PayPal transaction
 */
function storePendingPayPalTransaction($conn, $tenant_id, $room_id, $booking_id, $paypal_order_id, $amount, $payment_period) {
    $stmt = $conn->prepare("
        INSERT INTO paypal_transactions 
        (tenant_id, room_id, booking_id, paypal_order_id, amount, payment_period, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("iiisds", $tenant_id, $room_id, $booking_id, $paypal_order_id, $amount, $payment_period);
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $insert_id : false;
}

/**
 * Update PayPal transaction status
 */
function updatePayPalTransaction($conn, $paypal_order_id, $status, $capture_id = null, $payer_email = null) {
    $stmt = $conn->prepare("
        UPDATE paypal_transactions 
        SET status = ?, capture_id = ?, payer_email = ?, updated_at = NOW() 
        WHERE paypal_order_id = ?
    ");
    $stmt->bind_param("ssss", $status, $capture_id, $payer_email, $paypal_order_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get pending transaction by PayPal order ID
 */
function getPendingTransaction($conn, $paypal_order_id) {
    $stmt = $conn->prepare("
        SELECT * FROM paypal_transactions 
        WHERE paypal_order_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("s", $paypal_order_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}