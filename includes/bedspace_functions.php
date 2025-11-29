<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\includes\bedspace_functions.php
/**
 * Bedspace Management Functions
 * Phase 7: Bedspacing Feature
 */

/**
 * Check if a room is configured as a bedspace room
 */
function is_bedspace_room($conn, $room_id) {
    $stmt = $conn->prepare("SELECT is_bedspace FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ? (bool)$result['is_bedspace'] : false;
}

/**
 * Get all bedspaces for a specific room
 */
function get_room_bedspaces($conn, $room_id) {
    $query = "SELECT b.*, u.first_name, u.last_name, u.email
              FROM bedspaces b
              LEFT JOIN users u ON b.current_tenant_id = u.id
              WHERE b.room_id = ?
              ORDER BY b.bedspace_number ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bedspaces = [];
    
    while ($row = $result->fetch_assoc()) {
        $bedspaces[] = $row;
    }
    
    $stmt->close();
    return $bedspaces;
}

/**
 * Get available bedspaces for a room
 */
function get_available_bedspaces($conn, $room_id) {
    $query = "SELECT * FROM bedspaces 
              WHERE room_id = ? AND status = 'available'
              ORDER BY bedspace_number ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bedspaces = [];
    
    while ($row = $result->fetch_assoc()) {
        $bedspaces[] = $row;
    }
    
    $stmt->close();
    return $bedspaces;
}

/**
 * Check bedspace availability
 */
function is_bedspace_available($conn, $bedspace_id) {
    $stmt = $conn->prepare("SELECT status FROM bedspaces WHERE id = ?");
    $stmt->bind_param("i", $bedspace_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result && $result['status'] === 'available';
}

/**
 * Assign bedspace to tenant
 */
function assign_bedspace($conn, $bedspace_id, $tenant_id) {
    $stmt = $conn->prepare("UPDATE bedspaces SET status = 'occupied', current_tenant_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $tenant_id, $bedspace_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Update room occupied_bedspaces count
        $update_room = $conn->prepare("UPDATE rooms SET occupied_bedspaces = occupied_bedspaces + 1 WHERE id = (SELECT room_id FROM bedspaces WHERE id = ?)");
        $update_room->bind_param("i", $bedspace_id);
        $update_room->execute();
        $update_room->close();
    }
    
    return $success;
}

/**
 * Release bedspace from tenant
 */
function release_bedspace($conn, $bedspace_id) {
    $stmt = $conn->prepare("UPDATE bedspaces SET status = 'available', current_tenant_id = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $bedspace_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Update room occupied_bedspaces count
        $update_room = $conn->prepare("UPDATE rooms SET occupied_bedspaces = GREATEST(0, occupied_bedspaces - 1) WHERE id = (SELECT room_id FROM bedspaces WHERE id = ?)");
        $update_room->bind_param("i", $bedspace_id);
        $update_room->execute();
        $update_room->close();
    }
    
    return $success;
}

/**
 * Create bedspaces for a room
 */
function create_bedspaces($conn, $room_id, $count, $prefix = '') {
    $labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
    $created = 0;
    
    for ($i = 0; $i < $count && $i < count($labels); $i++) {
        $bedspace_number = $prefix . $labels[$i];
        $stmt = $conn->prepare("INSERT INTO bedspaces (room_id, bedspace_number, status) VALUES (?, ?, 'available')");
        $stmt->bind_param("is", $room_id, $bedspace_number);
        
        if ($stmt->execute()) {
            $created++;
        }
        $stmt->close();
    }
    
    return $created;
}

/**
 * Delete all bedspaces for a room
 */
function delete_room_bedspaces($conn, $room_id) {
    $stmt = $conn->prepare("DELETE FROM bedspaces WHERE room_id = ?");
    $stmt->bind_param("i", $room_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Get bedspace details by ID
 */
function get_bedspace($conn, $bedspace_id) {
    $query = "SELECT b.*, r.room_number, r.room_type, r.price_per_bedspace
              FROM bedspaces b
              JOIN rooms r ON b.room_id = r.id
              WHERE b.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bedspace_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}

/**
 * Get bedspace occupancy statistics
 */
function get_bedspace_stats($conn) {
    $query = "SELECT 
                COUNT(DISTINCT r.id) as total_bedspace_rooms,
                SUM(r.total_bedspaces) as total_bedspaces,
                SUM(r.occupied_bedspaces) as occupied_bedspaces,
                SUM(r.total_bedspaces - r.occupied_bedspaces) as available_bedspaces,
                COALESCE(SUM(r.price_per_bedspace * r.occupied_bedspaces), 0) as monthly_revenue
              FROM rooms r
              WHERE r.is_bedspace = TRUE";
    
    $result = $conn->query($query);
    $stats = $result->fetch_assoc();
    
    return $stats;
}

/**
 * Update bedspace status
 */
function update_bedspace_status($conn, $bedspace_id, $status) {
    $valid_statuses = ['available', 'occupied', 'maintenance'];
    if (!in_array($status, $valid_statuses)) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE bedspaces SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $bedspace_id);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Check if room has available bedspaces
 */
function has_available_bedspaces($conn, $room_id) {
    $stmt = $conn->prepare("SELECT total_bedspaces, occupied_bedspaces FROM rooms WHERE id = ? AND is_bedspace = TRUE");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$room) return false;
    
    return ($room['total_bedspaces'] - $room['occupied_bedspaces']) > 0;
}

/**
 * Get tenant's current bedspace
 */
function get_tenant_bedspace($conn, $tenant_id) {
    $query = "SELECT b.*, r.room_number, r.room_type, r.price_per_bedspace
              FROM bedspaces b
              JOIN rooms r ON b.room_id = r.id
              WHERE b.current_tenant_id = ? AND b.status = 'occupied'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}

/**
 * Get roommates for a bedspace (other tenants in same room)
 */
function get_roommates($conn, $bedspace_id) {
    $query = "SELECT u.id, u.first_name, u.last_name, u.email, b.bedspace_number
              FROM bedspaces b
              JOIN users u ON b.current_tenant_id = u.id
              WHERE b.room_id = (SELECT room_id FROM bedspaces WHERE id = ?)
              AND b.id != ?
              AND b.status = 'occupied'
              ORDER BY b.bedspace_number ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $bedspace_id, $bedspace_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roommates = [];
    while ($row = $result->fetch_assoc()) {
        $roommates[] = $row;
    }
    
    $stmt->close();
    return $roommates;
}

/**
 * Convert regular room to bedspace room
 */
function convert_to_bedspace_room($conn, $room_id, $bedspace_count, $price_per_bedspace) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update room
        $stmt = $conn->prepare("UPDATE rooms SET is_bedspace = TRUE, total_bedspaces = ?, occupied_bedspaces = 0, price_per_bedspace = ? WHERE id = ?");
        $stmt->bind_param("idi", $bedspace_count, $price_per_bedspace, $room_id);
        $stmt->execute();
        $stmt->close();
        
        // Create bedspaces
        $created = create_bedspaces($conn, $room_id, $bedspace_count);
        
        if ($created != $bedspace_count) {
            throw new Exception("Failed to create all bedspaces");
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Convert to bedspace room failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert bedspace room back to regular room
 */
function convert_to_regular_room($conn, $room_id) {
    // Check if any bedspaces are occupied
    $stmt = $conn->prepare("SELECT COUNT(*) as occupied FROM bedspaces WHERE room_id = ? AND status = 'occupied'");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['occupied'] > 0) {
        return false; // Cannot convert if bedspaces are occupied
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete all bedspaces
        delete_room_bedspaces($conn, $room_id);
        
        // Update room
        $stmt = $conn->prepare("UPDATE rooms SET is_bedspace = FALSE, total_bedspaces = 0, occupied_bedspaces = 0, price_per_bedspace = NULL WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Convert to regular room failed: " . $e->getMessage());
        return false;
    }
}
?>
