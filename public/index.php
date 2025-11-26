<?php
// filepath: public/index.php
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = 'Home';

// Get room statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
        MIN(price) as min_price,
        MAX(price) as max_price
    FROM rooms
";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get featured rooms (3 random available rooms)
$featured_query = "
    SELECT r.*, 
           (SELECT photo_path FROM room_photos WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_photo
    FROM rooms r 
    WHERE r.status = 'available' 
    ORDER BY RAND() 
    LIMIT 3
";
$featured_rooms = $conn->query($featured_query);

require_once '../includes/header.php';
?>

<style>
    .homepage {
        animation: fadeIn 0.8s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Hero Section */
    .hero-section {
        position: relative;
        min-height: 600px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        overflow: hidden;
        margin: -2rem -2rem 3rem -2rem;
        padding: 5rem 2rem;
        border-radius: 0 0 40px 40px;
    }
    
    .hero-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 800px;
        height: 800px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 20s infinite ease-in-out;
    }
    
    .hero-section::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 600px;
        height: 600px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        animation: float 15s infinite ease-in-out reverse;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-30px) rotate(180deg); }
    }
    
    .hero-content {
        position: relative;
        z-index: 1;
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }
    
    .hero-badge {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 2rem;
        animation: slideDown 0.8s ease-out 0.2s both;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .hero-content h1 {
        font-size: 4rem;
        font-weight: 900;
        margin: 0 0 1.5rem 0;
        line-height: 1.2;
        animation: slideUp 0.8s ease-out 0.4s both;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .hero-content p {
        font-size: 1.4rem;
        margin: 0 auto 3rem;
        max-width: 700px;
        opacity: 0.95;
        line-height: 1.8;
        animation: slideUp 0.8s ease-out 0.6s both;
    }
    
    .hero-buttons {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        flex-wrap: wrap;
        animation: slideUp 0.8s ease-out 0.8s both;
    }
    
    .hero-btn {
        padding: 1rem 2.5rem;
        font-size: 1.1rem;
        font-weight: 700;
        border-radius: 16px;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }
    
    .hero-btn.primary {
        background: white;
        color: #667eea;
    }
    
    .hero-btn.primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
    }
    
    .hero-btn.outline {
        background: transparent;
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.5);
    }
    
    .hero-btn.outline:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: white;
        transform: translateY(-3px);
    }
    
    /* Stats Section */
    .stats-section {
        margin: -80px auto 4rem;
        position: relative;
        z-index: 2;
        max-width: 1200px;
        padding: 0 2rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 2.5rem;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        border-color: #667eea;
    }
    
    .stat-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: #1e293b;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .stat-label {
        font-size: 1.1rem;
        color: #64748b;
        font-weight: 600;
    }
    
    /* Features Section */
    .features-section {
        padding: 4rem 0;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .section-header {
        text-align: center;
        margin-bottom: 4rem;
    }
    
    .section-badge {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 2px solid #667eea;
        border-radius: 30px;
        color: #667eea;
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 1rem;
    }
    
    .section-title {
        font-size: 3rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 1rem 0;
    }
    
    .section-subtitle {
        font-size: 1.25rem;
        color: #64748b;
        max-width: 700px;
        margin: 0 auto;
        line-height: 1.8;
    }
    
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2.5rem;
        margin-top: 3rem;
    }
    
    .feature-card {
        background: white;
        padding: 3rem;
        border-radius: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid #e2e8f0;
        position: relative;
        overflow: hidden;
    }
    
    .feature-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        border-radius: 50%;
        transition: all 0.5s ease;
    }
    
    .feature-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }
    
    .feature-card:hover::before {
        top: -20%;
        right: -20%;
    }
    
    .feature-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        position: relative;
        z-index: 1;
    }
    
    .feature-card h3 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 1rem 0;
        position: relative;
        z-index: 1;
    }
    
    .feature-card p {
        font-size: 1.05rem;
        color: #64748b;
        line-height: 1.8;
        margin: 0;
        position: relative;
        z-index: 1;
    }
    
    /* Featured Rooms Section */
    .featured-rooms-section {
        padding: 4rem 0;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        margin: 4rem -2rem;
        padding: 4rem 2rem;
    }
    
    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2.5rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .room-card-featured {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .room-card-featured:hover {
        transform: translateY(-10px);
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        border-color: #667eea;
    }
    
    .room-image {
        width: 100%;
        height: 250px;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .room-card-featured:hover .room-image {
        transform: scale(1.1);
    }
    
    .room-image-placeholder {
        width: 100%;
        height: 250px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: white;
    }
    
    .room-content {
        padding: 2rem;
    }
    
    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1.5rem;
    }
    
    .room-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 0.5rem 0;
    }
    
    .room-type {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .room-price {
        text-align: right;
    }
    
    .price-amount {
        font-size: 2rem;
        font-weight: 900;
        color: #667eea;
        margin: 0;
        line-height: 1;
    }
    
    .price-period {
        font-size: 0.9rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }
    
    .room-features {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }
    
    .room-feature-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
    }
    
    .room-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .room-btn {
        flex: 1;
        padding: 0.875rem;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .room-btn.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .room-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
    }
    
    .room-btn.outline {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }
    
    .room-btn.outline:hover {
        background: #667eea;
        color: white;
    }
    
    /* CTA Section */
    .cta-section {
        margin: 4rem 0;
        padding: 4rem 3rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 32px;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .cta-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -20%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .cta-section::after {
        content: '';
        position: absolute;
        bottom: -40%;
        right: -15%;
        width: 350px;
        height: 350px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }
    
    .cta-content {
        position: relative;
        z-index: 1;
    }
    
    .cta-content h2 {
        font-size: 2.5rem;
        font-weight: 900;
        margin: 0 0 1rem 0;
    }
    
    .cta-content p {
        font-size: 1.25rem;
        margin: 0 auto 2.5rem;
        max-width: 600px;
        opacity: 0.95;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .hero-content h1 {
            font-size: 2.5rem;
        }
        
        .hero-content p {
            font-size: 1.1rem;
        }
        
        .hero-buttons {
            flex-direction: column;
        }
        
        .section-title {
            font-size: 2rem;
        }
        
        .stats-grid,
        .features-grid,
        .rooms-grid {
            grid-template-columns: 1fr;
        }
        
        .cta-section {
            padding: 2rem 1.5rem;
        }
        
        .cta-content h2 {
            font-size: 1.8rem;
        }
    }
</style>

<div class="homepage">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-badge">
                🏠 Find Your Perfect Stay
            </div>
            <h1>Welcome to Our<br>Dormitory Management System</h1>
            <p>
                Experience comfortable living with modern amenities, affordable prices, 
                and a seamless booking process. Your ideal room is just a click away!
            </p>
            <div class="hero-buttons">
                <a href="rooms.php" class="hero-btn primary">
                    <span>Browse Available Rooms</span>
                    <span>→</span>
                </a>
                <a href="../login.php" class="hero-btn outline">
                    <span>Login to Your Account</span>
                </a>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🏠</div>
                <div class="stat-value"><?php echo $stats['total_rooms']; ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-value"><?php echo $stats['available_rooms']; ?></div>
                <div class="stat-label">Available Now</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₱<?php echo number_format($stats['min_price'], 0); ?>+</div>
                <div class="stat-label">Starting From</div>
            </div>
        </div>
    </section>
    
    <div class="container">
        <!-- Features Section -->
        <section class="features-section">
            <div class="section-header">
                <span class="section-badge">Why Choose Us</span>
                <h2 class="section-title">Everything You Need</h2>
                <p class="section-subtitle">
                    We provide a comprehensive solution for all your dormitory needs, 
                    making your stay comfortable and hassle-free.
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🚀</div>
                    <h3>Easy Online Booking</h3>
                    <p>
                        Book your room online with just a few clicks. Our streamlined process 
                        makes it simple and convenient to secure your perfect space.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>Secure Payments</h3>
                    <p>
                        Track your payments and rental history securely in your personal portal. 
                        All transactions are safe and transparent.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🏆</div>
                    <h3>Quality Rooms</h3>
                    <p>
                        All rooms are well-maintained with modern amenities including WiFi, 
                        air conditioning, and private bathrooms.
                    </p>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Featured Rooms Section -->
    <?php if ($featured_rooms && $featured_rooms->num_rows > 0): ?>
    <section class="featured-rooms-section">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Featured Rooms</span>
                <h2 class="section-title">Available Rooms</h2>
                <p class="section-subtitle">
                    Check out some of our most popular rooms currently available for booking
                </p>
            </div>
            
            <div class="rooms-grid">
                <?php while ($room = $featured_rooms->fetch_assoc()): ?>
                    <div class="room-card-featured">
                        <?php if ($room['primary_photo']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($room['primary_photo']); ?>" 
                                 alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                                 class="room-image">
                        <?php else: ?>
                            <div class="room-image-placeholder">
                                🏠
                            </div>
                        <?php endif; ?>
                        
                        <div class="room-content">
                            <div class="room-header">
                                <div>
                                    <h3 class="room-title">Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                                    <p class="room-type"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></p>
                                </div>
                                <div class="room-price">
                                    <div class="price-amount">₱<?php echo number_format($room['price'], 0); ?></div>
                                    <div class="price-period">/month</div>
                                </div>
                            </div>
                            
                            <div class="room-features">
                                <span class="room-feature-badge">
                                    👥 <?php echo $room['capacity']; ?> Person(s)
                                </span>
                                <?php if ($room['has_wifi']): ?>
                                    <span class="room-feature-badge">📶 WiFi</span>
                                <?php endif; ?>
                                <?php if ($room['has_ac']): ?>
                                    <span class="room-feature-badge">❄️ AC</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="room-actions">
                                <a href="room_view.php?id=<?php echo $room['id']; ?>" class="room-btn outline">
                                    View Details
                                </a>
                                <a href="rooms.php" class="room-btn primary">
                                    Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div style="text-align: center; margin-top: 3rem;">
                <a href="rooms.php" class="hero-btn primary" style="display: inline-flex;">
                    View All Rooms
                    <span>→</span>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <div class="container">
        <!-- CTA Section -->
        <section class="cta-section">
            <div class="cta-content">
                <h2>Ready to Find Your Perfect Room?</h2>
                <p>
                    Join hundreds of satisfied tenants who have found their ideal living space with us. 
                    Start your journey today!
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="rooms.php" class="hero-btn primary">
                        Browse Available Rooms
                    </a>
                    <a href="../register.php" class="hero-btn outline">
                        Create Account
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>