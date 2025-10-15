<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Bookings';
require_once '../includes/header.php';

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $booking_id = intval($_POST['booking_id']);
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            $message = 'Booking approved successfully';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            $message = 'Booking rejected';
        }
        
        if (isset($stmt) && $stmt->execute()) {
            set_flash_message($message, 'success');
        }
        
        if (isset($stmt)) $stmt->close();
        redirect('bookings.php');
    }
}

// Fetch all bookings
$query = "
    SELECT b.*, r.room_number, u.first_name, u.last_name, u.email 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.tenant_id = u.id 
    ORDER BY b.created_at DESC
";
$result = $conn->query($query);
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Booking Requests</h2>
        </div>
        <div class="card-body">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tenant</th>
                                <th>Email</th>
                                <th>Room</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Requested On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['email']); ?></td>
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
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <form method="POST" action="" style="display: inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No booking requests found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
