<?php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'My Bookings';
$tenant_id = $_SESSION['user_id'];

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $booking_id = intval($_POST['booking_id']);
        
        // Verify booking belongs to this tenant and can be cancelled
        $check_stmt = $conn->prepare("
            SELECT status FROM bookings 
            WHERE id = ? AND tenant_id = ? AND status IN ('pending', 'approved')
        ");
        $check_stmt->bind_param("ii", $booking_id, $tenant_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $cancel_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $cancel_stmt->bind_param("i", $booking_id);
            
            if ($cancel_stmt->execute()) {
                set_flash_message('Booking cancelled successfully', 'success');
            } else {
                set_flash_message('Failed to cancel booking', 'error');
            }
            $cancel_stmt->close();
        } else {
            set_flash_message('Cannot cancel this booking', 'error');
        }
        $check_stmt->close();
        
        redirect('bookings.php');
    }
}

// Fetch all bookings for this tenant
$query = "
    SELECT b.*, r.room_number, r.room_type, r.price
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ?
    ORDER BY b.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in
    FROM bookings
    WHERE tenant_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $tenant_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

require_once '../includes/header.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color);
    line-height: 1;
}

.stat-label {
    color: #666;
    margin-top: 0.5rem;
    font-size: 0.9rem;
}

.bookings-list {
    display: grid;
    gap: 1.5rem;
}

.booking-item {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s;
}

.booking-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.booking-item-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--primary-color), #1976d2);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.booking-item-body {
    padding: 1.5rem;
}

.booking-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.detail-value {
    font-weight: 600;
    color: #333;
}

.booking-actions {
    display: flex;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e0e0e0;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 8px;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="container">
    <div class="page-header">
        <h1>📋 My Bookings</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="booking_calendar.php" class="btn btn-secondary">📅 Calendar View</a>
            <a href="../public/rooms.php" class="btn btn-primary">+ New Booking</a>
        </div>
    </div>

    <?php if (get_flash_message()): ?>
        <div class="flash-message flash-<?php echo get_flash_message()['type']; ?>">
            <?php echo get_flash_message()['message']; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['checked_in']; ?></div>
            <div class="stat-label">Active</div>
        </div>
    </div>

    <!-- Bookings List -->
    <?php if ($result->num_rows > 0): ?>
        <div class="bookings-list">
            <?php while ($booking = $result->fetch_assoc()): ?>
                <div class="booking-item">
                    <div class="booking-item-header">
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9;">Booking #<?php echo $booking['id']; ?></div>
                            <h3 style="margin: 0.25rem 0 0 0;">Room <?php echo htmlspecialchars($booking['room_number']); ?></h3>
                        </div>
                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                        </span>
                    </div>
                    
                    <div class="booking-item-body">
                        <div class="booking-details">
                            <div class="detail-item">
                                <div class="detail-label">Room Type</div>
                                <div class="detail-value"><?php echo ucfirst($booking['room_type']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Start Date</div>
                                <div class="detail-value">
                                    <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">End Date</div>
                                <div class="detail-value">
                                    <?php echo $booking['end_date'] ? date('M d, Y', strtotime($booking['end_date'])) : 'Open-ended'; ?>
                                </div>
                            </div>
                            <?php if ($booking['duration_months']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Duration</div>
                                <div class="detail-value"><?php echo $booking['duration_months']; ?> month(s)</div>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <div class="detail-label">Monthly Rate</div>
                                <div class="detail-value">₱<?php echo number_format($booking['price'], 2); ?></div>
                            </div>
                            <?php if ($booking['total_amount']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Total Amount</div>
                                <div class="detail-value" style="color: var(--primary-color); font-size: 1.2rem;">
                                    ₱<?php echo number_format($booking['total_amount'], 2); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($booking['notes']): ?>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-top: 1rem;">
                            <strong>Your Notes:</strong>
                            <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($booking['rejected_reason']): ?>
                        <div style="background: #fee; padding: 1rem; border-radius: 6px; margin-top: 1rem; border-left: 4px solid #dc3545;">
                            <strong>Rejection Reason:</strong>
                            <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($booking['rejected_reason'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="booking-actions">
                            <a href="view_booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                                👁️ View Details
                            </a>
                            
                            <?php if (in_array($booking['status'], ['pending', 'approved'])): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="cancel_booking" value="1">
                                    <button type="submit" 
                                            onclick="return confirm('Are you sure you want to cancel this booking?');"
                                            class="btn btn-danger">
                                        🚫 Cancel Booking
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h3>No Bookings Yet</h3>
            <p>You haven't made any booking requests yet.</p>
            <a href="../public/rooms.php" class="btn btn-primary">Browse Available Rooms</a>
        </div>
    <?php endif; ?>
</div>

<?php
$stmt->close();
require_once '../includes/footer.php';
?>