<?php
// public/booking.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';

$page_title = 'Book a Room';
$error = '';
$success = '';

// Get room details
if (!isset($_GET['room_id'])) {
    redirect('rooms.php');
}

$room_id = intval($_GET['room_id']);
// FIXED: Changed price_per_month to price
$stmt = $conn->prepare("
    SELECT id, room_number, room_type, capacity, price, status, 
           description, floor_number, category, has_wifi, has_ac, has_bathroom 
    FROM rooms 
    WHERE id = ? AND status = 'available'
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Room not available', 'error');
    redirect('rooms.php');
}

$room = $result->fetch_assoc();
$stmt->close();

// Debug: Check if price exists
if (!isset($room['price']) || $room['price'] === null) {
    error_log("Price missing for room ID: " . $room_id);
    $error = 'Room pricing information is not available. Please contact administration.';
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $tenant_id = $_SESSION['user_id'];
        $start_date = sanitize_input($_POST['start_date']);
        $end_date = sanitize_input($_POST['end_date']);
        $notes = sanitize_input($_POST['notes']);
        
        // Validate dates
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $start->setTime(0, 0, 0);
        
        if ($start < $today) {
            $error = 'Start date cannot be in the past';
        } elseif ($end <= $start) {
            $error = 'End date must be after start date';
        } elseif (!isset($room['price']) || $room['price'] <= 0) {
            $error = 'Invalid room pricing. Please contact administration.';
        } else {
            // Check availability
            if (!check_room_availability($conn, $room_id, $start_date, $end_date)) {
                $error = 'Room is not available for the selected dates';
            } else {
                // Calculate duration and total using 'price' instead of 'price_per_month'
                $calculation = calculate_booking_total($start_date, $end_date, $room['price']);
                
                // Insert booking
                $insert_stmt = $conn->prepare("
                    INSERT INTO bookings 
                    (room_id, tenant_id, start_date, end_date, duration_months, total_amount, status, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $insert_stmt->bind_param("iissids", $room_id, $tenant_id, $start_date, $end_date, 
                    $calculation['months'], $calculation['total'], $notes);
                
                if ($insert_stmt->execute()) {
                    $booking_id = $insert_stmt->insert_id;
                    
                    // Create notification for admins
                    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    
                    while ($admin = $admin_result->fetch_assoc()) {
                        create_notification(
                            $conn,
                            $admin['id'],
                            'booking',
                            'New Booking Request',
                            'New booking request for Room ' . $room['room_number'],
                            $booking_id,
                            'booking'
                        );
                    }
                    $admin_stmt->close();
                    
                    set_flash_message('Booking request submitted successfully!', 'success');
                    redirect('../tenant/bookings.php');
                } else {
                    $error = 'Failed to submit booking request';
                }
                
                $insert_stmt->close();
            }
        }
    }
}

require_once '../includes/header.php';
?>

<style>
.booking-card {
    max-width: 800px;
    margin: 2rem auto;
}

.room-summary {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.room-summary h3 {
    margin-top: 0;
    color: var(--primary-color);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.summary-item {
    display: flex;
    flex-direction: column;
}

.summary-label {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.summary-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.date-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.booking-summary {
    background: #e3f2fd;
    padding: 1rem;
    border-radius: 6px;
    margin: 1rem 0;
    display: none;
}

.booking-summary.show {
    display: block;
}

.booking-summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #bbdefb;
}

.booking-summary-item:last-child {
    border-bottom: none;
    font-weight: bold;
    font-size: 1.2rem;
    color: var(--primary-color);
}

.availability-notice {
    padding: 1rem;
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 4px;
    margin: 1rem 0;
}

@media (max-width: 768px) {
    .date-inputs {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="card booking-card">
        <div class="card-header">
            <h2>Book Room</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="flash-message flash-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Room Summary -->
            <div class="room-summary">
                <h3>Room Details</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Room Number</span>
                        <span class="summary-value"><?php echo htmlspecialchars($room['room_number']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Type</span>
                        <span class="summary-value"><?php echo ucfirst($room['room_type']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Capacity</span>
                        <span class="summary-value"><?php echo $room['capacity']; ?> person(s)</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Price per Month</span>
                        <span class="summary-value">
                            ₱<?php echo number_format($room['price'] ?? 0, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="availability-notice">
                <strong>📅 Booking Information:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>Minimum booking period: 1 month</li>
                    <li>Check availability before submitting</li>
                    <li>You will receive a notification when your booking is reviewed</li>
                </ul>
            </div>
            
            <!-- Booking Form -->
            <form method="POST" action="" id="bookingForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="date-inputs">
                    <div class="form-group">
                        <label class="form-label" for="start_date">Start Date *</label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date" 
                               class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="end_date">End Date *</label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date" 
                               class="form-control" 
                               min="<?php echo date('Y-m-d', strtotime('+1 month')); ?>"
                               required>
                    </div>
                </div>
                
                <!-- Booking Summary -->
                <div id="bookingSummary" class="booking-summary">
                    <h4>Booking Summary</h4>
                    <div class="booking-summary-item">
                        <span>Duration:</span>
                        <span id="duration">-</span>
                    </div>
                    <div class="booking-summary-item">
                        <span>Monthly Rate:</span>
                        <span>₱<?php echo number_format($room['price'] ?? 0, 2); ?></span>
                    </div>
                    <div class="booking-summary-item">
                        <span>Total Amount:</span>
                        <span id="totalAmount">₱0.00</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Additional Notes (Optional)</label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control" 
                              rows="4" 
                              placeholder="Any special requests or requirements..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        Submit Booking Request
                    </button>
                    <a href="rooms.php" class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const pricePerMonth = <?php echo json_encode($room['price'] ?? 0); ?>;
const roomId = <?php echo $room_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const bookingSummary = document.getElementById('bookingSummary');
    const durationSpan = document.getElementById('duration');
    const totalAmountSpan = document.getElementById('totalAmount');
    
    function calculateBooking() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            if (end > start) {
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const months = Math.ceil(diffDays / 30);
                const total = months * pricePerMonth;
                
                durationSpan.textContent = months + ' month' + (months > 1 ? 's' : '');
                totalAmountSpan.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                bookingSummary.classList.add('show');
            } else {
                bookingSummary.classList.remove('show');
            }
        }
    }
    
    startDateInput.addEventListener('change', function() {
        const startDate = new Date(this.value);
        const minEndDate = new Date(startDate);
        minEndDate.setMonth(minEndDate.getMonth() + 1);
        endDateInput.min = minEndDate.toISOString().split('T')[0];
        calculateBooking();
    });
    
    endDateInput.addEventListener('change', calculateBooking);
    
    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (startDate < today) {
            e.preventDefault();
            alert('Start date cannot be in the past');
            return false;
        }
        
        if (endDate <= startDate) {
            e.preventDefault();
            alert('End date must be after start date');
            return false;
        }
        
        if (!pricePerMonth || pricePerMonth <= 0) {
            e.preventDefault();
            alert('Room pricing is not available. Please contact administration.');
            return false;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>