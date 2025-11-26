<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\tenant\payment_cancel.php

require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../includes/paypal_functions.php';

$page_title = 'Payment Cancelled';

// Update transaction status if exists
$paypal_order_id = isset($_GET['token']) ? $_GET['token'] : null;
if ($paypal_order_id) {
    updatePayPalTransaction($conn, $paypal_order_id, 'cancelled');
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

require_once '../includes/header.php';
?>

<style>
    .cancel-page {
        padding: 4rem 0;
        text-align: center;
    }
    
    .cancel-card {
        max-width: 500px;
        margin: 0 auto;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        padding: 3rem;
    }
    
    .cancel-icon {
        font-size: 5rem;
        margin-bottom: 1.5rem;
    }
    
    .cancel-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 1rem;
    }
    
    .cancel-message {
        color: #6c757d;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
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
    
    .btn-secondary {
        background: #f8f9fa;
        color: #1a1a2e;
        border: 2px solid #e9ecef;
    }
</style>

<div class="container cancel-page">
    <div class="cancel-card">
        <div class="cancel-icon">🚫</div>
        <h1 class="cancel-title">Payment Cancelled</h1>
        <p class="cancel-message">
            Your payment was cancelled. No charges have been made to your account.<br>
            You can try again whenever you're ready.
        </p>
        
        <div class="action-buttons">
            <?php if ($booking_id): ?>
                <a href="make_payment.php?booking_id=<?php echo $booking_id; ?>" class="btn-action btn-primary">
                    Try Again
                </a>
            <?php endif; ?>
            <a href="portal.php" class="btn-action btn-secondary">Back to Portal</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>