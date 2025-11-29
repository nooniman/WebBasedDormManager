<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\tenant\cancel_booking.php

require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';
require_once '../includes/bedspace_functions.php';

$tenant_id = $_SESSION['user_id'];

// Handle POST request (from modal form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('Invalid request', 'error');
        redirect(TENANT_URL . '/bookings');
    }
    
    $booking_id = (int)$_POST['booking_id'];
    
    // Verify booking belongs to tenant
    $stmt = $conn->prepare("SELECT b.*, r.room_number FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ? AND b.tenant_id = ?");
    $stmt->bind_param("ii", $booking_id, $tenant_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        set_flash_message('Booking not found', 'error');
        redirect(TENANT_URL . '/bookings');
    }
    
    // Check if booking can be cancelled
    if (!in_array($booking['status'], ['pending', 'approved'])) {
        set_flash_message('Only pending or approved bookings can be cancelled', 'error');
        redirect(TENANT_URL . '/bookings');
    }
    
    // Cancel the booking
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        // Release bedspace if it was a bedspace booking
        if ($booking['is_bedspace_booking'] && $booking['bedspace_id']) {
            release_bedspace($conn, $booking['bedspace_id']);
        }
        
        // Update room status if it was occupied
        if ($booking['status'] === 'checked_in') {
            $update_room = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $update_room->bind_param("i", $booking['room_id']);
            $update_room->execute();
            $update_room->close();
        }
        
        set_flash_message('Booking cancelled successfully', 'success');
    } else {
        set_flash_message('Failed to cancel booking', 'error');
    }
    $stmt->close();
    
    redirect(TENANT_URL . '/bookings');
}

// Handle GET request (from link with confirmation)
if (isset($_GET['id'])) {
    $booking_id = (int)$_GET['id'];
    
    // Verify booking belongs to tenant
    $stmt = $conn->prepare("SELECT b.*, r.room_number FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ? AND b.tenant_id = ?");
    $stmt->bind_param("ii", $booking_id, $tenant_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        set_flash_message('Booking not found', 'error');
        redirect(TENANT_URL . '/bookings');
    }
    
    // Check if booking can be cancelled
    if (!in_array($booking['status'], ['pending', 'approved'])) {
        set_flash_message('Only pending or approved bookings can be cancelled', 'error');
        redirect(TENANT_URL . '/bookings');
    }
    
    // Cancel the booking
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    
    if ($stmt->execute()) {
        // Release bedspace if it was a bedspace booking
        if ($booking['is_bedspace_booking'] && $booking['bedspace_id']) {
            release_bedspace($conn, $booking['bedspace_id']);
        }
        
        // Update room status if it was occupied
        if ($booking['status'] === 'checked_in') {
            $update_room = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $update_room->bind_param("i", $booking['room_id']);
            $update_room->execute();
            $update_room->close();
        }
        
        set_flash_message('Booking for Room ' . $booking['room_number'] . ' cancelled successfully', 'success');
    } else {
        set_flash_message('Failed to cancel booking', 'error');
    }
    $stmt->close();
    
    redirect(TENANT_URL . '/bookings');
}

// If no valid request, redirect
set_flash_message('Invalid request', 'error');
redirect(TENANT_URL . '/bookings');
?>
