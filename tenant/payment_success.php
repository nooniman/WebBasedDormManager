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

// Fetch tenant info for receipt
$tenant_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$tenant_stmt->bind_param("i", $tenant_id);
$tenant_stmt->execute();
$tenant_info = $tenant_stmt->get_result()->fetch_assoc();
$tenant_stmt->close();

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
            
            // Fetch room info for receipt
            $room_stmt = $conn->prepare("SELECT room_number, room_type, price FROM rooms WHERE id = ?");
            $room_stmt->bind_param("i", $transaction['room_id']);
            $room_stmt->execute();
            $room_info = $room_stmt->get_result()->fetch_assoc();
            $room_stmt->close();
            
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
                'payment_id' => $payment_id,
                'amount' => $transaction['amount'],
                'period' => $transaction['payment_period'],
                'transaction_id' => $result['capture']['id'],
                'paypal_order_id' => $paypal_order_id,
                'payer_email' => $result['payer']['email'],
                'payment_date' => date('F j, Y'),
                'payment_time' => date('g:i A'),
                'room_number' => $room_info['room_number'] ?? 'N/A',
                'room_type' => $room_info['room_type'] ?? 'N/A',
                'tenant_name' => ($tenant_info['first_name'] ?? '') . ' ' . ($tenant_info['last_name'] ?? ''),
                'tenant_email' => $tenant_info['email'] ?? ''
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
        max-width: 600px;
        margin: 0 auto;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        overflow: hidden;
        animation: slideUp 0.5s ease;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .receipt-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
    }
    
    .receipt-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .success-icon {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        animation: scaleIn 0.5s ease 0.2s both;
        position: relative;
        z-index: 1;
    }
    
    @keyframes scaleIn {
        from { transform: scale(0); }
        to { transform: scale(1); }
    }
    
    .success-icon svg {
        width: 40px;
        height: 40px;
        color: white;
    }
    
    .success-title {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0 0 0.5rem 0;
        position: relative;
        z-index: 1;
    }
    
    .success-message {
        opacity: 0.9;
        margin: 0;
        position: relative;
        z-index: 1;
    }
    
    .receipt-body {
        padding: 2rem;
    }
    
    .receipt-id {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        border: 1px dashed #cbd5e1;
    }
    
    .receipt-id-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .receipt-id-value {
        font-family: monospace;
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        word-break: break-all;
    }
    
    .receipt-amount-box {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 2px solid #a7f3d0;
    }
    
    .receipt-amount-label {
        font-size: 0.875rem;
        color: #047857;
        margin-bottom: 0.25rem;
    }
    
    .receipt-amount-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: #059669;
    }
    
    .receipt-details {
        text-align: left;
        margin-bottom: 1.5rem;
    }
    
    .receipt-section {
        margin-bottom: 1.25rem;
    }
    
    .receipt-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .receipt-row {
        display: flex;
        justify-content: space-between;
        padding: 0.6rem 0;
        font-size: 0.95rem;
    }
    
    .receipt-row .label {
        color: #64748b;
    }
    
    .receipt-row .value {
        font-weight: 600;
        color: #1e293b;
        text-align: right;
    }
    
    .receipt-row .value.highlight {
        color: #0070ba;
    }
    
    .receipt-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
        margin: 1.5rem 0;
    }
    
    .receipt-footer {
        background: #f8fafc;
        padding: 1.5rem 2rem;
        border-top: 1px solid #e2e8f0;
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .btn-action {
        padding: 0.875rem 1.75rem;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
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
        background: white;
        color: #475569;
        border: 2px solid #e2e8f0;
    }
    
    .btn-secondary:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    
    .btn-print {
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        color: white;
    }
    
    .btn-print:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
    }
    
    .paypal-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(0, 112, 186, 0.1);
        color: #0070ba;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
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
    
    /* Print Styles */
    @media print {
        body * {
            visibility: hidden;
        }
        
        .success-card, .success-card * {
            visibility: visible;
        }
        
        .success-card {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: none;
            box-shadow: none;
            border-radius: 0;
        }
        
        .action-buttons, .receipt-footer {
            display: none !important;
        }
        
        .receipt-header {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .receipt-amount-box {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="container success-page">
    <?php if ($success): ?>
        <div class="success-card" id="receipt">
            <!-- Receipt Header -->
            <div class="receipt-header">
                <div class="success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="success-title">Payment Successful!</h1>
                <p class="success-message">Your payment has been processed and confirmed.</p>
            </div>
            
            <!-- Receipt Body -->
            <div class="receipt-body">
                <!-- Receipt ID -->
                <div class="receipt-id">
                    <div class="receipt-id-label">Receipt Number</div>
                    <div class="receipt-id-value">RCP-<?php echo str_pad($payment_details['payment_id'], 8, '0', STR_PAD_LEFT); ?></div>
                </div>
                
                <!-- Amount Box -->
                <div class="receipt-amount-box">
                    <div class="receipt-amount-label">Amount Paid</div>
                    <div class="receipt-amount-value"><?php echo format_currency($payment_details['amount']); ?></div>
                </div>
                
                <!-- Payment Details -->
                <div class="receipt-details">
                    <div class="receipt-section">
                        <div class="receipt-section-title">Payment Information</div>
                        <div class="receipt-row">
                            <span class="label">Date</span>
                            <span class="value"><?php echo $payment_details['payment_date']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Time</span>
                            <span class="value"><?php echo $payment_details['payment_time']; ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Payment Period</span>
                            <span class="value"><?php echo htmlspecialchars($payment_details['period']); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Status</span>
                            <span class="value"><span class="status-badge">✓ Confirmed</span></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Method</span>
                            <span class="value"><span class="paypal-badge">🅿️ PayPal</span></span>
                        </div>
                    </div>
                    
                    <div class="receipt-divider"></div>
                    
                    <div class="receipt-section">
                        <div class="receipt-section-title">Room Details</div>
                        <div class="receipt-row">
                            <span class="label">Room Number</span>
                            <span class="value"><?php echo htmlspecialchars($payment_details['room_number']); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Room Type</span>
                            <span class="value"><?php echo htmlspecialchars(ucfirst($payment_details['room_type'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="receipt-divider"></div>
                    
                    <div class="receipt-section">
                        <div class="receipt-section-title">Tenant Information</div>
                        <div class="receipt-row">
                            <span class="label">Name</span>
                            <span class="value"><?php echo htmlspecialchars($payment_details['tenant_name']); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Email</span>
                            <span class="value"><?php echo htmlspecialchars($payment_details['tenant_email']); ?></span>
                        </div>
                    </div>
                    
                    <div class="receipt-divider"></div>
                    
                    <div class="receipt-section">
                        <div class="receipt-section-title">Transaction Details</div>
                        <div class="receipt-row">
                            <span class="label">Transaction ID</span>
                            <span class="value highlight" style="font-size: 0.85rem; font-family: monospace;"><?php echo htmlspecialchars($payment_details['transaction_id']); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">PayPal Order ID</span>
                            <span class="value" style="font-size: 0.85rem; font-family: monospace;"><?php echo htmlspecialchars($payment_details['paypal_order_id']); ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="label">Payer Email</span>
                            <span class="value"><?php echo htmlspecialchars($payment_details['payer_email']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Receipt Footer with Actions -->
            <div class="receipt-footer">
                <div class="action-buttons">
                    <button onclick="printReceipt()" class="btn-action btn-print">
                        🖨️ Print Receipt
                    </button>
                    <a href="<?php echo TENANT_URL; ?>/payments" class="btn-action btn-primary">
                        View All Payments
                    </a>
                    <a href="<?php echo TENANT_URL; ?>/portal" class="btn-action btn-secondary">
                        Back to Portal
                    </a>
                </div>
            </div>
        </div>
        
        <script>
        function printReceipt() {
            window.print();
        }
        </script>
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