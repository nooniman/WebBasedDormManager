<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\tenant\payments.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'My Payments';
$tenant_id = $_SESSION['user_id'];

// Get ALL tenant's active bookings for "Make Payment" buttons
$bookings_query = $conn->prepare("
    SELECT b.id, b.status, b.room_id, b.is_bedspace_booking, b.bedspace_id,
           r.room_number, r.price, r.room_type, r.is_bedspace, r.price_per_bedspace,
           bs.bedspace_number
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN bedspaces bs ON b.bedspace_id = bs.id
    WHERE b.tenant_id = ? AND b.status IN ('approved', 'checked_in')
    ORDER BY r.room_number ASC
");
$bookings_query->bind_param("i", $tenant_id);
$bookings_query->execute();
$active_bookings = $bookings_query->get_result()->fetch_all(MYSQLI_ASSOC);
$bookings_query->close();

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$room_filter = isset($_GET['room']) ? (int)$_GET['room'] : 0;

// Build query
$query = "
    SELECT p.*, b.id as booking_id, r.room_number, r.room_type,
           pt.payer_email as paypal_email, pt.capture_id as paypal_capture,
           bs.bedspace_number
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN rooms r ON p.room_id = r.id
    LEFT JOIN bedspaces bs ON p.bedspace_id = bs.id
    LEFT JOIN paypal_transactions pt ON p.paypal_transaction_id = pt.paypal_order_id
    WHERE p.tenant_id = ?
";

$params = [$tenant_id];
$types = "i";

if ($status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($year_filter) {
    $query .= " AND YEAR(p.payment_date) = ?";
    $params[] = $year_filter;
    $types .= "i";
}

if ($room_filter > 0) {
    $query .= " AND p.room_id = ?";
    $params[] = $room_filter;
    $types .= "i";
}

$query .= " ORDER BY p.payment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments_result = $stmt->get_result();
$stmt->close();

// Get payment statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as confirmed_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN payment_method = 'paypal' THEN amount ELSE 0 END) as paypal_amount,
        SUM(CASE WHEN payment_method = 'paypal' THEN 1 ELSE 0 END) as paypal_count
    FROM payments 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get available years for filter
$years_query = "SELECT DISTINCT YEAR(payment_date) as year FROM payments WHERE tenant_id = ? ORDER BY year DESC";
$stmt = $conn->prepare($years_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$years_result = $stmt->get_result();
$stmt->close();

// Get all rooms tenant has paid for (for filter)
$rooms_query = "
    SELECT DISTINCT r.id, r.room_number 
    FROM payments p
    JOIN rooms r ON p.room_id = r.id
    WHERE p.tenant_id = ?
    ORDER BY r.room_number
";
$stmt = $conn->prepare($rooms_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$rooms_filter_result = $stmt->get_result();
$stmt->close();

// Get monthly breakdown for current year
$monthly_query = "
    SELECT 
        MONTH(payment_date) as month,
        SUM(amount) as total
    FROM payments 
    WHERE tenant_id = ? AND YEAR(payment_date) = ? AND status = 'confirmed'
    GROUP BY MONTH(payment_date)
    ORDER BY MONTH(payment_date)
";
$stmt = $conn->prepare($monthly_query);
$stmt->bind_param("ii", $tenant_id, $year_filter);
$stmt->execute();
$monthly_result = $stmt->get_result();
$monthly_data = array_fill(1, 12, 0);
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[$row['month']] = $row['total'];
}
$stmt->close();

require_once '../includes/header.php';
?>

<style>
    .payments-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Active Bookings Section */
    .active-bookings-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
    }
    
    .active-bookings-section h2 {
        margin: 0 0 1.5rem 0;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .bookings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }
    
    .booking-payment-card {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
    }
    
    .booking-payment-card:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-4px);
    }
    
    .booking-room-info {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .booking-room-number {
        font-size: 1.5rem;
        font-weight: 800;
    }
    
    .booking-room-type {
        font-size: 0.875rem;
        opacity: 0.9;
    }
    
    .booking-status-badge {
        background: rgba(16, 185, 129, 0.3);
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .booking-price {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    
    .booking-pay-btn {
        width: 100%;
        padding: 0.75rem;
        background: white;
        color: #667eea;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .booking-pay-btn:hover {
        background: #f0f0f0;
        transform: scale(1.02);
    }
    
    .booking-pay-btn.paypal {
        background: #0070ba;
        color: white;
    }
    
    .booking-pay-btn.paypal:hover {
        background: #005ea6;
    }
    
    /* Payment Stats Cards */
    .payment-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .payment-stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .payment-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        opacity: 0.08;
        transition: all 0.5s ease;
    }
    
    .payment-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    
    .payment-stat-card:hover::before {
        top: -20%;
        right: -20%;
    }
    
    .payment-stat-card.total::before { background: #667eea; }
    .payment-stat-card.confirmed::before { background: #10b981; }
    .payment-stat-card.pending::before { background: #f59e0b; }
    .payment-stat-card.paypal::before { background: #0070ba; }
    
    .payment-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 1;
    }
    
    .payment-stat-card.total .payment-stat-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .payment-stat-card.confirmed .payment-stat-icon {
        background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
    }
    
    .payment-stat-card.pending .payment-stat-icon {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
    
    .payment-stat-card.paypal .payment-stat-icon {
        background: linear-gradient(135deg, #0070ba 0%, #003087 100%);
    }
    
    .payment-stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.25rem;
        position: relative;
        z-index: 1;
    }
    
    .payment-stat-label {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }
    
    .payment-stat-count {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-top: 0.25rem;
        position: relative;
        z-index: 1;
    }
    
    /* Filter Section */
    .payment-filter-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
        border: 2px solid #e2e8f0;
    }
    
    .filter-row {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 150px;
    }
    
    .filter-label {
        display: block;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    
    .filter-select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Chart Section */
    .chart-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }
    
    .chart-card h2 {
        margin: 0 0 1.5rem 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .chart-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .chart-container {
        height: 250px;
        position: relative;
    }
    
    .bar-chart {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 100%;
        gap: 0.5rem;
    }
    
    .bar-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    
    .bar {
        width: 100%;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        border-radius: 6px 6px 0 0;
        position: relative;
        transition: all 0.3s ease;
        cursor: pointer;
        min-height: 4px;
    }
    
    .bar:hover {
        opacity: 0.8;
    }
    
    .bar-value {
        position: absolute;
        top: -22px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.65rem;
        font-weight: 700;
        color: #475569;
        white-space: nowrap;
    }
    
    .bar-label {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 600;
    }
    
    /* Payments Table */
    .payments-table-enhanced {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .payments-table-enhanced thead tr {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .payments-table-enhanced th {
        padding: 1rem;
        text-align: left;
        font-weight: 700;
        font-size: 0.8rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #cbd5e0;
    }
    
    .payments-table-enhanced th:first-child {
        border-top-left-radius: 12px;
    }
    
    .payments-table-enhanced th:last-child {
        border-top-right-radius: 12px;
    }
    
    .payments-table-enhanced tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .payments-table-enhanced tbody tr:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .payments-table-enhanced td {
        padding: 1rem;
        color: #475569;
        font-weight: 500;
    }
    
    .payment-amount {
        font-size: 1.05rem;
        font-weight: 700;
        color: #10b981;
    }
    
    .payment-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .payment-method-badge.paypal {
        background: rgba(0, 112, 186, 0.1);
        color: #0070ba;
    }
    
    .payment-method-badge.cash {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .payment-method-badge.bank {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .room-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border-radius: 8px;
        font-weight: 600;
        color: #667eea;
        font-size: 0.875rem;
    }
    
    .room-badge.bedspace {
        background: linear-gradient(135deg, #bfdbfe 0%, #dbeafe 100%);
        color: #1e40af;
        border: 1px solid #60a5fa;
    }
    
    /* Empty State */
    .empty-state-payments {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 20px;
        border: 2px dashed #cbd5e0;
    }
    
    .empty-icon-large {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
    
    .empty-state-payments h3 {
        font-size: 1.5rem;
        color: #475569;
        margin-bottom: 0.75rem;
    }
    
    .empty-state-payments p {
        color: #94a3b8;
        font-size: 1.1rem;
        margin-bottom: 2rem;
    }
    
    /* Export Button */
    .export-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: white;
        border: 2px solid #667eea;
        color: #667eea;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .export-btn:hover {
        background: #667eea;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    /* No bookings message */
    .no-bookings-msg {
        background: rgba(255, 255, 255, 0.1);
        padding: 2rem;
        border-radius: 12px;
        text-align: center;
    }
    
    .no-bookings-msg a {
        color: white;
        text-decoration: underline;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .payment-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .bookings-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-row {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .payments-table-enhanced {
            font-size: 0.85rem;
        }
        
        .payments-table-enhanced th,
        .payments-table-enhanced td {
            padding: 0.75rem 0.5rem;
        }
        
        .bar-chart {
            gap: 0.25rem;
        }
        
        .bar-label {
            font-size: 0.6rem;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>💳 Payment History</h1>
                <p class="subtitle">Track all your payment transactions</p>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="<?php echo TENANT_URL; ?>/portal" class="btn-enhanced outline">
                    ← Back to Portal
                </a>
                <button onclick="window.print()" class="export-btn">
                    🖨️ Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container payments-page">
    
    <!-- Active Bookings - Make Payment Section -->
    <?php if (!empty($active_bookings)): ?>
    <div class="active-bookings-section">
        <h2>🏠 Your Active Rooms - Make a Payment</h2>
        <div class="bookings-grid">
            <?php foreach ($active_bookings as $booking): ?>
                <?php 
                $display_price = $booking['is_bedspace_booking'] && $booking['price_per_bedspace'] 
                    ? $booking['price_per_bedspace'] 
                    : $booking['price'];
                $is_bedspace = $booking['is_bedspace_booking'] && $booking['bedspace_number'];
                ?>
                <div class="booking-payment-card">
                    <div class="booking-room-info">
                        <div>
                            <div class="booking-room-number">
                                <?php if ($is_bedspace): ?>
                                    🛏️ Room <?php echo htmlspecialchars($booking['room_number']); ?> Bed <?php echo htmlspecialchars($booking['bedspace_number']); ?>
                                <?php else: ?>
                                    Room <?php echo htmlspecialchars($booking['room_number']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="booking-room-type">
                                <?php echo $is_bedspace ? 'Bedspace' : ucfirst(htmlspecialchars($booking['room_type'])); ?>
                            </div>
                        </div>
                        <span class="booking-status-badge">
                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                        </span>
                    </div>
                    <div class="booking-price">
                        <?php echo format_currency($display_price); ?> / month
                        <?php if ($is_bedspace): ?>
                            <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 0.25rem;">Bedspace Rental</div>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo TENANT_URL; ?>/make_payment?booking_id=<?php echo $booking['id']; ?>" class="booking-pay-btn paypal">
                        🅿️ Pay with PayPal
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Payment Statistics -->
    <div class="payment-stats-grid">
        <div class="payment-stat-card total">
            <div class="payment-stat-icon">💰</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['total_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">Total Payments</div>
            <div class="payment-stat-count"><?php echo $stats['total_count'] ?? 0; ?> transactions</div>
        </div>
        
        <div class="payment-stat-card confirmed">
            <div class="payment-stat-icon">✓</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['confirmed_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">Confirmed</div>
            <div class="payment-stat-count"><?php echo $stats['confirmed_count'] ?? 0; ?> confirmed</div>
        </div>
        
        <div class="payment-stat-card pending">
            <div class="payment-stat-icon">⏳</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['pending_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">Pending</div>
            <div class="payment-stat-count"><?php echo $stats['pending_count'] ?? 0; ?> pending</div>
        </div>
        
        <div class="payment-stat-card paypal">
            <div class="payment-stat-icon">🅿️</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['paypal_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">PayPal Payments</div>
            <div class="payment-stat-count"><?php echo $stats['paypal_count'] ?? 0; ?> via PayPal</div>
        </div>
    </div>
    
    <!-- Monthly Chart -->
    <div class="chart-card">
        <h2>
            <span class="chart-icon">📈</span>
            Monthly Payment Overview - <?php echo $year_filter; ?>
        </h2>
        <div class="chart-container">
            <div class="bar-chart">
                <?php 
                $max_value = max($monthly_data);
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                foreach ($monthly_data as $month => $amount):
                    $height = $max_value > 0 ? ($amount / $max_value) * 100 : 0;
                ?>
                    <div class="bar-wrapper">
                        <div class="bar" style="height: <?php echo max($height, 2); ?>%;" title="<?php echo format_currency($amount); ?>">
                            <?php if ($amount > 0): ?>
                                <span class="bar-value"><?php echo '₱' . number_format($amount/1000, 1) . 'k'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bar-label"><?php echo $months[$month - 1]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="payment-filter-card">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Room</label>
                    <select name="room" class="filter-select">
                        <option value="0">All Rooms</option>
                        <?php 
                        $rooms_filter_result->data_seek(0);
                        while ($room = $rooms_filter_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $room['id']; ?>" <?php echo $room_filter == $room['id'] ? 'selected' : ''; ?>>
                                Room <?php echo htmlspecialchars($room['room_number']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Year</label>
                    <select name="year" class="filter-select">
                        <?php 
                        $years_result->data_seek(0);
                        $has_years = false;
                        while ($year = $years_result->fetch_assoc()): 
                            $has_years = true;
                        ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endwhile; ?>
                        <?php if (!$has_years): ?>
                            <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn-enhanced primary" style="width: 100%;">
                        🔍 Apply Filter
                    </button>
                </div>
                
                <?php if ($status_filter !== 'all' || $year_filter != date('Y') || $room_filter > 0): ?>
                <div class="filter-group">
                    <a href="<?php echo TENANT_URL; ?>/payments" class="btn-enhanced outline" style="width: 100%; display: block; text-align: center;">
                        ✕ Clear
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Payments Table -->
    <div class="card-modern">
        <div class="card-header-modern">
            <h2 style="margin: 0;">Payment Transactions</h2>
        </div>
        <div class="card-body-modern">
            <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="payments-table-enhanced">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Room</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($payment['room_number']): ?>
                                            <?php if ($payment['bedspace_number']): ?>
                                                <span class="room-badge bedspace">
                                                    🛏️ Room <?php echo htmlspecialchars($payment['room_number']); ?> Bed <?php echo htmlspecialchars($payment['bedspace_number']); ?>
                                                </span>
                                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.25rem;">Bedspace</div>
                                            <?php else: ?>
                                                <span class="room-badge">
                                                    🏠 Room <?php echo htmlspecialchars($payment['room_number']); ?>
                                                </span>
                                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.25rem;">Full Room</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="payment-amount"><?php echo format_currency($payment['amount']); ?></span></td>
                                    <td><?php echo htmlspecialchars($payment['payment_period'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $method = strtolower($payment['payment_method'] ?? 'cash');
                                        $method_class = $method === 'paypal' ? 'paypal' : ($method === 'bank' || $method === 'bank_transfer' ? 'bank' : 'cash');
                                        $method_icons = ['paypal' => '🅿️', 'cash' => '💵', 'bank' => '🏦', 'bank_transfer' => '🏦'];
                                        ?>
                                        <span class="payment-method-badge <?php echo $method_class; ?>">
                                            <?php echo $method_icons[$method] ?? '💳'; ?>
                                            <?php echo ucfirst($method); ?>
                                        </span>
                                        <?php if ($payment['paypal_email']): ?>
                                            <div style="font-size: 0.7rem; color: #0070ba; margin-top: 0.25rem;">
                                                <?php echo htmlspecialchars($payment['paypal_email']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($payment['paypal_capture_id']): ?>
                                            <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; display: inline-block; max-width: 120px; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($payment['paypal_capture_id']); ?>
                                            </code>
                                        <?php elseif ($payment['reference_number']): ?>
                                            <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                                <?php echo htmlspecialchars($payment['reference_number']); ?>
                                            </code>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-enhanced <?php echo $payment['status'] === 'confirmed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-payments">
                    <div class="empty-icon-large">💳</div>
                    <h3>No Payments Found</h3>
                    <p>
                        <?php if ($status_filter !== 'all' || $year_filter != date('Y') || $room_filter > 0): ?>
                            No payments match your current filters.
                        <?php else: ?>
                            You haven't made any payments yet.
                        <?php endif; ?>
                    </p>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <?php if ($status_filter !== 'all' || $year_filter != date('Y') || $room_filter > 0): ?>
                            <a href="<?php echo TENANT_URL; ?>/payments" class="btn-enhanced primary">
                                Clear Filters
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($active_bookings)): ?>
                            <a href="<?php echo TENANT_URL; ?>/make_payment?booking_id=<?php echo $active_bookings[0]['id']; ?>" class="btn-enhanced primary" style="background: linear-gradient(135deg, #0070ba 0%, #003087 100%);">
                                🅿️ Make Payment via PayPal
                            </a>
                        <?php else: ?>
                            <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-enhanced primary">
                                Browse Available Rooms
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Print functionality
window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.btn-enhanced, .export-btn, .booking-pay-btn').forEach(btn => btn.style.display = 'none');
});

window.addEventListener('afterprint', function() {
    document.querySelectorAll('.btn-enhanced, .export-btn, .booking-pay-btn').forEach(btn => btn.style.display = '');
});
</script>

<?php require_once '../includes/footer.php'; ?>