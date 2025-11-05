<?php
// filepath: tenant/portal.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Tenant Portal';
require_once '../includes/header.php';

$tenant_id = $_SESSION['user_id'];

// Get tenant's current booking
$booking_query = "
    SELECT b.*, r.room_number, r.room_type, r.price, r.floor_number,
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_photo
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.tenant_id = ? AND b.status = 'approved'
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
</style>

<div class="container">
    <h1 class="mb-4">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h1>
    
    <!-- Statistics -->
    <div class="grid grid-3 mb-4">
        <div class="stat-card">
            <div class="stat-value"><?php echo $payment_stats['total_payments'] ?? 0; ?></div>
            <div class="stat-label">Total Payments</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="stat-value"><?php echo format_currency($payment_stats['total_paid'] ?? 0); ?></div>
            <div class="stat-label">Total Paid</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="stat-value"><?php echo format_currency($payment_stats['pending_amount'] ?? 0); ?></div>
            <div class="stat-label">Pending Payments</div>
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
                        <?php endif; ?>
                        <div>
                            <h3 style="margin-bottom: 0.25rem;">Room <?php echo htmlspecialchars($current_booking['room_number']); ?></h3>
                            <p style="color: var(--text-light); margin: 0; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($current_booking['room_type']); ?>
                                <?php if ($current_booking['floor_number']): ?>
                                    • Floor <?php echo $current_booking['floor_number']; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div style="background: #f9fafb; padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem;">
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Monthly Rate:</strong> <?php echo format_currency($current_booking['price']); ?>
                        </p>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Start Date:</strong> <?php echo format_date($current_booking['start_date']); ?>
                        </p>
                        <p style="margin-bottom: 0;">
                            <strong>Status:</strong> 
                            <span class="badge badge-success">Active</span>
                        </p>
                    </div>
                    
                    <a href="../public/room_view.php?id=<?php echo $current_booking['room_id']; ?>" 
                       class="btn btn-outline" style="width: 100%;">
                        View Room Details
                    </a>
                <?php else: ?>
                    <p style="margin-bottom: 1rem;">You don't have an active booking.</p>
                    <a href="../public/rooms.php" class="btn btn-primary" style="width: 100%;">Browse Available Rooms</a>
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
                        <div style="background: #fef3c7; padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem;">
                            <h4 style="margin-bottom: 0.5rem;">Room <?php echo htmlspecialchars($pending['room_number']); ?></h4>
                            <p style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($pending['room_type']); ?> • 
                                <?php echo format_currency($pending['price']); ?>/mo
                            </p>
                            <p style="margin-bottom: 0; font-size: 0.875rem; color: var(--text-light);">
                                Requested on <?php echo format_date($pending['created_at']); ?>
                            </p>
                            <span class="badge badge-warning" style="margin-top: 0.5rem;">Awaiting Approval</span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No pending booking requests.</p>
                    <a href="../public/rooms.php" class="btn btn-outline" style="width: 100%; margin-top: 1rem;">
                        Browse Rooms
                    </a>
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
                <p>No payment records yet.</p>
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
                <a href="profile.php" class="btn btn-outline">
                    👤 Update Profile
                </a>
                <a href="payments.php" class="btn btn-outline">
                    💳 View Payments
                </a>
                <a href="../logout.php" class="btn btn-secondary">
                    🚪 Logout
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
                <p>No announcements at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>