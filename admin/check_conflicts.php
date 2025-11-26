<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';

$page_title = 'Check Booking Conflicts';

if (!isset($_GET['booking_id'])) {
    set_flash_message('Invalid booking ID', 'error');
    redirect('admin/bookings');
}

$booking_id = intval($_GET['booking_id']);

// Get booking details
$stmt = $conn->prepare("
    SELECT b.*, r.room_number, r.room_type, 
           u.first_name, u.last_name, u.email
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.tenant_id = u.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    set_flash_message('Booking not found', 'error');
    redirect('admin/bookings');
}

// Detect conflicts
$conflicts = detect_booking_conflicts(
    $conn,
    $booking['room_id'],
    $booking['start_date'],
    $booking['end_date']
);

require_once '../includes/header.php';
?>

<style>
.conflict-container {
    max-width: 1000px;
    margin: 2rem auto;
}

.booking-summary {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.conflict-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid #dc3545;
}

.conflict-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e0e0e0;
}

.conflict-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.timeline-visual {
    margin: 1rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.timeline-bar {
    height: 40px;
    border-radius: 4px;
    margin: 0.5rem 0;
    position: relative;
    display: flex;
    align-items: center;
    padding: 0 1rem;
    color: white;
    font-weight: 600;
}

.timeline-bar.current {
    background: #2196F3;
}

.timeline-bar.conflict {
    background: #dc3545;
}

.no-conflicts {
    text-align: center;
    padding: 3rem;
    background: #d4edda;
    border-radius: 8px;
    border: 2px dashed #28a745;
}

.no-conflicts-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.conflict-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 4px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    max-width: 500px;
    width: 90%;
    border-radius: 8px;
    padding: 2rem;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}
</style>

<div class="container conflict-container">
    <div class="page-header">
        <h1>⚠️ Conflict Check: Booking #<?php echo $booking['id']; ?></h1>
        <a href="view_booking?id=<?php echo $booking_id; ?>" class="btn btn-secondary">← Back</a>
    </div>

    <!-- Current Booking Summary -->
    <div class="booking-summary">
        <h3>📋 Pending Booking Details</h3>
        <div class="conflict-details">
            <div>
                <strong>Room:</strong> Room <?php echo htmlspecialchars($booking['room_number']); ?>
            </div>
            <div>
                <strong>Tenant:</strong> <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
            </div>
            <div>
                <strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
            </div>
            <div>
                <strong>End Date:</strong> 
                <?php echo $booking['end_date'] ? date('M d, Y', strtotime($booking['end_date'])) : 'Open-ended'; ?>
            </div>
        </div>

        <!-- Visual Timeline -->
        <div class="timeline-visual">
            <h4>Timeline Visualization</h4>
            <div class="timeline-bar current">
                📅 Pending Booking: <?php echo date('M d', strtotime($booking['start_date'])); ?> - 
                <?php echo $booking['end_date'] ? date('M d', strtotime($booking['end_date'])) : 'Ongoing'; ?>
            </div>
            <?php foreach ($conflicts as $conflict): ?>
                <div class="timeline-bar conflict">
                    🚫 Conflict: <?php echo date('M d', strtotime($conflict['start_date'])); ?> - 
                    <?php echo $conflict['end_date'] ? date('M d', strtotime($conflict['end_date'])) : 'Ongoing'; ?>
                    (<?php echo htmlspecialchars($conflict['first_name'] . ' ' . $conflict['last_name']); ?>)
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (count($conflicts) > 0): ?>
        <!-- Conflicts Found -->
        <div class="conflict-warning">
            <span style="font-size: 2rem;">⚠️</span>
            <div>
                <strong>Warning: <?php echo count($conflicts); ?> Conflict(s) Detected</strong>
                <p style="margin: 0.25rem 0 0 0;">
                    This booking overlaps with <?php echo count($conflicts); ?> existing approved booking(s) for the same room.
                </p>
            </div>
        </div>

        <!-- Conflict Details -->
        <?php foreach ($conflicts as $conflict): ?>
            <div class="conflict-card">
                <div class="conflict-header">
                    <div>
                        <h4 style="margin: 0;">Conflicting Booking #<?php echo $conflict['id']; ?></h4>
                        <span class="status-badge status-<?php echo $conflict['status']; ?>">
                            <?php echo ucfirst($conflict['status']); ?>
                        </span>
                    </div>
                    <a href="view_booking?id=<?php echo $conflict['id']; ?>" 
                       class="btn btn-sm btn-secondary" target="_blank">
                        View Details
                    </a>
                </div>

                <div class="conflict-details">
                    <div>
                        <strong>Tenant:</strong><br>
                        <?php echo htmlspecialchars($conflict['first_name'] . ' ' . $conflict['last_name']); ?>
                    </div>
                    <div>
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($conflict['email']); ?>
                    </div>
                    <div>
                        <strong>Start Date:</strong><br>
                        <?php echo date('M d, Y', strtotime($conflict['start_date'])); ?>
                    </div>
                    <div>
                        <strong>End Date:</strong><br>
                        <?php echo $conflict['end_date'] ? date('M d, Y', strtotime($conflict['end_date'])) : 'Open-ended'; ?>
                    </div>
                    <div>
                        <strong>Duration:</strong><br>
                        <?php echo $conflict['duration_months'] ?? 'N/A'; ?> month(s)
                    </div>
                    <div>
                        <strong>Status:</strong><br>
                        <span class="status-badge status-<?php echo $conflict['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $conflict['status'])); ?>
                        </span>
                    </div>
                </div>

                <!-- Overlap Period -->
                <?php
                $overlap_start = max($booking['start_date'], $conflict['start_date']);
                $overlap_end = min(
                    $booking['end_date'] ?? '9999-12-31',
                    $conflict['end_date'] ?? '9999-12-31'
                );
                ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 4px;">
                    <strong>📅 Overlap Period:</strong>
                    <?php echo date('M d, Y', strtotime($overlap_start)); ?> to 
                    <?php echo $overlap_end !== '9999-12-31' ? date('M d, Y', strtotime($overlap_end)) : 'Ongoing'; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Actions -->
        <div class="card">
            <div class="card-body">
                <h4>⚠️ Resolution Required</h4>
                <p>You cannot approve this booking until the conflicts are resolved. Possible actions:</p>
                <ul>
                    <li>Reject this booking and notify the tenant</li>
                    <li>Contact the tenant to modify the booking dates</li>
                    <li>Cancel or modify the conflicting booking(s)</li>
                    <li>Assign the tenant to a different room</li>
                </ul>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button onclick="showRejectModal(<?php echo $booking_id; ?>)" 
                            class="btn btn-danger" style="flex: 1;">
                        ✗ Reject Booking
                    </button>
                    <a href="edit_booking?id=<?php echo $booking_id; ?>" 
                       class="btn btn-warning" style="flex: 1;">
                        ✏️ Edit Dates
                    </a>
                    <a href="bookings" class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- No Conflicts -->
        <div class="no-conflicts">
            <div class="no-conflicts-icon">✅</div>
            <h3>No Conflicts Detected!</h3>
            <p>This booking does not overlap with any existing approved bookings for this room.</p>
            <p style="margin-top: 1rem;">
                <strong>You can safely approve this booking.</strong>
            </p>

            <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">
                <form method="POST" action="bookings" style="flex: 1;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        ✓ Approve Booking
                    </button>
                </form>
                <a href="view_booking?id=<?php echo $booking_id; ?>" 
                   class="btn btn-secondary" style="flex: 1;">
                    Back to Details
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeRejectModal()">×</button>
        <h3>Reject Booking</h3>
        <form method="POST" action="bookings">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            
            <div class="form-group">
                <label class="form-label">Reason for Rejection *</label>
                <textarea name="rejection_reason" 
                          class="form-control" 
                          rows="4" 
                          required 
                          placeholder="Due to booking conflicts with existing reservations..."></textarea>
            </div>
            
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-danger" style="flex: 1;">Reject Booking</button>
                <button type="button" onclick="closeRejectModal()" class="btn btn-secondary" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(bookingId) {
    document.getElementById('rejectModal').classList.add('show');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('show');
}

document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>