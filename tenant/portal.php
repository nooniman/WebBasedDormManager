<?php
// filepath: tenant/portal.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Tenant Portal';
require_once '../includes/header.php';

$tenant_id = $_SESSION['user_id'];

// Get tenant's current booking (approved or checked_in)
$booking_query = "
    SELECT b.*, r.room_number, r.room_type, r.price, r.floor_number,
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_photo
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.tenant_id = ? AND b.status IN ('approved', 'checked_in')
    ORDER BY b.created_at DESC
    LIMIT 1
";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$booking_result = $stmt->get_result();
$current_booking = $booking_result->fetch_assoc();
$stmt->close();

// Get pending bookings
$pending_query = "
    SELECT b.*, r.room_number, r.room_type, r.price 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.tenant_id = ? AND b.status = 'pending'
    ORDER BY b.created_at DESC
";
$stmt = $conn->prepare($pending_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$pending_result = $stmt->get_result();
$stmt->close();

// Get all bookings count for statistics
$bookings_stats_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status IN ('approved', 'checked_in') THEN 1 ELSE 0 END) as active_bookings
    FROM bookings 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($bookings_stats_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$bookings_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get payment statistics
$payment_stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM payments 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($payment_stats_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$payment_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent payments
$payment_query = "
    SELECT * FROM payments 
    WHERE tenant_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 5
";
$stmt = $conn->prepare($payment_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$payments_result = $stmt->get_result();
$stmt->close();

// Get recent announcements
$announcements = $conn->query("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 3
");
?>

<style>
.stat-card {
    background: linear-gradient(135deg, var(--primary-color), #1e40af);
    color: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    opacity: 0.9;
    font-size: 0.875rem;
}

.room-preview {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.room-photo-small {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: var(--border-radius);
}

.info-box {
    background: #f9fafb;
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.info-row:last-child {
    margin-bottom: 0;
}
</style>

<div class="container">
    <h1 class="mb-4">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h1>
    
    <!-- Statistics -->
    <div class="grid grid-3 mb-4">
        <div class="stat-card">
            <div class="stat-value"><?php echo $bookings_stats['total_bookings'] ?? 0; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="stat-value"><?php echo $bookings_stats['active_bookings'] ?? 0; ?></div>
            <div class="stat-label">Active Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="stat-value"><?php echo $bookings_stats['pending_bookings'] ?? 0; ?></div>
            <div class="stat-label">Pending Bookings</div>
        </div>
    </div>
    
    <div class="grid grid-2 mb-4">
        <!-- Current Booking -->
        <div class="card">
            <div class="card-header">
                <h2>Current Booking</h2>
            </div>
            <div class="card-body">
                <?php if ($current_booking): ?>
                    <div class="room-preview mb-3">
                        <?php if ($current_booking['room_photo']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($current_booking['room_photo']); ?>" 
                                 alt="Room" class="room-photo-small">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; background: #e5e7eb; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                🏠
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3 style="margin-bottom: 0.25rem;">Room <?php echo htmlspecialchars($current_booking['room_number']); ?></h3>
                            <p style="color: var(--text-light); margin: 0; font-size: 0.875rem;">
                                <?php echo ucfirst(htmlspecialchars($current_booking['room_type'])); ?>
                                <?php if ($current_booking['floor_number']): ?>
                                    • Floor <?php echo $current_booking['floor_number']; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-row">
                            <span><strong>Monthly Rate:</strong></span>
                            <span><?php echo format_currency($current_booking['price']); ?></span>
                        </div>
                        <div class="info-row">
                            <span><strong>Start Date:</strong></span>
                            <span><?php echo format_date($current_booking['start_date']); ?></span>
                        </div>
                        <?php if ($current_booking['end_date']): ?>
                        <div class="info-row">
                            <span><strong>End Date:</strong></span>
                            <span><?php echo format_date($current_booking['end_date']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span><strong>Status:</strong></span>
                            <span>
                                <?php if ($current_booking['status'] === 'checked_in'): ?>
                                    <span class="badge badge-success">Checked In</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Approved</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="bookings.php" class="btn btn-primary" style="flex: 1;">
                            View Details
                        </a>
                        <a href="../public/room_view.php?id=<?php echo $current_booking['room_id']; ?>" 
                           class="btn btn-outline" style="flex: 1;">
                            Room Info
                        </a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem 1rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🏠</div>
                        <p style="margin-bottom: 1rem; color: var(--text-light);">You don't have an active booking.</p>
                        <a href="../public/rooms.php" class="btn btn-primary" style="width: 100%;">
                            Browse Available Rooms
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Bookings -->
        <div class="card">
            <div class="card-header">
                <h2>Pending Bookings</h2>
            </div>
            <div class="card-body">
                <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                    <?php while ($pending = $pending_result->fetch_assoc()): ?>
                        <div style="background: #fef3c7; padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem; border-left: 4px solid #f59e0b;">
                            <h4 style="margin-bottom: 0.5rem;">Room <?php echo htmlspecialchars($pending['room_number']); ?></h4>
                            <p style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                <?php echo ucfirst(htmlspecialchars($pending['room_type'])); ?> • 
                                <?php echo format_currency($pending['price']); ?>/month
                            </p>
                            <p style="margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-light);">
                                Requested on <?php echo format_date($pending['created_at']); ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="badge badge-warning">Awaiting Approval</span>
                                <a href="view_booking_details.php?id=<?php echo $pending['id']; ?>" 
                                   class="btn btn-sm btn-outline">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <a href="bookings.php" class="btn btn-outline" style="width: 100%; margin-top: 0.5rem;">
                        View All Bookings
                    </a>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem 1rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                        <p style="margin-bottom: 1rem; color: var(--text-light);">No pending booking requests.</p>
                        <a href="../public/rooms.php" class="btn btn-outline" style="width: 100%;">
                            Browse Rooms
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Payments -->
    <div class="card mb-4">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Recent Payments</h2>
            <a href="payments.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo format_date($payment['payment_date']); ?></td>
                                    <td><strong><?php echo format_currency($payment['amount']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: var(--text-light);">No payment records yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="card-body">
            <div class="grid grid-4">
                <a href="../public/rooms.php" class="btn btn-primary">
                    🏠 Browse Rooms
                </a>
                <a href="bookings.php" class="btn btn-outline">
                    📋 My Bookings
                </a>
                <a href="profile.php" class="btn btn-outline">
                    👤 Update Profile
                </a>
                <a href="payments.php" class="btn btn-outline">
                    💳 View Payments
                </a>
            </div>
        </div>
    </div>
    
    <!-- Announcements -->
    <div class="card">
        <div class="card-header">
            <h2>Latest Announcements</h2>
        </div>
        <div class="card-body">
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while ($announcement = $announcements->fetch_assoc()): ?>
                    <div class="card mb-3" style="border-left: 4px solid <?php 
                        echo $announcement['priority'] === 'urgent' ? '#ef4444' : 
                            ($announcement['priority'] === 'important' ? '#f59e0b' : '#3b82f6'); 
                    ?>;">
                        <div class="card-body">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.875rem; margin-bottom: 1rem;">
                                Posted on <?php echo format_date($announcement['created_at']); ?>
                                <span class="badge badge-<?php 
                                    echo $announcement['priority'] === 'urgent' ? 'danger' : 
                                        ($announcement['priority'] === 'important' ? 'warning' : 'info'); 
                                ?>" style="margin-left: 0.5rem;">
                                    <?php echo ucfirst($announcement['priority']); ?>
                                </span>
                            </p>
                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: var(--text-light);">No announcements at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>