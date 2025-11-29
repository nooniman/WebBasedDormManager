<?php
// filepath: tenant/portal.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'Tenant Portal';
require_once '../includes/header.php';

$tenant_id = $_SESSION['user_id'];

// Get tenant's current booking (approved or checked_in)
$booking_query = "
    SELECT b.*, r.room_number, r.room_type, r.price, r.floor_number, r.is_bedspace, r.price_per_bedspace,
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_photo,
           bs.bedspace_number
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN bedspaces bs ON b.bedspace_id = bs.id
    WHERE b.tenant_id = ? AND b.status IN ('approved', 'checked_in')
    ORDER BY b.created_at DESC
    LIMIT 1
";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$booking_result = $stmt->get_result();
$current_booking = $booking_result->fetch_assoc();
$stmt->close();

// Get pending bookings
$pending_query = "
    SELECT b.*, r.room_number, r.room_type, r.price 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.tenant_id = ? AND b.status = 'pending'
    ORDER BY b.created_at DESC
";
$stmt = $conn->prepare($pending_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$pending_result = $stmt->get_result();
$stmt->close();

// Get all bookings count for statistics
$bookings_stats_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status IN ('approved', 'checked_in') THEN 1 ELSE 0 END) as active_bookings,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as completed_bookings
    FROM bookings 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($bookings_stats_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$bookings_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get payment statistics
$payment_stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM payments 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($payment_stats_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$payment_stats = $stmt->get_result()->fetch_assoc();
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

// Get bedspace information if tenant has bedspace booking
require_once '../includes/bedspace_functions.php';
$my_bedspace = null;
$roommates = [];
if ($current_booking && $current_booking['is_bedspace_booking'] && $current_booking['bedspace_id']) {
    $my_bedspace = get_bedspace($conn, $current_booking['bedspace_id']);
    $roommates = get_roommates($conn, $current_booking['bedspace_id']);
}

// Get unread notifications count
$notif_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notif_count_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$unread_notifications = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
?>

<style>
    .portal-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Welcome Header */
    .welcome-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 3rem 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
        box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    .welcome-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .welcome-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2.5rem;
        font-weight: 800;
        position: relative;
        z-index: 1;
    }
    
    .welcome-subtitle {
        font-size: 1.1rem;
        opacity: 0.95;
        position: relative;
        z-index: 1;
    }
    
    /* Statistics Grid */
    .stats-grid-modern {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card-modern {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid transparent;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card-modern::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        opacity: 0.08;
        transition: all 0.5s ease;
    }
    
    .stat-card-modern:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border-color: #e2e8f0;
    }
    
    .stat-card-modern:hover::before {
        top: -20%;
        right: -20%;
    }
    
    .stat-card-modern.total::before { background: #667eea; }
    .stat-card-modern.active::before { background: #10b981; }
    .stat-card-modern.pending::before { background: #f59e0b; }
    .stat-card-modern.completed::before { background: #3b82f6; }
    
    .stat-icon-modern {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 1;
    }
    
    .stat-card-modern.total .stat-icon-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-card-modern.active .stat-icon-modern {
        background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
    }
    
    .stat-card-modern.pending .stat-icon-modern {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
    
    .stat-card-modern.completed .stat-icon-modern {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
    }
    
    .stat-value-modern {
        font-size: 2.5rem;
        font-weight: 800;
        color: #1e293b;
        line-height: 1;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }
    
    .stat-label-modern {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }
    
    /* Current Booking Card */
    .current-booking-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .room-preview-modern {
        display: flex;
        gap: 1.5rem;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .room-photo-container {
        position: relative;
        width: 120px;
        height: 120px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .room-photo-small {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .room-placeholder {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .room-info-modern h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .room-meta {
        color: #64748b;
        font-size: 0.95rem;
        font-weight: 500;
    }
    
    .info-grid-modern {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
    
    .info-item-modern {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .info-label-modern {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value-modern {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .info-value-highlight {
        font-size: 1.3rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Pending Bookings */
    .pending-booking-item {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        border-left: 4px solid #f59e0b;
        transition: all 0.3s ease;
    }
    
    .pending-booking-item:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
    }
    
    .pending-booking-item h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: #78350f;
    }
    
    /* Empty State */
    .empty-state-modern {
        text-align: center;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 16px;
        border: 2px dashed #cbd5e0;
    }
    
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .empty-state-modern h3 {
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .empty-state-modern p {
        color: #94a3b8;
        margin-bottom: 1.5rem;
    }
    
    /* Payments Table */
    .payments-table-modern {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .payments-table-modern thead tr {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .payments-table-modern th {
        padding: 1rem;
        text-align: left;
        font-weight: 700;
        font-size: 0.875rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #cbd5e0;
    }
    
    .payments-table-modern th:first-child {
        border-top-left-radius: 12px;
    }
    
    .payments-table-modern th:last-child {
        border-top-right-radius: 12px;
    }
    
    .payments-table-modern tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .payments-table-modern tbody tr:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .payments-table-modern td {
        padding: 1rem;
        color: #475569;
        font-weight: 500;
    }
    
    /* Announcements */
    .announcement-card-modern {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border-left: 4px solid;
        transition: all 0.3s ease;
    }
    
    .announcement-card-modern:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .announcement-card-modern.urgent {
        border-left-color: #ef4444;
        background: linear-gradient(135deg, rgba(254, 226, 226, 0.3) 0%, rgba(254, 202, 202, 0.3) 100%);
    }
    
    .announcement-card-modern.important {
        border-left-color: #f59e0b;
        background: linear-gradient(135deg, rgba(254, 243, 199, 0.3) 0%, rgba(253, 230, 138, 0.3) 100%);
    }
    
    .announcement-card-modern.info {
        border-left-color: #3b82f6;
        background: linear-gradient(135deg, rgba(219, 234, 254, 0.3) 0%, rgba(191, 219, 254, 0.3) 100%);
    }
    
    .announcement-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .announcement-card-modern h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .announcement-meta {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 1rem;
    }
    
    /* Quick Actions Grid */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .quick-action-btn {
        padding: 1.25rem;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        text-decoration: none;
        color: #1e293b;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
    }
    
    .quick-action-btn:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
        color: #667eea;
    }
    
    .quick-action-icon {
        font-size: 2rem;
    }
    
    /* Notification Badge */
    .notif-badge {
        background: #ef4444;
        color: white;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        margin-left: 0.5rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .welcome-header h1 {
            font-size: 1.8rem;
        }
        
        .room-preview-modern {
            flex-direction: column;
            text-align: center;
        }
        
        .stats-grid-modern {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .info-grid-modern {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container portal-page">
    <!-- Welcome Header -->
    <div class="welcome-header">
        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>! 👋</h1>
        <p class="welcome-subtitle">
            Here's what's happening with your dormitory stay
            <?php if ($unread_notifications > 0): ?>
                <span class="notif-badge"><?php echo $unread_notifications; ?> new</span>
            <?php endif; ?>
        </p>
    </div>
    
    <!-- Statistics Grid -->
    <div class="stats-grid-modern">
        <div class="stat-card-modern total">
            <div class="stat-icon-modern">📊</div>
            <div class="stat-value-modern"><?php echo $bookings_stats['total_bookings'] ?? 0; ?></div>
            <div class="stat-label-modern">Total Bookings</div>
        </div>
        
        <div class="stat-card-modern active">
            <div class="stat-icon-modern">✓</div>
            <div class="stat-value-modern"><?php echo $bookings_stats['active_bookings'] ?? 0; ?></div>
            <div class="stat-label-modern">Active Bookings</div>
        </div>
        
        <div class="stat-card-modern pending">
            <div class="stat-icon-modern">⏳</div>
            <div class="stat-value-modern"><?php echo $bookings_stats['pending_bookings'] ?? 0; ?></div>
            <div class="stat-label-modern">Pending Requests</div>
        </div>
        
        <div class="stat-card-modern completed">
            <div class="stat-icon-modern">🏁</div>
            <div class="stat-value-modern"><?php echo $bookings_stats['completed_bookings'] ?? 0; ?></div>
            <div class="stat-label-modern">Completed Stays</div>
        </div>
    </div>
    
    <div class="grid grid-2 mb-4">
        <!-- Current Booking -->
        <div class="current-booking-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Current Booking</h2>
            </div>
            
            <?php if ($current_booking): ?>
                <div class="room-preview-modern">
                    <?php if ($current_booking['room_photo']): ?>
                        <div class="room-photo-container">
                            <img src="../uploads/<?php echo htmlspecialchars($current_booking['room_photo']); ?>" 
                                 alt="Room" class="room-photo-small">
                        </div>
                    <?php else: ?>
                        <div class="room-placeholder">🏠</div>
                    <?php endif; ?>
                    
                    <div class="room-info-modern">
                        <h3>
                            Room <?php echo htmlspecialchars($current_booking['room_number']); ?>
                            <?php if ($current_booking['is_bedspace_booking'] && $current_booking['bedspace_number']): ?>
                                <span class="badge badge-info" style="font-size: 0.75rem; margin-left: 0.5rem;">Bed <?php echo htmlspecialchars($current_booking['bedspace_number']); ?></span>
                            <?php endif; ?>
                        </h3>
                        <p class="room-meta">
                            <?php echo ucfirst(htmlspecialchars($current_booking['room_type'])); ?>
                            <?php if ($current_booking['floor_number']): ?>
                                • Floor <?php echo $current_booking['floor_number']; ?>
                            <?php endif; ?>
                            <?php if ($current_booking['is_bedspace_booking']): ?>
                                • <strong>Bedspace Rental</strong>
                            <?php endif; ?>
                        </p>
                        <span class="badge-enhanced <?php echo $current_booking['status'] === 'checked_in' ? 'success' : 'info'; ?>">
                            <?php echo $current_booking['status'] === 'checked_in' ? 'Checked In' : 'Approved'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-grid-modern">
                    <div class="info-item-modern">
                        <span class="info-label-modern">Monthly Rate</span>
                        <span class="info-value-modern"><?php echo format_currency($current_booking['price']); ?></span>
                    </div>
                    <div class="info-item-modern">
                        <span class="info-label-modern">Start Date</span>
                        <span class="info-value-modern"><?php echo date('M d, Y', strtotime($current_booking['start_date'])); ?></span>
                    </div>
                    <?php if ($current_booking['end_date']): ?>
                    <div class="info-item-modern">
                        <span class="info-label-modern">End Date</span>
                        <span class="info-value-modern"><?php echo date('M d, Y', strtotime($current_booking['end_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($current_booking['total_amount']): ?>
                    <div class="info-item-modern">
                        <span class="info-label-modern">Total Amount</span>
                        <span class="info-value-modern info-value-highlight">
                            <?php echo format_currency($current_booking['total_amount']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($my_bedspace && count($roommates) > 0): ?>
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem;">
                        👥 Your Roommates (<?php echo count($roommates); ?>)
                    </h4>
                    <div style="display: grid; gap: 0.75rem;">
                        <?php foreach ($roommates as $mate): ?>
                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: #f8fafc; border-radius: 8px;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1rem;">
                                    <?php echo strtoupper(substr($mate['first_name'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #1e293b;">
                                        <?php echo htmlspecialchars($mate['first_name'] . ' ' . $mate['last_name']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b;">
                                        Bedspace <?php echo htmlspecialchars($mate['bedspace_number']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                    <a href="<?php echo TENANT_URL; ?>/view_booking_details?id=<?php echo $current_booking['id']; ?>" 
                       class="btn-enhanced primary">
                        View Details
                    </a>
                    <a href="<?php echo PUBLIC_URL; ?>/room_view?id=<?php echo $current_booking['room_id']; ?>" 
                       class="btn-enhanced outline">
                        Room Info
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-icon">🏠</div>
                    <h3>No Active Booking</h3>
                    <p>You don't have an active booking at the moment.</p>
                    <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-enhanced primary">
                        Browse Available Rooms
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Bookings -->
        <div class="current-booking-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Pending Bookings</h2>
                <span class="badge-enhanced warning"><?php echo $pending_result->num_rows; ?></span>
            </div>
            
            <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php while ($pending = $pending_result->fetch_assoc()): ?>
                        <div class="pending-booking-item">
                            <h4>Room <?php echo htmlspecialchars($pending['room_number']); ?></h4>
                            <p style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: #78350f;">
                                <?php echo ucfirst(htmlspecialchars($pending['room_type'])); ?> • 
                                <strong><?php echo format_currency($pending['price']); ?></strong>/month
                            </p>
                            <p style="margin: 0 0 1rem 0; font-size: 0.875rem; color: #92400e;">
                                Requested on <?php echo date('M d, Y', strtotime($pending['created_at'])); ?>
                            </p>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="<?php echo TENANT_URL; ?>/view_booking_details?id=<?php echo $pending['id']; ?>" 
                                   class="btn-enhanced outline sm" style="flex: 1;">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <a href="<?php echo TENANT_URL; ?>/bookings" class="btn-enhanced outline" style="width: 100%; margin-top: 1rem;">
                    View All Bookings
                </a>
            <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-icon">📋</div>
                    <h3>No Pending Requests</h3>
                    <p>You don't have any pending booking requests.</p>
                    <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-enhanced outline">
                        Browse Rooms
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Payments -->
    <div class="card-modern mb-4">
        <div class="card-header-modern">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin: 0 0 0.25rem 0;">Recent Payments</h2>
                    <p style="margin: 0; color: #64748b; font-size: 0.95rem;">
                        Track your payment history
                    </p>
                </div>
                <a href="<?php echo TENANT_URL; ?>/payments" class="btn-enhanced outline sm">View All</a>
            </div>
        </div>
        <div class="card-body-modern">
            <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="payments-table-modern">
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
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: #94a3b8;">No payment records yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card-modern mb-4">
        <div class="card-header-modern">
            <h2 style="margin: 0;">Quick Actions</h2>
        </div>
        <div class="card-body-modern">
            <div class="quick-actions-grid">
                <a href="<?php echo PUBLIC_URL; ?>/rooms" class="quick-action-btn">
                    <span class="quick-action-icon">🏠</span>
                    <span>Browse Rooms</span>
                </a>
                <a href="<?php echo TENANT_URL; ?>/bookings" class="quick-action-btn">
                    <span class="quick-action-icon">📋</span>
                    <span>My Bookings</span>
                </a>
                <a href="<?php echo TENANT_URL; ?>/booking_calendar" class="quick-action-btn">
                    <span class="quick-action-icon">📅</span>
                    <span>Calendar View</span>
                </a>
                <a href="<?php echo TENANT_URL; ?>/profile" class="quick-action-btn">
                    <span class="quick-action-icon">👤</span>
                    <span>Update Profile</span>
                </a>
                <a href="<?php echo TENANT_URL; ?>/payments" class="quick-action-btn">
                    <span class="quick-action-icon">💳</span>
                    <span>View Payments</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Announcements -->
    <div class="card-modern">
        <div class="card-header-modern">
            <h2 style="margin: 0;">Latest Announcements</h2>
        </div>
        <div class="card-body-modern">
            <?php if ($announcements && $announcements->num_rows > 0): ?>
                <?php while ($announcement = $announcements->fetch_assoc()): ?>
                    <div class="announcement-card-modern <?php echo $announcement['priority']; ?>">
                        <div class="announcement-header">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <span class="badge-enhanced <?php 
                                echo $announcement['priority'] === 'urgent' ? 'danger' : 
                                    ($announcement['priority'] === 'important' ? 'warning' : 'info'); 
                            ?>">
                                <?php echo ucfirst($announcement['priority']); ?>
                            </span>
                        </div>
                        <p class="announcement-meta">
                            Posted on <?php echo date('F d, Y', strtotime($announcement['created_at'])); ?> by 
                            <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                        </p>
                        <p style="margin: 0; color: #475569;">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: #94a3b8;">No announcements at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>