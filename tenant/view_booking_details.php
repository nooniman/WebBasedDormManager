<?php
// filepath: tenant/view_booking_details.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$tenant_id = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get booking details
$query = "
    SELECT b.*, 
           r.room_number, r.room_type, r.price, r.floor_number, r.category,
           r.has_wifi, r.has_ac, r.has_bathroom,
           u.first_name as approver_first_name, u.last_name as approver_last_name
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN users u ON b.approved_by = u.id
    WHERE b.id = ? AND b.tenant_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    redirect("tenant/bookings");
}

$page_title = 'Booking Details - Room ' . $booking['room_number'];

// Get room photos
$photos_query = "SELECT * FROM room_photos WHERE room_id = ? ORDER BY is_primary DESC, id ASC";
$stmt = $conn->prepare($photos_query);
$stmt->bind_param("i", $booking['room_id']);
$stmt->execute();
$photos_result = $stmt->get_result();
$stmt->close();

// Get room amenities
$amenities_query = "
    SELECT ra.name, ra.icon 
    FROM room_amenity_assignments raa 
    JOIN room_amenities ra ON raa.amenity_id = ra.id 
    WHERE raa.room_id = ?
";
$stmt = $conn->prepare($amenities_query);
$stmt->bind_param("i", $booking['room_id']);
$stmt->execute();
$amenities_result = $stmt->get_result();
$stmt->close();

// Get payment history for this booking
$payments_query = "
    SELECT * FROM payments 
    WHERE booking_id = ? 
    ORDER BY payment_date DESC
";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$payments_result = $stmt->get_result();
$stmt->close();

require_once '../includes/header.php';
?>

<style>
    .booking-details-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Hero Section */
    .booking-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }
    
    .booking-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .booking-hero-content {
        position: relative;
        z-index: 1;
    }
    
    .booking-hero h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2.5rem;
        font-weight: 900;
    }
    
    .booking-hero-meta {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
        margin: 1rem 0;
        font-size: 1.1rem;
        opacity: 0.95;
    }
    
    .hero-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .booking-status-hero {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        font-weight: 700;
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 1rem;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    /* Quick Stats */
    .quick-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .quick-stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .quick-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    
    .quick-stat-icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }
    
    .quick-stat-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }
    
    .quick-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Main Grid Layout */
    .details-main-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
    /* Room Gallery */
    .room-gallery-section {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }
    
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .gallery-item {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        aspect-ratio: 4/3;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .gallery-item:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }
    
    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .primary-badge {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    /* Information Sections */
    .info-section-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }
    
    .info-section-card h2 {
        margin: 0 0 1.5rem 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .info-grid-two-col {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .info-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .info-field-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-field-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    /* Amenities Grid */
    .amenities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .amenity-badge {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1rem;
        border-radius: 12px;
        text-align: center;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .amenity-badge:hover {
        background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        border-color: #667eea;
        transform: translateY(-2px);
    }
    
    .amenity-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .amenity-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
    }
    
    /* Timeline */
    .timeline-container {
        position: relative;
        padding-left: 2.5rem;
    }
    
    .timeline-line {
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    
    .timeline-dot {
        position: absolute;
        left: -2.4rem;
        top: 0.25rem;
        width: 16px;
        height: 16px;
        background: white;
        border: 3px solid #667eea;
        border-radius: 50%;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .timeline-dot.completed {
        background: #10b981;
        border-color: #10b981;
    }
    
    .timeline-content {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1.25rem;
        border-radius: 12px;
        border-left: 3px solid #667eea;
    }
    
    .timeline-content h4 {
        margin: 0 0 0.5rem 0;
        color: #1e293b;
        font-weight: 700;
    }
    
    .timeline-content p {
        margin: 0;
        color: #64748b;
        font-size: 0.95rem;
    }
    
    .timeline-date {
        font-size: 0.85rem;
        color: #94a3b8;
        margin-top: 0.5rem;
        font-weight: 600;
    }
    
    /* Payments Table */
    .payments-table-detailed {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 1rem;
    }
    
    .payments-table-detailed thead tr {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .payments-table-detailed th {
        padding: 1rem;
        text-align: left;
        font-weight: 700;
        font-size: 0.875rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #cbd5e0;
    }
    
    .payments-table-detailed th:first-child {
        border-top-left-radius: 12px;
    }
    
    .payments-table-detailed th:last-child {
        border-top-right-radius: 12px;
    }
    
    .payments-table-detailed td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
        font-weight: 500;
    }
    
    .payments-table-detailed tbody tr:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    /* Sidebar Actions */
    .sidebar-actions {
        position: sticky;
        top: 100px;
    }
    
    .action-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }
    
    .action-card h3 {
        margin: 0 0 1.5rem 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    /* Alert Box */
    .alert-box-detailed {
        padding: 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
        animation: slideIn 0.5s ease;
    }
    
    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .alert-box-detailed.info {
        background: linear-gradient(135deg, rgba(219, 234, 254, 0.5) 0%, rgba(191, 219, 254, 0.5) 100%);
        border-left-color: #3b82f6;
        color: #1e3a8a;
    }
    
    .alert-box-detailed.success {
        background: linear-gradient(135deg, rgba(212, 244, 221, 0.5) 0%, rgba(198, 246, 213, 0.5) 100%);
        border-left-color: #10b981;
        color: #065f46;
    }
    
    .alert-box-detailed.warning {
        background: linear-gradient(135deg, rgba(254, 243, 199, 0.5) 0%, rgba(253, 230, 138, 0.5) 100%);
        border-left-color: #f59e0b;
        color: #78350f;
    }
    
    .alert-box-detailed.danger {
        background: linear-gradient(135deg, rgba(254, 226, 226, 0.5) 0%, rgba(254, 202, 202, 0.5) 100%);
        border-left-color: #ef4444;
        color: #991b1b;
    }
    
    .alert-box-detailed h4 {
        margin: 0 0 0.5rem 0;
        font-weight: 700;
    }
    
    .alert-box-detailed p {
        margin: 0;
        line-height: 1.6;
    }
    
    /* Print Button */
    .print-btn-floating {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 50%;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.3s ease;
        z-index: 1000;
    }
    
    .print-btn-floating:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .details-main-grid {
            grid-template-columns: 1fr;
        }
        
        .sidebar-actions {
            position: static;
        }
    }
    
    @media (max-width: 768px) {
        .booking-hero h1 {
            font-size: 1.8rem;
        }
        
        .booking-hero-meta {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .info-grid-two-col {
            grid-template-columns: 1fr;
        }
        
        .quick-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .gallery-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media print {
        .print-btn-floating,
        .action-card,
        .page-header-enhanced {
            display: none;
        }
        
        .booking-hero {
            background: white;
            color: black;
            border: 2px solid #e2e8f0;
        }
    }
</style>

<div class="container booking-details-page">
    <!-- Back Button -->
    <div style="margin-bottom: 1rem;">
        <a href="<?php echo TENANT_URL; ?>/bookings" class="btn-enhanced outline sm">
            ← Back to Bookings
        </a>
    </div>
    
    <!-- Hero Section -->
    <div class="booking-hero">
        <div class="booking-hero-content">
            <h1>Room <?php echo htmlspecialchars($booking['room_number']); ?></h1>
            <div class="booking-hero-meta">
                <span class="hero-meta-item">
                    🏠 <?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?>
                </span>
                <?php if ($booking['floor_number']): ?>
                <span class="hero-meta-item">
                    📍 Floor <?php echo $booking['floor_number']; ?>
                </span>
                <?php endif; ?>
                <span class="hero-meta-item">
                    💰 <?php echo format_currency($booking['price']); ?>/month
                </span>
            </div>
            <div class="booking-status-hero">
                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="quick-stats-grid">
        <div class="quick-stat-card">
            <div class="quick-stat-icon">📅</div>
            <div class="quick-stat-label">Start Date</div>
            <div class="quick-stat-value">
                <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
            </div>
        </div>
        <?php if ($booking['end_date']): ?>
        <div class="quick-stat-card">
            <div class="quick-stat-icon">🏁</div>
            <div class="quick-stat-label">End Date</div>
            <div class="quick-stat-value">
                <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($booking['duration_months']): ?>
        <div class="quick-stat-card">
            <div class="quick-stat-icon">⏱️</div>
            <div class="quick-stat-label">Duration</div>
            <div class="quick-stat-value">
                <?php echo $booking['duration_months']; ?> month(s)
            </div>
        </div>
        <?php endif; ?>
        <?php if ($booking['total_amount']): ?>
        <div class="quick-stat-card">
            <div class="quick-stat-icon">💵</div>
            <div class="quick-stat-label">Total Amount</div>
            <div class="quick-stat-value">
                <?php echo format_currency($booking['total_amount']); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Status Alerts -->
    <?php if ($booking['status'] === 'pending'): ?>
        <div class="alert-box-detailed warning">
            <h4>⏳ Pending Approval</h4>
            <p>Your booking request is awaiting approval from the administrator. You will be notified once it's reviewed.</p>
        </div>
    <?php elseif ($booking['status'] === 'approved'): ?>
        <div class="alert-box-detailed success">
            <h4>✓ Booking Approved!</h4>
            <p>Your booking has been approved! Please proceed with the check-in process on your scheduled date.</p>
            <?php if ($booking['approved_by']): ?>
            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                Approved by <?php echo htmlspecialchars($booking['approver_first_name'] . ' ' . $booking['approver_last_name']); ?>
                on <?php echo date('M d, Y', strtotime($booking['approved_at'])); ?>
            </p>
            <?php endif; ?>
        </div>
    <?php elseif ($booking['status'] === 'checked_in'): ?>
        <div class="alert-box-detailed info">
            <h4>🏠 Currently Checked In</h4>
            <p>You are currently checked in to this room. Enjoy your stay!</p>
            <?php if ($booking['check_in_date']): ?>
            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                Checked in on <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?>
            </p>
            <?php endif; ?>
        </div>
    <?php elseif ($booking['status'] === 'rejected'): ?>
        <div class="alert-box-detailed danger">
            <h4>❌ Booking Rejected</h4>
            <p>Unfortunately, your booking request was rejected.</p>
            <?php if ($booking['rejected_reason']): ?>
            <p style="margin-top: 0.75rem; font-weight: 600;">
                Reason: <?php echo htmlspecialchars($booking['rejected_reason']); ?>
            </p>
            <?php endif; ?>
        </div>
    <?php elseif ($booking['status'] === 'cancelled'): ?>
        <div class="alert-box-detailed warning">
            <h4>🚫 Booking Cancelled</h4>
            <p>This booking has been cancelled.</p>
        </div>
    <?php elseif ($booking['status'] === 'checked_out'): ?>
        <div class="alert-box-detailed success">
            <h4>✓ Stay Completed</h4>
            <p>Your stay has been completed. Thank you for choosing our dormitory!</p>
            <?php if ($booking['check_out_date']): ?>
            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                Checked out on <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
            </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Main Grid -->
    <div class="details-main-grid">
        <!-- Main Content -->
        <div>
            <!-- Room Gallery -->
            <?php if ($photos_result && $photos_result->num_rows > 0): ?>
            <div class="room-gallery-section">
                <h2 style="margin: 0 0 1rem 0;">
                    <span class="section-icon">🖼️</span>
                    Room Photos
                </h2>
                <div class="gallery-grid">
                    <?php while ($photo = $photos_result->fetch_assoc()): ?>
                        <div class="gallery-item" onclick="window.open('../uploads/<?php echo htmlspecialchars($photo['photo_path']); ?>', '_blank')">
                            <img src="../uploads/<?php echo htmlspecialchars($photo['photo_path']); ?>" 
                                 alt="Room Photo">
                            <?php if ($photo['is_primary']): ?>
                                <span class="primary-badge">Primary</span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Room Details -->
            <div class="info-section-card">
                <h2>
                    <span class="section-icon">🏠</span>
                    Room Details
                </h2>
                <div class="info-grid-two-col">
                    <div class="info-field">
                        <span class="info-field-label">Room Number</span>
                        <span class="info-field-value"><?php echo htmlspecialchars($booking['room_number']); ?></span>
                    </div>
                    <div class="info-field">
                        <span class="info-field-label">Room Type</span>
                        <span class="info-field-value"><?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?></span>
                    </div>
                    <?php if ($booking['category']): ?>
                    <div class="info-field">
                        <span class="info-field-label">Category</span>
                        <span class="info-field-value"><?php echo ucfirst(htmlspecialchars($booking['category'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['floor_number']): ?>
                    <div class="info-field">
                        <span class="info-field-label">Floor</span>
                        <span class="info-field-value">Floor <?php echo $booking['floor_number']; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-field">
                        <span class="info-field-label">Monthly Rate</span>
                        <span class="info-field-value"><?php echo format_currency($booking['price']); ?></span>
                    </div>
                </div>
                
                <!-- Built-in Amenities -->
                <h3 style="margin: 2rem 0 1rem 0; font-size: 1.1rem; color: #475569;">Built-in Features</h3>
                <div class="amenities-grid">
                    <?php if ($booking['has_wifi']): ?>
                    <div class="amenity-badge">
                        <div class="amenity-icon">📶</div>
                        <div class="amenity-name">Wi-Fi</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['has_ac']): ?>
                    <div class="amenity-badge">
                        <div class="amenity-icon">❄️</div>
                        <div class="amenity-name">Air Conditioning</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['has_bathroom']): ?>
                    <div class="amenity-badge">
                        <div class="amenity-icon">🚿</div>
                        <div class="amenity-name">Private Bathroom</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Additional Amenities -->
                <?php if ($amenities_result && $amenities_result->num_rows > 0): ?>
                <h3 style="margin: 2rem 0 1rem 0; font-size: 1.1rem; color: #475569;">Additional Amenities</h3>
                <div class="amenities-grid">
                    <?php while ($amenity = $amenities_result->fetch_assoc()): ?>
                    <div class="amenity-badge">
                        <div class="amenity-icon"><?php echo htmlspecialchars($amenity['icon']); ?></div>
                        <div class="amenity-name"><?php echo htmlspecialchars($amenity['name']); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Booking Information -->
            <div class="info-section-card">
                <h2>
                    <span class="section-icon">📋</span>
                    Booking Information
                </h2>
                <div class="info-grid-two-col">
                    <div class="info-field">
                        <span class="info-field-label">Booking ID</span>
                        <span class="info-field-value">#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="info-field">
                        <span class="info-field-label">Status</span>
                        <span class="info-field-value">
                            <span class="badge-enhanced <?php 
                                echo $booking['status'] === 'pending' ? 'warning' : 
                                    ($booking['status'] === 'approved' ? 'success' : 
                                    ($booking['status'] === 'checked_in' ? 'info' : 
                                    ($booking['status'] === 'rejected' ? 'danger' : 'secondary'))); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-field">
                        <span class="info-field-label">Start Date</span>
                        <span class="info-field-value"><?php echo date('F d, Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    <?php if ($booking['end_date']): ?>
                    <div class="info-field">
                        <span class="info-field-label">End Date</span>
                        <span class="info-field-value"><?php echo date('F d, Y', strtotime($booking['end_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['duration_months']): ?>
                    <div class="info-field">
                        <span class="info-field-label">Duration</span>
                        <span class="info-field-value"><?php echo $booking['duration_months']; ?> month(s)</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['total_amount']): ?>
                    <div class="info-field">
                        <span class="info-field-label">Total Amount</span>
                        <span class="info-field-value"><?php echo format_currency($booking['total_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-field">
                        <span class="info-field-label">Booking Date</span>
                        <span class="info-field-value"><?php echo date('F d, Y', strtotime($booking['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Booking Timeline -->
            <div class="info-section-card">
                <h2>
                    <span class="section-icon">📅</span>
                    Booking Timeline
                </h2>
                <div class="timeline-container">
                    <div class="timeline-line"></div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content">
                            <h4>Booking Requested</h4>
                            <p>Your booking request was submitted successfully.</p>
                            <div class="timeline-date">
                                <?php echo date('F d, Y \a\t g:i A', strtotime($booking['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($booking['status'] !== 'pending'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo in_array($booking['status'], ['approved', 'checked_in', 'checked_out']) ? 'completed' : ''; ?>"></div>
                        <div class="timeline-content">
                            <h4><?php echo $booking['status'] === 'rejected' ? 'Booking Rejected' : 'Booking Approved'; ?></h4>
                            <p>
                                <?php if ($booking['status'] === 'rejected'): ?>
                                    Your booking was rejected by the administrator.
                                    <?php if ($booking['rejected_reason']): ?>
                                        <br><strong>Reason:</strong> <?php echo htmlspecialchars($booking['rejected_reason']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Your booking was approved and confirmed.
                                <?php endif; ?>
                            </p>
                            <?php if ($booking['approved_at']): ?>
                            <div class="timeline-date">
                                <?php echo date('F d, Y \a\t g:i A', strtotime($booking['approved_at'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['status'] === 'checked_in' || $booking['status'] === 'checked_out'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content">
                            <h4>Checked In</h4>
                            <p>You have successfully checked in to the room.</p>
                            <?php if ($booking['check_in_date']): ?>
                            <div class="timeline-date">
                                <?php echo date('F d, Y', strtotime($booking['check_in_date'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['status'] === 'checked_out'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot completed"></div>
                        <div class="timeline-content">
                            <h4>Checked Out</h4>
                            <p>Your stay has been completed. Thank you!</p>
                            <?php if ($booking['check_out_date']): ?>
                            <div class="timeline-date">
                                <?php echo date('F d, Y', strtotime($booking['check_out_date'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment History -->
            <div class="info-section-card">
                <h2>
                    <span class="section-icon">💳</span>
                    Payment History
                </h2>
                <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                    <table class="payments-table-detailed">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><strong><?php echo format_currency($payment['amount']); ?></strong></td>
                                <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge-enhanced <?php echo $payment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: #94a3b8;">
                        No payment records yet for this booking.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="sidebar-actions">
            <div class="action-card">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="<?php echo PUBLIC_URL; ?>/room_view?id=<?php echo $booking['room_id']; ?>" 
                       class="btn-enhanced outline">
                        View Room Details
                    </a>
                    <a href="<?php echo TENANT_URL; ?>/bookings" class="btn-enhanced outline">
                        All Bookings
                    </a>
                    <a href="<?php echo TENANT_URL; ?>/payments" class="btn-enhanced outline">
                        Payment History
                    </a>
                    <button onclick="window.print()" class="btn-enhanced outline">
                        Print Details
                    </button>
                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'approved'): ?>
                    <a href="<?php echo TENANT_URL; ?>/cancel_booking?id=<?php echo $booking['id']; ?>" 
                       class="btn-enhanced danger"
                       onclick="return confirm('Are you sure you want to cancel this booking?')">
                        Cancel Booking
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Contact Support -->
            <div class="action-card">
                <h3>Need Help?</h3>
                <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1rem;">
                    Contact our support team if you have any questions about your booking.
                </p>
                <a href="mailto:support@dormitory.com" class="btn-enhanced primary">
                    Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Floating Print Button -->
<button onclick="window.print()" class="print-btn-floating" title="Print Details">
    🖨️
</button>

<?php require_once '../includes/footer.php'; ?>