<?php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Book a Room';
require_once '../includes/header.php';

// Get room details
if (!isset($_GET['room_id'])) {
    redirect('rooms.php');
}

$room_id = intval($_GET['room_id']);
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'available'");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Room not available', 'error');
    redirect('rooms.php');
}

$room = $result->fetch_assoc();

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $tenant_id = $_SESSION['user_id'];
        $start_date = sanitize_input($_POST['start_date']);
        $notes = sanitize_input($_POST['notes']);
        
        // Insert booking
        $insert_stmt = $conn->prepare("INSERT INTO bookings (room_id, tenant_id, start_date, status, notes, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())");
        $insert_stmt->bind_param("iiss", $room_id, $tenant_id, $start_date, $notes);
        
        if ($insert_stmt->execute()) {
            set_flash_message('Booking request submitted successfully!', 'success');
            redirect('../tenant/portal.php');
        } else {
            $error = 'Failed to submit booking request';
        }
        
        $insert_stmt->close();
    }
}

$stmt->close();
?>

<div class="container">
    <div class="card" style="max-width: 700px; margin: 2rem auto;">
        <div class="card-header">
            <h2>Book Room <?php echo htmlspecialchars($room['room_number']); ?></h2>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="flash-message flash-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <p><strong>Room Type:</strong> <?php echo htmlspecialchars($room['room_type']); ?></p>
                <p><strong>Capacity:</strong> <?php echo htmlspecialchars($room['capacity']); ?> person(s)</p>
                <p><strong>Price:</strong> <?php echo format_currency($room['price']); ?>/month</p>
            </div>
            
            <form method="POST" action="" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="start_date">Preferred Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Additional Notes (Optional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Submit Booking Request</button>
                    <a href="rooms.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
