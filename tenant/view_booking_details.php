<?php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Booking Details';
$tenant_id = $_SESSION['user_id'];

// Get booking ID
if (!isset($_GET['id'])) {
    set_flash_message('Invalid booking ID', 'error');
    redirect('bookings.php');
}

$booking_id = intval($_GET['id']);

// Fetch booking details - ensure it belongs to this tenant
$query = "
    SELECT b.*, r.room_number, r.room_type, r.price, r.capacity, r.floor_number, r.description,
           r.has_wifi, r.has_ac, r.has_bathroom
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.id = ? AND b.tenant_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Booking not found', 'error');
    redirect('bookings.php');
}

$booking = $result->fetch_assoc();
$stmt->close();

require_once '../includes/header.php';
?>

<style>
.detail-container {
    max-width: 900px;
    margin: 2rem auto;
}

.detail-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.card-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--primary-color), #1976d2);
    color: white;
    border-radius: 8px 8px 0 0;
}

.card-body {
    padding: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.info-value.large {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.status-timeline {
    border-left: 2px solid #e0e0e0;
    padding-left: 2rem;
    margin: 2rem 0;
}

.timeline-event {
    position: relative;
    padding-bottom: 2rem;
}

.timeline-event::before {
    content: '';
    position: absolute;
    left: -2.25rem;
    top: 0;
    width: 12px;
    height: 12px;
    background: var(--primary-color);
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 0 2px var(--primary-color);
}

.timeline-event:last-child {
    padding-bottom: 0;
}

.timeline-date {
    font-size: 0.85rem;
    color: #999;
    margin-bottom: 0.25rem;
}

.timeline-content {
    font-weight: 600;
    color: #333;
}

.amenities-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.amenity-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
}

.note-box {
    background: #fffbf0;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 4px;
    margin: 1rem 0;
}

.rejection-box {
    background: #fee;
    border-left: 4px solid #dc3545;
    padding: 1rem;
    border-radius: 4px;
    margin: 1rem 0;
}
</style>

<div class="container detail-container">
    <div class="page-header">
        <h1>📋 Booking Details</h1>
        <a href="bookings.php" class="btn btn-secondary">← Back to My Bookings</a>
    </div>

    <!-- Booking Status Card -->
    <div class="detail-card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">Booking #<?php echo $booking['id']; ?></div>
                    <h2 style="margin: 0.25rem 0 0 0;">Room <?php echo htmlspecialchars($booking['room_number']); ?></h2>
                </div>
                <span class="status-badge status-<?php echo $booking['status']; ?>" style="font-size: 1rem;">
                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Room Type</div>
                    <div class="info-value"><?php echo ucfirst($booking['room_type']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Capacity</div>
                    <div class="info-value"><?php echo $booking['capacity']; ?> person(s)</div>
                </div>
                <?php if ($booking['floor_number']): ?>
                <div class="info-item">
                    <div class="info-label">Floor</div>
                    <div class="info-value"><?php echo $booking['floor_number']; ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Start Date</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($booking['start_date'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">End Date</div>
                    <div class="info-value">
                        <?php echo $booking['end_date'] ? date('F d, Y', strtotime($booking['end_date'])) : 'Open-ended'; ?>
                    </div>
                </div>
                <?php if ($booking['duration_months']): ?>
                <div class="info-item">
                    <div class="info-label">Duration</div>
                    <div class="info-value"><?php echo $booking['duration_months']; ?> month(s)</div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Monthly Rate</div>
                    <div class="info-value">₱<?php echo number_format($booking['price'], 2); ?></div>
                </div>
                <?php if ($booking['total_amount']): ?>
                <div class="info-item">
                    <div class="info-label">Total Amount</div>
                    <div class="info-value large">₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Room Amenities -->
            <?php if ($booking['has_wifi'] || $booking['has_ac'] || $booking['has_bathroom']): ?>
            <div style="margin-top: 2rem;">
                <strong>Room Amenities:</strong>
                <div class="amenities-list">
                    <?php if ($booking['has_wifi']): ?>
                        <span class="amenity-badge">📶 WiFi</span>
                    <?php endif; ?>
                    <?php if ($booking['has_ac']): ?>
                        <span class="amenity-badge">❄️ Air Conditioning</span>
                    <?php endif; ?>
                    <?php if ($booking['has_bathroom']): ?>
                        <span class="amenity-badge">🚿 Private Bathroom</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($booking['description']): ?>
            <div style="margin-top: 2rem;">
                <strong>Room Description:</strong>
                <p style="color: #666; margin: 0.5rem 0 0 0;">
                    <?php echo nl2br(htmlspecialchars($booking['description'])); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline Card -->
    <div class="detail-card">
        <div class="card-header">
            <h3 style="margin: 0;">📅 Booking Timeline</h3>
        </div>
        <div class="card-body">
            <div class="status-timeline">
                <div class="timeline-event">
                    <div class="timeline-date">
                        <?php echo date('F d, Y g:i A', strtotime($booking['created_at'])); ?>
                    </div>
                    <div class="timeline-content">Booking request submitted</div>
                </div>

                <?php if ($booking['approved_at']): ?>
                <div class="timeline-event">
                    <div class="timeline-date">
                        <?php echo date('F d, Y g:i A', strtotime($booking['approved_at'])); ?>
                    </div>
                    <div class="timeline-content">
                        Booking <?php echo $booking['status']; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($booking['check_in_date']): ?>
                <div class="timeline-event">
                    <div class="timeline-date">
                        <?php echo date('F d, Y', strtotime($booking['check_in_date'])); ?>
                    </div>
                    <div class="timeline-content">Checked in</div>
                </div>
                <?php endif; ?>

                <?php if ($booking['check_out_date']): ?>
                <div class="timeline-event">
                    <div class="timeline-date">
                        <?php echo date('F d, Y', strtotime($booking['check_out_date'])); ?>
                    </div>
                    <div class="timeline-content">Checked out</div>
                </div>
                <?php endif; ?>

                <?php if ($booking['status'] === 'pending'): ?>
                <div class="timeline-event">
                    <div class="timeline-content" style="color: #ffc107;">
                        ⏳ Waiting for admin approval
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <?php if ($booking['notes']): ?>
    <div class="note-box">
        <strong>📝 Your Notes:</strong>
        <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Rejection Reason -->
    <?php if ($booking['rejected_reason']): ?>
    <div class="rejection-box">
        <strong>❌ Rejection Reason:</strong>
        <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($booking['rejected_reason'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <?php if (in_array($booking['status'], ['pending', 'approved'])): ?>
    <div style="text-align: center; margin-top: 2rem;">
        <form method="POST" action="bookings.php" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
            <input type="hidden" name="cancel_booking" value="1">
            <button type="submit" 
                    onclick="return confirm('Are you sure you want to cancel this booking?');"
                    class="btn btn-danger">
                🚫 Cancel This Booking
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>