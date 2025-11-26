<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\tenant\payment_success.php

require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';
require_once '../includes/paypal_functions.php';

$page_title = 'Payment Successful';
$tenant_id = $_SESSION['user_id'];

$success = false;
$payment_details = null;
$error = '';

// Get PayPal token from URL (PayPal redirects with token parameter)
$paypal_order_id = isset($_GET['token']) ? $_GET['token'] : null;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($paypal_order_id) {
    // Get pending transaction
    $transaction = getPendingTransaction($conn, $paypal_order_id);
    
    if ($transaction && $transaction['tenant_id'] == $tenant_id) {
        // Capture the payment
        $result = capturePayPalOrder($paypal_order_id);
        
        if ($result['success'] && $result['status'] === 'COMPLETED') {
            // Update transaction status
            updatePayPalTransaction(
                $conn,
                $paypal_order_id,
                'completed',
                $result['capture']['id'],
                $result['payer']['email']
            );
            
            // Create payment record in payments table
            $stmt = $conn->prepare("
                INSERT INTO payments 
                (tenant_id, room_id, amount, payment_date, payment_period, status, payment_method, paypal_transaction_id, paypal_capture_id) 
                VALUES (?, ?, ?, NOW(), ?, 'confirmed', 'paypal', ?, ?)
            ");
            $stmt->bind_param(
                "iidsss",
                $transaction['tenant_id'],
                $transaction['room_id'],
                $transaction['amount'],
                $transaction['payment_period'],
                $paypal_order_id,
                $result['capture']['id']
            );
            $stmt->execute();
            $payment_id = $stmt->insert_id;
            $stmt->close();
            
            // Create notification
            if (function_exists('create_notification')) {
                create_notification(
                    $conn,
                    $tenant_id,
                    'payment',
                    'Payment Successful',
                    'Your payment of ' . format_currency($transaction['amount']) . ' has been processed successfully.',
                    $payment_id,
                    'payment'
                );
            }
            
            $success = true;
            $payment_details = [
                'amount' => $transaction['amount'],
                'period' => $transaction['payment_period'],
                'transaction_id' => $result['capture']['id'],
                'payer_email' => $result['payer']['email']
            ];
        } else {
            $error = 'Payment capture failed: ' . ($result['error'] ?? 'Unknown error');
            updatePayPalTransaction($conn, $paypal_order_id, 'failed');
        }
    } else {
        $error = 'Invalid or expired transaction';
    }
} else {
    $error = 'No payment information received';
}

require_once '../includes/header.php';
?>

<style>
    .success-page {
        padding: 4rem 0;
        text-align: center;
    }
    
    .success-card {
        max-width: 500px;
        margin: 0 auto;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        padding: 3rem;
        animation: slideUp 0.5s ease;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        animation: scaleIn 0.5s ease 0.2s both;
    }
    
    @keyframes scaleIn {
        from { transform: scale(0); }
        to { transform: scale(1); }
    }
    
    .success-icon svg {
        width: 50px;
        height: 50px;
        color: white;
    }
    
    .success-title {
        font-size: 2rem;
        font-weight: 800;
        color: #1a1a2e;
        margin-bottom: 1rem;
    }
    
    .success-message {
        color: #6c757d;
        margin-bottom: 2rem;
    }
    
    .payment-summary {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        text-align: left;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .summary-item .label {
        color: #6c757d;
    }
    
    .summary-item .value {
        font-weight: 600;
        color: #1a1a2e;
    }
    
    .summary-item .value.amount {
        color: #10b981;
        font-size: 1.25rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }
    
    .btn-action {
        padding: 0.875rem 2rem;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .btn-secondary {
        background: #f8f9fa;
        color: #1a1a2e;
        border: 2px solid #e9ecef;
    }
    
    .btn-secondary:hover {
        background: #e9ecef;
    }
    
    .error-card {
        max-width: 500px;
        margin: 0 auto;
        background: #fff5f5;
        border-radius: 20px;
        padding: 3rem;
        text-align: center;
    }
    
    .error-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .error-title {
        color: #c53030;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    
    .error-message {
        color: #6c757d;
        margin-bottom: 2rem;
    }
</style>

<div class="container success-page">
    <?php if ($success): ?>
        <div class="success-card">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <h1 class="success-title">Payment Successful!</h1>
            <p class="success-message">Your payment has been processed successfully. Thank you for your payment!</p>
            
            <div class="payment-summary">
                <div class="summary-item">
                    <span class="label">Amount Paid</span>
                    <span class="value amount"><?php echo format_currency($payment_details['amount']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Payment Period</span>
                    <span class="value"><?php echo htmlspecialchars($payment_details['period']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Transaction ID</span>
                    <span class="value" style="font-size: 0.875rem;"><?php echo htmlspecialchars($payment_details['transaction_id']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">PayPal Email</span>
                    <span class="value"><?php echo htmlspecialchars($payment_details['payer_email']); ?></span>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?php echo TENANT_URL; ?>/payments" class="btn-action btn-primary">View Payments</a>
                <a href="<?php echo TENANT_URL; ?>/portal" class="btn-action btn-secondary">Back to Portal</a>
            </div>
        </div>
    <?php else: ?>
        <div class="error-card">
            <div class="error-icon">❌</div>
            <h1 class="error-title">Payment Failed</h1>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <div class="action-buttons">
                <a href="<?php echo TENANT_URL; ?>/payments" class="btn-action btn-primary">Try Again</a>
                <a href="<?php echo TENANT_URL; ?>/portal" class="btn-action btn-secondary">Back to Portal</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>