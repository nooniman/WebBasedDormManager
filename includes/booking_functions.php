<?php
/**
 * Booking Helper Functions
 * Phase 4: Enhanced Booking System
 */

/**
 * Check if room is available for given date range
 */
function check_room_availability($conn, $room_id, $start_date, $end_date, $exclude_booking_id = null) {
    $query = "SELECT COUNT(*) as conflicts FROM bookings 
              WHERE room_id = ? 
              AND status IN ('approved', 'checked_in')
              AND (
                  (start_date <= ? AND (end_date >= ? OR end_date IS NULL))
                  OR (start_date >= ? AND start_date <= ?)
              )";
    
    if ($exclude_booking_id) {
        $query .= " AND id != ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($exclude_booking_id) {
        $stmt->bind_param("issssi", $room_id, $end_date, $start_date, $start_date, $end_date, $exclude_booking_id);
    } else {
        $stmt->bind_param("issss", $room_id, $end_date, $start_date, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['conflicts'] == 0;
}

/**
 * Get bookings for calendar view
 */
function get_calendar_bookings($conn, $start_date, $end_date, $room_id = null) {
    $query = "SELECT b.*, r.room_number, r.room_type, u.first_name, u.last_name 
              FROM bookings b
              JOIN rooms r ON b.room_id = r.id
              JOIN users u ON b.tenant_id = u.id
              WHERE b.start_date <= ? AND (b.end_date >= ? OR b.end_date IS NULL)";
    
    if ($room_id) {
        $query .= " AND b.room_id = ?";
    }
    
    $query .= " ORDER BY b.start_date ASC";
    
    $stmt = $conn->prepare($query);
    
    if ($room_id) {
        $stmt->bind_param("ssi", $end_date, $start_date, $room_id);
    } else {
        $stmt->bind_param("ss", $end_date, $start_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    $stmt->close();
    return $bookings;
}

/**
 * Calculate booking duration and total amount
 */
function calculate_booking_total($start_date, $end_date, $monthly_price) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    
    $months = ($interval->y * 12) + $interval->m;
    if ($interval->d > 0) {
        $months++; // Round up partial month
    }
    
    return [
        'months' => max(1, $months),
        'total' => $monthly_price * max(1, $months)
    ];
}

/**
 * Create notification for user
 */
function create_notification($conn, $user_id, $type, $title, $message, $related_id = null, $related_type = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssis", $user_id, $type, $title, $message, $related_id, $related_type);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get unread notifications count
 */
function get_unread_notifications_count($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'];
}

/**
 * Get recent notifications
 */
function get_notifications($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Mark notification as read
 */
function mark_notification_read($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Detect booking conflicts
 */
function detect_booking_conflicts($conn, $room_id, $start_date, $end_date) {
    $conflicts = [];
    
    $stmt = $conn->prepare("
        SELECT b.*, u.first_name, u.last_name 
        FROM bookings b
        JOIN users u ON b.tenant_id = u.id
        WHERE b.room_id = ? 
        AND b.status IN ('approved', 'checked_in')
        AND (
            (b.start_date <= ? AND (b.end_date >= ? OR b.end_date IS NULL))
            OR (b.start_date >= ? AND b.start_date <= ?)
        )
    ");
    
    $stmt->bind_param("issss", $room_id, $end_date, $start_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $conflicts[] = $row;
    }
    
    $stmt->close();
    return $conflicts;
}