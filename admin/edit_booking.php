<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';

$page_title = 'Edit Booking';

// Get booking ID
if (!isset($_GET['id'])) {
    set_flash_message('Invalid booking ID', 'error');
    redirect('admin/bookings');
}

$booking_id = intval($_GET['id']);

// Fetch booking details
$stmt = $conn->prepare("
    SELECT b.*, r.room_number, u.first_name, u.last_name 
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.tenant_id = u.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Booking not found', 'error');
    redirect('admin/bookings');
}

$booking = $result->fetch_assoc();
$stmt->close();

// Get all available rooms
$rooms_query = "SELECT id, room_number, room_type, price FROM rooms ORDER BY room_number";
$rooms_result = $conn->query($rooms_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $start_date = sanitize_input($_POST['start_date']);
        $end_date = !empty($_POST['end_date']) ? sanitize_input($_POST['end_date']) : null;
        $notes = sanitize_input($_POST['notes']);
        
        // Calculate new duration and total
        $room_price_query = "SELECT price FROM rooms WHERE id = ?";
        $price_stmt = $conn->prepare($room_price_query);
        $price_stmt->bind_param("i", $booking['room_id']);
        $price_stmt->execute();
        $room_price = $price_stmt->get_result()->fetch_assoc()['price'];
        $price_stmt->close();
        
        if ($end_date) {
            $calculation = calculate_booking_total($start_date, $end_date, $room_price);
            $duration = $calculation['months'];
            $total = $calculation['total'];
        } else {
            $duration = null;
            $total = null;
        }
        
        // Update booking
        $update_stmt = $conn->prepare("
            UPDATE bookings 
            SET start_date = ?, end_date = ?, duration_months = ?, total_amount = ?, notes = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("ssidsi", $start_date, $end_date, $duration, $total, $notes, $booking_id);
        
        if ($update_stmt->execute()) {
            set_flash_message('Booking updated successfully', 'success');
            redirect('admin/view_booking?id=' . $booking_id);
        } else {
            $error = 'Failed to update booking';
        }
        
        $update_stmt->close();
    }
}

require_once '../includes/header.php';
?>

<style>
.edit-container {
    max-width: 800px;
    margin: 2rem auto;
}

.form-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.form-section h3 {
    margin-top: 0;
    color: var(--primary-color);
    padding-bottom: 1rem;
    border-bottom: 2px solid #e0e0e0;
}

.info-display {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1.5rem;
}
</style>

<div class="container edit-container">
    <div class="page-header">
        <h1>✏️ Edit Booking</h1>
        <a href="view_booking?id=<?php echo $booking_id; ?>" class="btn btn-secondary">← Back</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="flash-message flash-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="form-section">
        <h3>Booking Information</h3>
        
        <div class="info-display">
            <div class="info-row">
                <strong>Booking ID:</strong>
                <span>#<?php echo $booking['id']; ?></span>
            </div>
            <div class="info-row">
                <strong>Room:</strong>
                <span>Room <?php echo htmlspecialchars($booking['room_number']); ?></span>
            </div>
            <div class="info-row">
                <strong>Tenant:</strong>
                <span><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
            </div>
            <div class="info-row">
                <strong>Status:</strong>
                <span class="status-badge status-<?php echo $booking['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                </span>
            </div>
        </div>

        <?php if ($booking['status'] !== 'pending'): ?>
        <div class="warning-box">
            ⚠️ <strong>Warning:</strong> This booking has already been <?php echo $booking['status']; ?>. 
            Changes may affect existing records and payments.
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-group">
                <label class="form-label" for="start_date">Start Date *</label>
                <input type="date" 
                       id="start_date" 
                       name="start_date" 
                       class="form-control" 
                       value="<?php echo $booking['start_date']; ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" 
                       id="end_date" 
                       name="end_date" 
                       class="form-control" 
                       value="<?php echo $booking['end_date'] ?? ''; ?>">
                <small style="color: #666;">Leave empty for open-ended booking</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" 
                          name="notes" 
                          class="form-control" 
                          rows="4"><?php echo htmlspecialchars($booking['notes'] ?? ''); ?></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    💾 Save Changes
                </button>
                <a href="view_booking?id=<?php echo $booking_id; ?>" 
                   class="btn btn-secondary" style="flex: 1;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>