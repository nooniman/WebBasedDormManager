<?php
// filepath: admin/room_details.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Room Details';
require_once '../includes/header.php';

$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($room_id === 0) {
    redirect('rooms.php');
}

// Fetch room details
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_flash_message('Room not found', 'error');
    redirect('rooms.php');
}

$room = $result->fetch_assoc();
$stmt->close();

// Handle room update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $room_number = sanitize_input($_POST['room_number']);
        $room_type = sanitize_input($_POST['room_type']);
        $floor_number = intval($_POST['floor_number']);
        $category = sanitize_input($_POST['category']);
        $capacity = intval($_POST['capacity']);
        $price = floatval($_POST['price']);
        $status = sanitize_input($_POST['status']);
        $description = sanitize_input($_POST['description']);
        $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $has_bathroom = isset($_POST['has_bathroom']) ? 1 : 0;
        
        $update_stmt = $conn->prepare("UPDATE rooms SET room_number = ?, room_type = ?, floor_number = ?, category = ?, capacity = ?, price = ?, status = ?, description = ?, has_wifi = ?, has_ac = ?, has_bathroom = ? WHERE id = ?");
        $update_stmt->bind_param("ssisisssiiii", $room_number, $room_type, $floor_number, $category, $capacity, $price, $status, $description, $has_wifi, $has_ac, $has_bathroom, $room_id);
        
        if ($update_stmt->execute()) {
            set_flash_message('Room updated successfully', 'success');
            redirect("room_details.php?id=$room_id");
        } else {
            $error = 'Failed to update room';
        }
        
        $update_stmt->close();
    }
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = upload_file($_FILES['photo'], ['jpg', 'jpeg', 'png'], 5242880);
            
            if ($upload_result['success']) {
                $photo_path = $upload_result['filename'];
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                
                // If this is primary, unset other primary photos
                if ($is_primary) {
                    $conn->query("UPDATE room_photos SET is_primary = 0 WHERE room_id = $room_id");
                }
                
                $insert_photo = $conn->prepare("INSERT INTO room_photos (room_id, photo_path, is_primary) VALUES (?, ?, ?)");
                $insert_photo->bind_param("isi", $room_id, $photo_path, $is_primary);
                
                if ($insert_photo->execute()) {
                    set_flash_message('Photo uploaded successfully', 'success');
                } else {
                    set_flash_message('Failed to save photo', 'error');
                }
                
                $insert_photo->close();
                redirect("room_details.php?id=$room_id");
            } else {
                $error = $upload_result['message'];
            }
        }
    }
}

// Fetch room photos
$photos_result = $conn->query("SELECT * FROM room_photos WHERE room_id = $room_id ORDER BY is_primary DESC, created_at ASC");

// Fetch available amenities
$amenities_result = $conn->query("SELECT * FROM room_amenities ORDER BY name");

// Fetch assigned amenities
$assigned_amenities = $conn->query("SELECT amenity_id FROM room_amenity_assignments WHERE room_id = $room_id");
$assigned_ids = [];
while ($row = $assigned_amenities->fetch_assoc()) {
    $assigned_ids[] = $row['amenity_id'];
}
?>

<div class="container">
    <h1 class="mb-4">Room <?php echo htmlspecialchars($room['room_number']); ?> Details</h1>
    
    <div class="grid grid-2 mb-4">
        <!-- Room Information -->
        <div class="card">
            <div class="card-header">
                <h2>Room Information</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_room">
                    
                    <div class="form-group">
                        <label class="form-label" for="room_number">Room Number</label>
                        <input type="text" id="room_number" name="room_number" class="form-control" 
                               value="<?php echo htmlspecialchars($room['room_number']); ?>" required>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label" for="room_type">Room Type</label>
                            <select id="room_type" name="room_type" class="form-control" required>
                                <option value="Single" <?php echo $room['room_type'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Double" <?php echo $room['room_type'] === 'Double' ? 'selected' : ''; ?>>Double</option>
                                <option value="Suite" <?php echo $room['room_type'] === 'Suite' ? 'selected' : ''; ?>>Suite</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="floor_number">Floor Number</label>
                            <input type="number" id="floor_number" name="floor_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($room['floor_number'] ?? ''); ?>" min="1">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label" for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">Select Category</option>
                                <option value="Standard" <?php echo $room['category'] === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="Deluxe" <?php echo $room['category'] === 'Deluxe' ? 'selected' : ''; ?>>Deluxe</option>
                                <option value="Premium" <?php echo $room['category'] === 'Premium' ? 'selected' : ''; ?>>Premium</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="capacity">Capacity</label>
                            <input type="number" id="capacity" name="capacity" class="form-control" 
                                   value="<?php echo $room['capacity']; ?>" min="1" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label" for="price">Monthly Price</label>
                            <input type="number" id="price" name="price" class="form-control" 
                                   value="<?php echo $room['price']; ?>" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="available" <?php echo $room['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="occupied" <?php echo $room['status'] === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                <option value="maintenance" <?php echo $room['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Features</label>
                        <div>
                            <label style="display: inline-block; margin-right: 1rem;">
                                <input type="checkbox" name="has_wifi" value="1" <?php echo $room['has_wifi'] ? 'checked' : ''; ?>>
                                WiFi
                            </label>
                            <label style="display: inline-block; margin-right: 1rem;">
                                <input type="checkbox" name="has_ac" value="1" <?php echo $room['has_ac'] ? 'checked' : ''; ?>>
                                Air Conditioning
                            </label>
                            <label style="display: inline-block;">
                                <input type="checkbox" name="has_bathroom" value="1" <?php echo $room['has_bathroom'] ? 'checked' : ''; ?>>
                                Private Bathroom
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Room</button>
                    <a href="rooms.php" class="btn btn-outline">Back to List</a>
                </form>
            </div>
        </div>
        
        <!-- Room Photos -->
        <div>
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Room Photos</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="upload_photo">
                        
                        <div class="form-group">
                            <label class="form-label" for="photo">Upload Photo</label>
                            <input type="file" id="photo" name="photo" class="form-control" accept="image/*" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_primary" value="1">
                                Set as primary photo
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Upload Photo</button>
                    </form>
                </div>
            </div>
            
            <?php if ($photos_result && $photos_result->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Current Photos</h2>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-2">
                            <?php while ($photo = $photos_result->fetch_assoc()): ?>
                                <div class="card">
                                    <img src="../uploads/<?php echo htmlspecialchars($photo['photo_path']); ?>" 
                                         alt="Room photo"
                                         style="width: 100%; height: 150px; object-fit: cover; border-radius: var(--border-radius);">
                                    <?php if ($photo['is_primary']): ?>
                                        <span class="badge badge-success mt-2">Primary</span>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>