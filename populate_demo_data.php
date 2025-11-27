<?php
/**
 * Demo Data Population Script v2
 * Run this script once on Hostinger to populate the database with realistic demo data
 * DELETE THIS FILE AFTER RUNNING!
 * 
 * Access: https://zcdormi.site/populate_demo_v2.php?key=DEMO2025
 */

// Security key - change this or remove after use
$security_key = 'DEMO2025';

if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Access denied. Please provide the correct security key as ?key=DEMO2025');
}

require_once 'config/database.php';

echo "<!DOCTYPE html><html><head><title>Demo Data Population</title>";
echo "<style>body{font-family:monospace;background:#1e293b;color:#e2e8f0;padding:2rem;} 
      h1{color:#10b981;} h2{color:#667eea;margin-top:2rem;} 
      .success{color:#10b981;} .error{color:#ef4444;} .info{color:#60a5fa;}
      pre{background:#0f172a;padding:1rem;border-radius:8px;overflow-x:auto;}</style>";
echo "</head><body>";
echo "<h1>🏠 Dormitory Demo Data Population v2</h1>";
echo "<pre>";

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$errors = [];
$success = [];

try {
    // ============================================
    // 1. CLEAR OLD DEMO DATA (Optional - be careful!)
    // ============================================
    echo "\n<span class='info'>🧹 Preparing database...</span>\n";
    
    // We won't delete existing data, just add new

    // ============================================
    // 2. ADD MORE ROOMS IF NEEDED
    // ============================================
    echo "\n<h2>📦 Step 1: Adding Rooms</h2>\n";
    
    $new_rooms = [
        ['301', 3, 'single', 'standard', 1, 4500.00, 'available', 'Comfortable single room on 3rd floor with study area', 1, 0, 1],
        ['302', 3, 'double', 'deluxe', 2, 9000.00, 'available', 'Deluxe double room with balcony view', 1, 1, 1],
        ['303', 3, 'suite', 'premium', 2, 15000.00, 'available', 'Premium suite with living area and kitchenette', 1, 1, 1],
        ['401', 4, 'single', 'deluxe', 1, 6000.00, 'available', 'Top floor single with panoramic view', 1, 1, 1],
        ['402', 4, 'double', 'premium', 2, 11000.00, 'available', 'Premium double room with modern amenities', 1, 1, 1],
        ['403', 4, 'quad', 'standard', 4, 18000.00, 'available', 'Large quad room perfect for groups', 1, 1, 1],
    ];
    
    $rooms_added = 0;
    foreach ($new_rooms as $room) {
        $check = $conn->query("SELECT id FROM rooms WHERE room_number = '{$room[0]}'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO rooms (room_number, floor_number, room_type, category, capacity, price, status, description, has_wifi, has_ac, has_bathroom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissisissii", $room[0], $room[1], $room[2], $room[3], $room[4], $room[5], $room[6], $room[7], $room[8], $room[9], $room[10]);
            if ($stmt->execute()) {
                $rooms_added++;
            }
            $stmt->close();
        }
    }
    echo "<span class='success'>✅ Added $rooms_added new rooms</span>\n";

    // ============================================
    // 3. ADD MORE TENANTS
    // ============================================
    echo "\n<h2>👥 Step 2: Adding Tenants</h2>\n";
    
    $password_hash = password_hash('tenant123', PASSWORD_DEFAULT);
    
    $new_tenants = [
        ['demo.tenant1@email.com', 'Maria', 'Santos', '09171234567'],
        ['demo.tenant2@email.com', 'Juan', 'Dela Cruz', '09181234568'],
        ['demo.tenant3@email.com', 'Anna', 'Reyes', '09191234569'],
        ['demo.tenant4@email.com', 'Miguel', 'Garcia', '09201234570'],
        ['demo.tenant5@email.com', 'Sofia', 'Mendoza', '09211234571'],
        ['demo.tenant6@email.com', 'Carlos', 'Villanueva', '09221234572'],
        ['demo.tenant7@email.com', 'Elena', 'Fernandez', '09231234573'],
        ['demo.tenant8@email.com', 'Paolo', 'Cruz', '09241234574'],
    ];
    
    $tenants_added = 0;
    $tenant_ids = [];
    
    foreach ($new_tenants as $tenant) {
        $check = $conn->query("SELECT id FROM users WHERE email = '{$tenant[0]}'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, 'tenant', 1)");
            $stmt->bind_param("sssss", $tenant[0], $password_hash, $tenant[1], $tenant[2], $tenant[3]);
            if ($stmt->execute()) {
                $tenant_ids[] = $conn->insert_id;
                $tenants_added++;
            }
            $stmt->close();
        } else {
            $tenant_ids[] = $check->fetch_assoc()['id'];
        }
    }
    
    // Also get existing tenant IDs
    $existing = $conn->query("SELECT id FROM users WHERE role = 'tenant' ORDER BY id");
    $all_tenant_ids = [];
    while ($row = $existing->fetch_assoc()) {
        $all_tenant_ids[] = $row['id'];
    }
    
    echo "<span class='success'>✅ Added $tenants_added new tenants (password: tenant123)</span>\n";
    echo "<span class='info'>   Total tenants available: " . count($all_tenant_ids) . "</span>\n";

    // ============================================
    // 4. CREATE BOOKINGS WITH PROPER STRUCTURE
    // ============================================
    echo "\n<h2>📅 Step 3: Creating Bookings</h2>\n";
    
    // Get all rooms
    $rooms_result = $conn->query("SELECT id, room_number, price, status FROM rooms ORDER BY id");
    $all_rooms = [];
    while ($row = $rooms_result->fetch_assoc()) {
        $all_rooms[$row['id']] = $row;
    }
    
    $bookings_created = 0;
    
    // Define bookings to create
    $bookings_to_create = [
        // Active checked-in bookings (these generate payments)
        ['room_id' => 1, 'tenant_idx' => 0, 'start' => '2025-06-01', 'end' => '2025-12-31', 'status' => 'checked_in', 'check_in' => '2025-06-01'],
        ['room_id' => 2, 'tenant_idx' => 1, 'start' => '2025-07-01', 'end' => '2026-01-31', 'status' => 'checked_in', 'check_in' => '2025-07-01'],
        ['room_id' => 4, 'tenant_idx' => 2, 'start' => '2025-08-01', 'end' => '2026-02-28', 'status' => 'checked_in', 'check_in' => '2025-08-01'],
        ['room_id' => 5, 'tenant_idx' => 3, 'start' => '2025-09-01', 'end' => '2026-03-31', 'status' => 'checked_in', 'check_in' => '2025-09-01'],
        ['room_id' => 7, 'tenant_idx' => 4, 'start' => '2025-10-01', 'end' => '2026-04-30', 'status' => 'checked_in', 'check_in' => '2025-10-01'],
        
        // Pending bookings
        ['room_id' => 6, 'tenant_idx' => 5, 'start' => '2025-12-01', 'end' => '2026-05-31', 'status' => 'pending', 'check_in' => null],
        ['room_id' => 8, 'tenant_idx' => 6, 'start' => '2025-12-15', 'end' => '2026-06-14', 'status' => 'pending', 'check_in' => null],
        
        // Approved waiting for check-in
        ['room_id' => 3, 'tenant_idx' => 7, 'start' => '2025-12-01', 'end' => '2026-05-31', 'status' => 'approved', 'check_in' => null],
    ];
    
    foreach ($bookings_to_create as $b) {
        if (!isset($all_tenant_ids[$b['tenant_idx']]) || !isset($all_rooms[$b['room_id']])) {
            continue;
        }
        
        $tenant_id = $all_tenant_ids[$b['tenant_idx']];
        $room = $all_rooms[$b['room_id']];
        
        // Check if booking already exists
        $check = $conn->query("SELECT id FROM bookings WHERE room_id = {$b['room_id']} AND tenant_id = $tenant_id AND start_date = '{$b['start']}'");
        if ($check->num_rows > 0) {
            continue; // Skip if exists
        }
        
        // Calculate duration and total
        $start = new DateTime($b['start']);
        $end = new DateTime($b['end']);
        $months = max(1, ceil($start->diff($end)->days / 30));
        $total = $room['price'] * $months;
        
        if ($b['check_in']) {
            $stmt = $conn->prepare("INSERT INTO bookings (room_id, tenant_id, start_date, end_date, check_in_date, duration_months, total_amount, status, approved_by, approved_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, NOW(), NOW())");
            $stmt->bind_param("iisssids", $b['room_id'], $tenant_id, $b['start'], $b['end'], $b['check_in'], $months, $total, $b['status']);
        } else {
            $stmt = $conn->prepare("INSERT INTO bookings (room_id, tenant_id, start_date, end_date, duration_months, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iissids", $b['room_id'], $tenant_id, $b['start'], $b['end'], $months, $total, $b['status']);
        }
        
        if ($stmt->execute()) {
            $bookings_created++;
            
            // Update room status for checked_in bookings
            if ($b['status'] === 'checked_in') {
                $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = {$b['room_id']}");
            }
        }
        $stmt->close();
    }
    
    echo "<span class='success'>✅ Created $bookings_created new bookings</span>\n";

    // ============================================
    // 5. CREATE PAYMENT HISTORY (THE IMPORTANT PART!)
    // ============================================
    echo "\n<h2>💰 Step 4: Creating Payment Records</h2>\n";
    
    // Get all checked-in bookings
    $bookings_result = $conn->query("
        SELECT b.id as booking_id, b.tenant_id, b.room_id, b.start_date, b.status, r.price, r.room_number
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.status IN ('checked_in', 'checked_out')
    ");
    
    $payments_created = 0;
    $payment_methods = ['cash', 'paypal', 'gcash', 'bank_transfer'];
    
    // Create payments for each month from start date until now
    while ($booking = $bookings_result->fetch_assoc()) {
        $start = new DateTime($booking['start_date']);
        $now = new DateTime('2025-11-30'); // End of November 2025
        
        // Generate payments for each month
        $current = clone $start;
        while ($current <= $now) {
            $payment_period = $current->format('F Y');
            $payment_date = $current->format('Y-m') . '-05'; // Pay on 5th of each month
            
            // Check if payment already exists
            $check = $conn->query("SELECT id FROM payments WHERE tenant_id = {$booking['tenant_id']} AND room_id = {$booking['room_id']} AND payment_period = '$payment_period'");
            if ($check->num_rows > 0) {
                $current->modify('+1 month');
                continue;
            }
            
            $method = $payment_methods[array_rand($payment_methods)];
            $amount = $booking['price'];
            
            // Insert payment
            $stmt = $conn->prepare("INSERT INTO payments (tenant_id, booking_id, room_id, amount, payment_date, payment_period, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())");
            $stmt->bind_param("iiidsss", 
                $booking['tenant_id'], 
                $booking['booking_id'], 
                $booking['room_id'], 
                $amount, 
                $payment_date, 
                $payment_period, 
                $method
            );
            
            if ($stmt->execute()) {
                $payment_id = $conn->insert_id;
                $payments_created++;
                
                // If PayPal, also create PayPal transaction record
                if ($method === 'paypal') {
                    $order_id = 'PP' . strtoupper(substr(md5(rand() . time()), 0, 15));
                    $capture_id = 'CAP' . strtoupper(substr(md5(rand() . time()), 0, 14));
                    
                    $pp_stmt = $conn->prepare("INSERT INTO paypal_transactions (tenant_id, room_id, booking_id, paypal_order_id, capture_id, amount, payment_period, status, payer_email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())");
                    $payer_email = "tenant{$booking['tenant_id']}@example.com";
                    $pp_stmt->bind_param("iiissdss", 
                        $booking['tenant_id'],
                        $booking['room_id'],
                        $booking['booking_id'],
                        $order_id,
                        $capture_id,
                        $amount,
                        $payment_period,
                        $payer_email
                    );
                    $pp_stmt->execute();
                    $pp_stmt->close();
                    
                    // Update payment with PayPal transaction ID
                    $conn->query("UPDATE payments SET paypal_transaction_id = '$order_id', paypal_capture_id = '$capture_id' WHERE id = $payment_id");
                }
            }
            $stmt->close();
            
            $current->modify('+1 month');
        }
    }
    
    echo "<span class='success'>✅ Created $payments_created payment records</span>\n";

    // ============================================
    // 6. ADD SOME PENDING PAYMENTS FOR CURRENT MONTH
    // ============================================
    echo "\n<h2>⏳ Step 5: Adding Pending Payments</h2>\n";
    
    $pending_added = 0;
    $bookings_result = $conn->query("
        SELECT b.id as booking_id, b.tenant_id, b.room_id, r.price 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.status = 'checked_in'
        LIMIT 3
    ");
    
    while ($booking = $bookings_result->fetch_assoc()) {
        $payment_period = 'December 2025';
        
        // Check if exists
        $check = $conn->query("SELECT id FROM payments WHERE tenant_id = {$booking['tenant_id']} AND payment_period = '$payment_period'");
        if ($check->num_rows > 0) continue;
        
        $stmt = $conn->prepare("INSERT INTO payments (tenant_id, booking_id, room_id, amount, payment_date, payment_period, payment_method, status, created_at) VALUES (?, ?, ?, ?, '2025-12-05', ?, 'pending', 'pending', NOW())");
        $method = 'pending';
        $stmt->bind_param("iiids", 
            $booking['tenant_id'], 
            $booking['booking_id'], 
            $booking['room_id'], 
            $booking['price'],
            $payment_period
        );
        if ($stmt->execute()) {
            $pending_added++;
        }
        $stmt->close();
    }
    
    echo "<span class='success'>✅ Added $pending_added pending payments for December 2025</span>\n";

    // ============================================
    // 7. CREATE ANNOUNCEMENTS
    // ============================================
    echo "\n<h2>📢 Step 6: Creating Announcements</h2>\n";
    
    $announcements = [
        ['🎄 Holiday Schedule 2025', 'Dear Tenants, the dormitory office will be closed from December 24-26, 2025 for Christmas holidays. Emergency contacts are available at the front desk. Wishing everyone a Merry Christmas!', 'important', 1],
        ['📶 WiFi Upgrade Complete', 'Great news! Our WiFi infrastructure has been upgraded. You should now experience faster and more stable internet connections throughout the building.', 'normal', 3],
        ['🏋️ New Gym Facilities', 'We are excited to announce that our new fitness center on the ground floor is now open! Available to all tenants from 6:00 AM to 10:00 PM daily.', 'normal', 5],
        ['💳 Payment Reminder', 'Friendly reminder: Rent payments are due on the 5th of each month. You can pay via PayPal, GCash, or bank transfer through our portal. Late payments incur a 5% penalty.', 'urgent', 7],
        ['🔥 Fire Drill Notice', 'A mandatory fire drill will be conducted on December 10, 2025 at 10:00 AM. All tenants are required to participate. Please review evacuation routes posted on each floor.', 'important', 10],
        ['🚰 Water Maintenance', 'Due to scheduled maintenance, water supply will be temporarily interrupted on December 8, 2025 from 9:00 AM to 3:00 PM. Please store water for your needs.', 'urgent', 12],
    ];
    
    $announcements_added = 0;
    foreach ($announcements as $ann) {
        $check = $conn->query("SELECT id FROM announcements WHERE title = '{$ann[0]}'");
        if ($check->num_rows > 0) continue;
        
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, created_by, created_at) VALUES (?, ?, ?, 3, NOW() - INTERVAL ? DAY)");
        $stmt->bind_param("sssi", $ann[0], $ann[1], $ann[2], $ann[3]);
        if ($stmt->execute()) {
            $announcements_added++;
        }
        $stmt->close();
    }
    
    echo "<span class='success'>✅ Created $announcements_added announcements</span>\n";

    // ============================================
    // 8. CREATE NOTIFICATIONS
    // ============================================
    echo "\n<h2>🔔 Step 7: Creating Notifications</h2>\n";
    
    // Payment reminders for active tenants
    $conn->query("
        INSERT IGNORE INTO notifications (user_id, type, title, message, related_id, related_type, is_read, created_at)
        SELECT 
            b.tenant_id,
            'payment',
            'Payment Reminder',
            CONCAT('Your rent payment for Room ', r.room_number, ' is due on December 5th. Amount: ₱', FORMAT(r.price, 0)),
            b.id,
            'booking',
            0,
            NOW() - INTERVAL FLOOR(RAND() * 3) DAY
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in'
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.user_id = b.tenant_id 
            AND n.type = 'payment' 
            AND n.related_id = b.id
        )
    ");
    
    echo "<span class='success'>✅ Created notifications</span>\n";

    // ============================================
    // 9. UPDATE ROOM STATUSES
    // ============================================
    echo "\n<h2>🏠 Step 8: Updating Room Statuses</h2>\n";
    
    // Make sure rooms with checked_in bookings are occupied
    $conn->query("
        UPDATE rooms r
        SET status = 'occupied'
        WHERE EXISTS (
            SELECT 1 FROM bookings b 
            WHERE b.room_id = r.id 
            AND b.status = 'checked_in'
        )
    ");
    
    // Set one room to maintenance for variety
    $conn->query("UPDATE rooms SET status = 'maintenance' WHERE room_number = '203' OR room_number = '206'");
    
    echo "<span class='success'>✅ Updated room statuses</span>\n";

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // ============================================
    // FINAL SUMMARY
    // ============================================
    echo "\n\n" . str_repeat("=", 60) . "\n";
    echo "<span class='success'>✅ DEMO DATA POPULATION COMPLETE!</span>\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Show comprehensive summary
    $summary = [
        'Total Rooms' => $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'],
        'Available Rooms' => $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'available'")->fetch_assoc()['c'],
        'Occupied Rooms' => $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'occupied'")->fetch_assoc()['c'],
        'Maintenance' => $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'maintenance'")->fetch_assoc()['c'],
        '---' => '---',
        'Total Tenants' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'tenant'")->fetch_assoc()['c'],
        'Total Bookings' => $conn->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'],
        'Active Bookings' => $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'checked_in'")->fetch_assoc()['c'],
        'Pending Bookings' => $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'")->fetch_assoc()['c'],
        '----' => '----',
        'Total Payments' => $conn->query("SELECT COUNT(*) as c FROM payments")->fetch_assoc()['c'],
        'Confirmed Payments' => $conn->query("SELECT COUNT(*) as c FROM payments WHERE status = 'confirmed'")->fetch_assoc()['c'],
        'Pending Payments' => $conn->query("SELECT COUNT(*) as c FROM payments WHERE status = 'pending'")->fetch_assoc()['c'],
        'PayPal Transactions' => $conn->query("SELECT COUNT(*) as c FROM paypal_transactions")->fetch_assoc()['c'],
        'Total Revenue' => '₱' . number_format($conn->query("SELECT COALESCE(SUM(amount), 0) as c FROM payments WHERE status = 'confirmed'")->fetch_assoc()['c'], 2),
        '-----' => '-----',
        'Announcements' => $conn->query("SELECT COUNT(*) as c FROM announcements")->fetch_assoc()['c'],
        'Notifications' => $conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'],
    ];
    
    echo "<h2>📊 DATABASE SUMMARY</h2>\n";
    echo str_repeat("-", 40) . "\n";
    foreach ($summary as $label => $value) {
        if (strpos($label, '-') === 0) {
            echo str_repeat("-", 40) . "\n";
        } else {
            echo sprintf("   %-20s %s\n", $label . ":", $value);
        }
    }
    echo str_repeat("-", 40) . "\n";
    
    // Monthly revenue breakdown
    echo "\n<h2>📈 MONTHLY REVENUE (Last 6 Months)</h2>\n";
    $monthly = $conn->query("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            DATE_FORMAT(payment_date, '%M %Y') as month_name,
            COUNT(*) as count,
            SUM(amount) as total
        FROM payments 
        WHERE status = 'confirmed'
        AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month DESC
    ");
    
    echo str_repeat("-", 50) . "\n";
    printf("   %-20s %10s %15s\n", "Month", "Count", "Revenue");
    echo str_repeat("-", 50) . "\n";
    while ($row = $monthly->fetch_assoc()) {
        printf("   %-20s %10d %15s\n", $row['month_name'], $row['count'], '₱' . number_format($row['total'], 2));
    }
    echo str_repeat("-", 50) . "\n";
    
    echo "\n<span class='error'>⚠️  IMPORTANT: DELETE THIS FILE AFTER USE!</span>\n";
    echo "   File: /populate_demo_v2.php\n";
    
} catch (Exception $e) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "<span class='error'>❌ ERROR: " . $e->getMessage() . "</span>\n";
    echo "<span class='error'>Line: " . $e->getLine() . "</span>\n";
}

echo "</pre>";
echo "</body></html>";
?>
