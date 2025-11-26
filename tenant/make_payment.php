<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\tenant\make_payment.php

require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';
require_once '../includes/paypal_functions.php';

$page_title = 'Make Payment';
$tenant_id = $_SESSION['user_id'];
$error = '';
$debug_info = []; // For debugging

// Get ALL active bookings for this tenant
$all_bookings_stmt = $conn->prepare("
    SELECT b.id, b.status, b.room_id, b.start_date, r.room_number, r.price, r.room_type
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ? AND b.status IN ('approved', 'checked_in')
    ORDER BY r.room_number ASC
");
$all_bookings_stmt->bind_param("i", $tenant_id);
$all_bookings_stmt->execute();
$all_bookings = $all_bookings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_bookings_stmt->close();

// If no bookings at all
if (empty($all_bookings)) {
    set_flash_message('No active bookings found. Please book a room first.', 'error');
    redirect('tenant/portal');
}

// Get booking ID from URL or use first booking
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : $all_bookings[0]['id'];

// Find the selected booking
$booking = null;
foreach ($all_bookings as $b) {
    if ($b['id'] == $booking_id) {
        $booking = $b;
        break;
    }
}

// If booking_id doesn't match any of user's bookings, use first one
if (!$booking) {
    $booking = $all_bookings[0];
    $booking_id = $booking['id'];
}

// Get last payment for this booking
$last_payment_stmt = $conn->prepare("
    SELECT payment_period, payment_date 
    FROM payments 
    WHERE tenant_id = ? AND room_id = ? AND status = 'confirmed'
    ORDER BY payment_date DESC 
    LIMIT 1
");
$last_payment_stmt->bind_param("ii", $tenant_id, $booking['room_id']);
$last_payment_stmt->execute();
$last_payment = $last_payment_stmt->get_result()->fetch_assoc();
$last_payment_stmt->close();

// Handle payment initiation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info[] = "POST request received";
    $debug_info[] = "POST data: " . print_r($_POST, true);
    
    if (isset($_POST['initiate_payment'])) {
        $debug_info[] = "initiate_payment is set";
        
        // Check CSRF token
        $csrf_valid = verify_csrf_token($_POST['csrf_token'] ?? '');
        $debug_info[] = "CSRF valid: " . ($csrf_valid ? 'yes' : 'no');
        $debug_info[] = "CSRF token received: " . ($_POST['csrf_token'] ?? 'none');
        
        if (!$csrf_valid) {
            $error = 'Invalid security token. Please refresh the page and try again.';
        } else {
            $payment_months = isset($_POST['payment_months']) ? (int)$_POST['payment_months'] : 1;
            $selected_booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
            
            $debug_info[] = "Payment months: $payment_months";
            $debug_info[] = "Selected booking ID: $selected_booking_id";
            
            // Verify booking belongs to user
            $verify_stmt = $conn->prepare("
                SELECT b.*, r.room_number, r.price 
                FROM bookings b 
                JOIN rooms r ON b.room_id = r.id 
                WHERE b.id = ? AND b.tenant_id = ? AND b.status IN ('approved', 'checked_in')
            ");
            $verify_stmt->bind_param("ii", $selected_booking_id, $tenant_id);
            $verify_stmt->execute();
            $verified_booking = $verify_stmt->get_result()->fetch_assoc();
            $verify_stmt->close();
            
            $debug_info[] = "Verified booking: " . ($verified_booking ? 'found' : 'not found');
            
            if (!$verified_booking) {
                $error = 'Invalid booking selected. Please try again.';
            } else {
                $amount = $verified_booking['price'] * $payment_months;
                $start_month = date('F Y');
                $end_month = date('F Y', strtotime("+".($payment_months - 1)." months"));
                $payment_period = $payment_months == 1 ? $start_month : "{$start_month} - {$end_month}";
                
                $debug_info[] = "Amount: $amount";
                $debug_info[] = "Payment period: $payment_period";
                
                // Load PayPal config
                $config = require '../config/paypal_config.php';
                $debug_info[] = "PayPal config loaded";
                $debug_info[] = "Return URL: " . $config['return_url'];
                
                // Create PayPal order
                $description = "Room {$verified_booking['room_number']} - {$payment_months} month(s) rent";
                $reference_id = "BOOKING-{$selected_booking_id}-" . time();
                
                $debug_info[] = "Creating PayPal order...";
                
                try {
                    $result = createPayPalOrder(
                        $amount,
                        $description,
                        $reference_id,
                        $config['return_url'] . "?booking_id={$selected_booking_id}",
                        $config['cancel_url'] . "?booking_id={$selected_booking_id}"
                    );
                    
                    $debug_info[] = "PayPal result: " . print_r($result, true);
                    
                    if ($result['success']) {
                        $debug_info[] = "PayPal order created successfully";
                        $debug_info[] = "Order ID: " . $result['order_id'];
                        
                        // Store pending transaction
                        $store_result = storePendingPayPalTransaction(
                            $conn,
                            $tenant_id,
                            $verified_booking['room_id'],
                            $selected_booking_id,
                            $result['order_id'],
                            $amount,
                            $payment_period
                        );
                        
                        $debug_info[] = "Transaction stored: " . ($store_result ? 'yes' : 'no');
                        
                        // Get approval URL
                        $approval_url = getApprovalUrl($result['links']);
                        $debug_info[] = "Approval URL: " . ($approval_url ?? 'none');
                        
                        if ($approval_url) {
                            // Redirect to PayPal
                            header("Location: " . $approval_url);
                            exit();
                        } else {
                            $error = 'Could not get PayPal approval URL. Please try again.';
                            $debug_info[] = "Links received: " . print_r($result['links'], true);
                        }
                    } else {
                        $error = 'Failed to create PayPal order: ' . ($result['error'] ?? 'Unknown error');
                        $debug_info[] = "PayPal error: " . ($result['error'] ?? 'Unknown');
                    }
                } catch (Exception $e) {
                    $error = 'PayPal error: ' . $e->getMessage();
                    $debug_info[] = "Exception: " . $e->getMessage();
                }
            }
        }
    } else {
        $debug_info[] = "initiate_payment NOT set in POST";
        $debug_info[] = "Available POST keys: " . implode(', ', array_keys($_POST));
    }
}

require_once '../includes/header.php';
?>

<style>
    .payment-page {
        padding: 2rem 0;
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .payment-container {
        max-width: 700px;
        margin: 0 auto;
    }
    
    /* Debug box */
    .debug-box {
        background: #1e293b;
        color: #10b981;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-family: monospace;
        font-size: 0.8rem;
        max-height: 300px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    
    .debug-box h4 {
        color: #f59e0b;
        margin: 0 0 0.5rem 0;
    }
    
    /* Room Selector */
    .room-selector-section {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    
    .room-selector-title {
        font-size: 1rem;
        font-weight: 700;
        color: #475569;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .room-tabs {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .room-tab {
        flex: 1;
        min-width: 150px;
        padding: 1rem;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
        text-align: center;
    }
    
    .room-tab:hover {
        border-color: #667eea;
        background: #f0f4ff;
    }
    
    .room-tab.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    
    .room-tab-number {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
    }
    
    .room-tab.active .room-tab-number {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .room-tab-type {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .room-tab-price {
        font-size: 0.9rem;
        font-weight: 700;
        color: #10b981;
        margin-top: 0.5rem;
    }
    
    .room-tab-status {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 50px;
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        display: inline-block;
        margin-top: 0.5rem;
    }
    
    /* Payment Card */
    .payment-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .payment-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .payment-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    
    .payment-header h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }
    
    .payment-header .room-info {
        margin-top: 0.75rem;
        opacity: 0.95;
        position: relative;
        z-index: 1;
    }
    
    .payment-header .room-badge {
        display: inline-block;
        background: rgba(255,255,255,0.2);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
    }
    
    .payment-body {
        padding: 2rem;
    }
    
    .booking-summary {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid #e2e8f0;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .summary-row:last-child {
        border-bottom: none;
    }
    
    .summary-label {
        color: #64748b;
        font-weight: 500;
    }
    
    .summary-value {
        font-weight: 600;
        color: #1e293b;
    }
    
    .summary-value.status {
        color: #10b981;
    }
    
    .last-payment-info {
        background: #fffbeb;
        border: 1px solid #fef3c7;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
        color: #92400e;
    }
    
    .last-payment-info strong {
        color: #78350f;
    }
    
    .amount-selector {
        margin-bottom: 1.5rem;
    }
    
    .amount-selector > label {
        display: block;
        margin-bottom: 0.75rem;
        font-weight: 600;
        color: #1e293b;
    }
    
    .months-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 0.75rem;
    }
    
    .month-option {
        position: relative;
    }
    
    .month-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .month-option .month-label-box {
        display: block;
        padding: 1rem;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .month-option input[type="radio"]:checked + .month-label-box {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    
    .month-option .month-label-box:hover {
        border-color: #667eea;
    }
    
    .month-option .month-num {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
    }
    
    .month-option input[type="radio"]:checked + .month-label-box .month-num {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .month-option .month-text {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    
    .total-display {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    
    .total-display::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -30%;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    
    .total-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }
    
    .total-amount {
        font-size: 2.5rem;
        font-weight: 800;
        position: relative;
        z-index: 1;
    }
    
    .total-breakdown {
        font-size: 0.85rem;
        opacity: 0.9;
        margin-top: 0.5rem;
        position: relative;
        z-index: 1;
    }
    
    .paypal-btn {
        width: 100%;
        padding: 1.25rem 2rem;
        background: linear-gradient(135deg, #0070ba 0%, #003087 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
    }
    
    .paypal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 112, 186, 0.35);
    }
    
    .paypal-btn:active {
        transform: translateY(0);
    }
    
    .paypal-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }
    
    .paypal-btn svg {
        height: 24px;
    }
    
    .security-badges {
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }
    
    .security-badge {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        color: #64748b;
    }
    
    .error-alert {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        color: #991b1b;
        padding: 1rem 1.25rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #ef4444;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }
    
    .back-link:hover {
        color: #764ba2;
        transform: translateX(-4px);
    }
    
    @media (max-width: 600px) {
        .room-tabs {
            flex-direction: column;
        }
        
        .room-tab {
            min-width: 100%;
        }
        
        .months-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .total-amount {
            font-size: 2rem;
        }
        
        .security-badges {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

<div class="container payment-page">
    <a href="<?php echo TENANT_URL; ?>/payments" class="back-link">← Back to Payments</a>
    
    <div class="payment-container">
        
        <?php 
        // Show debug info (remove in production)
        if (!empty($debug_info)): 
        ?>
        <div class="debug-box">
            <h4>🔧 Debug Information (Remove in production)</h4>
            <?php foreach ($debug_info as $info): ?>
<?php echo htmlspecialchars($info); ?>

<?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (count($all_bookings) > 1): ?>
        <!-- Room Selector for Multiple Bookings -->
        <div class="room-selector-section">
            <div class="room-selector-title">
                🏠 Select Room to Pay For
            </div>
            <div class="room-tabs">
                <?php foreach ($all_bookings as $b): ?>
                    <a href="?booking_id=<?php echo $b['id']; ?>" 
                       class="room-tab <?php echo $b['id'] == $booking_id ? 'active' : ''; ?>">
                        <div class="room-tab-number">Room <?php echo htmlspecialchars($b['room_number']); ?></div>
                        <div class="room-tab-type"><?php echo ucfirst($b['room_type']); ?></div>
                        <div class="room-tab-price"><?php echo format_currency($b['price']); ?>/mo</div>
                        <div class="room-tab-status"><?php echo ucfirst(str_replace('_', ' ', $b['status'])); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="payment-card">
            <div class="payment-header">
                <h1>💳 Make a Payment</h1>
                <div class="room-info">
                    <span class="room-badge">
                        Room <?php echo htmlspecialchars($booking['room_number']); ?> • <?php echo ucfirst($booking['room_type']); ?>
                    </span>
                </div>
            </div>
            
            <div class="payment-body">
                <?php if ($error): ?>
                    <div class="error-alert">
                        <span>❌</span>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="booking-summary">
                    <div class="summary-row">
                        <span class="summary-label">Room Number</span>
                        <span class="summary-value"><?php echo htmlspecialchars($booking['room_number']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Room Type</span>
                        <span class="summary-value"><?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Monthly Rate</span>
                        <span class="summary-value" style="color: #10b981; font-weight: 700;">
                            <?php echo format_currency($booking['price']); ?>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Booking Status</span>
                        <span class="summary-value status">
                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($last_payment): ?>
                <div class="last-payment-info">
                    📅 <strong>Last Payment:</strong> 
                    <?php echo htmlspecialchars($last_payment['payment_period']); ?> 
                    (<?php echo date('M d, Y', strtotime($last_payment['payment_date'])); ?>)
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="hidden" name="initiate_payment" value="1">
                    
                    <div class="amount-selector">
                        <label>Select Payment Duration</label>
                        <div class="months-grid">
                            <div class="month-option">
                                <input type="radio" name="payment_months" id="month1" value="1" checked>
                                <label for="month1" class="month-label-box">
                                    <div class="month-num">1</div>
                                    <div class="month-text">Month</div>
                                </label>
                            </div>
                            <div class="month-option">
                                <input type="radio" name="payment_months" id="month2" value="2">
                                <label for="month2" class="month-label-box">
                                    <div class="month-num">2</div>
                                    <div class="month-text">Months</div>
                                </label>
                            </div>
                            <div class="month-option">
                                <input type="radio" name="payment_months" id="month3" value="3">
                                <label for="month3" class="month-label-box">
                                    <div class="month-num">3</div>
                                    <div class="month-text">Months</div>
                                </label>
                            </div>
                            <div class="month-option">
                                <input type="radio" name="payment_months" id="month6" value="6">
                                <label for="month6" class="month-label-box">
                                    <div class="month-num">6</div>
                                    <div class="month-text">Months</div>
                                </label>
                            </div>
                            <div class="month-option">
                                <input type="radio" name="payment_months" id="month12" value="12">
                                <label for="month12" class="month-label-box">
                                    <div class="month-num">12</div>
                                    <div class="month-text">Months</div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="total-display">
                        <div class="total-label">Total Amount to Pay</div>
                        <div class="total-amount" id="totalAmount">
                            <?php echo format_currency($booking['price']); ?>
                        </div>
                        <div class="total-breakdown" id="totalBreakdown">
                            <?php echo format_currency($booking['price']); ?> × 1 month
                        </div>
                    </div>
                    
                    <button type="submit" class="paypal-btn" id="paypalBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 124 33" fill="white">
                            <path d="M46.211 6.749h-6.839a.95.95 0 0 0-.939.802l-2.766 17.537a.57.57 0 0 0 .564.658h3.265a.95.95 0 0 0 .939-.803l.746-4.73a.95.95 0 0 1 .938-.803h2.165c4.505 0 7.105-2.18 7.784-6.5.306-1.89.013-3.375-.872-4.415-.972-1.142-2.696-1.746-4.985-1.746zM47 13.154c-.374 2.454-2.249 2.454-4.062 2.454h-1.032l.724-4.583a.57.57 0 0 1 .563-.481h.473c1.235 0 2.4 0 3.002.704.359.42.469 1.044.332 1.906zM66.654 13.075h-3.275a.57.57 0 0 0-.563.481l-.145.916-.229-.332c-.709-1.029-2.29-1.373-3.868-1.373-3.619 0-6.71 2.741-7.312 6.586-.313 1.918.132 3.752 1.22 5.031.998 1.176 2.426 1.666 4.125 1.666 2.916 0 4.533-1.875 4.533-1.875l-.146.91a.57.57 0 0 0 .562.66h2.95a.95.95 0 0 0 .939-.803l1.77-11.209a.568.568 0 0 0-.561-.658zm-4.565 6.374c-.316 1.871-1.801 3.127-3.695 3.127-.951 0-1.711-.305-2.199-.883-.484-.574-.668-1.391-.514-2.301.295-1.855 1.805-3.152 3.67-3.152.93 0 1.686.309 2.184.892.499.589.697 1.411.554 2.317zM84.096 13.075h-3.291a.954.954 0 0 0-.787.417l-4.539 6.686-1.924-6.425a.953.953 0 0 0-.912-.678h-3.234a.57.57 0 0 0-.541.754l3.625 10.638-3.408 4.811a.57.57 0 0 0 .465.9h3.287a.949.949 0 0 0 .781-.408l10.946-15.8a.57.57 0 0 0-.468-.895z"/>
                            <path d="M94.992 6.749h-6.84a.95.95 0 0 0-.938.802l-2.766 17.537a.569.569 0 0 0 .562.658h3.51a.665.665 0 0 0 .656-.562l.785-4.971a.95.95 0 0 1 .938-.803h2.164c4.506 0 7.105-2.18 7.785-6.5.307-1.89.012-3.375-.873-4.415-.971-1.142-2.694-1.746-4.983-1.746zm.789 6.405c-.373 2.454-2.248 2.454-4.062 2.454h-1.031l.725-4.583a.568.568 0 0 1 .562-.481h.473c1.234 0 2.4 0 3.002.704.359.42.468 1.044.331 1.906zM115.434 13.075h-3.273a.567.567 0 0 0-.562.481l-.145.916-.23-.332c-.709-1.029-2.289-1.373-3.867-1.373-3.619 0-6.709 2.741-7.311 6.586-.312 1.918.131 3.752 1.219 5.031 1 1.176 2.426 1.666 4.125 1.666 2.916 0 4.533-1.875 4.533-1.875l-.146.91a.57.57 0 0 0 .564.66h2.949a.95.95 0 0 0 .938-.803l1.771-11.209a.571.571 0 0 0-.565-.658zm-4.565 6.374c-.314 1.871-1.801 3.127-3.695 3.127-.949 0-1.711-.305-2.199-.883-.484-.574-.666-1.391-.514-2.301.297-1.855 1.805-3.152 3.67-3.152.93 0 1.686.309 2.184.892.501.589.699 1.411.554 2.317zM119.295 7.23l-2.807 17.858a.569.569 0 0 0 .562.658h2.822c.469 0 .867-.34.939-.803l2.768-17.536a.57.57 0 0 0-.562-.659h-3.16a.571.571 0 0 0-.562.482z"/>
                        </svg>
                        Pay with PayPal
                    </button>
                </form>
                
                <div class="security-badges">
                    <div class="security-badge">
                        <span>🔒</span> SSL Encrypted
                    </div>
                    <div class="security-badge">
                        <span>✓</span> Buyer Protection
                    </div>
                    <div class="security-badge">
                        <span>🛡️</span> Secure Payment
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const monthlyRate = <?php echo $booking['price']; ?>;

function formatCurrency(amount) {
    return '₱' + amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function updateTotal() {
    const checked = document.querySelector('input[name="payment_months"]:checked');
    if (!checked) return;
    
    const months = parseInt(checked.value);
    const total = monthlyRate * months;
    
    document.getElementById('totalAmount').textContent = formatCurrency(total);
    document.getElementById('totalBreakdown').textContent = 
        formatCurrency(monthlyRate) + ' × ' + months + ' month' + (months > 1 ? 's' : '');
}

// Add event listeners to all radio buttons
document.querySelectorAll('input[name="payment_months"]').forEach(radio => {
    radio.addEventListener('change', updateTotal);
});

// Form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    console.log('Form submitting...');
    const btn = document.getElementById('paypalBtn');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:20px;height:20px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></span> Processing...';
});

// Add spin animation
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>

<?php require_once '../includes/footer.php'; ?>