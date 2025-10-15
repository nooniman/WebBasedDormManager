<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Rooms';
require_once '../includes/header.php';

// Handle room addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $room_number = sanitize_input($_POST['room_number']);
        $room_type = sanitize_input($_POST['room_type']);
        $capacity = intval($_POST['capacity']);
        $price = floatval($_POST['price']);
        $status = sanitize_input($_POST['status']);
        $description = sanitize_input($_POST['description']);
        
        if (isset($_POST['room_id']) && !empty($_POST['room_id'])) {
            // Update existing room
            $room_id = intval($_POST['room_id']);
            $stmt = $conn->prepare("UPDATE rooms SET room_number = ?, room_type = ?, capacity = ?, price = ?, status = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssidssi", $room_number, $room_type, $capacity, $price, $status, $description, $room_id);
            $message = 'Room updated successfully';
        } else {
            // Add new room
            $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, capacity, price, status, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssidss", $room_number, $room_type, $capacity, $price, $status, $description);
            $message = 'Room added successfully';
        }
        
        if ($stmt->execute()) {
            set_flash_message($message, 'success');
        } else {
            set_flash_message('Operation failed', 'error');
        }
        
        $stmt->close();
        redirect('rooms.php');
    }
}

// Fetch all rooms
$query = "SELECT * FROM rooms ORDER BY room_number";
$result = $conn->query($query);
?>

<div class="container">
    <div class="card mb-4">
        <div class="card-header">
            <h2>Add New Room</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="room_number">Room Number</label>
                        <input type="text" id="room_number" name="room_number" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="room_type">Room Type</label>
                        <select id="room_type" name="room_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Single">Single</option>
                            <option value="Double">Double</option>
                            <option value="Suite">Suite</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="price">Price (Monthly)</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Room</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2>All Rooms</h2>
        </div>
        <div class="card-body">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($room = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                    <td><?php echo $room['capacity']; ?></td>
                                    <td><?php echo format_currency($room['price']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $room['status'] === 'available' ? 'success' : 
                                                ($room['status'] === 'occupied' ? 'info' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($room['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="room_details.php?id=<?php echo $room['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Edit</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No rooms found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
