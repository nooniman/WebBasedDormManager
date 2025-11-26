<?php
// filepath: admin/dashboard.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Admin Dashboard';
require_once '../includes/header.php';

// Get statistics
$stats = [];

// PayPal transactions today
$result = $conn->query("
    SELECT COUNT(*) as count, SUM(amount) as total 
    FROM paypal_transactions 
    WHERE DATE(created_at) = CURDATE() AND status = 'completed'
");
$paypal_today = $result->fetch_assoc();
$stats['paypal_today_count'] = $paypal_today['count'] ?? 0;
$stats['paypal_today_amount'] = $paypal_today['total'] ?? 0;

// Pending PayPal transactions
$result = $conn->query("SELECT COUNT(*) as count FROM paypal_transactions WHERE status = 'pending'");
$stats['pending_paypal'] = $result->fetch_assoc()['count'];

// Total tenants
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tenant'");
$stats['total_tenants'] = $result->fetch_assoc()['count'];

// Active tenants (with checked_in bookings)
$result = $conn->query("SELECT COUNT(DISTINCT tenant_id) as count FROM bookings WHERE status = 'checked_in'");
$stats['active_tenants'] = $result->fetch_assoc()['count'];

// Total rooms
$result = $conn->query("SELECT COUNT(*) as count FROM rooms");
$stats['total_rooms'] = $result->fetch_assoc()['count'];

// Available rooms
$result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
$stats['available_rooms'] = $result->fetch_assoc()['count'];

// Occupancy rate
$stats['occupancy_rate'] = $stats['total_rooms'] > 0 ? round((($stats['total_rooms'] - $stats['available_rooms']) / $stats['total_rooms']) * 100, 1) : 0;

// Pending bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['count'];

// Total revenue (this month)
$result = $conn->query("
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    AND status = 'confirmed'
");
$stats['monthly_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Previous month revenue for comparison
$result = $conn->query("
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE MONTH(payment_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    AND YEAR(payment_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    AND status = 'confirmed'
");
$stats['prev_month_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Calculate revenue change
$stats['revenue_change'] = 0;
if ($stats['prev_month_revenue'] > 0) {
    $stats['revenue_change'] = round((($stats['monthly_revenue'] - $stats['prev_month_revenue']) / $stats['prev_month_revenue']) * 100, 1);
}

// Recent bookings
$recent_bookings = $conn->query("
    SELECT b.*, r.room_number, r.room_type, u.first_name, u.last_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.tenant_id = u.id 
    ORDER BY b.created_at DESC 
    LIMIT 10
");

// Recent payments
$recent_payments = $conn->query("
    SELECT p.*, r.room_number, u.first_name, u.last_name 
    FROM payments p 
    JOIN users u ON p.tenant_id = u.id 
    JOIN rooms r ON p.room_id = r.id 
    ORDER BY p.payment_date DESC 
    LIMIT 5
");
?>

<style>
    .dashboard-container {
        animation: fadeIn 0.5s ease-out;
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <h1>👋 Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>!</h1>
        <p class="subtitle">Here's what's happening with your dormitory today</p>
        <div class="breadcrumb">
            <span>🏠</span>
            <span>Dashboard</span>
        </div>
    </div>
</div>

<div class="container dashboard-container">
    
    <!-- Enhanced Statistics Cards -->
    <div class="grid grid-4 mb-4">
        <div class="stat-card-enhanced primary animate-slide-in">
            <div class="stat-icon-wrapper">
                👥
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_tenants']; ?></div>
                <div class="stat-label">Total Tenants</div>
                <div class="stat-change positive">
                    ↑ <?php echo $stats['active_tenants']; ?> Active
                </div>
            </div>
        </div>
        
        <div class="stat-card-enhanced success animate-slide-in">
            <div class="stat-icon-wrapper">
                🏠
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['available_rooms']; ?>/<?php echo $stats['total_rooms']; ?></div>
                <div class="stat-label">Available Rooms</div>
                <div class="stat-change <?php echo $stats['occupancy_rate'] > 70 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['occupancy_rate']; ?>% Occupancy
                </div>
            </div>
        </div>
        
        <div class="stat-card-enhanced warning animate-slide-in">
            <div class="stat-icon-wrapper">
                📋
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_bookings']; ?></div>
                <div class="stat-label">Pending Bookings</div>
                <?php if ($stats['pending_bookings'] > 0): ?>
                    <div class="stat-change negative">
                        ⚠️ Needs Review
                    </div>
                <?php else: ?>
                    <div class="stat-change positive">
                        ✓ All Clear
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stat-card-enhanced info animate-slide-in">
            <div class="stat-icon-wrapper">
                💰
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo format_currency($stats['monthly_revenue']); ?></div>
                <div class="stat-label">Monthly Revenue</div>
                <?php if ($stats['revenue_change'] != 0): ?>
                    <div class="stat-change <?php echo $stats['revenue_change'] > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $stats['revenue_change'] > 0 ? '↑' : '↓'; ?> <?php echo abs($stats['revenue_change']); ?>% vs last month
                    </div>
                <?php else: ?>
                    <div class="stat-change">
                        Same as last month
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card-enhanced animate-slide-in mb-4">
        <div class="card-header-enhanced">
            <h2>
                <div class="header-icon">⚡</div>
                Quick Actions
            </h2>
        </div>
        <div class="card-body-enhanced">
            <div class="quick-actions-grid">
                <a href="bookings.php?status=pending" class="quick-action-card">
                    <div class="icon">📋</div>
                    <div class="title">Review Bookings</div>
                    <div class="description"><?php echo $stats['pending_bookings']; ?> pending requests</div>
                </a>
                
                <a href="rooms.php" class="quick-action-card">
                    <div class="icon">🏠</div>
                    <div class="title">Manage Rooms</div>
                    <div class="description"><?php echo $stats['total_rooms']; ?> total rooms</div>
                </a>
                
                <a href="tenants.php" class="quick-action-card">
                    <div class="icon">👥</div>
                    <div class="title">View Tenants</div>
                    <div class="description"><?php echo $stats['total_tenants']; ?> registered</div>
                </a>
                
                <a href="payments.php" class="quick-action-card">
                    <div class="icon">💳</div>
                    <div class="title">Payments</div>
                    <div class="description">Track transactions</div>
                </a>

                <a href="payments.php?method=paypal" class="quick-action-card">
                    <div class="icon">🅿️</div>
                    <div class="title">PayPal Payments</div>
                    <div class="description">
                        <?php echo $stats['paypal_today_count']; ?> today 
                        (<?php echo format_currency($stats['paypal_today_amount']); ?>)
                    </div>
                </a>
                
                <a href="reports.php" class="quick-action-card">
                    <div class="icon">📊</div>
                    <div class="title">View Reports</div>
                    <div class="description">Analytics & insights</div>
                </a>
                
                <a href="announcements.php" class="quick-action-card">
                    <div class="icon">📢</div>
                    <div class="title">Announcements</div>
                    <div class="description">Post updates</div>
                </a>
            </div>
        </div>
    </div>
    
    <div class="grid grid-2">
        <!-- Recent Bookings -->
        <div class="card-enhanced animate-slide-in">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">📋</div>
                    Recent Booking Requests
                </h2>
                <a href="bookings.php" class="btn-enhanced sm outline">View All</a>
            </div>
            <div class="card-body-enhanced">
                <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table-enhanced">
                            <thead>
                                <tr>
                                    <th>Tenant</th>
                                    <th>Room</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span style="background: #f7fafc; padding: 0.25rem 0.75rem; border-radius: 6px; font-weight: 600;">
                                                Room <?php echo htmlspecialchars($booking['room_number']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                                        <td>
                                            <span class="badge-enhanced <?php 
                                                echo $booking['status'] === 'approved' ? 'approved' : 
                                                    ($booking['status'] === 'pending' ? 'pending' : 
                                                    ($booking['status'] === 'rejected' ? 'rejected' : 'info')); 
                                            ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_booking.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn-enhanced sm primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state-enhanced">
                        <div class="icon">📋</div>
                        <h3>No Recent Bookings</h3>
                        <p>No booking requests have been made recently</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="card-enhanced animate-slide-in">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">💳</div>
                    Recent Payments
                </h2>
                <a href="payments.php" class="btn-enhanced sm outline">View All</a>
            </div>
            <div class="card-body-enhanced">
                <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8fafc; border-radius: 12px; border-left: 4px solid #10b981;">
                                <div>
                                    <strong style="display: block; margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                    </strong>
                                    <small style="color: #64748b;">
                                        Room <?php echo htmlspecialchars($payment['room_number']); ?> • 
                                        <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    </small>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="color: #10b981; font-size: 1.1rem; display: block;">
                                        <?php echo format_currency($payment['amount']); ?>
                                    </strong>
                                    <span class="badge-enhanced <?php echo $payment['status'] === 'confirmed' ? 'success' : 'pending'; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-enhanced">
                        <div class="icon">💳</div>
                        <h3>No Recent Payments</h3>
                        <p>No payments have been recorded recently</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>