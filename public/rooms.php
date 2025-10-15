<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = 'Available Rooms';
require_once '../includes/header.php';

// Fetch available rooms
$query = "SELECT * FROM rooms WHERE status = 'available' ORDER BY room_number";
$result = $conn->query($query);
?>

<div class="container">
    <h1 class="mb-4">Available Rooms</h1>
    
    <div class="grid grid-3">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($room = $result->fetch_assoc()): ?>
                <div class="card">
                    <?php if ($room['photo']): ?>
                        <img src="/dormitory-management-system/uploads/<?php echo htmlspecialchars($room['photo']); ?>" 
                             alt="Room <?php echo htmlspecialchars($room['room_number']); ?>"
                             style="width: 100%; height: 200px; object-fit: cover; border-radius: var(--border-radius) var(--border-radius) 0 0; margin: -1.5rem -1.5rem 1rem -1.5rem;">
                    <?php endif; ?>
                    
                    <h3 style="color: var(--primary-color);">Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($room['room_type']); ?></p>
                    <p><strong>Capacity:</strong> <?php echo htmlspecialchars($room['capacity']); ?> person(s)</p>
                    <p><strong>Price:</strong> <?php echo format_currency($room['price']); ?>/month</p>
                    
                    <?php if ($room['description']): ?>
                        <p><?php echo htmlspecialchars($room['description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'tenant'): ?>
                        <a href="booking.php?room_id=<?php echo $room['id']; ?>" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Book Now</a>
                    <?php else: ?>
                        <a href="../login.php" class="btn btn-outline" style="width: 100%; margin-top: 1rem;">Login to Book</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No rooms available at the moment. Please check back later.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
