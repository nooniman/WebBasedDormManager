<?php
// filepath: public/rooms.php
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = 'Available Rooms';

// Get filter parameters
$room_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 999999;
$capacity = isset($_GET['capacity']) ? (int)$_GET['capacity'] : 0;
$has_wifi = isset($_GET['wifi']) ? 1 : 0;
$has_ac = isset($_GET['ac']) ? 1 : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'price_asc';

// Build query
$query = "
    SELECT r.*, 
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_photo,
           (SELECT COUNT(*) FROM room_photos WHERE room_id = r.id) as photo_count,
           CASE 
               WHEN r.is_bedspace = 1 THEN (r.total_bedspaces - r.occupied_bedspaces)
               ELSE NULL 
           END as available_bedspaces
    FROM rooms r 
    WHERE r.status = 'available'
";

$params = [];
$types = "";

if ($room_type !== 'all') {
    $query .= " AND r.room_type = ?";
    $params[] = $room_type;
    $types .= "s";
}

if ($min_price > 0) {
    $query .= " AND r.price >= ?";
    $params[] = $min_price;
    $types .= "i";
}

if ($max_price < 999999) {
    $query .= " AND r.price <= ?";
    $params[] = $max_price;
    $types .= "i";
}

if ($capacity > 0) {
    $query .= " AND r.capacity >= ?";
    $params[] = $capacity;
    $types .= "i";
}

if ($has_wifi) {
    $query .= " AND r.has_wifi = 1";
}

if ($has_ac) {
    $query .= " AND r.has_ac = 1";
}

// Sorting
switch ($sort_by) {
    case 'price_asc':
        $query .= " ORDER BY r.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY r.price DESC";
        break;
    case 'capacity_asc':
        $query .= " ORDER BY r.capacity ASC";
        break;
    case 'capacity_desc':
        $query .= " ORDER BY r.capacity DESC";
        break;
    case 'room_number':
        $query .= " ORDER BY r.room_number ASC";
        break;
    default:
        $query .= " ORDER BY r.price ASC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rooms = $stmt->get_result();
$stmt->close();

// Get room type counts
$type_counts = $conn->query("
    SELECT room_type, COUNT(*) as count 
    FROM rooms 
    WHERE status = 'available' 
    GROUP BY room_type
")->fetch_all(MYSQLI_ASSOC);

// Get price range
$price_range = $conn->query("
    SELECT MIN(price) as min_price, MAX(price) as max_price 
    FROM rooms 
    WHERE status = 'available'
")->fetch_assoc();

require_once '../includes/header.php';
?>

<style>
    .rooms-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Page Header */
    .rooms-page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 3rem 0;
        margin: -2rem -2rem 2rem -2rem;
        border-radius: 0 0 40px 40px;
        position: relative;
        overflow: hidden;
    }
    
    .rooms-page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .rooms-page-header-content {
        position: relative;
        z-index: 1;
        text-align: center;
    }
    
    .rooms-page-header h1 {
        font-size: 3rem;
        font-weight: 900;
        margin: 0 0 1rem 0;
    }
    
    .rooms-page-header p {
        font-size: 1.25rem;
        opacity: 0.95;
        max-width: 700px;
        margin: 0 auto;
    }
    
    /* Filter Layout */
    .rooms-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 2rem;
        align-items: start;
    }
    
    /* Filter Sidebar */
    .filter-sidebar {
        position: sticky;
        top: 2rem;
    }
    
    .filter-card {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    
    .filter-card h3 {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 1.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-group {
        margin-bottom: 1.5rem;
    }
    
    .filter-group:last-child {
        margin-bottom: 0;
    }
    
    .filter-label {
        display: block;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    
    .filter-select,
    .filter-input {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .filter-select:focus,
    .filter-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .price-range-inputs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .checkbox-label:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }
    
    .checkbox-label input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #667eea;
    }
    
    .checkbox-label span {
        font-weight: 600;
        color: #475569;
        font-size: 0.95rem;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
    }
    
    .filter-btn {
        flex: 1;
        padding: 0.875rem;
        border-radius: 12px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .filter-btn.apply {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .filter-btn.apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
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
    
    /* Room Type Tabs */
    .room-type-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .room-type-tab {
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #64748b;
    }
    
    .room-type-tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }
    
    .room-type-tab .count {
        margin-left: 0.5rem;
        opacity: 0.7;
    }
    
    /* Results Section */
    .results-section {
        min-height: 500px;
    }
    
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .results-info {
        font-size: 1.1rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .results-info strong {
        color: #1e293b;
        font-size: 1.25rem;
    }
    
    .sort-dropdown {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .sort-dropdown label {
        font-weight: 600;
        color: #475569;
    }
    
    .sort-dropdown select {
        padding: 0.75rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        background: white;
        transition: all 0.3s ease;
    }
    
    .sort-dropdown select:focus {
        outline: none;
        border-color: #667eea;
    }
    
    /* Room Grid */
    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
    }
    
    .room-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid transparent;
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
        height: 240px;
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
        background: rgba(16, 185, 129, 0.95);
        backdrop-filter: blur(10px);
        color: white;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
    
    .room-card-content {
        padding: 1.75rem;
    }
    
    .room-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1.25rem;
    }
    
    .room-info {
        flex: 1;
    }
    
    .room-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 0.25rem 0;
    }
    
    .room-type-label {
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
    
    .room-price-box {
        text-align: right;
    }
    
    .room-price {
        font-size: 2rem;
        font-weight: 900;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    
    .room-price-period {
        font-size: 0.85rem;
        color: #94a3b8;
        font-weight: 600;
    }
    
    .room-features-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }
    
    .room-feature-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.875rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
    }
    
    .room-description {
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 1.25rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .room-card-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .room-action-btn {
        padding: 0.875rem;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        font-size: 0.95rem;
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
    
    .room-action-btn.book {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: 2px solid transparent;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .room-action-btn.book:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
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
    
    /* Active Filters Display */
    .active-filters {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        padding: 1rem 1.5rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
        border: 2px solid #667eea;
    }
    
    .active-filters-title {
        font-weight: 700;
        color: #667eea;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    
    .active-filters-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.875rem;
        background: white;
        border: 1px solid #667eea;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #667eea;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .rooms-layout {
            grid-template-columns: 1fr;
        }
        
        .filter-sidebar {
            position: static;
        }
    }
    
    @media (max-width: 768px) {
        .rooms-page-header h1 {
            font-size: 2rem;
        }
        
        .rooms-grid {
            grid-template-columns: 1fr;
        }
        
        .results-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .sort-dropdown {
            flex-direction: column;
            align-items: stretch;
        }
        
        .sort-dropdown select {
            width: 100%;
        }
    }
</style>

<div class="rooms-page">
    <!-- Page Header -->
    <div class="rooms-page-header">
        <div class="container">
            <div class="rooms-page-header-content">
                <h1>Available Rooms</h1>
                <p>Find your perfect living space from our selection of quality rooms</p>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="rooms-layout">
            <!-- Filter Sidebar -->
            <aside class="filter-sidebar">
                <form method="GET" action="" id="filterForm">
                    <div class="filter-card">
                        <h3>🔍 Filter Rooms</h3>
                        
                        
                        
                        <!-- Price Range -->
                        <div class="filter-group">
                            <label class="filter-label">Price Range (₱)</label>
                            <div class="price-range-inputs">
                                <input type="number" name="min_price" class="filter-input" 
                                       placeholder="Min" value="<?php echo $min_price > 0 ? $min_price : ''; ?>"
                                       min="0">
                                <input type="number" name="max_price" class="filter-input" 
                                       placeholder="Max" value="<?php echo $max_price < 999999 ? $max_price : ''; ?>"
                                       min="0">
                            </div>
                        </div>
                        
                        <!-- Capacity -->
                        <div class="filter-group">
                            <label class="filter-label">Minimum Capacity</label>
                            <select name="capacity" class="filter-select">
                                <option value="0" <?php echo $capacity === 0 ? 'selected' : ''; ?>>Any</option>
                                <option value="1" <?php echo $capacity === 1 ? 'selected' : ''; ?>>1 Person</option>
                                <option value="2" <?php echo $capacity === 2 ? 'selected' : ''; ?>>2 Persons</option>
                                <option value="3" <?php echo $capacity === 3 ? 'selected' : ''; ?>>3 Persons</option>
                                <option value="4" <?php echo $capacity === 4 ? 'selected' : ''; ?>>4+ Persons</option>
                            </select>
                        </div>
                        
                        <!-- Amenities -->
                        <div class="filter-group">
                            <label class="filter-label">Amenities</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="wifi" value="1" <?php echo $has_wifi ? 'checked' : ''; ?>>
                                    <span>📶 WiFi</span>
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="ac" value="1" <?php echo $has_ac ? 'checked' : ''; ?>>
                                    <span>❄️ Air Conditioning</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="filter-btn apply">
                                Apply Filters
                            </button>
                            <a href="rooms" class="filter-btn reset">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
                
                <!-- Room Type Quick Filter -->
                <div class="filter-card">
                    <h3>📋 Room Types</h3>
                    <div class="room-type-tabs">
                        <a href="?type=all" class="room-type-tab <?php echo $room_type === 'all' ? 'active' : ''; ?>">
                            All Rooms
                            <span class="count">
                                (<?php 
                                    $total = array_sum(array_column($type_counts, 'count'));
                                    echo $total;
                                ?>)
                            </span>
                        </a>
                        <?php foreach ($type_counts as $type): ?>
                            <a href="?type=<?php echo $type['room_type']; ?>" 
                               class="room-type-tab <?php echo $room_type === $type['room_type'] ? 'active' : ''; ?>">
                                <?php echo ucfirst($type['room_type']); ?>
                                <span class="count">(<?php echo $type['count']; ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>
            
            <!-- Results Section -->
            <main class="results-section">
                <!-- Results Header -->
                <div class="results-header">
                    <div class="results-info">
                        <strong><?php echo $rooms->num_rows; ?></strong> 
                        room<?php echo $rooms->num_rows !== 1 ? 's' : ''; ?> found
                    </div>
                    
                    <div class="sort-dropdown">
                        <label for="sortBy">Sort by:</label>
                        <select id="sortBy" name="sort" class="filter-select" 
                                onchange="this.form.submit()" form="filterForm">
                            <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>
                                Price: Low to High
                            </option>
                            <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>
                                Price: High to Low
                            </option>
                            <option value="capacity_asc" <?php echo $sort_by === 'capacity_asc' ? 'selected' : ''; ?>>
                                Capacity: Low to High
                            </option>
                            <option value="capacity_desc" <?php echo $sort_by === 'capacity_desc' ? 'selected' : ''; ?>>
                                Capacity: High to Low
                            </option>
                            <option value="room_number" <?php echo $sort_by === 'room_number' ? 'selected' : ''; ?>>
                                Room Number
                            </option>
                        </select>
                    </div>
                </div>
                
                <!-- Active Filters -->
                <?php if ($room_type !== 'all' || $min_price > 0 || $max_price < 999999 || $capacity > 0 || $has_wifi || $has_ac): ?>
                    <div class="active-filters">
                        <div class="active-filters-title">Active Filters:</div>
                        <div class="active-filters-list">
                            <?php if ($room_type !== 'all'): ?>
                                <span class="filter-tag">Type: <?php echo ucfirst($room_type); ?></span>
                            <?php endif; ?>
                            <?php if ($min_price > 0): ?>
                                <span class="filter-tag">Min: ₱<?php echo number_format($min_price); ?></span>
                            <?php endif; ?>
                            <?php if ($max_price < 999999): ?>
                                <span class="filter-tag">Max: ₱<?php echo number_format($max_price); ?></span>
                            <?php endif; ?>
                            <?php if ($capacity > 0): ?>
                                <span class="filter-tag">Capacity: <?php echo $capacity; ?>+ person(s)</span>
                            <?php endif; ?>
                            <?php if ($has_wifi): ?>
                                <span class="filter-tag">📶 WiFi</span>
                            <?php endif; ?>
                            <?php if ($has_ac): ?>
                                <span class="filter-tag">❄️ AC</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Rooms Grid -->
                <?php if ($rooms && $rooms->num_rows > 0): ?>
                    <div class="rooms-grid">
                        <?php while ($room = $rooms->fetch_assoc()): ?>
                            <article class="room-card">
                                <div class="room-image-container">
                                    <?php if ($room['primary_photo']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($room['primary_photo']); ?>" 
                                             alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                                             class="room-image">
                                    <?php else: ?>
                                        <div class="room-image-placeholder">🏠</div>
                                    <?php endif; ?>
                                    
                                    <span class="room-status-badge">
                                        ✓ Available
                                    </span>
                                    
                                    <?php if ($room['photo_count'] > 0): ?>
                                        <span class="photo-count-badge">
                                            📷 <?php echo $room['photo_count']; ?> photo<?php echo $room['photo_count'] > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="room-card-content">
                                    <div class="room-card-header">
                                        <div class="room-info">
                                            <h3 class="room-number">Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                                            <span class="room-type-label"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></span>
                                            <?php if ($room['is_bedspace']): ?>
                                                <span class="room-type-label" style="background: #10b981; color: white; margin-left: 0.5rem;">🛏️ Bedspacing</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="room-price-box">
                                            <?php if ($room['is_bedspace']): ?>
                                                <div class="room-price">₱<?php echo number_format($room['price_per_bedspace'], 0); ?></div>
                                                <div class="room-price-period">/bed/month</div>
                                            <?php else: ?>
                                                <div class="room-price">₱<?php echo number_format($room['price'], 0); ?></div>
                                                <div class="room-price-period">/month</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="room-features-list">
                                        <?php if ($room['is_bedspace']): ?>
                                            <span class="room-feature-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-weight: 700;">
                                                🛏️ <?php echo $room['available_bedspaces']; ?>/<?php echo $room['total_bedspaces']; ?> Bedspaces Available
                                            </span>
                                        <?php else: ?>
                                            <span class="room-feature-item">
                                                👥 <?php echo $room['capacity']; ?> Person(s)
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($room['has_wifi']): ?>
                                            <span class="room-feature-item">📶 WiFi</span>
                                        <?php endif; ?>
                                        <?php if ($room['has_ac']): ?>
                                            <span class="room-feature-item">❄️ AC</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($room['description']): ?>
                                        <p class="room-description">
                                            <?php echo htmlspecialchars($room['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="room-card-actions">
                                        <a href="room_view?id=<?php echo $room['id']; ?>" 
                                           class="room-action-btn view">
                                            View Details
                                        </a>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <a href="booking?room_id=<?php echo $room['id']; ?>" 
                                               class="room-action-btn book">
                                                Book Now
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo LOGIN_URL; ?>?redirect=booking?room_id=<?php echo $room['id']; ?>" 
                                               class="room-action-btn book">
                                                Login to Book
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>No Rooms Found</h3>
                        <p>No rooms match your current filter criteria. Try adjusting your filters.</p>
                        <a href="rooms" class="hero-btn primary" style="display: inline-flex;">
                            View All Rooms
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>