<?php
// filepath: admin/bedspaces.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';
require_once '../includes/bedspace_functions.php';

$page_title = 'Bedspace Management';

// Handle bedspace status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_status' && isset($_POST['bedspace_id']) && isset($_POST['status'])) {
            $bedspace_id = intval($_POST['bedspace_id']);
            $status = sanitize_input($_POST['status']);
            
            if (update_bedspace_status($conn, $bedspace_id, $status)) {
                set_flash_message('Bedspace status updated successfully', 'success');
            } else {
                set_flash_message('Failed to update bedspace status', 'error');
            }
        }
        
        if ($action === 'release_bedspace' && isset($_POST['bedspace_id'])) {
            $bedspace_id = intval($_POST['bedspace_id']);
            
            if (release_bedspace($conn, $bedspace_id)) {
                set_flash_message('Bedspace released successfully', 'success');
            } else {
                set_flash_message('Failed to release bedspace', 'error');
            }
        }
        
        redirect('admin/bedspaces');
    }
}

// Get filters
$filter_room = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Get bedspace statistics
$stats = get_bedspace_stats($conn);

// Build query for bedspaces
$query = "
    SELECT b.*, 
           r.room_number, r.room_type, r.floor_number, r.price_per_bedspace,
           u.first_name, u.last_name, u.email, u.phone
    FROM bedspaces b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN users u ON b.current_tenant_id = u.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filter_room > 0) {
    $query .= " AND b.room_id = ?";
    $params[] = $filter_room;
    $types .= 'i';
}

if ($filter_status !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($search) {
    $query .= " AND (r.room_number LIKE ? OR b.bedspace_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$query .= " ORDER BY r.room_number ASC, b.bedspace_number ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bedspaces_result = $stmt->get_result();
$stmt->close();

// Get all bedspace rooms for filter dropdown
$rooms_query = "SELECT id, room_number, total_bedspaces, occupied_bedspaces 
                FROM rooms 
                WHERE is_bedspace = TRUE 
                ORDER BY room_number ASC";
$rooms_result = $conn->query($rooms_query);

// Get bedspace rooms with full details
$bedspace_rooms_query = "
    SELECT r.*, 
           (r.total_bedspaces - r.occupied_bedspaces) as available_count,
           COUNT(DISTINCT bo.id) as active_bookings
    FROM rooms r
    LEFT JOIN bookings bo ON r.id = bo.room_id AND bo.status IN ('approved', 'checked_in')
    WHERE r.is_bedspace = TRUE
    GROUP BY r.id
    ORDER BY r.room_number ASC
";
$bedspace_rooms_result = $conn->query($bedspace_rooms_query);

require_once '../includes/header.php';
?>

<style>
    .bedspace-page {
        padding: 2rem 0;
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Page Header */
    .page-header-enhanced {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
    }
    
    .page-header-enhanced h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
        font-weight: 700;
    }
    
    .page-header-enhanced p {
        margin: 0;
        opacity: 0.9;
    }
    
    /* Statistics Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .stat-card.purple::before { background: linear-gradient(180deg, #667eea, #764ba2); }
    .stat-card.green::before { background: linear-gradient(180deg, #10b981, #059669); }
    .stat-card.yellow::before { background: linear-gradient(180deg, #f59e0b, #d97706); }
    .stat-card.blue::before { background: linear-gradient(180deg, #3b82f6, #2563eb); }
    .stat-card.red::before { background: linear-gradient(180deg, #ef4444, #dc2626); }
    
    .stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0.5rem 0;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-sublabel {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.25rem;
    }
    
    /* Filter Section */
    .filter-section {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }
    
    @media (max-width: 968px) {
        .filter-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 640px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }
    
    .form-control {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: border-color 0.2s;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Tabs */
    .tabs-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }
    
    .tabs-header {
        display: flex;
        border-bottom: 2px solid #e5e7eb;
        padding: 0 1rem;
    }
    
    .tab-btn {
        padding: 1rem 1.5rem;
        background: none;
        border: none;
        color: #6b7280;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }
    
    .tab-btn.active {
        color: #667eea;
    }
    
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: #667eea;
    }
    
    .tab-content {
        display: none;
        padding: 1.5rem;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Bedspace Cards Grid */
    .bedspace-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    
    .bedspace-card {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 1.25rem;
        transition: all 0.2s;
        position: relative;
    }
    
    .bedspace-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    
    .bedspace-card.available {
        border-color: #10b981;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }
    
    .bedspace-card.occupied {
        border-color: #3b82f6;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
    }
    
    .bedspace-card.maintenance {
        border-color: #f59e0b;
        background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
    }
    
    .bedspace-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    
    .bedspace-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .room-badge {
        display: inline-block;
        background: #667eea;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .bedspace-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
    }
    
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-badge.available {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.occupied {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-badge.maintenance {
        background: #fef3c7;
        color: #92400e;
    }
    
    .bedspace-info {
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
    
    .bedspace-info strong {
        color: #374151;
        font-weight: 600;
    }
    
    .tenant-info {
        background: #f9fafb;
        padding: 0.75rem;
        border-radius: 6px;
        margin-top: 1rem;
    }
    
    .tenant-name {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }
    
    .tenant-contact {
        font-size: 0.75rem;
        color: #6b7280;
    }
    
    .bedspace-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .btn-action {
        flex: 1;
        padding: 0.5rem;
        border: none;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: #667eea;
        color: white;
    }
    
    .btn-primary:hover {
        background: #5568d3;
        transform: translateY(-1px);
    }
    
    .btn-warning {
        background: #f59e0b;
        color: white;
    }
    
    .btn-warning:hover {
        background: #d97706;
    }
    
    .btn-success {
        background: #10b981;
        color: white;
    }
    
    .btn-success:hover {
        background: #059669;
    }
    
    /* Room Overview Cards */
    .room-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    
    .room-overview-card {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 1.5rem;
        transition: all 0.2s;
    }
    
    .room-overview-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    
    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .room-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
    }
    
    .room-type-badge {
        background: #667eea;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .room-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 8px;
    }
    
    .room-stat {
        text-align: center;
    }
    
    .room-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
    }
    
    .room-stat-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
    }
    
    .occupancy-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin: 1rem 0;
    }
    
    .occupancy-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 0.3s ease;
    }
    
    .bedspaces-visual {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .bedspace-slot {
        flex: 1;
        height: 40px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.2s;
    }
    
    .bedspace-slot.available {
        background: #d1fae5;
        color: #065f46;
        border: 2px solid #10b981;
    }
    
    .bedspace-slot.occupied {
        background: #dbeafe;
        color: #1e40af;
        border: 2px solid #3b82f6;
    }
    
    .bedspace-slot.maintenance {
        background: #fef3c7;
        color: #92400e;
        border: 2px solid #f59e0b;
    }
    
    .room-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .btn-outline {
        flex: 1;
        padding: 0.5rem;
        border: 2px solid #667eea;
        background: white;
        color: #667eea;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-outline:hover {
        background: #667eea;
        color: white;
        transform: translateY(-1px);
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 10px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        margin-bottom: 1.5rem;
    }
    
    .modal-header h2 {
        margin: 0;
        color: #1f2937;
    }
    
    .modal-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .btn-modal {
        flex: 1;
        padding: 0.75rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-cancel {
        background: #e5e7eb;
        color: #374151;
    }
    
    .btn-cancel:hover {
        background: #d1d5db;
    }
    
    .btn-confirm {
        background: #667eea;
        color: white;
    }
    
    .btn-confirm:hover {
        background: #5568d3;
    }
</style>

<!-- Page Header -->
<div class="page-header-enhanced">
    <h1>🛏️ Bedspace Management</h1>
    <p>Manage bedspace rooms, monitor occupancy, and assign tenants to individual bed slots</p>
</div>

<div class="container bedspace-page">
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="stat-icon">🏠</div>
            <div class="stat-value"><?php echo $stats['total_bedspace_rooms'] ?? 0; ?></div>
            <div class="stat-label">Bedspace Rooms</div>
        </div>
        
        <div class="stat-card blue">
            <div class="stat-icon">🛏️</div>
            <div class="stat-value"><?php echo $stats['total_bedspaces'] ?? 0; ?></div>
            <div class="stat-label">Total Bedspaces</div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $stats['available_bedspaces'] ?? 0; ?></div>
            <div class="stat-label">Available</div>
            <div class="stat-sublabel">
                <?php 
                $total = $stats['total_bedspaces'] ?? 1;
                $available = $stats['available_bedspaces'] ?? 0;
                $percentage = round(($available / $total) * 100);
                echo $percentage . '% available';
                ?>
            </div>
        </div>
        
        <div class="stat-card yellow">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo $stats['occupied_bedspaces'] ?? 0; ?></div>
            <div class="stat-label">Occupied</div>
            <div class="stat-sublabel">
                <?php 
                $occupied = $stats['occupied_bedspaces'] ?? 0;
                $percentage = round(($occupied / $total) * 100);
                echo $percentage . '% occupancy';
                ?>
            </div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₱<?php echo number_format($stats['monthly_revenue'] ?? 0, 0); ?></div>
            <div class="stat-label">Monthly Revenue</div>
            <div class="stat-sublabel">From occupied bedspaces</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('bedspaces', this)">
                All Bedspaces (<?php echo $bedspaces_result->num_rows; ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('rooms', this)">
                Room Overview
            </button>
        </div>
        
        <!-- Tab 1: All Bedspaces -->
        <div class="tab-content active" id="bedspaces-tab">
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Room</label>
                        <select name="room_id" class="form-control" onchange="this.form.submit()">
                            <option value="0">All Rooms</option>
                            <?php 
                            $rooms_result->data_seek(0);
                            while ($room = $rooms_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $room['id']; ?>" <?php echo $filter_room == $room['id'] ? 'selected' : ''; ?>>
                                    Room <?php echo htmlspecialchars($room['room_number']); ?> 
                                    (<?php echo $room['occupied_bedspaces']; ?>/<?php echo $room['total_bedspaces']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="occupied" <?php echo $filter_status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="maintenance" <?php echo $filter_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Room, bedspace, tenant..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn-primary" style="width: 100%; padding: 0.625rem;">Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- Bedspace Cards -->
            <?php if ($bedspaces_result->num_rows > 0): ?>
                <div class="bedspace-cards-grid">
                    <?php while ($bedspace = $bedspaces_result->fetch_assoc()): ?>
                        <div class="bedspace-card <?php echo $bedspace['status']; ?>">
                            <div class="bedspace-header">
                                <div class="bedspace-label">
                                    <span class="room-badge">Room <?php echo htmlspecialchars($bedspace['room_number']); ?></span>
                                    <span class="bedspace-number"><?php echo htmlspecialchars($bedspace['bedspace_number']); ?></span>
                                </div>
                                <span class="status-badge <?php echo $bedspace['status']; ?>">
                                    <?php echo ucfirst($bedspace['status']); ?>
                                </span>
                            </div>
                            
                            <div class="bedspace-info">
                                <strong>Type:</strong> <?php echo htmlspecialchars($bedspace['room_type']); ?>
                            </div>
                            <div class="bedspace-info">
                                <strong>Floor:</strong> <?php echo $bedspace['floor_number']; ?>
                            </div>
                            <div class="bedspace-info">
                                <strong>Price:</strong> ₱<?php echo number_format($bedspace['price_per_bedspace'], 2); ?>/month
                            </div>
                            
                            <?php if ($bedspace['status'] === 'occupied' && $bedspace['first_name']): ?>
                                <div class="tenant-info">
                                    <div class="tenant-name">
                                        👤 <?php echo htmlspecialchars($bedspace['first_name'] . ' ' . $bedspace['last_name']); ?>
                                    </div>
                                    <div class="tenant-contact">
                                        📧 <?php echo htmlspecialchars($bedspace['email']); ?>
                                    </div>
                                    <?php if ($bedspace['phone']): ?>
                                        <div class="tenant-contact">
                                            📱 <?php echo htmlspecialchars($bedspace['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bedspace-actions">
                                    <button class="btn-action btn-warning" 
                                            onclick="releaseBedspace(<?php echo $bedspace['id']; ?>, '<?php echo htmlspecialchars($bedspace['room_number']); ?>', '<?php echo htmlspecialchars($bedspace['bedspace_number']); ?>')">
                                        Release Bedspace
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="bedspace-actions">
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="bedspace_id" value="<?php echo $bedspace['id']; ?>">
                                        <select name="status" class="form-control" onchange="this.form.submit()" style="font-size: 0.75rem;">
                                            <option value="available" <?php echo $bedspace['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="maintenance" <?php echo $bedspace['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        </select>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3>No bedspaces found</h3>
                    <p>Try adjusting your filters or search criteria</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab 2: Room Overview -->
        <div class="tab-content" id="rooms-tab">
            <?php if ($bedspace_rooms_result->num_rows > 0): ?>
                <div class="room-cards-grid">
                    <?php while ($room = $bedspace_rooms_result->fetch_assoc()): 
                        $occupancy_percentage = $room['total_bedspaces'] > 0 
                            ? ($room['occupied_bedspaces'] / $room['total_bedspaces']) * 100 
                            : 0;
                        
                        // Get bedspaces for this room
                        $room_bedspaces = get_room_bedspaces($conn, $room['id']);
                    ?>
                        <div class="room-overview-card">
                            <div class="room-header">
                                <div class="room-number">Room <?php echo htmlspecialchars($room['room_number']); ?></div>
                                <div class="room-type-badge"><?php echo htmlspecialchars($room['room_type']); ?></div>
                            </div>
                            
                            <div class="room-stats">
                                <div class="room-stat">
                                    <div class="room-stat-value"><?php echo $room['total_bedspaces']; ?></div>
                                    <div class="room-stat-label">Total Beds</div>
                                </div>
                                <div class="room-stat">
                                    <div class="room-stat-value"><?php echo $room['occupied_bedspaces']; ?></div>
                                    <div class="room-stat-label">Occupied</div>
                                </div>
                                <div class="room-stat">
                                    <div class="room-stat-value"><?php echo $room['available_count']; ?></div>
                                    <div class="room-stat-label">Available</div>
                                </div>
                            </div>
                            
                            <div class="occupancy-bar">
                                <div class="occupancy-fill" style="width: <?php echo $occupancy_percentage; ?>%"></div>
                            </div>
                            
                            <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                                <strong>Occupancy:</strong> <?php echo round($occupancy_percentage); ?>%
                            </div>
                            
                            <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                                <strong>Price per bedspace:</strong> ₱<?php echo number_format($room['price_per_bedspace'], 2); ?>/month
                            </div>
                            
                            <!-- Visual bedspace slots -->
                            <div class="bedspaces-visual">
                                <?php foreach ($room_bedspaces as $bedspace): ?>
                                    <div class="bedspace-slot <?php echo $bedspace['status']; ?>" 
                                         title="<?php echo $bedspace['bedspace_number'] . ' - ' . ucfirst($bedspace['status']); ?>">
                                        <?php echo htmlspecialchars($bedspace['bedspace_number']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="room-actions">
                                <a href="<?php echo ADMIN_URL; ?>/room_details?id=<?php echo $room['id']; ?>" class="btn-outline">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏠</div>
                    <h3>No bedspace rooms</h3>
                    <p>Create a bedspace room from the <a href="<?php echo ADMIN_URL; ?>/rooms">Rooms</a> page</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Release Bedspace Modal -->
<div id="releaseModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Release Bedspace</h2>
        </div>
        <p>Are you sure you want to release this bedspace? The current tenant will be removed.</p>
        <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
            <strong>Room:</strong> <span id="releaseRoom"></span><br>
            <strong>Bedspace:</strong> <span id="releaseBedspace"></span>
        </div>
        <form method="POST" id="releaseForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="release_bedspace">
            <input type="hidden" name="bedspace_id" id="releaseBedspaceId">
            
            <div class="modal-actions">
                <button type="button" class="btn-modal btn-cancel" onclick="closeReleaseModal()">Cancel</button>
                <button type="submit" class="btn-modal btn-confirm">Confirm Release</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabName, btn) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    btn.classList.add('active');
}

function releaseBedspace(bedspaceId, roomNumber, bedspaceNumber) {
    document.getElementById('releaseBedspaceId').value = bedspaceId;
    document.getElementById('releaseRoom').textContent = roomNumber;
    document.getElementById('releaseBedspace').textContent = bedspaceNumber;
    document.getElementById('releaseModal').classList.add('active');
}

function closeReleaseModal() {
    document.getElementById('releaseModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('releaseModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReleaseModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReleaseModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
