<?php
// filepath: tenant/payments.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'My Payments';
$tenant_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Build query
$query = "
    SELECT p.*, b.id as booking_id, r.room_number 
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN rooms r ON b.room_id = r.id
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
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
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
    
    /* Payment Stats Cards */
    .payment-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .payment-stat-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
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
    .payment-stat-card.average::before { background: #3b82f6; }
    
    .payment-stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
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
    
    .payment-stat-card.average .payment-stat-icon {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
    }
    
    .payment-stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }
    
    .payment-stat-label {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }
    
    .payment-stat-count {
        font-size: 0.85rem;
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
        min-width: 200px;
    }
    
    .filter-label {
        display: block;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }
    
    .filter-select {
        width: 100%;
        padding: 0.875rem 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
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
        font-size: 1.5rem;
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
        height: 300px;
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
        border-radius: 8px 8px 0 0;
        position: relative;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .bar:hover {
        opacity: 0.8;
        transform: scaleY(1.05);
    }
    
    .bar-value {
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        white-space: nowrap;
    }
    
    .bar-label {
        font-size: 0.75rem;
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
        font-size: 0.875rem;
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
        font-size: 1.1rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .payment-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
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
        font-size: 5rem;
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .payment-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filter-row {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .payments-table-enhanced {
            font-size: 0.875rem;
        }
        
        .payments-table-enhanced th,
        .payments-table-enhanced td {
            padding: 0.75rem;
        }
        
        .bar-chart {
            gap: 0.25rem;
        }
        
        .bar-label {
            font-size: 0.65rem;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Payment History</h1>
                <p class="subtitle">Track all your payment transactions</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="portal.php" class="btn-enhanced outline">
                    ← Back to Portal
                </a>
                <button onclick="window.print()" class="export-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                    </svg>
                    Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container payments-page">
    
    <!-- Payment Statistics -->
    <div class="payment-stats-grid">
        <div class="payment-stat-card total">
            <div class="payment-stat-icon">💰</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['total_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">Total Amount</div>
            <div class="payment-stat-count"><?php echo $stats['total_count']; ?> transaction(s)</div>
        </div>
        
        <div class="payment-stat-card confirmed">
            <div class="payment-stat-icon">✓</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['confirmed_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">Confirmed Payments</div>
            <div class="payment-stat-count"><?php echo $stats['confirmed_count']; ?> confirmed</div>
        </div>
        
        <div class="payment-stat-card pending">
            <div class="payment-stat-icon">⏳</div>
            <div class="payment-stat-value"><?php echo format_currency($stats['pending_amount'] ?? 0); ?></div>
            <div class="payment-stat-label">Pending Payments</div>
            <div class="payment-stat-count"><?php echo $stats['pending_count']; ?> pending</div>
        </div>
        
        <div class="payment-stat-card average">
            <div class="payment-stat-icon">📊</div>
            <div class="payment-stat-value">
                <?php 
                $avg = $stats['total_count'] > 0 ? $stats['total_amount'] / $stats['total_count'] : 0;
                echo format_currency($avg); 
                ?>
            </div>
            <div class="payment-stat-label">Average Payment</div>
            <div class="payment-stat-count">per transaction</div>
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
                        <div class="bar" style="height: <?php echo $height; ?>%;" title="<?php echo format_currency($amount); ?>">
                            <?php if ($amount > 0): ?>
                                <span class="bar-value"><?php echo format_currency($amount); ?></span>
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
                    <label class="filter-label">Year</label>
                    <select name="year" class="filter-select">
                        <?php 
                        $years_result->data_seek(0);
                        while ($year = $years_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn-enhanced primary" style="width: 100%;">
                        Apply Filter
                    </button>
                </div>
                
                <?php if ($status_filter !== 'all' || $year_filter != date('Y')): ?>
                <div class="filter-group">
                    <a href="payments.php" class="btn-enhanced outline" style="width: 100%; display: block; text-align: center;">
                        Clear Filters
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
                                <th>Amount</th>
                                <th>Room</th>
                                <th>Period</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><span class="payment-amount"><?php echo format_currency($payment['amount']); ?></span></td>
                                    <td>
                                        <?php if ($payment['room_number']): ?>
                                            <strong>Room <?php echo htmlspecialchars($payment['room_number']); ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="payment-method-badge">
                                            <?php 
                                            $method_icons = [
                                                'cash' => '💵',
                                                'bank_transfer' => '🏦',
                                                'credit_card' => '💳',
                                                'debit_card' => '💳',
                                                'online' => '🌐'
                                            ];
                                            $method = strtolower($payment['payment_method'] ?? '');
                                            echo $method_icons[$method] ?? '💰';
                                            ?>
                                            <?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($payment['reference_number']): ?>
                                            <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($payment['reference_number']); ?>
                                            </code>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-enhanced <?php echo $payment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($payment['booking_id']): ?>
                                            <a href="view_booking_details.php?id=<?php echo $payment['booking_id']; ?>" 
                                               class="btn-enhanced outline sm">
                                                View Booking
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
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
                        <?php if ($status_filter !== 'all' || $year_filter != date('Y')): ?>
                            No payments matching your filter criteria.
                        <?php else: ?>
                            You haven't made any payments yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($status_filter !== 'all' || $year_filter != date('Y')): ?>
                        <a href="payments.php" class="btn-enhanced primary">
                            Clear Filters
                        </a>
                    <?php else: ?>
                        <a href="../public/rooms.php" class="btn-enhanced primary">
                            Browse Available Rooms
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Notes -->
    <div class="card-modern" style="margin-top: 2rem;">
        <div class="card-header-modern">
            <h2 style="margin: 0;">
                <span class="chart-icon" style="width: 35px; height: 35px; font-size: 1rem;">ℹ️</span>
                Payment Information
            </h2>
        </div>
        <div class="card-body-modern">
            <div style="display: grid; gap: 1rem;">
                <div style="padding: 1rem; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 12px; border-left: 4px solid #3b82f6;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #1e40af;">💡 Payment Status</h4>
                    <ul style="margin: 0; padding-left: 1.5rem; color: #1e3a8a;">
                        <li><strong>Confirmed:</strong> Payment has been verified and processed</li>
                        <li><strong>Pending:</strong> Payment is awaiting verification</li>
                    </ul>
                </div>
                
                <div style="padding: 1rem; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 12px; border-left: 4px solid #10b981;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #065f46;">📋 Payment Methods</h4>
                    <p style="margin: 0; color: #064e3b;">
                        We accept various payment methods including cash, bank transfer, and online payments. 
                        Please keep your reference number for future reference.
                    </p>
                </div>
                
                <div style="padding: 1rem; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; border-left: 4px solid #f59e0b;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #78350f;">⚠️ Important Note</h4>
                    <p style="margin: 0; color: #78350f;">
                        If you have any questions about your payments or notice any discrepancies, 
                        please contact the administration immediately.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Print functionality
window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.btn-enhanced').forEach(btn => btn.style.display = 'none');
    document.querySelector('.page-header-enhanced').style.display = 'none';
});

window.addEventListener('afterprint', function() {
    document.querySelectorAll('.btn-enhanced').forEach(btn => btn.style.display = '');
    document.querySelector('.page-header-enhanced').style.display = '';
});
</script>

<?php require_once '../includes/footer.php'; ?>