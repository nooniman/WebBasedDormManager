<?php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Tenant Portal';
require_once '../includes/header.php';

$tenant_id = $_SESSION['user_id'];

// Get tenant's current booking
$booking_query = "
    SELECT b.*, r.room_number, r.room_type, r.price 
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

<div class="container">
    <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
    
    <div class="grid grid-2 mb-4">
        <!-- Current Booking -->
        <div class="card">
            <div class="card-header">
                <h2>Current Booking</h2>
            </div>
            <div class="card-body">
                <?php if ($current_booking): ?>
                    <p><strong>Room:</strong> <?php echo htmlspecialchars($current_booking['room_number']); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($current_booking['room_type']); ?></p>
                    <p><strong>Monthly Rate:</strong> <?php echo format_currency($current_booking['price']); ?></p>
                    <p><strong>Start Date:</strong> <?php echo format_date($current_booking['start_date']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge badge-success">Active</span>
                    </p>
                <?php else: ?>
                    <p>You don't have an active booking.</p>
                    <a href="../public/rooms.php" class="btn btn-primary mt-2">Browse Available Rooms</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="card-body">
                <a href="../public/rooms.php" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">Browse Rooms</a>
                <a href="profile.php" class="btn btn-outline" style="width: 100%; margin-bottom: 1rem;">Update Profile</a>
                <a href="../logout.php" class="btn btn-secondary" style="width: 100%;">Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Recent Payments -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Recent Payments</h2>
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
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo format_date($payment['payment_date']); ?></td>
                                    <td><?php echo format_currency($payment['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
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
