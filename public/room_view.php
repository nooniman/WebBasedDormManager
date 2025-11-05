<?php
// filepath: public/room_view.php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect('rooms.php');
}

$room_id = intval($_GET['id']);

// Fetch room details
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'available'");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('rooms.php');
}

$room = $result->fetch_assoc();
$stmt->close();

// Fetch room photos
$photos_result = $conn->query("SELECT * FROM room_photos WHERE room_id = $room_id ORDER BY is_primary DESC, created_at ASC");

// Fetch room amenities
$amenities_result = $conn->query("
    SELECT ra.name, ra.icon 
    FROM room_amenity_assignments raa
    JOIN room_amenities ra ON raa.amenity_id = ra.id
    WHERE raa.room_id = $room_id
    ORDER BY ra.name
");

$page_title = 'Room ' . $room['room_number'];
require_once '../includes/header.php';
?>

<style>
.photo-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.gallery-photo {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: transform 0.3s ease;
}

.gallery-photo:hover {
    transform: scale(1.05);
}

.primary-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.amenity-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.amenity-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: var(--border-radius);
}

.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.lightbox.active {
    display: flex;
}

.lightbox img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 40px;
    color: white;
    font-size: 40px;
    cursor: pointer;
    background: none;
    border: none;
}
</style>

<div class="container">
    <a href="rooms.php" class="btn btn-outline mb-3">← Back to Rooms</a>
    
    <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
        <!-- Main Content -->
        <div>
            <h1 class="mb-2">Room <?php echo htmlspecialchars($room['room_number']); ?></h1>
            <p style="color: var(--text-light); font-size: 1.125rem; margin-bottom: 2rem;">
                <?php echo htmlspecialchars($room['room_type']); ?>
                <?php if ($room['category']): ?>
                    • <?php echo htmlspecialchars($room['category']); ?>
                <?php endif; ?>
                <?php if ($room['floor_number']): ?>
                    • Floor <?php echo $room['floor_number']; ?>
                <?php endif; ?>
            </p>
            
            <!-- Photo Gallery -->
            <?php if ($photos_result && $photos_result->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>Photos</h2>
                    </div>
                    <div class="card-body">
                        <div class="photo-gallery">
                            <?php while ($photo = $photos_result->fetch_assoc()): ?>
                                <div style="position: relative;">
                                    <?php if ($photo['is_primary']): ?>
                                        <span class="primary-badge">Primary</span>
                                    <?php endif; ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($photo['photo_path']); ?>" 
                                         alt="Room photo"
                                         class="gallery-photo"
                                         onclick="openLightbox('<?php echo htmlspecialchars($photo['photo_path']); ?>')">
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Description -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Description</h2>
                </div>
                <div class="card-body">
                    <?php if ($room['description']): ?>
                        <p><?php echo nl2br(htmlspecialchars($room['description'])); ?></p>
                    <?php else: ?>
                        <p style="color: var(--text-light);">No description available.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Amenities -->
            <?php if ($amenities_result && $amenities_result->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>Amenities</h2>
                    </div>
                    <div class="card-body">
                        <div class="amenity-list">
                            <?php while ($amenity = $amenities_result->fetch_assoc()): ?>
                                <div class="amenity-item">
                                    <span style="font-size: 1.25rem;">
                                        <?php 
                                        $icons = [
                                            'wifi' => '📶',
                                            'ac' => '❄️',
                                            'bathroom' => '🚿',
                                            'desk' => '🪑',
                                            'closet' => '🚪',
                                            'window' => '🪟',
                                            'balcony' => '🏞️',
                                            'fridge' => '🧊'
                                        ];
                                        echo $icons[$amenity['icon']] ?? '✓';
                                        ?>
                                    </span>
                                    <span><?php echo htmlspecialchars($amenity['name']); ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Booking Sidebar -->
        <div>
            <div class="card" style="position: sticky; top: 80px;">
                <div class="card-body">
                    <div style="margin-bottom: 1.5rem;">
                        <p style="font-size: 2rem; font-weight: bold; color: var(--primary-color); margin-bottom: 0.25rem;">
                            <?php echo format_currency($room['price']); ?>
                        </p>
                        <p style="color: var(--text-light);">per month</p>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: var(--border-radius);">
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Capacity:</strong> <?php echo $room['capacity']; ?> person(s)
                        </p>
                        <?php if ($room['floor_number']): ?>
                            <p style="margin-bottom: 0.5rem;">
                                <strong>Floor:</strong> <?php echo $room['floor_number']; ?>
                            </p>
                        <?php endif; ?>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Type:</strong> <?php echo htmlspecialchars($room['room_type']); ?>
                        </p>
                        <?php if ($room['category']): ?>
                            <p style="margin-bottom: 0;">
                                <strong>Category:</strong> <?php echo htmlspecialchars($room['category']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
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
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'tenant'): ?>
                        <a href="booking.php?room_id=<?php echo $room['id']; ?>" 
                           class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                            Book This Room
                        </a>
                    <?php else: ?>
                        <a href="../login.php" 
                           class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                            Login to Book
                        </a>
                    <?php endif; ?>
                    
                    <p style="font-size: 0.875rem; color: var(--text-light); text-align: center;">
                        Available for immediate booking
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
    <img id="lightbox-img" src="" alt="Room photo">
</div>

<script>
function openLightbox(photoPath) {
    document.getElementById('lightbox-img').src = '../uploads/' + photoPath;
    document.getElementById('lightbox').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>