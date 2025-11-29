<?php
// filepath: public/booking.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';
require_once '../includes/bedspace_functions.php';

$page_title = 'Book a Room';
$error = '';
$success = '';

// Get room details
if (!isset($_GET['room_id'])) {
    redirect('public/rooms');
}

$room_id = intval($_GET['room_id']);
$stmt = $conn->prepare("
    SELECT id, room_number, room_type, capacity, price, status, 
           description, floor_number, category, has_wifi, has_ac, has_bathroom,
           is_bedspace, total_bedspaces, occupied_bedspaces, price_per_bedspace,
           (SELECT photo_path FROM room_photos WHERE room_id = rooms.id AND is_primary = 1 LIMIT 1) as primary_photo
    FROM rooms 
    WHERE id = ? AND status = 'available'
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Room not available', 'error');
    redirect('public/rooms');
}

$room = $result->fetch_assoc();
$stmt->close();

// Get available bedspaces if this is a bedspace room
$available_bedspaces = [];
if ($room['is_bedspace']) {
    $available_bedspaces = get_available_bedspaces($conn, $room_id);
    if (empty($available_bedspaces)) {
        set_flash_message('No bedspaces available in this room', 'error');
        redirect('public/rooms');
    }
}

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
        $bedspace_id = $room['is_bedspace'] && isset($_POST['bedspace_id']) ? intval($_POST['bedspace_id']) : null;
        
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
        } elseif ($room['is_bedspace'] && !$bedspace_id) {
            $error = 'Please select a bedspace';
        } elseif (!isset($room['price']) || $room['price'] <= 0) {
            $error = 'Invalid room pricing. Please contact administration.';
        } else {
            // For bedspace rooms, check bedspace availability
            if ($room['is_bedspace']) {
                if (!is_bedspace_available($conn, $bedspace_id)) {
                    $error = 'Selected bedspace is no longer available';
                } else {
                    // Calculate total based on bedspace price
                    $calculation = calculate_booking_total($start_date, $end_date, $room['price_per_bedspace']);
                    
                    // Insert bedspace booking
                    $insert_stmt = $conn->prepare("
                        INSERT INTO bookings 
                        (room_id, bedspace_id, is_bedspace_booking, tenant_id, start_date, end_date, duration_months, total_amount, status, notes, created_at) 
                        VALUES (?, ?, 1, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                    ");
                    $insert_stmt->bind_param("iiissids", $room_id, $bedspace_id, $tenant_id, $start_date, $end_date, 
                        $calculation['months'], $calculation['total'], $notes);
                }
            } else {
                // Check room availability for regular booking
                if (!check_room_availability($conn, $room_id, $start_date, $end_date)) {
                    $error = 'Room is not available for the selected dates';
                } else {
                    // Calculate duration and total
                    $calculation = calculate_booking_total($start_date, $end_date, $room['price']);
                    
                    // Insert regular booking
                    $insert_stmt = $conn->prepare("
                        INSERT INTO bookings 
                        (room_id, tenant_id, start_date, end_date, duration_months, total_amount, status, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                    ");
                    $insert_stmt->bind_param("iissids", $room_id, $tenant_id, $start_date, $end_date, 
                        $calculation['months'], $calculation['total'], $notes);
                }
            }
            
            // Execute the booking if no errors
            if (!$error && isset($insert_stmt)) {
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
                    redirect('tenant/bookings');
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
    .booking-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Breadcrumb */
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .breadcrumb a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .breadcrumb a:hover {
        color: #764ba2;
    }
    
    .breadcrumb span {
        color: #94a3b8;
    }
    
    /* Booking Layout */
    .booking-layout {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    /* Booking Form Card */
    .booking-form-card {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .form-title {
        font-size: 2rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .form-subtitle {
        color: #64748b;
        font-size: 1.05rem;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    
    .form-section {
        margin-bottom: 2rem;
    }
    
    .form-section-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 1.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .form-group-enhanced {
        margin-bottom: 1.5rem;
    }
    
    .form-label-enhanced {
        display: block;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    
    .form-input-enhanced {
        width: 100%;
        padding: 1rem 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
        font-family: inherit;
    }
    
    .form-input-enhanced:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .form-input-enhanced:disabled {
        background: #f8fafc;
        cursor: not-allowed;
        color: #94a3b8;
    }
    
    textarea.form-input-enhanced {
        min-height: 120px;
        resize: vertical;
    }
    
    .date-inputs-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    
    .input-hint {
        font-size: 0.875rem;
        color: #64748b;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    /* Info Notice */
    .info-notice {
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
        border-left: 4px solid #3b82f6;
        border-radius: 12px;
        margin-bottom: 2rem;
    }
    
    .info-notice-title {
        font-weight: 800;
        color: #1e40af;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .info-notice ul {
        margin: 0;
        padding-left: 1.5rem;
        color: #1e3a8a;
        line-height: 1.8;
    }
    
    .info-notice li {
        margin-bottom: 0.5rem;
    }
    
    /* Booking Summary Sidebar */
    .booking-summary-card {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        position: sticky;
        top: 2rem;
        height: fit-content;
    }
    
    .summary-header {
        margin-bottom: 2rem;
    }
    
    .summary-title {
        font-size: 1.5rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
    }
    
    .summary-subtitle {
        color: #64748b;
        font-size: 0.95rem;
    }
    
    /* Room Preview */
    .room-preview {
        margin-bottom: 2rem;
        border-radius: 16px;
        overflow: hidden;
        border: 2px solid #e2e8f0;
    }
    
    .room-preview-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    
    .room-preview-placeholder {
        width: 100%;
        height: 200px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: white;
    }
    
    .room-preview-info {
        padding: 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .room-preview-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
    }
    
    .room-preview-type {
        display: inline-block;
        padding: 0.375rem 0.875rem;
        background: white;
        border: 1px solid #667eea;
        border-radius: 8px;
        color: #667eea;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
    }
    
    .room-preview-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .room-preview-feature {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        background: white;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
    }
    
    /* Summary Details */
    .summary-details {
        margin-bottom: 2rem;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 2px solid #f1f5f9;
    }
    
    .summary-row:last-child {
        border-bottom: none;
    }
    
    .summary-label {
        font-weight: 600;
        color: #64748b;
        font-size: 0.95rem;
    }
    
    .summary-value {
        font-weight: 700;
        color: #1e293b;
        font-size: 1.05rem;
    }
    
    .summary-value.highlight {
        font-size: 1.25rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Calculation Box */
    .calculation-box {
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 2px solid #667eea;
        border-radius: 16px;
        margin-bottom: 2rem;
        display: none;
    }
    
    .calculation-box.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .calculation-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #667eea;
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .calculation-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(102, 126, 234, 0.2);
    }
    
    .calculation-item:last-child {
        border-bottom: none;
        padding-top: 1rem;
        margin-top: 0.5rem;
        border-top: 2px solid rgba(102, 126, 234, 0.3);
    }
    
    .calculation-item-label {
        font-weight: 600;
        color: #475569;
    }
    
    .calculation-item-value {
        font-weight: 700;
        color: #1e293b;
    }
    
    .calculation-item:last-child .calculation-item-label,
    .calculation-item:last-child .calculation-item-value {
        font-size: 1.25rem;
        color: #667eea;
    }
    
    /* Warning Box */
    .warning-box {
        padding: 1.25rem;
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
        border-left: 4px solid #ef4444;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: start;
        gap: 0.75rem;
    }
    
    .warning-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .warning-content {
        flex: 1;
    }
    
    .warning-title {
        font-weight: 700;
        color: #991b1b;
        margin-bottom: 0.25rem;
    }
    
    .warning-text {
        color: #7f1d1d;
        font-size: 0.95rem;
        margin: 0;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .btn-booking {
        flex: 1;
        padding: 1.25rem;
        border-radius: 14px;
        font-weight: 800;
        font-size: 1.05rem;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }
    
    .btn-booking.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
    }
    
    .btn-booking.primary:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(16, 185, 129, 0.4);
    }
    
    .btn-booking.primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-booking.secondary {
        background: white;
        color: #64748b;
        border-color: #e2e8f0;
    }
    
    .btn-booking.secondary:hover {
        border-color: #ef4444;
        color: #ef4444;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .booking-layout {
            grid-template-columns: 1fr;
        }
        
        .booking-summary-card {
            position: static;
            order: -1;
        }
    }
    
    @media (max-width: 768px) {
        .date-inputs-grid {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
</style>

<div class="booking-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="<?php echo PUBLIC_URL; ?>">Home</a>
            <span>→</span>
            <a href="rooms">Rooms</a>
            <span>→</span>
            <a href="room_view?id=<?php echo $room_id; ?>">Room <?php echo htmlspecialchars($room['room_number']); ?></a>
            <span>→</span>
            <span>Book Room</span>
        </nav>
        
        <div class="booking-layout">
            <!-- Booking Form -->
            <div class="booking-form-card">
                <h1 class="form-title">
                    <span style="font-size: 2.5rem;">📝</span>
                    Book Your Room
                </h1>
                <p class="form-subtitle">
                    Complete the form below to submit your booking request. 
                    Our team will review and confirm your booking shortly.
                </p>
                
                <?php if ($error): ?>
                    <div class="warning-box">
                        <span class="warning-icon">⚠️</span>
                        <div class="warning-content">
                            <div class="warning-title">Error</div>
                            <p class="warning-text"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="info-notice">
                    <div class="info-notice-title">
                        <span>ℹ️</span>
                        <span>Important Information</span>
                    </div>
                    <ul>
                        <li>Minimum booking period is 1 month</li>
                        <li>Your booking will be pending until approved by administration</li>
                        <li>You will receive a notification once your booking is reviewed</li>
                        <li>Please ensure your selected dates are accurate</li>
                    </ul>
                </div>
                
                <form method="POST" action="" id="bookingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php if ($room['is_bedspace']): ?>
                    <!-- Bedspace Selection -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <span>🛏️</span>
                            <span>Select Your Bedspace</span>
                        </h3>
                        
                        <div class="info-notice" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-color: #059669;">
                            <div class="info-notice-title" style="color: white;">
                                <span>💡</span>
                                <span>This is a Bedspacing Room</span>
                            </div>
                            <p style="color: white; opacity: 0.95;">
                                This room offers individual bed rentals. Select your preferred bedspace below. 
                                <?php echo count($available_bedspaces); ?> bedspace(s) currently available.
                            </p>
                        </div>
                        
                        <div class="form-group-enhanced">
                            <label class="form-label-enhanced" for="bedspace_id">
                                Choose Bedspace <span style="color: #ef4444;">*</span>
                            </label>
                            <select id="bedspace_id" name="bedspace_id" class="form-input-enhanced" required>
                                <option value="">-- Select a Bedspace --</option>
                                <?php foreach ($available_bedspaces as $bs): ?>
                                    <option value="<?php echo $bs['id']; ?>">
                                        Bedspace <?php echo htmlspecialchars($bs['bedspace_number']); ?>
                                        <?php if ($bs['description']): ?>
                                            - <?php echo htmlspecialchars($bs['description']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-hint">
                                <span>📌</span>
                                <span>Each bedspace is individually rented at ₱<?php echo number_format($room['price_per_bedspace'], 0); ?>/month</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Date Selection -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <span>📅</span>
                            <span>Select Your Dates</span>
                        </h3>
                        
                        <div class="date-inputs-grid">
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced" for="start_date">
                                    Check-in Date <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" 
                                       id="start_date" 
                                       name="start_date" 
                                       class="form-input-enhanced" 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       required>
                                <div class="input-hint">
                                    <span>📌</span>
                                    <span>Cannot be in the past</span>
                                </div>
                            </div>
                            
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced" for="end_date">
                                    Check-out Date <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" 
                                       id="end_date" 
                                       name="end_date" 
                                       class="form-input-enhanced" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 month')); ?>"
                                       required>
                                <div class="input-hint">
                                    <span>📌</span>
                                    <span>Minimum 1 month from check-in</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Notes -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <span>📄</span>
                            <span>Additional Information</span>
                        </h3>
                        
                        <div class="form-group-enhanced">
                            <label class="form-label-enhanced" for="notes">
                                Special Requests or Notes (Optional)
                            </label>
                            <textarea id="notes" 
                                      name="notes" 
                                      class="form-input-enhanced" 
                                      placeholder="Any special requests, questions, or additional information you'd like to share with us..."></textarea>
                            <div class="input-hint">
                                <span>💡</span>
                                <span>Let us know if you have any specific requirements</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-booking primary" id="submitBtn" disabled>
                            <span>✓</span>
                            <span>Submit Booking Request</span>
                        </button>
                        <a href="room_view?id=<?php echo $room_id; ?>" class="btn-booking secondary">
                            <span>←</span>
                            <span>Cancel</span>
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Booking Summary Sidebar -->
            <aside>
                <div class="booking-summary-card">
                    <div class="summary-header">
                        <h2 class="summary-title">Booking Summary</h2>
                        <p class="summary-subtitle">Review your booking details</p>
                    </div>
                    
                    <!-- Room Preview -->
                    <div class="room-preview">
                        <?php if ($room['primary_photo']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($room['primary_photo']); ?>" 
                                 alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                                 class="room-preview-image">
                        <?php else: ?>
                            <div class="room-preview-placeholder">🏠</div>
                        <?php endif; ?>
                        
                        <div class="room-preview-info">
                            <h3 class="room-preview-number">Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                            <span class="room-preview-type"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></span>
                            <?php if ($room['is_bedspace']): ?>
                                <span class="room-preview-type" style="background: #10b981; color: white; margin-top: 0.5rem; display: inline-block;">
                                    🛏️ Bedspacing Room
                                </span>
                            <?php endif; ?>
                            
                            <div class="room-preview-features">
                                <?php if ($room['is_bedspace']): ?>
                                    <span class="room-preview-feature">
                                        🛏️ <?php echo $room['total_bedspaces']; ?> bedspaces
                                    </span>
                                    <span class="room-preview-feature">
                                        ✅ <?php echo count($available_bedspaces); ?> available
                                    </span>
                                <?php else: ?>
                                    <span class="room-preview-feature">
                                        👥 <?php echo $room['capacity']; ?> person(s)
                                    </span>
                                <?php endif; ?>
                                <?php if ($room['has_wifi']): ?>
                                    <span class="room-preview-feature">📶 WiFi</span>
                                <?php endif; ?>
                                <?php if ($room['has_ac']): ?>
                                    <span class="room-preview-feature">❄️ AC</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Details -->
                    <div class="summary-details">
                        <div class="summary-row">
                            <span class="summary-label"><?php echo $room['is_bedspace'] ? 'Per Bedspace' : 'Monthly Rate'; ?></span>
                            <span class="summary-value">₱<?php echo number_format($room['is_bedspace'] ? $room['price_per_bedspace'] : $room['price'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Check-in Date</span>
                            <span class="summary-value" id="checkInDisplay">-</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Check-out Date</span>
                            <span class="summary-value" id="checkOutDisplay">-</span>
                        </div>
                    </div>
                    
                    <!-- Calculation Box -->
                    <div id="calculationBox" class="calculation-box">
                        <h4 class="calculation-title">
                            <span>🧮</span>
                            <span>Cost Breakdown</span>
                        </h4>
                        <div class="calculation-item">
                            <span class="calculation-item-label">Duration</span>
                            <span class="calculation-item-value" id="durationDisplay">-</span>
                        </div>
                        <div class="calculation-item">
                            <span class="calculation-item-label"><?php echo $room['is_bedspace'] ? 'Per Bedspace' : 'Monthly Rate'; ?></span>
                            <span class="calculation-item-value">₱<?php echo number_format($room['is_bedspace'] ? ($room['price_per_bedspace'] ?? 0) : ($room['price'] ?? 0), 2); ?></span>
                        </div>
                        <div class="calculation-item">
                            <span class="calculation-item-label">Total Amount</span>
                            <span class="calculation-item-value" id="totalDisplay">₱0.00</span>
                        </div>
                    </div>
                    
                    <!-- Terms Notice -->
                    <div style="padding: 1rem; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; font-size: 0.875rem; color: #64748b; line-height: 1.6;">
                        <strong style="color: #475569;">📋 Terms & Conditions</strong><br>
                        By submitting this booking request, you agree to our terms and conditions. 
                        Payment must be made within 3 days of booking approval.
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
const pricePerMonth = <?php echo json_encode($room['is_bedspace'] ? ($room['price_per_bedspace'] ?? 0) : ($room['price'] ?? 0)); ?>;
const roomId = <?php echo $room_id; ?>;
const isBedspace = <?php echo json_encode($room['is_bedspace'] ? true : false); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const calculationBox = document.getElementById('calculationBox');
    const submitBtn = document.getElementById('submitBtn');
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
    
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
                
                // Update displays
                document.getElementById('checkInDisplay').textContent = formatDate(startDate);
                document.getElementById('checkOutDisplay').textContent = formatDate(endDate);
                document.getElementById('durationDisplay').textContent = months + ' month' + (months > 1 ? 's' : '') + ' (' + diffDays + ' days)';
                document.getElementById('totalDisplay').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                calculationBox.classList.add('show');
                submitBtn.disabled = false;
            } else {
                calculationBox.classList.remove('show');
                submitBtn.disabled = true;
            }
        } else {
            calculationBox.classList.remove('show');
            submitBtn.disabled = true;
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
            alert('⚠️ Check-in date cannot be in the past');
            return false;
        }
        
        if (endDate <= startDate) {
            e.preventDefault();
            alert('⚠️ Check-out date must be after check-in date');
            return false;
        }
        
        if (!pricePerMonth || pricePerMonth <= 0) {
            e.preventDefault();
            alert('⚠️ Room pricing is not available. Please contact administration.');
            return false;
        }
        
        // Disable submit button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>⏳</span><span>Processing...</span>';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>