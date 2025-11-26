<?php
// filepath: admin/view_booking.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Booking Details';

// Get booking ID
if (!isset($_GET['id'])) {
    set_flash_message('Invalid booking ID', 'error');
    redirect('admin/bookings');
}

$booking_id = intval($_GET['id']);

// Fetch booking details with all related information
$query = "
    SELECT b.*, 
           r.room_number, r.room_type, r.price, r.capacity, r.floor_number, r.description,
           u.first_name, u.last_name, u.email, u.phone, u.address,
           admin.first_name as approved_by_name, admin.last_name as approved_by_lastname
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.tenant_id = u.id 
    LEFT JOIN users admin ON b.approved_by = admin.id
    WHERE b.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Booking not found', 'error');
    redirect('admin/bookings');
}

$booking = $result->fetch_assoc();
$stmt->close();

// Get payment history for this booking
$payment_query = "
    SELECT p.*, 
           pt.paypal_order_id,
           pt.capture_id as paypal_capture_id,
           pt.payer_email as paypal_payer_email
    FROM payments p
    LEFT JOIN paypal_transactions pt ON p.paypal_transaction_id = pt.paypal_order_id
    WHERE p.tenant_id = ? 
    AND p.room_id = ?
    AND p.payment_date >= ?
    ORDER BY p.payment_date DESC
";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->bind_param("iis", $booking['tenant_id'], $booking['room_id'], $booking['start_date']);
$payment_stmt->execute();
$payments_result = $payment_stmt->get_result();
$payment_stmt->close();

// Calculate payment summary
$total_paid = 0;
$pending_amount = 0;
if ($payments_result->num_rows > 0) {
    $payments_result->data_seek(0);
    while ($payment = $payments_result->fetch_assoc()) {
        if ($payment['status'] === 'confirmed') {
            $total_paid += $payment['amount'];
        } elseif ($payment['status'] === 'pending') {
            $pending_amount += $payment['amount'];
        }
    }
    $payments_result->data_seek(0);
}

require_once '../includes/header.php';
?>

<style>
    .booking-detail-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Hero Section with Booking Status */
    .booking-hero {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    
    .booking-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: var(--gradient-primary);
        opacity: 0.05;
        border-radius: 50%;
    }
    
    .booking-hero-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 2rem;
        position: relative;
        z-index: 1;
    }
    
    .booking-hero-left {
        flex: 1;
    }
    
    .booking-id-large {
        display: inline-block;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        padding: 0.5rem 1.25rem;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 1rem;
        border: 2px solid #e2e8f0;
    }
    
    .booking-title-section h1 {
        font-size: 2.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .booking-subtitle {
        font-size: 1.25rem;
        color: #64748b;
        font-weight: 500;
        margin-bottom: 1.5rem;
    }
    
    .booking-meta-tags {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .meta-tag {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #475569;
        border: 2px solid #e2e8f0;
    }
    
    .meta-tag .icon {
        font-size: 1.2rem;
    }
    
    .booking-hero-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 1rem;
    }
    
    .status-badge-hero {
        padding: 1rem 2rem;
        border-radius: 16px;
        font-size: 1.1rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .status-badge-hero.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #78350f;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .status-badge-hero.approved {
        background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%);
        color: #065f46;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .status-badge-hero.checked_in,
    .status-badge-hero.checked-in {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e3a8a;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .status-badge-hero.rejected {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    
    .status-badge-hero.cancelled {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        color: #374151;
        box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
    }
    
    .status-badge-hero.checked_out,
    .status-badge-hero.checked-out {
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        color: #4338ca;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }
    
    /* Quick Stats Row */
    .booking-quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .quick-stat-box {
        background: white;
        padding: 1.75rem;
        border-radius: 16px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .quick-stat-box:hover {
        border-color: #667eea;
        transform: translateY(-4px);
        box-shadow: var(--card-shadow-hover);
    }
    
    .quick-stat-box .label {
        font-size: 0.875rem;
        color: #94a3b8;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 0.75rem;
    }
    
    .quick-stat-box .value {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .quick-stat-box .value.amount {
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Two Column Layout */
    .detail-grid-modern {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
    /* Info Sections */
    .info-section-modern {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
        border: 1px solid #e2e8f0;
    }
    
    .info-section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .info-section-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .info-section-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .info-grid {
        display: grid;
        gap: 1.5rem;
    }
    
    .info-row {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 1.5rem;
        padding: 1rem 0;
    }
    
    .info-row:not(:last-child) {
        border-bottom: 1px dashed #e2e8f0;
    }
    
    .info-label {
        font-size: 0.875rem;
        color: #94a3b8;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .info-value {
        font-size: 1rem;
        color: #1e293b;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .info-value.highlight {
        font-size: 1.5rem;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 800;
    }
    
    .info-value a {
        color: #667eea;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .info-value a:hover {
        color: #764ba2;
        text-decoration: underline;
    }
    
    /* Tenant Card */
    .tenant-card-featured {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    }
    
    .tenant-avatar-large {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 800;
        flex-shrink: 0;
        border: 3px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .tenant-info-featured {
        flex: 1;
    }
    
    .tenant-name-featured {
        font-size: 1.75rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .tenant-contacts {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.95rem;
        opacity: 0.95;
    }
    
    .tenant-contacts a {
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }
    
    .tenant-contacts a:hover {
        opacity: 0.8;
        transform: translateX(5px);
    }
    
    /* Timeline Modern */
    .timeline-modern {
        position: relative;
        padding-left: 3rem;
    }
    
    .timeline-modern::before {
        content: '';
        position: absolute;
        left: 1rem;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        border-radius: 2px;
    }
    
    .timeline-item-modern {
        position: relative;
        padding-bottom: 2.5rem;
    }
    
    .timeline-item-modern:last-child {
        padding-bottom: 0;
    }
    
    .timeline-dot {
        position: absolute;
        left: -2.15rem;
        top: 0.25rem;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: white;
        border: 4px solid #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        z-index: 1;
    }
    
    .timeline-dot.completed {
        background: #667eea;
    }
    
    .timeline-content-modern {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1.25rem;
        border-radius: 12px;
        border-left: 3px solid #667eea;
    }
    
    .timeline-date-modern {
        font-size: 0.875rem;
        color: #94a3b8;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .timeline-title-modern {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .timeline-description-modern {
        color: #64748b;
        font-size: 0.95rem;
    }
    
    /* Payment History Table */
    .payment-table-modern {
        margin-top: 1.5rem;
    }
    
    .payment-item-modern {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        margin-bottom: 1rem;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .payment-item-modern:hover {
        border-color: #667eea;
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .payment-item-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .payment-icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: var(--gradient-success);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 12px rgba(17, 153, 142, 0.3);
    }
    
    .payment-details {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .payment-date {
        font-weight: 700;
        color: #1e293b;
        font-size: 1rem;
    }
    
    .payment-method {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .payment-item-right {
        text-align: right;
    }
    
    .payment-amount {
        font-size: 1.5rem;
        font-weight: 800;
        background: var(--gradient-success);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.25rem;
    }
    
    /* Alert Boxes Modern */
    .alert-modern {
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        border: 2px solid;
    }
    
    .alert-modern .alert-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .alert-modern .alert-content {
        flex: 1;
    }
    
    .alert-modern strong {
        display: block;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        font-weight: 700;
    }
    
    .alert-modern p {
        margin: 0;
        line-height: 1.6;
    }
    
    .alert-modern.warning {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #f59e0b;
    }
    
    .alert-modern.warning strong,
    .alert-modern.warning .alert-icon {
        color: #78350f;
    }
    
    .alert-modern.danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-color: #ef4444;
    }
    
    .alert-modern.danger strong,
    .alert-modern.danger .alert-icon {
        color: #991b1b;
    }
    
    .alert-modern.success {
        background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%);
        border-color: #10b981;
    }
    
    .alert-modern.success strong,
    .alert-modern.success .alert-icon {
        color: #065f46;
    }
    
    /* Quick Actions Sidebar */
    .quick-actions-modern {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        position: sticky;
        top: 2rem;
    }
    
    .quick-actions-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .quick-actions-header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .action-button-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .action-button-full {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
    
    /* Floating Action Button */
    .fab-print {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 65px;
        height: 65px;
        border-radius: 50%;
        background: var(--gradient-primary);
        color: white;
        border: none;
        font-size: 1.75rem;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .fab-print:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
    }
    
    /* Print Styles */
    @media print {
        .no-print,
        .fab-print,
        .quick-actions-modern {
            display: none !important;
        }
        
        .detail-grid-modern {
            grid-template-columns: 1fr;
        }
        
        .booking-hero,
        .info-section-modern {
            box-shadow: none;
            page-break-inside: avoid;
        }
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .detail-grid-modern {
            grid-template-columns: 1fr;
        }
        
        .quick-actions-modern {
            position: relative;
            top: 0;
        }
    }
    
    @media (max-width: 768px) {
        .booking-hero-content {
            flex-direction: column;
        }
        
        .booking-hero-right {
            width: 100%;
            align-items: flex-start;
        }
        
        .booking-title-section h1 {
            font-size: 1.75rem;
        }
        
        .booking-quick-stats {
            grid-template-columns: 1fr;
        }
        
        .info-row {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
        
        .tenant-card-featured {
            flex-direction: column;
            text-align: center;
        }
        
        .payment-item-modern {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .payment-item-right {
            text-align: left;
            width: 100%;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>📋 Booking Details</h1>
                <p class="subtitle">Complete information about booking #<?php echo $booking['id']; ?></p>
            </div>
            <div style="display: flex; gap: 0.5rem;" class="no-print">
                <button onclick="window.print()" class="btn-enhanced outline">
                    🖨️ Print
                </button>
                <a href="bookings" class="btn-enhanced outline">
                    ← Back to Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container booking-detail-page">
    
    <!-- Booking Hero Section -->
    <div class="booking-hero">
        <div class="booking-hero-content">
            <div class="booking-hero-left">
                <div class="booking-id-large">BOOKING ID: #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></div>
                <div class="booking-title-section">
                    <h1>🏠 Room <?php echo htmlspecialchars($booking['room_number']); ?></h1>
                    <div class="booking-subtitle">
                        <?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?> • 
                        Floor <?php echo $booking['floor_number'] ?? 'N/A'; ?>
                    </div>
                </div>
                <div class="booking-meta-tags">
                    <div class="meta-tag">
                        <span class="icon">📅</span>
                        <span>Since <?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    <div class="meta-tag">
                        <span class="icon">⏱️</span>
                        <span>Created <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="booking-hero-right">
                <div class="status-badge-hero <?php echo str_replace('_', '-', $booking['status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="booking-quick-stats">
        <div class="quick-stat-box">
            <div class="label">Monthly Rate</div>
            <div class="value amount"><?php echo format_currency($booking['price']); ?></div>
        </div>
        <?php if ($booking['total_amount']): ?>
        <div class="quick-stat-box">
            <div class="label">Total Amount</div>
            <div class="value amount"><?php echo format_currency($booking['total_amount']); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($total_paid > 0): ?>
        <div class="quick-stat-box">
            <div class="label">Total Paid</div>
            <div class="value" style="color: #10b981;"><?php echo format_currency($total_paid); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($booking['duration_months']): ?>
        <div class="quick-stat-box">
            <div class="label">Duration</div>
            <div class="value">
                <span style="color: #667eea;"><?php echo $booking['duration_months']; ?></span>
                <span style="font-size: 1rem; font-weight: 600; color: #64748b;">months</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Two Column Layout -->
    <div class="detail-grid-modern">
        <!-- Main Content -->
        <div>
            <!-- Tenant Information Featured -->
            <div class="info-section-modern">
                <div class="info-section-header">
                    <div class="info-section-icon">👤</div>
                    <h2>Tenant Information</h2>
                </div>
                
                <div class="tenant-card-featured">
                    <div class="tenant-avatar-large">
                        <?php echo strtoupper(substr($booking['first_name'], 0, 1)); ?>
                    </div>
                    <div class="tenant-info-featured">
                        <div class="tenant-name-featured">
                            <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                        </div>
                        <div class="tenant-contacts">
                            <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>">
                                📧 <?php echo htmlspecialchars($booking['email']); ?>
                            </a>
                            <?php if ($booking['phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>">
                                📱 <?php echo htmlspecialchars($booking['phone']); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($booking['address']): ?>
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">🏠 Address</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($booking['address'])); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Booking Information -->
            <div class="info-section-modern">
                <div class="info-section-header">
                    <div class="info-section-icon">📋</div>
                    <h2>Booking Information</h2>
                </div>
                
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">🏠 Room</div>
                        <div class="info-value">
                            Room <?php echo htmlspecialchars($booking['room_number']); ?> 
                            (<?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?>)
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">👥 Capacity</div>
                        <div class="info-value"><?php echo $booking['capacity']; ?> person(s)</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">🏢 Floor</div>
                        <div class="info-value">Floor <?php echo $booking['floor_number'] ?? 'N/A'; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">📅 Start Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($booking['start_date'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">📅 End Date</div>
                        <div class="info-value">
                            <?php echo $booking['end_date'] ? date('F d, Y', strtotime($booking['end_date'])) : 'Open-ended'; ?>
                        </div>
                    </div>
                    <?php if ($booking['check_in_date']): ?>
                    <div class="info-row">
                        <div class="info-label">🔑 Check In</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($booking['check_in_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['check_out_date']): ?>
                    <div class="info-row">
                        <div class="info-label">🚪 Check Out</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($booking['check_out_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Room Details -->
            <?php if ($booking['description']): ?>
            <div class="info-section-modern">
                <div class="info-section-header">
                    <div class="info-section-icon">🏠</div>
                    <h2>Room Description</h2>
                </div>
                <p style="color: #64748b; line-height: 1.8; margin: 0;">
                    <?php echo nl2br(htmlspecialchars($booking['description'])); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Notes -->
            <?php if ($booking['notes']): ?>
            <div class="alert-modern warning">
                <div class="alert-icon">📝</div>
                <div class="alert-content">
                    <strong>Tenant Notes</strong>
                    <p><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Rejection Reason -->
            <?php if ($booking['rejected_reason']): ?>
            <div class="alert-modern danger">
                <div class="alert-icon">❌</div>
                <div class="alert-content">
                    <strong>Rejection Reason</strong>
                    <p><?php echo nl2br(htmlspecialchars($booking['rejected_reason'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Current Status Info -->
            <?php if ($booking['status'] === 'checked_in'): ?>
            <div class="alert-modern success">
                <div class="alert-icon">ℹ️</div>
                <div class="alert-content">
                    <strong>Current Status</strong>
                    <p>
                        Tenant is currently occupying this room since 
                        <?php echo date('F d, Y', strtotime($booking['check_in_date'])); ?>.
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment History - Enhanced -->
            <?php if ($payments_result && $payments_result->num_rows > 0): ?>
            <div class="info-section-modern">
                <div class="info-section-header">
                    <div class="info-section-icon">💰</div>
                    <h2>Payment History</h2>
                </div>
                
                <div class="payment-table-modern">
                    <?php while ($payment = $payments_result->fetch_assoc()): ?>
                        <div class="payment-item-modern">
                            <div class="payment-item-left">
                                <div class="payment-icon-circle" style="<?php echo $payment['payment_method'] === 'paypal' ? 'background: linear-gradient(135deg, #0070ba 0%, #003087 100%);' : ''; ?>">
                                    <?php echo $payment['payment_method'] === 'paypal' ? '🅿️' : '💵'; ?>
                                </div>
                                <div class="payment-details">
                                    <div class="payment-date">
                                        <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    </div>
                                    <div class="payment-method">
                                        <?php echo ucfirst($payment['payment_method'] ?? 'Cash'); ?>
                                        <?php if ($payment['paypal_payer_email']): ?>
                                            <br><small style="color: #0070ba;"><?php echo htmlspecialchars($payment['paypal_payer_email']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($payment['paypal_capture_id']): ?>
                                        <div style="font-size: 0.75rem; color: #94a3b8; font-family: monospace;">
                                            ID: <?php echo htmlspecialchars(substr($payment['paypal_capture_id'], 0, 20)); ?>...
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="payment-item-right">
                                <div class="payment-amount"><?php echo format_currency($payment['amount']); ?></div>
                                <span class="badge-enhanced <?php echo $payment['status'] === 'confirmed' ? 'success' : 'pending'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div>
            <!-- Timeline -->
            <div class="info-section-modern">
                <div class="info-section-header">
                    <div class="info-section-icon">📅</div>
                    <h2>Timeline</h2>
                </div>
                
                <div class="timeline-modern">
                    <div class="timeline-item-modern">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content-modern">
                            <div class="timeline-date-modern">
                                📝 <?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?>
                            </div>
                            <div class="timeline-title-modern">Booking Submitted</div>
                            <div class="timeline-description-modern">Tenant submitted booking request</div>
                        </div>
                    </div>
                    
                    <?php if ($booking['approved_at']): ?>
                    <div class="timeline-item-modern">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content-modern">
                            <div class="timeline-date-modern">
                                <?php echo $booking['status'] === 'rejected' ? '❌' : '✅'; ?> 
                                <?php echo date('M d, Y g:i A', strtotime($booking['approved_at'])); ?>
                            </div>
                            <div class="timeline-title-modern">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                            </div>
                            <?php if ($booking['approved_by_name']): ?>
                            <div class="timeline-description-modern">
                                By <?php echo htmlspecialchars($booking['approved_by_name'] . ' ' . $booking['approved_by_lastname']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['check_in_date']): ?>
                    <div class="timeline-item-modern">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content-modern">
                            <div class="timeline-date-modern">
                                🔑 <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?>
                            </div>
                            <div class="timeline-title-modern">Checked In</div>
                            <div class="timeline-description-modern">Tenant moved into room</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['check_out_date']): ?>
                    <div class="timeline-item-modern">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content-modern">
                            <div class="timeline-date-modern">
                                🚪 <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
                            </div>
                            <div class="timeline-title-modern">Checked Out</div>
                            <div class="timeline-description-modern">Tenant vacated room</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions-modern no-print">
                <div class="quick-actions-header">
                    <h2>⚡ Quick Actions</h2>
                </div>
                
                <div class="action-button-group">
                    <?php if ($booking['status'] === 'pending'): ?>
                        <form method="POST" action="bookings" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-enhanced success action-button-full">
                                ✓ Approve Booking
                            </button>
                        </form>
                        <button onclick="showRejectModal()" class="btn-enhanced danger action-button-full">
                            ✗ Reject Booking
                        </button>
                    <?php elseif ($booking['status'] === 'approved'): ?>
                        <form method="POST" action="bookings" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="action" value="checkin">
                            <button type="submit" class="btn-enhanced primary action-button-full">
                                🔑 Check In Tenant
                            </button>
                        </form>
                    <?php elseif ($booking['status'] === 'checked_in'): ?>
                        <form method="POST" action="bookings" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="btn-enhanced warning action-button-full">
                                🚪 Check Out Tenant
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="edit_booking?id=<?php echo $booking['id']; ?>" 
                       class="btn-enhanced outline action-button-full">
                        ✏️ Edit Booking
                    </a>
                    
                    <a href="bookings" class="btn-enhanced outline action-button-full">
                        ← Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Print Button -->
<button onclick="window.print()" class="fab-print no-print" title="Print Booking Details">
    🖨️
</button>

<!-- Reject Modal -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-enhanced">
            <h3>❌ Reject Booking</h3>
            <button class="modal-close-btn" onclick="closeRejectModal()">×</button>
        </div>
        <div class="modal-body-enhanced">
            <form method="POST" action="bookings">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Reason for Rejection *</label>
                    <textarea name="rejection_reason" 
                              class="form-control" 
                              rows="5" 
                              required 
                              placeholder="Please provide a clear and detailed reason for rejecting this booking request..."></textarea>
                </div>
                
                <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn-enhanced danger" style="flex: 1;">
                        Confirm Rejection
                    </button>
                    <button type="button" onclick="closeRejectModal()" class="btn-enhanced outline" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRejectModal() {
    document.getElementById('rejectModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on outside click
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>