<?php
// filepath: public/room_view.php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: rooms.php');
    exit;
}

$room_id = intval($_GET['id']);

// Get room details with photos
$room_query = "
    SELECT r.*,
           CASE 
               WHEN r.is_bedspace = 1 THEN (r.total_bedspaces - r.occupied_bedspaces)
               ELSE NULL 
           END as available_bedspaces
    FROM rooms r 
    WHERE r.id = ?
";
$stmt = $conn->prepare($room_query);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    header('Location: rooms.php');
    exit;
}

// Get all photos for this room
$photos_query = "SELECT * FROM room_photos WHERE room_id = ? ORDER BY is_primary DESC, id ASC";
$stmt = $conn->prepare($photos_query);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$photos = $stmt->get_result();
$stmt->close();

// Get similar rooms
$similar_query = "
    SELECT r.*, 
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_photo
    FROM rooms r 
    WHERE r.room_type = ? 
    AND r.id != ? 
    AND r.status = 'available'
    ORDER BY ABS(r.price - ?) ASC
    LIMIT 3
";
$stmt = $conn->prepare($similar_query);
$stmt->bind_param("sii", $room['room_type'], $room_id, $room['price']);
$stmt->execute();
$similar_rooms = $stmt->get_result();
$stmt->close();

$page_title = 'Room ' . $room['room_number'];
require_once '../includes/header.php';
?>

<style>
    .room-view-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Breadcrumb */
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .breadcrumb a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .breadcrumb a:hover {
        color: #764ba2;
    }
    
    .breadcrumb span {
        color: #94a3b8;
    }
    
    /* Main Layout */
    .room-detail-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    /* Photo Gallery */
    .photo-gallery-section {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .main-photo-container {
        width: 100%;
        height: 500px;
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 1.5rem;
        position: relative;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .main-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    
    .main-photo:hover {
        transform: scale(1.05);
    }
    
    .main-photo-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 6rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .photo-counter {
        position: absolute;
        bottom: 1.5rem;
        right: 1.5rem;
        padding: 0.75rem 1.25rem;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        color: white;
        border-radius: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .photo-thumbnails {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 1rem;
    }
    
    .photo-thumbnail {
        width: 100%;
        height: 100px;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        border: 3px solid transparent;
        transition: all 0.3s ease;
    }
    
    .photo-thumbnail:hover,
    .photo-thumbnail.active {
        border-color: #667eea;
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
    }
    
    .photo-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .photo-thumbnail-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
    }
    
    /* Room Info Card */
    .room-info-card {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        position: sticky;
        top: 2rem;
    }
    
    .room-header-info {
        margin-bottom: 2rem;
    }
    
    .room-number-display {
        font-size: 2.5rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
    }
    
    .room-type-display {
        display: inline-block;
        padding: 0.5rem 1.25rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 2px solid #667eea;
        border-radius: 12px;
        color: #667eea;
        font-weight: 700;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .room-status-display {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
        border: 2px solid #10b981;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
        color: #065f46;
    }
    
    .price-section {
        padding: 2rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        text-align: center;
        margin-bottom: 2rem;
        color: white;
    }
    
    .price-label {
        font-size: 0.95rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    
    .price-amount-large {
        font-size: 3rem;
        font-weight: 900;
        margin: 0;
        line-height: 1;
    }
    
    .price-period-large {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-top: 0.5rem;
        font-weight: 600;
    }
    
    .room-features-detail {
        margin-bottom: 2rem;
    }
    
    .features-detail-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .features-detail-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .feature-detail-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        font-weight: 600;
        color: #475569;
        border: 2px solid #e2e8f0;
    }
    
    .feature-detail-item .icon {
        font-size: 1.5rem;
    }
    
    .booking-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .booking-btn {
        padding: 1.25rem;
        border-radius: 14px;
        font-weight: 800;
        font-size: 1.1rem;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }
    
    .booking-btn.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        border: 2px solid transparent;
    }
    
    .booking-btn.primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(16, 185, 129, 0.4);
    }
    
    .booking-btn.secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }
    
    .booking-btn.secondary:hover {
        background: #667eea;
        color: white;
    }
    
    /* Room Details Section */
    .room-details-section {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .section-title-detail {
        font-size: 1.75rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 1.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .room-description-text {
        font-size: 1.1rem;
        color: #64748b;
        line-height: 1.8;
        margin: 0;
    }
    
    .amenities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .amenity-card {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 16px;
        text-align: center;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .amenity-card:hover {
        border-color: #667eea;
        transform: translateY(-3px);
    }
    
    .amenity-icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }
    
    .amenity-label {
        font-weight: 700;
        color: #475569;
        font-size: 0.95rem;
    }
    
    /* Similar Rooms Section */
    .similar-rooms-section {
        margin-top: 3rem;
    }
    
    .similar-rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    
    .similar-room-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .similar-room-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 10px 32px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }
    
    .similar-room-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    
    .similar-room-placeholder {
        width: 100%;
        height: 200px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: white;
    }
    
    .similar-room-content {
        padding: 1.5rem;
    }
    
    .similar-room-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    
    .similar-room-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 0.25rem 0;
    }
    
    .similar-room-type {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .similar-room-price {
        font-size: 1.5rem;
        font-weight: 900;
        color: #667eea;
    }
    
    .similar-room-btn {
        display: block;
        width: 100%;
        padding: 0.75rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-align: center;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        transition: all 0.3s ease;
    }
    
    .similar-room-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    /* Lightbox Modal */
    .lightbox-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    
    .lightbox-modal.active {
        display: flex;
    }
    
    .lightbox-content {
        max-width: 90%;
        max-height: 90%;
        position: relative;
    }
    
    .lightbox-image {
        max-width: 100%;
        max-height: 90vh;
        border-radius: 12px;
    }
    
    .lightbox-close {
        position: absolute;
        top: -50px;
        right: 0;
        width: 50px;
        height: 50px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .lightbox-close:hover {
        background: #ef4444;
        color: white;
        transform: rotate(90deg);
    }
    
    .lightbox-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .lightbox-nav:hover {
        background: #667eea;
        color: white;
    }
    
    .lightbox-nav.prev {
        left: -70px;
    }
    
    .lightbox-nav.next {
        right: -70px;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .room-detail-layout {
            grid-template-columns: 1fr;
        }
        
        .room-info-card {
            position: static;
        }
    }
    
    @media (max-width: 768px) {
        .main-photo-container {
            height: 300px;
        }
        
        .room-number-display {
            font-size: 2rem;
        }
        
        .price-amount-large {
            font-size: 2.5rem;
        }
        
        .similar-rooms-grid {
            grid-template-columns: 1fr;
        }
        
        .amenities-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="room-view-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="<?php echo PUBLIC_URL; ?>">Home</a>
            <span>→</span>
            <a href="rooms">Rooms</a>
            <span>→</span>
            <span>Room <?php echo htmlspecialchars($room['room_number']); ?></span>
        </nav>
        
        <div class="room-detail-layout">
            <!-- Left Column - Photos & Details -->
            <div>
                <!-- Photo Gallery -->
                <div class="photo-gallery-section">
                    <?php 
                    $photos_array = [];
                    $photos->data_seek(0);
                    while ($photo = $photos->fetch_assoc()) {
                        $photos_array[] = $photo;
                    }
                    ?>
                    
                    <div class="main-photo-container" onclick="openLightbox(0)">
                        <?php if (!empty($photos_array)): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($photos_array[0]['photo_path']); ?>" 
                                 alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                                 class="main-photo"
                                 id="mainPhoto">
                            <div class="photo-counter">
                                📷 <span id="currentPhotoIndex">1</span> / <?php echo count($photos_array); ?>
                            </div>
                        <?php else: ?>
                            <div class="main-photo-placeholder">🏠</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($photos_array) > 1): ?>
                        <div class="photo-thumbnails">
                            <?php foreach ($photos_array as $index => $photo): ?>
                                <div class="photo-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     onclick="changePhoto(<?php echo $index; ?>)"
                                     data-index="<?php echo $index; ?>">
                                    <img src="../uploads/<?php echo htmlspecialchars($photo['photo_path']); ?>" 
                                         alt="Photo <?php echo $index + 1; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Room Description -->
                <div class="room-details-section">
                    <h2 class="section-title-detail">
                        <span class="section-icon">📝</span>
                        Room Description
                    </h2>
                    <?php if ($room['description']): ?>
                        <p class="room-description-text">
                            <?php echo nl2br(htmlspecialchars($room['description'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="room-description-text" style="color: #94a3b8;">
                            No detailed description available for this room. Please contact us for more information.
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Amenities -->
                <div class="room-details-section">
                    <h2 class="section-title-detail">
                        <span class="section-icon">⭐</span>
                        Amenities & Features
                    </h2>
                    <div class="amenities-grid">
                        <div class="amenity-card">
                            <div class="amenity-icon">👥</div>
                            <div class="amenity-label">Capacity: <?php echo $room['capacity']; ?> Person(s)</div>
                        </div>
                        <?php if ($room['has_wifi']): ?>
                            <div class="amenity-card">
                                <div class="amenity-icon">📶</div>
                                <div class="amenity-label">WiFi Available</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($room['has_ac']): ?>
                            <div class="amenity-card">
                                <div class="amenity-icon">❄️</div>
                                <div class="amenity-label">Air Conditioning</div>
                            </div>
                        <?php endif; ?>
                        <div class="amenity-card">
                            <div class="amenity-icon">🛏️</div>
                            <div class="amenity-label">Comfortable Beds</div>
                        </div>
                        <div class="amenity-card">
                            <div class="amenity-icon">🚿</div>
                            <div class="amenity-label">Private Bathroom</div>
                        </div>
                        <div class="amenity-card">
                            <div class="amenity-icon">🔐</div>
                            <div class="amenity-label">Secure Access</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Booking Info -->
            <aside>
                <div class="room-info-card">
                    <div class="room-header-info">
                        <h1 class="room-number-display">Room <?php echo htmlspecialchars($room['room_number']); ?></h1>
                        <span class="room-type-display"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></span>
                        
                        <?php if ($room['status'] === 'available'): ?>
                            <div class="room-status-display">
                                <span>✓</span>
                                <span>Available for Booking</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="price-section">
                        <div class="price-label"><?php echo $room['is_bedspace'] ? 'Per Bedspace' : 'Monthly Rent'; ?></div>
                        <div class="price-amount-large">₱<?php echo number_format($room['is_bedspace'] ? $room['price_per_bedspace'] : $room['price'], 0); ?></div>
                        <div class="price-period-large">per <?php echo $room['is_bedspace'] ? 'bed/month' : 'month'; ?></div>
                        <?php if ($room['is_bedspace']): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 12px; text-align: center; font-weight: 700;">
                                🛏️ <?php echo $room['available_bedspaces']; ?>/<?php echo $room['total_bedspaces']; ?> Bedspaces Available
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="room-features-detail">
                        <h3 class="features-detail-title">
                            🔑 Key Features
                        </h3>
                        <div class="features-detail-list">
                            <?php if ($room['is_bedspace']): ?>
                                <div class="feature-detail-item">
                                    <span class="icon">🛏️</span>
                                    <span>Bedspacing Room (<?php echo $room['total_bedspaces']; ?> beds total)</span>
                                </div>
                                <div class="feature-detail-item">
                                    <span class="icon">✅</span>
                                    <span><?php echo $room['available_bedspaces']; ?> bedspace(s) available</span>
                                </div>
                            <?php else: ?>
                                <div class="feature-detail-item">
                                    <span class="icon">👥</span>
                                    <span>Up to <?php echo $room['capacity']; ?> person(s)</span>
                                </div>
                            <?php endif; ?>
                            <div class="feature-detail-item">
                                <span class="icon">📐</span>
                                <span><?php echo ucfirst($room['room_type']); ?> Room</span>
                            </div>
                            <?php if ($room['has_wifi']): ?>
                                <div class="feature-detail-item">
                                    <span class="icon">📶</span>
                                    <span>High-Speed WiFi</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($room['has_ac']): ?>
                                <div class="feature-detail-item">
                                    <span class="icon">❄️</span>
                                    <span>Air Conditioned</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="booking-actions">
                        <?php if ($room['status'] === 'available'): ?>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="booking?room_id=<?php echo $room['id']; ?>" class="booking-btn primary">
                                    <span>📝</span>
                                    <span>Book This Room</span>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo LOGIN_URL; ?>?redirect=booking?room_id=<?php echo $room['id']; ?>" class="booking-btn primary">
                                    <span>🔐</span>
                                    <span>Login to Book</span>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="booking-btn" style="background: #ef4444; cursor: not-allowed;">
                                <span>⚠️</span>
                                <span>Not Available</span>
                            </div>
                        <?php endif; ?>
                        
                        <a href="rooms" class="booking-btn secondary">
                            <span>←</span>
                            <span>Back to Rooms</span>
                        </a>
                    </div>
                </div>
            </aside>
        </div>
        
        <!-- Similar Rooms -->
        <?php if ($similar_rooms && $similar_rooms->num_rows > 0): ?>
            <section class="similar-rooms-section">
                <h2 class="section-title-detail">
                    <span class="section-icon">🏠</span>
                    Similar Rooms You Might Like
                </h2>
                
                <div class="similar-rooms-grid">
                    <?php while ($similar = $similar_rooms->fetch_assoc()): ?>
                        <div class="similar-room-card">
                            <?php if ($similar['primary_photo']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($similar['primary_photo']); ?>" 
                                     alt="Room <?php echo htmlspecialchars($similar['room_number']); ?>"
                                     class="similar-room-image">
                            <?php else: ?>
                                <div class="similar-room-placeholder">🏠</div>
                            <?php endif; ?>
                            
                            <div class="similar-room-content">
                                <div class="similar-room-header">
                                    <div>
                                        <h3 class="similar-room-title">Room <?php echo htmlspecialchars($similar['room_number']); ?></h3>
                                        <p class="similar-room-type"><?php echo ucfirst(htmlspecialchars($similar['room_type'])); ?></p>
                                    </div>
                                    <div class="similar-room-price">₱<?php echo number_format($similar['price'], 0); ?></div>
                                </div>
                                <a href="room_view?id=<?php echo $similar['id']; ?>" class="similar-room-btn">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<!-- Lightbox Modal -->
<?php if (!empty($photos_array)): ?>
<div class="lightbox-modal" id="lightboxModal" onclick="closeLightbox(event)">
    <div class="lightbox-content">
        <div class="lightbox-close" onclick="closeLightbox(event)">×</div>
        <?php if (count($photos_array) > 1): ?>
            <div class="lightbox-nav prev" onclick="navigateLightbox(-1, event)">‹</div>
            <div class="lightbox-nav next" onclick="navigateLightbox(1, event)">›</div>
        <?php endif; ?>
        <img src="" alt="Room Photo" class="lightbox-image" id="lightboxImage">
    </div>
</div>
<?php endif; ?>

<script>
const photos = <?php echo json_encode(array_map(function($p) { return $p['photo_path']; }, $photos_array)); ?>;
let currentPhotoIndex = 0;
let currentLightboxIndex = 0;

function changePhoto(index) {
    currentPhotoIndex = index;
    const mainPhoto = document.getElementById('mainPhoto');
    const photoCounter = document.getElementById('currentPhotoIndex');
    
    if (mainPhoto && photos[index]) {
        mainPhoto.src = '../uploads/' + photos[index];
        photoCounter.textContent = index + 1;
        
        // Update active thumbnail
        document.querySelectorAll('.photo-thumbnail').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    }
}

function openLightbox(index) {
    currentLightboxIndex = index;
    const modal = document.getElementById('lightboxModal');
    const image = document.getElementById('lightboxImage');
    
    if (modal && image && photos[index]) {
        image.src = '../uploads/' + photos[index];
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeLightbox(event) {
    if (event.target.id === 'lightboxModal' || event.target.classList.contains('lightbox-close')) {
        const modal = document.getElementById('lightboxModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function navigateLightbox(direction, event) {
    event.stopPropagation();
    currentLightboxIndex += direction;
    
    if (currentLightboxIndex < 0) currentLightboxIndex = photos.length - 1;
    if (currentLightboxIndex >= photos.length) currentLightboxIndex = 0;
    
    const image = document.getElementById('lightboxImage');
    if (image && photos[currentLightboxIndex]) {
        image.src = '../uploads/' + photos[currentLightboxIndex];
    }
}

// Keyboard navigation for lightbox
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('lightboxModal');
    if (modal && modal.classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox({ target: modal });
        } else if (e.key === 'ArrowLeft') {
            navigateLightbox(-1, e);
        } else if (e.key === 'ArrowRight') {
            navigateLightbox(1, e);
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>