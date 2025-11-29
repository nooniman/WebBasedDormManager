<?php
// filepath: public/bedspaces.php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/bedspace_functions.php';

$page_title = 'Available Bedspaces';

// Get filter parameters
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 999999;
$floor = isset($_GET['floor']) ? (int)$_GET['floor'] : 0;
$available_only = isset($_GET['available_only']) ? 1 : 0;
$has_wifi = isset($_GET['wifi']) ? 1 : 0;
$has_ac = isset($_GET['ac']) ? 1 : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'price_asc';

// Build query for bedspace rooms
$query = "
    SELECT r.*,
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_photo,
           (r.total_bedspaces - r.occupied_bedspaces) as available_bedspaces
    FROM rooms r
    WHERE r.is_bedspace = 1 AND r.status = 'available'
";

$params = [];
$types = "";

if ($min_price > 0) {
    $query .= " AND r.price_per_bedspace >= ?";
    $params[] = $min_price;
    $types .= "i";
}

if ($max_price < 999999) {
    $query .= " AND r.price_per_bedspace <= ?";
    $params[] = $max_price;
    $types .= "i";
}

if ($floor > 0) {
    $query .= " AND r.floor_number = ?";
    $params[] = $floor;
    $types .= "i";
}

if ($available_only) {
    $query .= " AND (r.total_bedspaces - r.occupied_bedspaces) > 0";
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
        $query .= " ORDER BY r.price_per_bedspace ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY r.price_per_bedspace DESC";
        break;
    case 'availability':
        $query .= " ORDER BY (r.total_bedspaces - r.occupied_bedspaces) DESC";
        break;
    case 'room_number':
        $query .= " ORDER BY r.room_number ASC";
        break;
    default:
        $query .= " ORDER BY r.price_per_bedspace ASC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bedspace_rooms = $stmt->get_result();
$stmt->close();

// Get statistics
$stats_query = "
    SELECT 
        SUM(total_bedspaces) as total_bedspaces,
        SUM(occupied_bedspaces) as occupied_bedspaces,
        COUNT(*) as total_bedspace_rooms,
        MIN(price_per_bedspace) as min_price,
        MAX(price_per_bedspace) as max_price
    FROM rooms 
    WHERE is_bedspace = 1 AND status = 'available'
";
$stats = $conn->query($stats_query)->fetch_assoc();
$available_beds = $stats['total_bedspaces'] - $stats['occupied_bedspaces'];

// Get floor counts
$floor_query = "
    SELECT floor_number, COUNT(*) as count
    FROM rooms
    WHERE is_bedspace = 1 AND status = 'available'
    GROUP BY floor_number
    ORDER BY floor_number
";
$floors = $conn->query($floor_query)->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<style>
    .bedspaces-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Hero Section */
    .bedspaces-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 4rem 0;
        margin: -2rem -2rem 2rem -2rem;
        border-radius: 0 0 40px 40px;
        position: relative;
        overflow: hidden;
    }
    
    .bedspaces-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 20s infinite ease-in-out;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-30px) rotate(180deg); }
    }
    
    .bedspaces-hero-content {
        position: relative;
        z-index: 1;
        text-align: center;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .bedspaces-hero h1 {
        font-size: 3.5rem;
        font-weight: 900;
        margin: 0 0 1rem 0;
        line-height: 1.1;
    }
    
    .bedspaces-hero p {
        font-size: 1.3rem;
        opacity: 0.95;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    
    /* Stats Pills */
    .hero-stats {
        display: flex;
        gap: 2rem;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .hero-stat {
        padding: 1rem 2rem;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .hero-stat-value {
        font-size: 2rem;
        font-weight: 900;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .hero-stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
        font-weight: 600;
    }
    
    /* Layout */
    .bedspaces-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
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
        font-size: 1.2rem;
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
    
    .filter-input {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }
    
    .filter-input:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .price-range-inputs {
        display: grid;
        grid-template-columns: 1fr 1fr;
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
        margin-bottom: 0.5rem;
    }
    
    .checkbox-label:hover {
        border-color: #10b981;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
    }
    
    .checkbox-label input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #10b981;
    }
    
    .filter-select {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        cursor: pointer;
        background: white;
        transition: all 0.3s ease;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #10b981;
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
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .filter-btn.apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    }
    
    .filter-btn.reset {
        background: white;
        color: #64748b;
        border: 2px solid #e2e8f0;
    }
    
    /* Results Section */
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
        color: #10b981;
        font-size: 1.3rem;
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
    
    /* Bedspace Room Cards */
    .bedspaces-grid {
        display: grid;
        gap: 2.5rem;
    }
    
    .bedspace-room-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .bedspace-room-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        border-color: #10b981;
    }
    
    .bedspace-room-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 0;
    }
    
    .room-image-section {
        position: relative;
        height: 300px;
        overflow: hidden;
    }
    
    .room-image-section img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .bedspace-room-card:hover .room-image-section img {
        transform: scale(1.1);
    }
    
    .room-image-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 5rem;
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
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .room-info-section {
        padding: 2rem;
        display: flex;
        flex-direction: column;
    }
    
    .room-header-section {
        margin-bottom: 1.5rem;
    }
    
    .room-number-large {
        font-size: 2rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
    }
    
    .room-meta {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .room-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #475569;
        border: 2px solid #e2e8f0;
    }
    
    .price-display {
        padding: 1.5rem;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 16px;
        text-align: center;
        color: white;
        margin-bottom: 1.5rem;
    }
    
    .price-amount {
        font-size: 2.5rem;
        font-weight: 900;
        margin: 0;
        line-height: 1;
    }
    
    .price-period {
        font-size: 1rem;
        opacity: 0.9;
        margin-top: 0.25rem;
        font-weight: 600;
    }
    
    /* Bedspace Grid */
    .bedspaces-grid-visual {
        margin-bottom: 1.5rem;
    }
    
    .bedspaces-grid-title {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .bedspace-slots {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
        gap: 0.75rem;
    }
    
    .bedspace-slot {
        padding: 1rem 0.5rem;
        border-radius: 12px;
        text-align: center;
        font-weight: 700;
        font-size: 0.9rem;
        border: 2px solid;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .bedspace-slot.available {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-color: #059669;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .bedspace-slot.available:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    }
    
    .bedspace-slot.occupied {
        background: #f1f5f9;
        color: #94a3b8;
        border-color: #e2e8f0;
        cursor: not-allowed;
    }
    
    .bedspace-slot.maintenance {
        background: #fef3c7;
        color: #92400e;
        border-color: #fbbf24;
    }
    
    .bedspace-letter {
        font-size: 1.5rem;
        font-weight: 900;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .bedspace-status {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    /* Room Actions */
    .room-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: auto;
    }
    
    .room-action-btn {
        padding: 1rem;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .room-action-btn.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: 2px solid transparent;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .room-action-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    }
    
    .room-action-btn.secondary {
        background: white;
        color: #10b981;
        border: 2px solid #10b981;
    }
    
    .room-action-btn.secondary:hover {
        background: #10b981;
        color: white;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .empty-icon {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .empty-state p {
        font-size: 1.1rem;
        color: #64748b;
        margin-bottom: 2rem;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .bedspaces-layout {
            grid-template-columns: 1fr;
        }
        
        .filter-sidebar {
            position: static;
        }
        
        .bedspace-room-layout {
            grid-template-columns: 1fr;
        }
        
        .room-image-section {
            height: 250px;
        }
    }
    
    @media (max-width: 768px) {
        .bedspaces-hero h1 {
            font-size: 2.5rem;
        }
        
        .hero-stats {
            flex-direction: column;
            gap: 1rem;
        }
        
        .room-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="bedspaces-page">
    <!-- Hero Section -->
    <div class="bedspaces-hero">
        <div class="container bedspaces-hero-content">
            <h1>🛏️ Find Your Perfect Bedspace</h1>
            <p>Affordable individual bed rentals in shared rooms. Book only the space you need!</p>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo $available_beds; ?></span>
                    <span class="hero-stat-label">Beds Available</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value"><?php echo $stats['total_bedspace_rooms']; ?></span>
                    <span class="hero-stat-label">Bedspace Rooms</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-value">₱<?php echo number_format($stats['min_price']); ?></span>
                    <span class="hero-stat-label">Starting Price</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="bedspaces-layout">
            <!-- Filter Sidebar -->
            <aside class="filter-sidebar">
                <form method="GET" action="" id="filterForm">
                    <div class="filter-card">
                        <h3>🔍 Filter Bedspaces</h3>
                        
                        <!-- Price Range -->
                        <div class="filter-group">
                            <label class="filter-label">Price Range (₱/month)</label>
                            <div class="price-range-inputs">
                                <input type="number" name="min_price" class="filter-input" 
                                       placeholder="Min" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" min="0">
                                <input type="number" name="max_price" class="filter-input" 
                                       placeholder="Max" value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" min="0">
                            </div>
                        </div>
                        
                        <!-- Floor -->
                        <div class="filter-group">
                            <label class="filter-label">Floor</label>
                            <select name="floor" class="filter-select">
                                <option value="0" <?php echo $floor === 0 ? 'selected' : ''; ?>>All Floors</option>
                                <?php foreach ($floors as $f): ?>
                                    <option value="<?php echo $f['floor_number']; ?>" 
                                            <?php echo $floor === $f['floor_number'] ? 'selected' : ''; ?>>
                                        Floor <?php echo $f['floor_number']; ?> (<?php echo $f['count']; ?> rooms)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Availability -->
                        <div class="filter-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="available_only" value="1" <?php echo $available_only ? 'checked' : ''; ?>>
                                <span>Show Only Available Beds</span>
                            </label>
                        </div>
                        
                        <!-- Amenities -->
                        <div class="filter-group">
                            <label class="filter-label">Amenities</label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="wifi" value="1" <?php echo $has_wifi ? 'checked' : ''; ?>>
                                <span>📶 WiFi</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="ac" value="1" <?php echo $has_ac ? 'checked' : ''; ?>>
                                <span>❄️ Air Conditioning</span>
                            </label>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="filter-btn apply">Apply</button>
                            <a href="bedspaces" class="filter-btn reset">Reset</a>
                        </div>
                    </div>
                </form>
            </aside>
            
            <!-- Results Section -->
            <main class="results-section">
                <!-- Results Header -->
                <div class="results-header">
                    <div class="results-info">
                        <strong><?php echo $bedspace_rooms->num_rows; ?></strong> 
                        bedspace room<?php echo $bedspace_rooms->num_rows !== 1 ? 's' : ''; ?> found
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
                            <option value="availability" <?php echo $sort_by === 'availability' ? 'selected' : ''; ?>>
                                Most Available
                            </option>
                            <option value="room_number" <?php echo $sort_by === 'room_number' ? 'selected' : ''; ?>>
                                Room Number
                            </option>
                        </select>
                    </div>
                </div>
                
                <!-- Bedspace Rooms Grid -->
                <?php if ($bedspace_rooms && $bedspace_rooms->num_rows > 0): ?>
                    <div class="bedspaces-grid">
                        <?php while ($room = $bedspace_rooms->fetch_assoc()): 
                            $bedspaces = get_room_bedspaces($conn, $room['id']);
                        ?>
                            <article class="bedspace-room-card">
                                <div class="bedspace-room-layout">
                                    <!-- Room Image -->
                                    <div class="room-image-section">
                                        <?php if ($room['primary_photo']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($room['primary_photo']); ?>" 
                                                 alt="Room <?php echo htmlspecialchars($room['room_number']); ?>">
                                        <?php else: ?>
                                            <div class="room-image-placeholder">🛏️</div>
                                        <?php endif; ?>
                                        
                                        <span class="room-status-badge">
                                            <?php echo $room['available_bedspaces']; ?>/<?php echo $room['total_bedspaces']; ?> Available
                                        </span>
                                    </div>
                                    
                                    <!-- Room Info -->
                                    <div class="room-info-section">
                                        <div class="room-header-section">
                                            <h3 class="room-number-large">Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                                            <div class="room-meta">
                                                <span class="room-meta-item">
                                                    📐 <?php echo ucfirst($room['room_type']); ?>
                                                </span>
                                                <span class="room-meta-item">
                                                    🏢 Floor <?php echo $room['floor_number']; ?>
                                                </span>
                                                <?php if ($room['has_wifi']): ?>
                                                    <span class="room-meta-item">📶 WiFi</span>
                                                <?php endif; ?>
                                                <?php if ($room['has_ac']): ?>
                                                    <span class="room-meta-item">❄️ AC</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="price-display">
                                            <div class="price-amount">₱<?php echo number_format($room['price_per_bedspace'], 0); ?></div>
                                            <div class="price-period">per bed / month</div>
                                        </div>
                                        
                                        <!-- Bedspace Grid -->
                                        <div class="bedspaces-grid-visual">
                                            <div class="bedspaces-grid-title">
                                                <span>🛏️</span>
                                                <span>Bedspace Availability</span>
                                            </div>
                                            <div class="bedspace-slots">
                                                <?php foreach ($bedspaces as $bs): ?>
                                                    <div class="bedspace-slot <?php echo $bs['status']; ?>" 
                                                         title="Bedspace <?php echo $bs['bedspace_number']; ?> - <?php echo ucfirst($bs['status']); ?>">
                                                        <span class="bedspace-letter"><?php echo $bs['bedspace_number']; ?></span>
                                                        <span class="bedspace-status"><?php echo $bs['status']; ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="room-actions">
                                            <a href="room_view?id=<?php echo $room['id']; ?>" 
                                               class="room-action-btn secondary">
                                                <span>👁️</span>
                                                <span>View Details</span>
                                            </a>
                                            <?php if ($room['available_bedspaces'] > 0): ?>
                                                <?php if (isset($_SESSION['user_id'])): ?>
                                                    <a href="booking?room_id=<?php echo $room['id']; ?>" 
                                                       class="room-action-btn primary">
                                                        <span>✅</span>
                                                        <span>Book Bedspace</span>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo LOGIN_URL; ?>?redirect=booking?room_id=<?php echo $room['id']; ?>" 
                                                       class="room-action-btn primary">
                                                        <span>🔐</span>
                                                        <span>Login to Book</span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="room-action-btn" style="background: #ef4444; color: white; cursor: not-allowed;">
                                                    <span>❌</span>
                                                    <span>All Beds Taken</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>No Bedspaces Found</h3>
                        <p>No bedspaces match your current filters. Try adjusting your search criteria.</p>
                        <a href="bedspaces" class="room-action-btn primary" style="display: inline-flex;">
                            View All Bedspaces
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
