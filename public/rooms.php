<?php
// filepath: public/rooms.php
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = 'Available Rooms';
require_once '../includes/header.php';

// Get filter parameters
$room_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$floor = isset($_GET['floor']) ? intval($_GET['floor']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$has_wifi = isset($_GET['has_wifi']) ? 1 : 0;
$has_ac = isset($_GET['has_ac']) ? 1 : 0;

// Build query with filters
$query = "SELECT r.*, 
          (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_photo,
          (SELECT COUNT(*) FROM room_photos WHERE room_id = r.id) as photo_count
          FROM rooms r 
          WHERE r.status = 'available'";

$params = [];
$types = "";

if ($room_type) {
    $query .= " AND r.room_type = ?";
    $params[] = $room_type;
    $types .= "s";
}

if ($category) {
    $query .= " AND r.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($floor > 0) {
    $query .= " AND r.floor_number = ?";
    $params[] = $floor;
    $types .= "i";
}

if ($max_price > 0) {
    $query .= " AND r.price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

if ($has_wifi) {
    $query .= " AND r.has_wifi = 1";
}

if ($has_ac) {
    $query .= " AND r.has_ac = 1";
}

$query .= " ORDER BY r.room_number";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Get available filters data
$types_result = $conn->query("SELECT DISTINCT room_type FROM rooms WHERE status = 'available' ORDER BY room_type");
$categories_result = $conn->query("SELECT DISTINCT category FROM rooms WHERE status = 'available' AND category IS NOT NULL ORDER BY category");
$floors_result = $conn->query("SELECT DISTINCT floor_number FROM rooms WHERE status = 'available' AND floor_number IS NOT NULL ORDER BY floor_number");
?>

<style>
.filter-card {
    position: sticky;
    top: 80px;
}

.room-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.room-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.room-photo {
    position: relative;
    width: 100%;
    height: 220px;
    object-fit: cover;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    margin: -1.5rem -1.5rem 1rem -1.5rem;
}

.photo-count-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.amenity-icon {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    background: #f3f4f6;
    border-radius: 20px;
    font-size: 0.875rem;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.price-tag {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}

.filter-section {
    margin-bottom: 1.5rem;
}

.filter-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
}
</style>

<div class="container">
    <h1 class="mb-4">Available Rooms</h1>
    
    <div class="grid" style="grid-template-columns: 300px 1fr; gap: 2rem;">
        <!-- Filter Sidebar -->
        <div class="filter-card card">
            <div class="card-header">
                <h2>Filters</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="filter-section">
                        <label class="filter-label">Room Type</label>
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            <?php while ($type_row = $types_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($type_row['room_type']); ?>"
                                        <?php echo $room_type === $type_row['room_type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type_row['room_type']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <label class="filter-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php while ($cat_row = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat_row['category']); ?>"
                                        <?php echo $category === $cat_row['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat_row['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <label class="filter-label">Floor</label>
                        <select name="floor" class="form-control">
                            <option value="0">All Floors</option>
                            <?php while ($floor_row = $floors_result->fetch_assoc()): ?>
                                <option value="<?php echo $floor_row['floor_number']; ?>"
                                        <?php echo $floor == $floor_row['floor_number'] ? 'selected' : ''; ?>>
                                    Floor <?php echo $floor_row['floor_number']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <label class="filter-label">Max Price (Monthly)</label>
                        <input type="number" name="max_price" class="form-control" 
                               placeholder="Enter max price" 
                               value="<?php echo $max_price > 0 ? $max_price : ''; ?>" 
                               step="100" min="0">
                    </div>
                    
                    <div class="filter-section">
                        <label class="filter-label">Amenities</label>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem;">
                                <input type="checkbox" name="has_wifi" value="1" 
                                       <?php echo $has_wifi ? 'checked' : ''; ?>>
                                WiFi
                            </label>
                            <label style="display: block; margin-bottom: 0.5rem;">
                                <input type="checkbox" name="has_ac" value="1" 
                                       <?php echo $has_ac ? 'checked' : ''; ?>>
                                Air Conditioning
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                        Apply Filters
                    </button>
                    <a href="rooms.php" class="btn btn-outline" style="width: 100%;">
                        Clear Filters
                    </a>
                </form>
            </div>
        </div>
        
        <!-- Rooms Grid -->
        <div>
            <?php if ($result && $result->num_rows > 0): ?>
                <p class="mb-3" style="color: var(--text-light);">
                    Showing <?php echo $result->num_rows; ?> available room(s)
                </p>
                
                <div class="grid grid-2">
                    <?php while ($room = $result->fetch_assoc()): ?>
                        <div class="card room-card">
                            <?php if ($room['primary_photo']): ?>
                                <div style="position: relative;">
                                    <img src="../uploads/<?php echo htmlspecialchars($room['primary_photo']); ?>" 
                                         alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                                         class="room-photo">
                                    <?php if ($room['photo_count'] > 1): ?>
                                        <span class="photo-count-badge">
                                            📷 <?php echo $room['photo_count']; ?> photos
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="color: var(--primary-color); margin-bottom: 0.25rem;">
                                        Room <?php echo htmlspecialchars($room['room_number']); ?>
                                    </h3>
                                    <p style="color: var(--text-light); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($room['room_type']); ?>
                                        <?php if ($room['category']): ?>
                                            • <?php echo htmlspecialchars($room['category']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="price-tag">
                                    <?php echo format_currency($room['price']); ?>
                                    <span style="font-size: 0.875rem; font-weight: normal; color: var(--text-light);">/mo</span>
                                </span>
                            </div>
                            
                            <div style="margin-bottom: 1rem;">
                                <p style="margin-bottom: 0.5rem;">
                                    <strong>Capacity:</strong> <?php echo htmlspecialchars($room['capacity']); ?> person(s)
                                </p>
                                <?php if ($room['floor_number']): ?>
                                    <p style="margin-bottom: 0.5rem;">
                                        <strong>Floor:</strong> <?php echo $room['floor_number']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Amenities -->
                            <div style="margin-bottom: 1rem;">
                                <?php if ($room['has_wifi']): ?>
                                    <span class="amenity-icon">📶 WiFi</span>
                                <?php endif; ?>
                                <?php if ($room['has_ac']): ?>
                                    <span class="amenity-icon">❄️ AC</span>
                                <?php endif; ?>
                                <?php if ($room['has_bathroom']): ?>
                                    <span class="amenity-icon">🚿 Private Bath</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($room['description']): ?>
                                <p style="color: var(--text-light); margin-bottom: 1rem; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($room['description'], 0, 100)) . (strlen($room['description']) > 100 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="room_view.php?id=<?php echo $room['id']; ?>" 
                                   class="btn btn-outline" style="flex: 1;">
                                    View Details
                                </a>
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'tenant'): ?>
                                    <a href="booking.php?room_id=<?php echo $room['id']; ?>" 
                                       class="btn btn-primary" style="flex: 1;">
                                        Book Now
                                    </a>
                                <?php else: ?>
                                    <a href="../login.php" 
                                       class="btn btn-primary" style="flex: 1;">
                                        Login to Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card text-center" style="padding: 3rem;">
                    <h3 style="color: var(--text-light); margin-bottom: 1rem;">No rooms found</h3>
                    <p>Try adjusting your filters or check back later for new availability.</p>
                    <a href="rooms.php" class="btn btn-primary" style="margin-top: 1rem;">Clear All Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>