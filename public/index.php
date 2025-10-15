<?php
$page_title = 'Home';
require_once '../includes/header.php';
?>

<div class="container">
    <!-- Hero Section -->
    <section style="text-align: center; padding: 4rem 0;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem; color: var(--primary-color);">
            Welcome to Dormitory Management System
        </h1>
        <p style="font-size: 1.25rem; color: var(--text-light); max-width: 600px; margin: 0 auto 2rem;">
            Find your perfect room, manage your bookings, and enjoy a seamless dormitory experience.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <a href="rooms.php" class="btn btn-primary">Browse Rooms</a>
            <a href="../login.php" class="btn btn-outline">Login</a>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="grid grid-3 mt-4">
        <div class="card text-center">
            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Easy Booking</h3>
            <p>Book your room online with just a few clicks. Simple and hassle-free process.</p>
        </div>
        
        <div class="card text-center">
            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Secure Payments</h3>
            <p>Track your payments and rental history securely in your personal portal.</p>
        </div>
        
        <div class="card text-center">
            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">24/7 Support</h3>
            <p>Get help anytime with our dedicated support team and announcement system.</p>
        </div>
    </section>
</div>

<?php require_once '../includes/footer.php'; ?>
