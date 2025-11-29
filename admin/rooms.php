<?php
// filepath: admin/rooms.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Rooms';

// Handle AJAX requests for quick actions
if (isset($_GET['action']) && $_GET['action'] === 'quick_status') {
    header('Content-Type: application/json');
    
    if (!verify_csrf_token($_GET['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
    $room_id = intval($_GET['room_id']);
    $new_status = sanitize_input($_GET['status']);
    
    $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $room_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    $stmt->close();
    exit;
}

// Handle room deletion
if (isset($_GET['delete']) && verify_csrf_token($_GET['csrf_token'])) {
    $room_id = intval($_GET['delete']);
    
    // Check if room has active bookings
    $check_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM bookings 
        WHERE room_id = ? AND status IN ('pending', 'approved', 'checked_in')
    ");
    $check_stmt->bind_param("i", $room_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($check_result['count'] > 0) {
        set_flash_message('Cannot delete room with active bookings', 'error');
    } else {
        // Delete room photos first
        $conn->query("DELETE FROM room_photos WHERE room_id = $room_id");
        
        // Delete room
        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        
        if ($stmt->execute()) {
            set_flash_message('Room deleted successfully', 'success');
        } else {
            set_flash_message('Failed to delete room', 'error');
        }
        $stmt->close();
    }
    
    redirect('admin/rooms');
}

// Handle room addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $room_number = sanitize_input($_POST['room_number']);
        $room_type = sanitize_input($_POST['room_type']);
        $category = sanitize_input($_POST['category']);
        $floor_number = intval($_POST['floor_number']);
        $capacity = intval($_POST['capacity']);
        $price = floatval($_POST['price']);
        $status = sanitize_input($_POST['status']);
        $description = sanitize_input($_POST['description']);
        $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $has_bathroom = isset($_POST['has_bathroom']) ? 1 : 0;
        
        if (isset($_POST['room_id']) && !empty($_POST['room_id'])) {
            // Update existing room
            $room_id = intval($_POST['room_id']);
            $stmt = $conn->prepare("
                UPDATE rooms SET 
                room_number = ?, room_type = ?, category = ?, floor_number = ?,
                capacity = ?, price = ?, status = ?, description = ?,
                has_wifi = ?, has_ac = ?, has_bathroom = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssiidssiii", 
                $room_number, $room_type, $category, $floor_number,
                $capacity, $price, $status, $description,
                $has_wifi, $has_ac, $has_bathroom, $room_id
            );
            $message = 'Room updated successfully';
        } else {
            // Add new room
            $stmt = $conn->prepare("
                INSERT INTO rooms 
                (room_number, room_type, category, floor_number, capacity, price, status, description, has_wifi, has_ac, has_bathroom) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssiidssiii", 
                $room_number, $room_type, $category, $floor_number,
                $capacity, $price, $status, $description,
                $has_wifi, $has_ac, $has_bathroom
            );
            $message = 'Room added successfully';
        }
        
        if ($stmt->execute()) {
            set_flash_message($message, 'success');
        } else {
            set_flash_message('Operation failed: ' . $stmt->error, 'error');
        }
        
        $stmt->close();
        redirect('admin/rooms');
    }
}

// Get filter and search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'all';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$filter_category = isset($_GET['category']) ? sanitize_input($_GET['category']) : 'all';
$sort_by = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'room_number';

// Build query with filters
$query = "
    SELECT r.*, 
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_photo,
           (SELECT COUNT(*) FROM room_photos WHERE room_id = r.id) as photo_count,
           (SELECT COUNT(*) FROM bookings WHERE room_id = r.id AND status IN ('pending', 'approved', 'checked_in')) as active_bookings,
           CASE 
               WHEN r.is_bedspace = TRUE THEN CONCAT(r.total_bedspaces - r.occupied_bedspaces, '/', r.total_bedspaces)
               ELSE NULL
           END as bedspace_availability
    FROM rooms r 
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (r.room_number LIKE ? OR r.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($filter_type !== 'all') {
    $query .= " AND r.room_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_status !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_category !== 'all') {
    $query .= " AND r.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}

// Sorting
switch ($sort_by) {
    case 'price_asc':
        $query .= " ORDER BY r.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY r.price DESC";
        break;
    case 'capacity':
        $query .= " ORDER BY r.capacity DESC";
        break;
    case 'status':
        $query .= " ORDER BY r.status ASC";
        break;
    default:
        $query .= " ORDER BY r.room_number ASC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rooms = $stmt->get_result();
$stmt->close();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms,
        AVG(price) as avg_price,
        MIN(price) as min_price,
        MAX(price) as max_price
    FROM rooms
";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get counts by type
$type_counts = $conn->query("
    SELECT room_type, COUNT(*) as count 
    FROM rooms 
    GROUP BY room_type
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<style>
    .rooms-admin-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Page Header */
    .page-header-admin {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2.5rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .page-header-admin::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .page-header-content {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    
    .page-title-section h1 {
        font-size: 2.5rem;
        font-weight: 900;
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-subtitle {
        font-size: 1.1rem;
        opacity: 0.95;
        margin: 0;
    }
    
    .page-actions {
        display: flex;
        gap: 1rem;
    }
    
    .btn-add-room {
        padding: 1rem 2rem;
        background: white;
        color: #667eea;
        border-radius: 14px;
        text-decoration: none;
        font-weight: 800;
        font-size: 1.05rem;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    
    .btn-add-room:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
    }
    
    /* Statistics Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: var(--stat-gradient, linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%));
        border-radius: 0 0 0 100%;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        border-color: var(--stat-color, #667eea);
    }
    
    .stat-card.total {
        --stat-color: #667eea;
        --stat-gradient: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    }
    
    .stat-card.available {
        --stat-color: #10b981;
        --stat-gradient: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    }
    
    .stat-card.occupied {
        --stat-color: #3b82f6;
        --stat-gradient: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
    }
    
    .stat-card.maintenance {
        --stat-color: #f59e0b;
        --stat-gradient: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
    }
    
    .stat-content {
        position: relative;
        z-index: 1;
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        background: var(--stat-gradient);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1rem;
        border: 2px solid var(--stat-color);
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--stat-color);
        margin: 0 0 0.25rem 0;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Filters Section */
    .filters-section {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .filters-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filters-form {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-label {
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .filter-input,
    .filter-select {
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-btn {
        padding: 0.875rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .filter-btn.reset {
        background: white;
        color: #64748b;
        border: 2px solid #e2e8f0;
    }
    
    .filter-btn.reset:hover {
        border-color: #ef4444;
        color: #ef4444;
    }
    
    /* Active Filters */
    .active-filters {
        margin-top: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 1px solid #667eea;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #667eea;
    }
    
    .filter-tag-remove {
        cursor: pointer;
        font-weight: 700;
        transition: transform 0.2s;
    }
    
    .filter-tag-remove:hover {
        transform: scale(1.2);
    }
    
    /* Results Header */
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .results-count {
        font-size: 1.1rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .results-count strong {
        color: #1e293b;
        font-size: 1.25rem;
    }
    
    .sort-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .sort-group label {
        font-weight: 600;
        color: #475569;
    }
    
    .sort-select {
        padding: 0.75rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        background: white;
    }
    
    /* Rooms Grid */
    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .room-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid #e2e8f0;
        position: relative;
    }
    
    .room-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }
    
    .room-image-container {
        position: relative;
        width: 100%;
        height: 220px;
        overflow: hidden;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .room-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .room-card:hover .room-image {
        transform: scale(1.1);
    }
    
    .room-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .room-status-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .room-status-badge.available {
        color: #10b981;
        border: 2px solid #10b981;
    }
    
    .room-status-badge.occupied {
        color: #3b82f6;
        border: 2px solid #3b82f6;
    }
    
    .room-status-badge.maintenance {
        color: #f59e0b;
        border: 2px solid #f59e0b;
    }
    
    .photo-count-badge {
        position: absolute;
        bottom: 1rem;
        left: 1rem;
        padding: 0.5rem 0.875rem;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        color: white;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .room-content {
        padding: 1.75rem;
    }
    
    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    
    .room-info {
        flex: 1;
    }
    
    .room-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
    }
    
    .room-type-badge {
        display: inline-block;
        padding: 0.375rem 0.875rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 1px solid #667eea;
        border-radius: 8px;
        color: #667eea;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
    }
    
    .room-price {
        text-align: right;
    }
    
    .price-amount {
        font-size: 1.75rem;
        font-weight: 900;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
    }
    
    .price-period {
        font-size: 0.85rem;
        color: #94a3b8;
        font-weight: 600;
    }
    
    .room-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }
    
    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 0.875rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
    }
    
    .room-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .room-action-btn {
        padding: 0.875rem;
        border-radius: 10px;
        font-weight: 700;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .room-action-btn.edit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        border: 2px solid transparent;
    }
    
    .room-action-btn.edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
    }
    
    .room-action-btn.delete {
        background: white;
        color: #ef4444;
        border: 2px solid #ef4444;
    }
    
    .room-action-btn.delete:hover {
        background: #ef4444;
        color: white;
    }
    
    .room-action-btn.view {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }
    
    .room-action-btn.view:hover {
        background: #667eea;
        color: white;
    }
    
    /* Quick Actions Dropdown */
    .quick-actions-dropdown {
        position: relative;
    }
    
    .quick-actions-toggle {
        padding: 0.875rem;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        color: #64748b;
        transition: all 0.3s ease;
    }
    
    .quick-actions-toggle:hover {
        border-color: #667eea;
        color: #667eea;
    }
    
    .quick-actions-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        border: 2px solid #e2e8f0;
        min-width: 200px;
        z-index: 100;
        display: none;
    }
    
    .quick-actions-menu.active {
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
    
    .quick-action-item {
        padding: 0.875rem 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        color: #475569;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .quick-action-item:last-child {
        border-bottom: none;
    }
    
    .quick-action-item:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        color: #667eea;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 24px;
        border: 2px dashed #cbd5e0;
    }
    
    .empty-icon {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
    
    .empty-state h3 {
        font-size: 1.75rem;
        color: #475569;
        margin-bottom: 1rem;
    }
    
    .empty-state p {
        color: #94a3b8;
        font-size: 1.1rem;
        margin-bottom: 2rem;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .filters-form {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .page-header-content {
            flex-direction: column;
            align-items: stretch;
        }
        
        .page-title-section h1 {
            font-size: 2rem;
        }
        
        .page-actions {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filters-form {
            grid-template-columns: 1fr;
        }
        
        .rooms-grid {
            grid-template-columns: 1fr;
        }
        
        .results-header {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<div class="rooms-admin-page">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header-admin">
            <div class="page-header-content">
                <div class="page-title-section">
                    <h1>
                        <span style="font-size: 3rem;">🏠</span>
                        Manage Rooms
                    </h1>
                    <p class="page-subtitle">
                        Create, edit, and manage all dormitory rooms
                    </p>
                </div>
                
                <div class="page-actions">
                    <a href="room_add" class="btn-add-room">
                        <span style="font-size: 1.25rem;">➕</span>
                        <span>Add New Room</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-content">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-value"><?php echo number_format($stats['total_rooms']); ?></div>
                    <div class="stat-label">Total Rooms</div>
                </div>
            </div>
            
            <div class="stat-card available">
                <div class="stat-content">
                    <div class="stat-icon">✓</div>
                    <div class="stat-value"><?php echo number_format($stats['available_rooms']); ?></div>
                    <div class="stat-label">Available</div>
                </div>
            </div>
            
            <div class="stat-card occupied">
                <div class="stat-content">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?php echo number_format($stats['occupied_rooms']); ?></div>
                    <div class="stat-label">Occupied</div>
                </div>
            </div>
            
            <div class="stat-card maintenance">
                <div class="stat-content">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-value"><?php echo number_format($stats['maintenance_rooms']); ?></div>
                    <div class="stat-label">Maintenance</div>
                </div>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h3 class="filters-title">
                    <span>🔍</span>
                    Filter & Search
                </h3>
            </div>
            
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" 
                           name="search" 
                           class="filter-input" 
                           placeholder="Room number or description..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Room Type</label>
                    <select name="type" class="filter-select">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="single" <?php echo $filter_type === 'single' ? 'selected' : ''; ?>>Single</option>
                        <option value="double" <?php echo $filter_type === 'double' ? 'selected' : ''; ?>>Double</option>
                        <option value="quad" <?php echo $filter_type === 'quad' ? 'selected' : ''; ?>>Quad</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="occupied" <?php echo $filter_status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                        <option value="maintenance" <?php echo $filter_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-select">
                        <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <option value="standard" <?php echo $filter_category === 'standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="deluxe" <?php echo $filter_category === 'deluxe' ? 'selected' : ''; ?>>Deluxe</option>
                        <option value="premium" <?php echo $filter_category === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="filter-btn">
                        Apply
                    </button>
                </div>
                
                <div class="filter-group">
                    <a href="rooms" class="filter-btn reset">
                        Reset
                    </a>
                </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if ($search || $filter_type !== 'all' || $filter_status !== 'all' || $filter_category !== 'all'): ?>
                <div class="active-filters">
                    <?php if ($search): ?>
                        <span class="filter-tag">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" 
                               class="filter-tag-remove">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_type !== 'all'): ?>
                        <span class="filter-tag">
                            Type: <?php echo ucfirst($filter_type); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'all'])); ?>" 
                               class="filter-tag-remove">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_status !== 'all'): ?>
                        <span class="filter-tag">
                            Status: <?php echo ucfirst($filter_status); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all'])); ?>" 
                               class="filter-tag-remove">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_category !== 'all'): ?>
                        <span class="filter-tag">
                            Category: <?php echo ucfirst($filter_category); ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => 'all'])); ?>" 
                               class="filter-tag-remove">×</a>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Header -->
        <div class="results-header">
            <div class="results-count">
                <strong><?php echo $rooms->num_rows; ?></strong> 
                room<?php echo $rooms->num_rows !== 1 ? 's' : ''; ?> found
            </div>
            
            <div class="sort-group">
                <label>Sort by:</label>
                <select name="sort" class="sort-select" onchange="location.href='?<?php 
                    echo http_build_query(array_merge($_GET, ['sort' => ''])); ?>' + this.value">
                    <option value="room_number" <?php echo $sort_by === 'room_number' ? 'selected' : ''; ?>>Room Number</option>
                    <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="capacity" <?php echo $sort_by === 'capacity' ? 'selected' : ''; ?>>Capacity</option>
                    <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                </select>
            </div>
        </div>
        
        <!-- Rooms Grid -->
        <?php if ($rooms && $rooms->num_rows > 0): ?>
            <div class="rooms-grid">
                <?php while ($room = $rooms->fetch_assoc()): ?>
                    <div class="room-card">
                        <div class="room-image-container">
                            <?php if ($room['primary_photo']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($room['primary_photo']); ?>" 
                                     alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                                     class="room-image">
                            <?php else: ?>
                                <div class="room-image-placeholder">🏠</div>
                            <?php endif; ?>
                            
                            <span class="room-status-badge <?php echo $room['status']; ?>">
                                <?php 
                                    $status_icons = [
                                        'available' => '✓',
                                        'occupied' => '👥',
                                        'maintenance' => '🔧'
                                    ];
                                    echo $status_icons[$room['status']] ?? '•';
                                ?>
                                <?php echo ucfirst($room['status']); ?>
                            </span>
                            
                            <?php if ($room['photo_count'] > 0): ?>
                                <span class="photo-count-badge">
                                    📷 <?php echo $room['photo_count']; ?> photo<?php echo $room['photo_count'] > 1 ? 's' : ''; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="room-content">
                            <div class="room-header">
                                <div class="room-info">
                                    <h3 class="room-number">Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                                    <span class="room-type-badge"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></span>
                                </div>
                                <div class="room-price">
                                    <div class="price-amount">₱<?php echo number_format($room['price'], 0); ?></div>
                                    <div class="price-period">/month</div>
                                </div>
                            </div>
                            
                            <div class="room-features">
                                <span class="feature-item">
                                    <span>👥</span>
                                    <span><?php echo $room['capacity']; ?> person(s)</span>
                                </span>
                                <span class="feature-item">
                                    <span>📐</span>
                                    <span><?php echo ucfirst($room['category']); ?></span>
                                </span>
                                <?php if ($room['has_wifi']): ?>
                                    <span class="feature-item">
                                        <span>📶</span>
                                        <span>WiFi</span>
                                    </span>
                                <?php endif; ?>
                                <?php if ($room['has_ac']): ?>
                                    <span class="feature-item">
                                        <span>❄️</span>
                                        <span>AC</span>
                                    </span>
                                <?php endif; ?>
                                <?php if ($room['active_bookings'] > 0): ?>
                                    <span class="feature-item" style="border: 2px solid #3b82f6; color: #3b82f6;">
                                        <span>📋</span>
                                        <span><?php echo $room['active_bookings']; ?> booking(s)</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="room-actions">
                                <a href="room_details?id=<?php echo $room['id']; ?>" class="room-action-btn edit">
                                    <span>✏️</span>
                                    <span>Edit</span>
                                </a>
                                <a href="<?php echo PUBLIC_URL; ?>/room_view?id=<?php echo $room['id']; ?>" 
                                   class="room-action-btn view" 
                                   target="_blank">
                                    <span>👁️</span>
                                    <span>View</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h3>No Rooms Found</h3>
                <p>
                    <?php if ($search || $filter_type !== 'all' || $filter_status !== 'all' || $filter_category !== 'all'): ?>
                        No rooms match your filter criteria. Try adjusting your filters.
                    <?php else: ?>
                        No rooms have been added yet. Click "Add New Room" to get started.
                    <?php endif; ?>
                </p>
                <a href="room_add" class="btn-add-room" style="display: inline-flex; margin-top: 1rem;">
                    <span style="font-size: 1.25rem;">➕</span>
                    <span>Add Your First Room</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Confirm delete
document.querySelectorAll('.room-action-btn.delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('⚠️ Are you sure you want to delete this room?\n\nThis action cannot be undone.')) {
            e.preventDefault();
        }
    });
});

// Quick status change (future enhancement)
function changeRoomStatus(roomId, newStatus) {
    if (confirm(`Change room status to "${newStatus}"?`)) {
        const csrfToken = '<?php echo generate_csrf_token(); ?>';
        window.location.href = `?action=quick_status&room_id=${roomId}&status=${newStatus}&csrf_token=${csrfToken}`;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>