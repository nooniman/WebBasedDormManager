<?php
/**
 * Demo Data Population Script
 * Run this script once on Hostinger to populate the database with realistic demo data
 * DELETE THIS FILE AFTER RUNNING!
 * 
 * Access: https://zcdormi.site/populate_demo_data.php?key=DEMO2025SECRET
 */

// Security key - change this or remove after use
$security_key = 'DEMO2025SECRET';

if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Access denied. Please provide the correct security key.');
}

require_once 'config/database.php';

echo "<h1>🏠 Dormitory Demo Data Population</h1>";
echo "<pre>";

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

try {
    // ============================================
    // 1. ADD MORE ROOMS
    // ============================================
    echo "\n📦 Adding more rooms...\n";
    
    $rooms = [
        // Floor 1
        ['104', 1, 'single', 'standard', 1, 4500.00, 'available', 'Cozy single room with study desk and window view. Perfect for students.', 1, 0, 1],
        ['106', 1, 'double', 'deluxe', 2, 9500.00, 'available', 'Spacious deluxe double room with premium furnishings and balcony access.', 1, 1, 1],
        ['107', 1, 'single', 'premium', 1, 7000.00, 'available', 'Premium single room with private bathroom, mini-fridge, and city view.', 1, 1, 1],
        
        // Floor 2
        ['204', 2, 'quad', 'standard', 4, 16000.00, 'available', 'Large quad room ideal for groups. Includes shared bathroom and common area.', 1, 1, 1],
        ['205', 2, 'single', 'deluxe', 1, 6500.00, 'available', 'Deluxe single with air conditioning and modern amenities.', 1, 1, 1],
        ['206', 2, 'double', 'premium', 2, 12000.00, 'available', 'Premium double room with en-suite bathroom and workspace.', 1, 1, 1],
        
        // Floor 3
        ['301', 3, 'single', 'standard', 1, 4000.00, 'available', 'Budget-friendly single room with basic amenities.', 1, 0, 0],
        ['302', 3, 'double', 'standard', 2, 7500.00, 'available', 'Standard double room with shared facilities.', 1, 0, 1],
        ['303', 3, 'suite', 'premium', 3, 18000.00, 'available', 'Executive suite with living area, kitchenette, and panoramic view.', 1, 1, 1],
        ['304', 3, 'quad', 'deluxe', 4, 20000.00, 'available', 'Deluxe quad room with 2 bathrooms and study area.', 1, 1, 1],
        
        // Floor 4
        ['401', 4, 'single', 'premium', 1, 7500.00, 'available', 'Top floor premium single with rooftop access.', 1, 1, 1],
        ['402', 4, 'double', 'deluxe', 2, 11000.00, 'available', 'Deluxe double with mountain view and modern design.', 1, 1, 1],
        ['403', 4, 'suite', 'premium', 2, 15000.00, 'available', 'Penthouse suite with luxury finishes and private terrace.', 1, 1, 1],
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO rooms (room_number, floor_number, room_type, category, capacity, price, status, description, has_wifi, has_ac, has_bathroom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($rooms as $room) {
        $stmt->bind_param("sissidssiis", $room[0], $room[1], $room[2], $room[3], $room[4], $room[5], $room[6], $room[7], $room[8], $room[9], $room[10]);
        $stmt->execute();
    }
    $stmt->close();
    echo "✅ Added " . count($rooms) . " new rooms\n";

    // ============================================
    // 2. ADD MORE TENANTS
    // ============================================
    echo "\n👥 Adding more tenants...\n";
    
    $password_hash = password_hash('tenant123', PASSWORD_DEFAULT);
    
    $tenants = [
        ['maria.santos@email.com', 'Maria', 'Santos', '09171234567', 'tenant'],
        ['juan.delacruz@email.com', 'Juan', 'Dela Cruz', '09181234568', 'tenant'],
        ['anna.reyes@email.com', 'Anna', 'Reyes', '09191234569', 'tenant'],
        ['miguel.garcia@email.com', 'Miguel', 'Garcia', '09201234570', 'tenant'],
        ['sofia.mendoza@email.com', 'Sofia', 'Mendoza', '09211234571', 'tenant'],
        ['carlos.villanueva@email.com', 'Carlos', 'Villanueva', '09221234572', 'tenant'],
        ['elena.fernandez@email.com', 'Elena', 'Fernandez', '09231234573', 'tenant'],
        ['paolo.cruz@email.com', 'Paolo', 'Cruz', '09241234574', 'tenant'],
        ['jasmine.lim@email.com', 'Jasmine', 'Lim', '09251234575', 'tenant'],
        ['rafael.tan@email.com', 'Rafael', 'Tan', '09261234576', 'tenant'],
        ['camille.go@email.com', 'Camille', 'Go', '09271234577', 'tenant'],
        ['daniel.sy@email.com', 'Daniel', 'Sy', '09281234578', 'tenant'],
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO users (email, password, first_name, last_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    
    foreach ($tenants as $tenant) {
        $stmt->bind_param("ssssss", $tenant[0], $password_hash, $tenant[1], $tenant[2], $tenant[3], $tenant[4]);
        $stmt->execute();
    }
    $stmt->close();
    echo "✅ Added " . count($tenants) . " new tenants (password: tenant123)\n";

    // ============================================
    // 3. CREATE ACTIVE BOOKINGS
    // ============================================
    echo "\n📅 Creating bookings...\n";
    
    // Get all tenant IDs
    $tenant_ids = [];
    $result = $conn->query("SELECT id FROM users WHERE role = 'tenant' ORDER BY id");
    while ($row = $result->fetch_assoc()) {
        $tenant_ids[] = $row['id'];
    }
    
    // Get available room IDs
    $room_ids = [];
    $result = $conn->query("SELECT id, price FROM rooms WHERE status = 'available' ORDER BY id");
    while ($row = $result->fetch_assoc()) {
        $room_ids[$row['id']] = $row['price'];
    }
    
    $bookings_data = [];
    $booking_count = 0;
    
    // Create checked-in bookings (active tenants)
    $active_bookings = [
        ['room' => 1, 'tenant_idx' => 0, 'start' => '2025-09-01', 'end' => '2026-02-28', 'status' => 'checked_in', 'check_in' => '2025-09-01'],
        ['room' => 5, 'tenant_idx' => 1, 'start' => '2025-10-01', 'end' => '2026-03-31', 'status' => 'checked_in', 'check_in' => '2025-10-01'],
        ['room' => 4, 'tenant_idx' => 2, 'start' => '2025-10-15', 'end' => '2026-04-14', 'status' => 'checked_in', 'check_in' => '2025-10-15'],
        ['room' => 6, 'tenant_idx' => 3, 'start' => '2025-11-01', 'end' => '2026-04-30', 'status' => 'checked_in', 'check_in' => '2025-11-01'],
    ];
    
    // Create pending bookings (awaiting approval)
    $pending_bookings = [
        ['tenant_idx' => 4, 'start' => '2025-12-01', 'end' => '2026-05-31', 'status' => 'pending'],
        ['tenant_idx' => 5, 'start' => '2025-12-15', 'end' => '2026-06-14', 'status' => 'pending'],
        ['tenant_idx' => 6, 'start' => '2026-01-01', 'end' => '2026-06-30', 'status' => 'pending'],
    ];
    
    // Create approved bookings (waiting for check-in)
    $approved_bookings = [
        ['tenant_idx' => 7, 'start' => '2025-12-01', 'end' => '2026-05-31', 'status' => 'approved'],
        ['tenant_idx' => 8, 'start' => '2025-12-15', 'end' => '2026-03-14', 'status' => 'approved'],
    ];
    
    // Insert active bookings
    foreach ($active_bookings as $b) {
        if (!isset($tenant_ids[$b['tenant_idx']])) continue;
        $tenant_id = $tenant_ids[$b['tenant_idx']];
        $room_id = $b['room'];
        
        // Get room price
        $price_result = $conn->query("SELECT price FROM rooms WHERE id = $room_id");
        if ($price_result->num_rows === 0) continue;
        $price = $price_result->fetch_assoc()['price'];
        
        // Calculate months
        $start = new DateTime($b['start']);
        $end = new DateTime($b['end']);
        $months = max(1, ceil($start->diff($end)->days / 30));
        $total = $price * $months;
        
        $stmt = $conn->prepare("INSERT INTO bookings (room_id, tenant_id, start_date, end_date, check_in_date, duration_months, total_amount, status, approved_by, approved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, NOW(), NOW())");
        $stmt->bind_param("iisssisd", $room_id, $tenant_id, $b['start'], $b['end'], $b['check_in'], $months, $total, $b['status']);
        $stmt->execute();
        $stmt->close();
        
        // Update room status to occupied
        $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = $room_id");
        $booking_count++;
    }
    
    // Get available rooms for pending/approved bookings
    $available_rooms = [];
    $result = $conn->query("SELECT id, price FROM rooms WHERE status = 'available' LIMIT 10");
    while ($row = $result->fetch_assoc()) {
        $available_rooms[] = $row;
    }
    
    $room_idx = 0;
    
    // Insert pending bookings
    foreach ($pending_bookings as $b) {
        if (!isset($tenant_ids[$b['tenant_idx']]) || !isset($available_rooms[$room_idx])) continue;
        $tenant_id = $tenant_ids[$b['tenant_idx']];
        $room = $available_rooms[$room_idx++];
        
        $start = new DateTime($b['start']);
        $end = new DateTime($b['end']);
        $months = max(1, ceil($start->diff($end)->days / 30));
        $total = $room['price'] * $months;
        
        $stmt = $conn->prepare("INSERT INTO bookings (room_id, tenant_id, start_date, end_date, duration_months, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissids", $room['id'], $tenant_id, $b['start'], $b['end'], $months, $total, $b['status']);
        $stmt->execute();
        $stmt->close();
        $booking_count++;
    }
    
    // Insert approved bookings
    foreach ($approved_bookings as $b) {
        if (!isset($tenant_ids[$b['tenant_idx']]) || !isset($available_rooms[$room_idx])) continue;
        $tenant_id = $tenant_ids[$b['tenant_idx']];
        $room = $available_rooms[$room_idx++];
        
        $start = new DateTime($b['start']);
        $end = new DateTime($b['end']);
        $months = max(1, ceil($start->diff($end)->days / 30));
        $total = $room['price'] * $months;
        
        $stmt = $conn->prepare("INSERT INTO bookings (room_id, tenant_id, start_date, end_date, duration_months, total_amount, status, approved_by, approved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 3, NOW(), NOW())");
        $stmt->bind_param("iissids", $room['id'], $tenant_id, $b['start'], $b['end'], $months, $total, $b['status']);
        $stmt->execute();
        $stmt->close();
        $booking_count++;
    }
    
    echo "✅ Created $booking_count bookings\n";

    // ============================================
    // 4. CREATE PAYMENT HISTORY
    // ============================================
    echo "\n💰 Creating payment history...\n";
    
    $payment_count = 0;
    
    // Get all checked-in bookings for payments
    $result = $conn->query("
        SELECT b.id as booking_id, b.tenant_id, b.room_id, b.start_date, r.price 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.status = 'checked_in'
    ");
    
    $months = ['September 2025', 'October 2025', 'November 2025'];
    $payment_methods = ['cash', 'paypal', 'bank_transfer', 'gcash'];
    
    while ($booking = $result->fetch_assoc()) {
        $start = new DateTime($booking['start_date']);
        $now = new DateTime();
        
        // Create payments for each month since start
        foreach ($months as $idx => $period) {
            $payment_date = new DateTime("2025-" . (9 + $idx) . "-05");
            
            if ($payment_date >= $start && $payment_date <= $now) {
                $method = $payment_methods[array_rand($payment_methods)];
                $ref = $method === 'paypal' ? 'PP' . strtoupper(substr(md5(rand()), 0, 12)) : null;
                
                $stmt = $conn->prepare("INSERT INTO payments (tenant_id, booking_id, room_id, amount, payment_date, payment_period, payment_method, reference_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())");
                $date_str = $payment_date->format('Y-m-d');
                $stmt->bind_param("iiidssss", $booking['tenant_id'], $booking['booking_id'], $booking['room_id'], $booking['price'], $date_str, $period, $method, $ref);
                $stmt->execute();
                $stmt->close();
                $payment_count++;
            }
        }
    }
    
    // Add some PayPal transactions for demo
    $conn->query("
        INSERT INTO paypal_transactions (tenant_id, room_id, booking_id, paypal_order_id, capture_id, amount, payment_period, status, payer_email, created_at, updated_at)
        SELECT 
            p.tenant_id, p.room_id, p.booking_id,
            CONCAT('PAY', UPPER(SUBSTRING(MD5(RAND()), 1, 16))),
            CONCAT('CAP', UPPER(SUBSTRING(MD5(RAND()), 1, 16))),
            p.amount,
            p.payment_period,
            'completed',
            CONCAT('tenant', p.tenant_id, '@example.com'),
            p.created_at,
            p.created_at
        FROM payments p
        WHERE p.payment_method = 'paypal'
        AND NOT EXISTS (SELECT 1 FROM paypal_transactions pt WHERE pt.booking_id = p.booking_id AND pt.payment_period = p.payment_period)
    ");
    
    echo "✅ Created $payment_count payments\n";

    // ============================================
    // 5. CREATE ANNOUNCEMENTS
    // ============================================
    echo "\n📢 Creating announcements...\n";
    
    $announcements = [
        ['Holiday Schedule Announcement', 'Dear Tenants, please be informed that the dormitory office will be closed from December 24-26, 2025 for the Christmas holidays. Emergency contact numbers will be available at the front desk. Happy Holidays!', 'important'],
        ['WiFi Maintenance Notice', 'We will be upgrading our WiFi infrastructure on December 5, 2025 from 2:00 AM to 6:00 AM. Internet service may be intermittent during this period. We apologize for any inconvenience.', 'normal'],
        ['New Gym Facilities Now Open!', 'We are excited to announce that our new fitness center on the ground floor is now open! Available to all tenants from 6:00 AM to 10:00 PM daily. Please bring your ID for access.', 'normal'],
        ['Rent Payment Reminder', 'This is a friendly reminder that rent payments are due on the 5th of each month. You can now pay via PayPal, GCash, or bank transfer through our online portal. Late payments will incur a 5% penalty.', 'urgent'],
        ['Fire Drill Scheduled', 'A mandatory fire drill will be conducted on December 10, 2025 at 10:00 AM. All tenants are required to participate. Please familiarize yourself with the evacuation routes posted on each floor.', 'important'],
        ['Water Supply Interruption', 'Due to maintenance work by the local water district, water supply will be temporarily interrupted on December 8, 2025 from 9:00 AM to 3:00 PM. Please store sufficient water for your needs.', 'urgent'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, created_by, created_at) VALUES (?, ?, ?, 3, NOW() - INTERVAL ? DAY)");
    
    $days_ago = 0;
    foreach ($announcements as $ann) {
        $stmt->bind_param("sssi", $ann[0], $ann[1], $ann[2], $days_ago);
        $stmt->execute();
        $days_ago += rand(2, 5);
    }
    $stmt->close();
    echo "✅ Created " . count($announcements) . " announcements\n";

    // ============================================
    // 6. CREATE NOTIFICATIONS
    // ============================================
    echo "\n🔔 Creating notifications...\n";
    
    $conn->query("
        INSERT INTO notifications (user_id, type, title, message, related_id, related_type, is_read, created_at)
        SELECT 
            b.tenant_id,
            'payment',
            'Payment Reminder',
            CONCAT('Your rent payment for Room ', r.room_number, ' is due on the 5th. Current amount: ₱', FORMAT(r.price, 0)),
            b.id,
            'booking',
            0,
            NOW() - INTERVAL FLOOR(RAND() * 5) DAY
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in'
    ");
    
    $conn->query("
        INSERT INTO notifications (user_id, type, title, message, related_id, related_type, is_read, created_at)
        SELECT 
            b.tenant_id,
            'announcement',
            'New Announcement',
            'A new announcement has been posted. Check the portal for details.',
            a.id,
            'announcement',
            FLOOR(RAND() * 2),
            a.created_at
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        CROSS JOIN (SELECT id, created_at FROM announcements ORDER BY id DESC LIMIT 3) a
        WHERE b.status = 'checked_in'
    ");
    
    echo "✅ Created notifications\n";

    // ============================================
    // 7. UPDATE STATISTICS
    // ============================================
    echo "\n📊 Updating room statuses...\n";
    
    // Set one room to maintenance for variety
    $conn->query("UPDATE rooms SET status = 'maintenance', description = CONCAT(description, ' (Currently under renovation - available January 2026)') WHERE room_number = '203'");
    
    echo "✅ Updated room statuses\n";

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ DEMO DATA POPULATION COMPLETE!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Show summary
    $stats = [
        'Rooms' => $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'],
        'Tenants' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'tenant'")->fetch_assoc()['c'],
        'Active Bookings' => $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'checked_in'")->fetch_assoc()['c'],
        'Pending Bookings' => $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'")->fetch_assoc()['c'],
        'Total Payments' => $conn->query("SELECT COUNT(*) as c FROM payments")->fetch_assoc()['c'],
        'Revenue' => $conn->query("SELECT COALESCE(SUM(amount), 0) as c FROM payments WHERE status = 'confirmed'")->fetch_assoc()['c'],
        'Announcements' => $conn->query("SELECT COUNT(*) as c FROM announcements")->fetch_assoc()['c'],
    ];
    
    echo "📈 DATABASE SUMMARY:\n";
    echo str_repeat("-", 30) . "\n";
    foreach ($stats as $label => $value) {
        if ($label === 'Revenue') {
            echo "   $label: ₱" . number_format($value, 2) . "\n";
        } else {
            echo "   $label: $value\n";
        }
    }
    echo str_repeat("-", 30) . "\n";
    
    echo "\n⚠️  IMPORTANT: DELETE THIS FILE AFTER USE!\n";
    echo "   File location: /populate_demo_data.php\n";
    
} catch (Exception $e) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
