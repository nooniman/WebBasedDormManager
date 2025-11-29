<?php
// filepath: tenant/bookings.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'My Bookings';
$tenant_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "
    SELECT b.*, r.room_number, r.room_type, r.price, r.floor_number, r.is_bedspace, r.price_per_bedspace,
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_photo,
           bs.bedspace_number
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN bedspaces bs ON b.bedspace_id = bs.id
    WHERE b.tenant_id = ?
";

$params = [$tenant_id];
$types = "i";

if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND r.room_number LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings_result = $stmt->get_result();
$stmt->close();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once '../includes/header.php';
?>

<style>
    .bookings-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Filter Section */
    .filter-section-enhanced {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
        border: 2px solid #e2e8f0;
    }
    
    .filter-tabs {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .filter-tab {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        text-decoration: none;
        color: #475569;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-tab:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        color: #667eea;
        transform: translateY(-2px);
    }
    
    .filter-tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .filter-count {
        background: rgba(255, 255, 255, 0.3);
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 700;
    }
    
    .filter-tab.active .filter-count {
        background: rgba(255, 255, 255, 0.25);
    }
    
    .search-box-modern {
        display: flex;
        gap: 0.75rem;
    }
    
    .search-input-modern {
        flex: 1;
        padding: 0.875rem 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .search-input-modern:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Stats Pills */
    .stats-pills {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }
    
    .stat-pill {
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .stat-pill:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .stat-pill-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .stat-pill-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    /* Booking Cards Grid */
    .bookings-grid {
        display: grid;
        gap: 1.5rem;
    }
    
    .booking-card-enhanced {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        display: grid;
        grid-template-columns: 180px 1fr auto;
        gap: 1.5rem;
    }
    
    .booking-card-enhanced:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    
    .booking-photo-section {
        position: relative;
        height: 100%;
    }
    
    .booking-room-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .booking-room-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
    }
    
    .booking-status-overlay {
        position: absolute;
        top: 1rem;
        left: 1rem;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .booking-status-overlay.pending {
        background: linear-gradient(135deg, rgba(254, 243, 199, 0.95) 0%, rgba(253, 230, 138, 0.95) 100%);
        color: #78350f;
    }
    
    .booking-status-overlay.approved {
        background: linear-gradient(135deg, rgba(212, 244, 221, 0.95) 0%, rgba(198, 246, 213, 0.95) 100%);
        color: #065f46;
    }
    
    .booking-status-overlay.checked_in,
    .booking-status-overlay.checked-in {
        background: linear-gradient(135deg, rgba(219, 234, 254, 0.95) 0%, rgba(191, 219, 254, 0.95) 100%);
        color: #1e3a8a;
    }
    
    .booking-status-overlay.rejected {
        background: linear-gradient(135deg, rgba(254, 226, 226, 0.95) 0%, rgba(254, 202, 202, 0.95) 100%);
        color: #991b1b;
    }
    
    .booking-status-overlay.cancelled {
        background: linear-gradient(135deg, rgba(243, 244, 246, 0.95) 0%, rgba(229, 231, 235, 0.95) 100%);
        color: #374151;
    }
    
    .booking-status-overlay.checked_out,
    .booking-status-overlay.checked-out {
        background: linear-gradient(135deg, rgba(224, 231, 255, 0.95) 0%, rgba(199, 210, 254, 0.95) 100%);
        color: #3730a3;
    }
    
    .booking-content-section {
        padding: 1.5rem 0;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .booking-header-info h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
    }
    
    .booking-meta-info {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        color: #64748b;
        font-size: 0.95rem;
        font-weight: 600;
    }
    
    .booking-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .booking-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
    }
    
    .booking-detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .booking-detail-label {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .booking-detail-value {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .booking-actions-section {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        justify-content: center;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    /* Empty State */
    .empty-state-bookings {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 20px;
        border: 2px dashed #cbd5e0;
    }
    
    .empty-icon-large {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
    
    .empty-state-bookings h3 {
        font-size: 1.5rem;
        color: #475569;
        margin-bottom: 0.75rem;
    }
    
    .empty-state-bookings p {
        color: #94a3b8;
        font-size: 1.1rem;
        margin-bottom: 2rem;
    }
    
    /* Cancel Modal */
    .cancel-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }
    
    .cancel-modal-overlay.active {
        display: flex;
    }
    
    .cancel-modal-content {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from { transform: translateY(50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .cancel-modal-content h3 {
        margin: 0 0 1rem 0;
        font-size: 1.5rem;
        color: #1e293b;
    }
    
    .cancel-modal-content p {
        color: #64748b;
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }
    
    .cancel-modal-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .booking-card-enhanced {
            grid-template-columns: 1fr;
        }
        
        .booking-photo-section {
            height: 200px;
        }
        
        .filter-tabs {
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: 1rem;
        }
        
        .stats-pills {
            overflow-x: auto;
            flex-wrap: nowrap;
        }
        
        .booking-actions-section {
            flex-direction: row;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>My Bookings</h1>
                <p class="subtitle">Track and manage all your room bookings</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="<?php echo TENANT_URL; ?>/booking_calendar" class="btn-enhanced outline">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.5rem;">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                    Calendar View
                </a>
                <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-enhanced primary">
                    + New Booking
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container bookings-page">
    
    <!-- Statistics Pills -->
    <div class="stats-pills">
        <div class="stat-pill">
            <div class="stat-pill-value"><?php echo $stats['total']; ?></div>
            <div class="stat-pill-label">Total Bookings</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-pill-label">Pending</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-value"><?php echo $stats['approved']; ?></div>
            <div class="stat-pill-label">Approved</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-value"><?php echo $stats['checked_in']; ?></div>
            <div class="stat-pill-label">Checked In</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-value"><?php echo $stats['checked_out']; ?></div>
            <div class="stat-pill-label">Completed</div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section-enhanced">
        <div class="filter-tabs">
            <a href="?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                All Bookings
                <span class="filter-count"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                Pending
                <span class="filter-count"><?php echo $stats['pending']; ?></span>
            </a>
            <a href="?status=approved<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                Approved
                <span class="filter-count"><?php echo $stats['approved']; ?></span>
            </a>
            <a href="?status=checked_in<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'checked_in' ? 'active' : ''; ?>">
                Checked In
                <span class="filter-count"><?php echo $stats['checked_in']; ?></span>
            </a>
            <a href="?status=checked_out<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'checked_out' ? 'active' : ''; ?>">
                Completed
                <span class="filter-count"><?php echo $stats['checked_out']; ?></span>
            </a>
            <a href="?status=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                Rejected
                <span class="filter-count"><?php echo $stats['rejected']; ?></span>
            </a>
            <a href="?status=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                Cancelled
                <span class="filter-count"><?php echo $stats['cancelled']; ?></span>
            </a>
        </div>
        
        <form method="GET" class="search-box-modern">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <input type="text" name="search" placeholder="Search by room number..." 
                   class="search-input-modern" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-enhanced primary">
                Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn-enhanced outline">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Bookings Grid -->
    <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
        <div class="bookings-grid">
            <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                <div class="booking-card-enhanced">
                    <div class="booking-photo-section">
                        <?php if ($booking['room_photo']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($booking['room_photo']); ?>" 
                                 alt="Room" class="booking-room-photo">
                        <?php else: ?>
                            <div class="booking-room-placeholder">🏠</div>
                        <?php endif; ?>
                        <div class="booking-status-overlay <?php echo str_replace('_', '-', $booking['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                        </div>
                    </div>
                    
                    <div class="booking-content-section">
                        <div class="booking-header-info">
                            <h3>
                                Room <?php echo htmlspecialchars($booking['room_number']); ?>
                                <?php if ($booking['is_bedspace_booking'] && $booking['bedspace_number']): ?>
                                    <span class="badge badge-info" style="font-size: 0.8rem; margin-left: 0.5rem;">Bedspace <?php echo htmlspecialchars($booking['bedspace_number']); ?></span>
                                <?php endif; ?>
                            </h3>
                            <div class="booking-meta-info">
                                <span class="booking-meta-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    <?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?>
                                </span>
                                <?php if ($booking['floor_number']): ?>
                                <span class="booking-meta-item">
                                    Floor <?php echo $booking['floor_number']; ?>
                                </span>
                                <?php endif; ?>
                                <span class="booking-meta-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                                    </svg>
                                    <strong><?php echo format_currency($booking['price']); ?>/month</strong>
                                </span>
                            </div>
                        </div>
                        
                        <div class="booking-details-grid">
                            <div class="booking-detail-item">
                                <span class="booking-detail-label">Start Date</span>
                                <span class="booking-detail-value">
                                    <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                                </span>
                            </div>
                            <?php if ($booking['end_date']): ?>
                            <div class="booking-detail-item">
                                <span class="booking-detail-label">End Date</span>
                                <span class="booking-detail-value">
                                    <?php echo date('M d, Y', strtotime($booking['end_date'])); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['duration_months']): ?>
                            <div class="booking-detail-item">
                                <span class="booking-detail-label">Duration</span>
                                <span class="booking-detail-value">
                                    <?php echo $booking['duration_months']; ?> month(s)
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['total_amount']): ?>
                            <div class="booking-detail-item">
                                <span class="booking-detail-label">Total Amount</span>
                                <span class="booking-detail-value" style="color: #667eea;">
                                    <?php echo format_currency($booking['total_amount']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="booking-detail-item">
                                <span class="booking-detail-label">Booked On</span>
                                <span class="booking-detail-value">
                                    <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="booking-actions-section">
                        <a href="<?php echo TENANT_URL; ?>/view_booking_details?id=<?php echo $booking['id']; ?>" 
                           class="btn-enhanced primary sm" style="white-space: nowrap;">
                            View Details
                        </a>
                        
                        <?php if ($booking['status'] === 'pending' || $booking['status'] === 'approved'): ?>
                            <button onclick="showCancelModal(<?php echo $booking['id']; ?>)" 
                                    class="btn-enhanced danger sm" style="white-space: nowrap;">
                                Cancel
                            </button>
                        <?php elseif ($booking['status'] === 'checked_in'): ?>
                            <button onclick="showCheckoutModal(<?php echo $booking['id']; ?>)" 
                                    class="btn-enhanced warning sm" style="white-space: nowrap;">
                                🚪 Check Out
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state-bookings">
            <div class="empty-icon-large">📋</div>
            <h3>No Bookings Found</h3>
            <p>
                <?php if ($status_filter !== 'all'): ?>
                    No bookings with status "<?php echo ucfirst($status_filter); ?>" found.
                <?php elseif (!empty($search)): ?>
                    No bookings matching your search criteria.
                <?php else: ?>
                    You haven't made any bookings yet. Browse available rooms to get started!
                <?php endif; ?>
            </p>
            <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-enhanced primary">
                Browse Available Rooms
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="cancel-modal-overlay">
    <div class="cancel-modal-content">
        <h3>Cancel Booking</h3>
        <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
        <form id="cancelForm" method="POST" action="<?php echo TENANT_URL; ?>/cancel_booking">
            <input type="hidden" name="booking_id" id="cancelBookingId">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="cancel-modal-actions">
                <button type="button" onclick="hideCancelModal()" class="btn-enhanced outline" style="flex: 1;">
                    Keep Booking
                </button>
                <button type="submit" class="btn-enhanced danger" style="flex: 1;">
                    Yes, Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Checkout Modal -->
<div id="checkoutModal" class="cancel-modal-overlay">
    <div class="cancel-modal-content">
        <h3>🚪 Check Out</h3>
        <p>Are you sure you want to check out? This will mark your stay as complete and the room will become available.</p>
        <form id="checkoutForm" method="POST" action="<?php echo TENANT_URL; ?>/checkout_booking">
            <input type="hidden" name="booking_id" id="checkoutBookingId">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="cancel-modal-actions">
                <button type="button" onclick="hideCheckoutModal()" class="btn-enhanced outline" style="flex: 1;">
                    Not Yet
                </button>
                <button type="submit" class="btn-enhanced warning" style="flex: 1;">
                    Yes, Check Out
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showCancelModal(bookingId) {
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancelModal').classList.add('active');
}

function hideCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
}

function showCheckoutModal(bookingId) {
    document.getElementById('checkoutBookingId').value = bookingId;
    document.getElementById('checkoutModal').classList.add('active');
}

function hideCheckoutModal() {
    document.getElementById('checkoutModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCancelModal();
    }
});

document.getElementById('checkoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCheckoutModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideCancelModal();
        hideCheckoutModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>