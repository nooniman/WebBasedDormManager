<?php
// filepath: admin/bookings.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';
require_once '../includes/booking_functions.php';

$page_title = 'Manage Bookings';

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $booking_id = intval($_POST['booking_id']);
        $action = $_POST['action'];
        $admin_id = $_SESSION['user_id'];
        
        if ($action === 'approve') {
            // Check for conflicts before approving
            $check_stmt = $conn->prepare("SELECT room_id, start_date, end_date FROM bookings WHERE id = ?");
            $check_stmt->bind_param("i", $booking_id);
            $check_stmt->execute();
            $booking_data = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($booking_data) {
                $conflicts = detect_booking_conflicts(
                    $conn, 
                    $booking_data['room_id'], 
                    $booking_data['start_date'], 
                    $booking_data['end_date']
                );
                
                if (count($conflicts) > 0) {
                    set_flash_message('Cannot approve: Booking conflicts detected with existing approved bookings', 'error');
                } else {
                    $stmt = $conn->prepare("UPDATE bookings SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                    $stmt->bind_param("ii", $admin_id, $booking_id);
                    
                    if ($stmt->execute()) {
                        // Get tenant info for notification
                        $tenant_stmt = $conn->prepare("SELECT tenant_id FROM bookings WHERE id = ?");
                        $tenant_stmt->bind_param("i", $booking_id);
                        $tenant_stmt->execute();
                        $tenant_data = $tenant_stmt->get_result()->fetch_assoc();
                        $tenant_stmt->close();
                        
                        // Create notification
                        create_notification(
                            $conn,
                            $tenant_data['tenant_id'],
                            'booking',
                            'Booking Approved',
                            'Your booking request has been approved!',
                            $booking_id,
                            'booking'
                        );
                        
                        set_flash_message('Booking approved successfully', 'success');
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'reject') {
            $rejection_reason = sanitize_input($_POST['rejection_reason']);
            $stmt = $conn->prepare("UPDATE bookings SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejected_reason = ? WHERE id = ?");
            $stmt->bind_param("isi", $admin_id, $rejection_reason, $booking_id);
            
            if ($stmt->execute()) {
                // Get tenant info for notification
                $tenant_stmt = $conn->prepare("SELECT tenant_id FROM bookings WHERE id = ?");
                $tenant_stmt->bind_param("i", $booking_id);
                $tenant_stmt->execute();
                $tenant_data = $tenant_stmt->get_result()->fetch_assoc();
                $tenant_stmt->close();
                
                // Create notification
                create_notification(
                    $conn,
                    $tenant_data['tenant_id'],
                    'booking',
                    'Booking Rejected',
                    'Your booking request has been rejected. Reason: ' . $rejection_reason,
                    $booking_id,
                    'booking'
                );
                
                set_flash_message('Booking rejected', 'success');
            }
            $stmt->close();
        } elseif ($action === 'checkin') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'checked_in', check_in_date = CURDATE() WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            
            if ($stmt->execute()) {
                // Update room status to occupied
                $room_stmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
                $room_stmt->bind_param("i", $booking_id);
                $room_stmt->execute();
                $room_stmt->close();
                
                set_flash_message('Tenant checked in successfully', 'success');
            }
            $stmt->close();
        } elseif ($action === 'checkout') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'checked_out', check_out_date = CURDATE() WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            
            if ($stmt->execute()) {
                // Update room status back to available
                $room_stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
                $room_stmt->bind_param("i", $booking_id);
                $room_stmt->execute();
                $room_stmt->close();
                
                set_flash_message('Tenant checked out successfully', 'success');
            }
            $stmt->close();
        }
        
        redirect('bookings');
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter) {
    $where_clauses[] = "b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_clauses[] = "(r.room_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count total
$count_query = "SELECT COUNT(*) as total FROM bookings b JOIN rooms r ON b.room_id = r.id JOIN users u ON b.tenant_id = u.id $where_sql";
$count_stmt = $conn->prepare($count_query);
if (count($params) > 0) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_bookings = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_bookings / $per_page);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out
    FROM bookings
";
$stats = $conn->query($stats_query)->fetch_assoc();

// Fetch bookings
$query = "
    SELECT b.*, r.room_number, r.room_type, r.price, r.floor_number, u.first_name, u.last_name, u.email, u.phone,
           admin.first_name as approved_by_name, admin.last_name as approved_by_lastname
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN users u ON b.tenant_id = u.id 
    LEFT JOIN users admin ON b.approved_by = admin.id
    $where_sql
    ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'approved' THEN 2
            WHEN b.status = 'checked_in' THEN 3
            ELSE 4
        END,
        b.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

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
    
    /* Quick Stats Grid */
    .quick-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .quick-stat {
        background: white;
        padding: 1.25rem;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: block;
    }
    
    .quick-stat:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-shadow-hover);
    }
    
    .quick-stat.total {
        background: var(--gradient-primary);
        color: white;
    }
    
    .quick-stat.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #78350f;
        border-left: 4px solid #f59e0b;
    }
    
    .quick-stat.approved {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border-left: 4px solid #10b981;
    }
    
    .quick-stat.checked-in {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e3a8a;
        border-left: 4px solid #3b82f6;
    }
    
    .quick-stat.rejected {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }
    
    .quick-stat.cancelled {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        color: #374151;
        border-left: 4px solid #6b7280;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        font-weight: 600;
        opacity: 0.9;
    }
    
    /* Filter Section */
    .filter-controls {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    /* Booking Cards Grid */
    .bookings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .booking-card-modern {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }
    
    .booking-card-modern:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-shadow-hover);
        border-color: #cbd5e0;
    }
    
    .booking-card-header {
        padding: 1.25rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        position: relative;
    }
    
    .booking-id-badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .booking-room-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .booking-card-body {
        padding: 1.5rem;
    }
    
    .tenant-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        margin-bottom: 1.25rem;
        border-left: 4px solid #667eea;
    }
    
    .tenant-avatar-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.25rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .tenant-details {
        flex: 1;
        min-width: 0;
    }
    
    .tenant-name {
        font-weight: 700;
        font-size: 1rem;
        color: #1e293b;
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .tenant-contact {
        font-size: 0.875rem;
        color: #64748b;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .booking-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .detail-label {
        font-size: 0.75rem;
        color: #94a3b8;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .detail-value {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .detail-value.highlight {
        color: #667eea;
        font-size: 1.25rem;
    }
    
    .booking-timeline-compact {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.25rem;
        font-size: 0.875rem;
    }
    
    .timeline-item-compact {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.5rem 0;
    }
    
    .timeline-item-compact:not(:last-child) {
        border-bottom: 1px dashed #e2e8f0;
    }
    
    .timeline-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    
    .timeline-content-compact {
        flex: 1;
    }
    
    .timeline-date-compact {
        color: #94a3b8;
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    .timeline-text-compact {
        color: #475569;
        font-weight: 500;
    }
    
    .notes-alert {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-left: 4px solid #f59e0b;
        padding: 0.875rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    
    .notes-alert strong {
        display: block;
        margin-bottom: 0.5rem;
        color: #78350f;
    }
    
    .rejection-alert {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-left: 4px solid #ef4444;
        padding: 0.875rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    
    .rejection-alert strong {
        display: block;
        margin-bottom: 0.5rem;
        color: #991b1b;
    }
    
    .card-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px dashed #e2e8f0;
    }
    
    .card-actions .btn-enhanced {
        width: 100%;
        justify-content: center;
    }
    
    /* Status Badge in Header */
.status-badge-header {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    padding: 0.35rem 0.875rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.status-badge-header.pending { color: #f59e0b; }
.status-badge-header.approved { color: #10b981; }
.status-badge-header.checked_in { color: #3b82f6; }
.status-badge-header.checked-in { color: #3b82f6; } /* Added dash version */
.status-badge-header.rejected { color: #ef4444; }
.status-badge-header.cancelled { color: #6b7280; }
.status-badge-header.checked_out { color: #8b5cf6; }
.status-badge-header.checked-out { color: #8b5cf6; } /* Added dash version */
    
    /* Modal Enhanced */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        overflow-y: auto;
        padding: 2rem;
    }
    
    .modal-overlay.show {
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    }
    
    .modal-container {
        background: white;
        max-width: 500px;
        width: 100%;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .modal-header-enhanced {
        background: var(--gradient-primary);
        color: white;
        padding: 1.5rem;
        position: relative;
    }
    
    .modal-header-enhanced h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
    }
    
    .modal-close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }
    
    .modal-body-enhanced {
        padding: 2rem;
    }
    
    /* Pagination Enhanced */
    .pagination-enhanced {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }
    
    .pagination-enhanced .btn-enhanced {
        min-width: 100px;
    }
    
    .page-info {
        padding: 0.75rem 1.5rem;
        background: white;
        border-radius: 10px;
        font-weight: 600;
        color: #475569;
        box-shadow: var(--card-shadow);
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .bookings-grid {
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .bookings-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .booking-details-grid {
            grid-template-columns: 1fr;
        }
        
        .card-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>📋 Manage Bookings</h1>
                <p class="subtitle">Review and process booking requests</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="calendar" class="btn-enhanced info">📅 Calendar View</a>
                <a href="dashboard" class="btn-enhanced outline">← Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container bookings-page">
    
    <!-- Quick Statistics -->
    <div class="quick-stats-grid">
        <a href="?status=" class="quick-stat total">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Bookings</div>
        </a>
        <a href="?status=pending" class="quick-stat pending">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">⏳ Pending</div>
        </a>
        <a href="?status=approved" class="quick-stat approved">
            <div class="stat-number"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">✓ Approved</div>
        </a>
        <a href="?status=checked_in" class="quick-stat checked-in">
            <div class="stat-number"><?php echo $stats['checked_in']; ?></div>
            <div class="stat-label">🏠 Checked In</div>
        </a>
        <a href="?status=rejected" class="quick-stat rejected">
            <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">✗ Rejected</div>
        </a>
        <a href="?status=cancelled" class="quick-stat cancelled">
            <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
            <div class="stat-label">🚫 Cancelled</div>
        </a>
    </div>
    
    <!-- Filter Controls -->
    <div class="filter-controls">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Filter by Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✓ Approved</option>
                        <option value="checked_in" <?php echo $status_filter === 'checked_in' ? 'selected' : ''; ?>>🏠 Checked In</option>
                        <option value="checked_out" <?php echo $status_filter === 'checked_out' ? 'selected' : ''; ?>>🚪 Checked Out</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>✗ Rejected</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>🚫 Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Search Bookings</label>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Room, tenant name, email..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-enhanced primary" style="flex: 1;">
                        🔍 Apply Filters
                    </button>
                    <a href="bookings" class="btn-enhanced outline" style="flex: 1;">
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Bookings Grid -->
    <?php if ($result->num_rows > 0): ?>
        <div class="bookings-grid">
            <?php while ($booking = $result->fetch_assoc()): ?>
                <div class="booking-card-modern">
                    <div class="booking-card-header">
                        <div class="booking-id-badge">#<?php echo $booking['id']; ?></div>
                        <h3 class="booking-room-title">
                            🏠 Room <?php echo htmlspecialchars($booking['room_number']); ?>
                        </h3>
                        <span class="status-badge-header <?php echo str_replace('-', '_', $booking['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                        </span>
                    </div>
                    
                    <div class="booking-card-body">
                        <!-- Tenant Info -->
                        <div class="tenant-section">
                            <div class="tenant-avatar-circle">
                                <?php echo strtoupper(substr($booking['first_name'], 0, 1)); ?>
                            </div>
                            <div class="tenant-details">
                                <div class="tenant-name">
                                    <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                </div>
                                <div class="tenant-contact">
                                    <span>📧 <?php echo htmlspecialchars($booking['email']); ?></span>
                                    <?php if ($booking['phone']): ?>
                                        <span>📱 <?php echo htmlspecialchars($booking['phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Booking Details -->
                        <div class="booking-details-grid">
                            <div class="detail-item">
                                <div class="detail-label">Room Type</div>
                                <div class="detail-value"><?php echo ucfirst($booking['room_type']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Floor</div>
                                <div class="detail-value"><?php echo $booking['floor_number']; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Start Date</div>
                                <div class="detail-value">
                                    <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">End Date</div>
                                <div class="detail-value">
                                    <?php echo $booking['end_date'] ? date('M d, Y', strtotime($booking['end_date'])) : 'Open-ended'; ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Monthly Rate</div>
                                <div class="detail-value highlight">
                                    <?php echo format_currency($booking['price']); ?>
                                </div>
                            </div>
                            <?php if ($booking['total_amount']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Total Amount</div>
                                <div class="detail-value highlight">
                                    <?php echo format_currency($booking['total_amount']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Compact Timeline -->
                        <div class="booking-timeline-compact">
                            <div class="timeline-item-compact">
                                <div class="timeline-icon">📝</div>
                                <div class="timeline-content-compact">
                                    <div class="timeline-date-compact">
                                        <?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?>
                                    </div>
                                    <div class="timeline-text-compact">Booking submitted</div>
                                </div>
                            </div>
                            
                            <?php if ($booking['approved_at']): ?>
                            <div class="timeline-item-compact">
                                <div class="timeline-icon">
                                    <?php echo $booking['status'] === 'rejected' ? '✗' : '✓'; ?>
                                </div>
                                <div class="timeline-content-compact">
                                    <div class="timeline-date-compact">
                                        <?php echo date('M d, Y g:i A', strtotime($booking['approved_at'])); ?>
                                    </div>
                                    <div class="timeline-text-compact">
                                        <?php echo ucfirst($booking['status']); ?> by 
                                        <?php echo htmlspecialchars($booking['approved_by_name'] . ' ' . $booking['approved_by_lastname']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['check_in_date']): ?>
                            <div class="timeline-item-compact">
                                <div class="timeline-icon">🔑</div>
                                <div class="timeline-content-compact">
                                    <div class="timeline-date-compact">
                                        <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?>
                                    </div>
                                    <div class="timeline-text-compact">Tenant checked in</div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['check_out_date']): ?>
                            <div class="timeline-item-compact">
                                <div class="timeline-icon">🚪</div>
                                <div class="timeline-content-compact">
                                    <div class="timeline-date-compact">
                                        <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
                                    </div>
                                    <div class="timeline-text-compact">Tenant checked out</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Notes -->
                        <?php if ($booking['notes']): ?>
                        <div class="notes-alert">
                            <strong>📝 Tenant Notes:</strong>
                            <?php echo nl2br(htmlspecialchars($booking['notes'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Rejection Reason -->
                        <?php if ($booking['rejected_reason']): ?>
                        <div class="rejection-alert">
                            <strong>❌ Rejection Reason:</strong>
                            <?php echo nl2br(htmlspecialchars($booking['rejected_reason'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions -->
                        <div class="card-actions">
                            <?php if ($booking['status'] === 'pending'): ?>
                                <button onclick="approveBooking(<?php echo $booking['id']; ?>)" 
                                        class="btn-enhanced success sm">
                                    ✓ Approve
                                </button>
                                <button onclick="showRejectModal(<?php echo $booking['id']; ?>)" 
                                        class="btn-enhanced danger sm">
                                    ✗ Reject
                                </button>
                            <?php elseif ($booking['status'] === 'approved'): ?>
                                <button onclick="checkIn(<?php echo $booking['id']; ?>)" 
                                        class="btn-enhanced primary sm" style="grid-column: span 2;">
                                    🔑 Check In Tenant
                                </button>
                            <?php elseif ($booking['status'] === 'checked_in'): ?>
                                <button onclick="checkOut(<?php echo $booking['id']; ?>)" 
                                        class="btn-enhanced warning sm" style="grid-column: span 2;">
                                    🚪 Check Out Tenant
                                </button>
                            <?php endif; ?>
                            
                            <a href="view_booking?id=<?php echo $booking['id']; ?>" 
                               class="btn-enhanced outline sm" 
                               style="grid-column: span 2;">
                                👁️ View Full Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-enhanced">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn-enhanced outline">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <div class="page-info">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn-enhanced outline">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state-enhanced">
            <div class="icon">📭</div>
            <h3>No Bookings Found</h3>
            <p>No booking requests match your current filters.</p>
            <?php if ($status_filter || $search): ?>
                <a href="bookings" class="btn-enhanced primary">Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-enhanced">
            <h3>❌ Reject Booking</h3>
            <button class="modal-close-btn" onclick="closeRejectModal()">×</button>
        </div>
        <div class="modal-body-enhanced">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="booking_id" id="rejectBookingId">
                
                <div class="form-group">
                    <label class="form-label">Reason for Rejection *</label>
                    <textarea name="rejection_reason" 
                              class="form-control" 
                              rows="5" 
                              required 
                              placeholder="Please provide a clear and detailed reason for rejecting this booking request..."></textarea>
                </div>
                
                <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn-enhanced danger" style="flex: 1;">
                        Confirm Rejection
                    </button>
                    <button type="button" onclick="closeRejectModal()" class="btn-enhanced outline" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveBooking(bookingId) {
    if (confirm('✓ Approve this booking?\n\nThe tenant will be notified and can proceed with payment.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showRejectModal(bookingId) {
    document.getElementById('rejectBookingId').value = bookingId;
    document.getElementById('rejectModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('show');
    document.body.style.overflow = '';
}

function checkIn(bookingId) {
    if (confirm('🔑 Check in this tenant?\n\nThis will mark the room as occupied.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="checkin">
            <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function checkOut(bookingId) {
    if (confirm('🚪 Check out this tenant?\n\nThis will mark the room as available.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="booking_id" value="${bookingId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal on outside click
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});
</script>

<?php 
$stmt->close();
require_once '../includes/footer.php'; 
?>