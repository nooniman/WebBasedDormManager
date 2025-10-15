<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Admin Dashboard';
require_once '../includes/header.php';

// Get statistics
$stats = [];

// Total tenants
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tenant'");
$stats['total_tenants'] = $result->fetch_assoc()['count'];

// Total rooms
$result = $conn->query("SELECT COUNT(*) as count FROM rooms");
$stats['total_rooms'] = $result->fetch_assoc()['count'];

// Available rooms
$result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
$stats['available_rooms'] = $result->fetch_assoc()['count'];

// Pending bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
$stats['pending_bookings'] = $result->fetch_assoc()['count'];

// Total revenue (this month)
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
$stats['monthly_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Recent bookings
$recent_bookings = $conn->query("
    SELECT b.*, r.room_number, u.first_name, u.last_name 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.tenant_id = u.id 
    ORDER BY b.created_at DESC 
    LIMIT 5
");
?>

<div class="container">
    <h1 class="mb-4">Admin Dashboard</h1>
    
    <!-- Statistics Cards -->
    <div class="grid grid-4 mb-4">
        <div class="stat-card" style="border-left-color: #3b82f6;">
            <h3><?php echo $stats['total_tenants']; ?></h3>
            <p>Total Tenants</p>
        </div>
        
        <div class="stat-card" style="border-left-color: #10b981;">
            <h3><?php echo $stats['available_rooms']; ?>/<?php echo $stats['total_rooms']; ?></h3>
            <p>Available Rooms</p>
        </div>
        
        <div class="stat-card" style="border-left-color: #f59e0b;">
            <h3><?php echo $stats['pending_bookings']; ?></h3>
            <p>Pending Bookings</p>
        </div>
        
        <div class="stat-card" style="border-left-color: #8b5cf6;">
            <h3><?php echo format_currency($stats['monthly_revenue']); ?></h3>
            <p>Monthly Revenue</p>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <div class="card">
        <div class="card-header">
            <h2>Recent Booking Requests</h2>
        </div>
        <div class="card-body">
            <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Room</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Date Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                    <td>Room <?php echo htmlspecialchars($booking['room_number']); ?></td>
                                    <td><?php echo format_date($booking['start_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $booking['status'] === 'approved' ? 'success' : 
                                                ($booking['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($booking['created_at']); ?></td>
                                    <td>
                                        <a href="bookings.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No recent bookings.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
