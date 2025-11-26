<?php
// filepath: admin/room_details.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Room Details';

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
        
        $update_stmt = $conn->prepare("
            UPDATE rooms SET 
            room_number = ?, room_type = ?, floor_number = ?, category = ?, 
            capacity = ?, price = ?, status = ?, description = ?, 
            has_wifi = ?, has_ac = ?, has_bathroom = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("ssisisssiiii", 
            $room_number, $room_type, $floor_number, $category, 
            $capacity, $price, $status, $description, 
            $has_wifi, $has_ac, $has_bathroom, $room_id
        );
        
        if ($update_stmt->execute()) {
            set_flash_message('Room updated successfully! 🎉', 'success');
            redirect("room_details.php?id=$room_id");
        } else {
            set_flash_message('Failed to update room', 'error');
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
                    set_flash_message('Photo uploaded successfully! 📸', 'success');
                } else {
                    set_flash_message('Failed to save photo', 'error');
                }
                
                $insert_photo->close();
                redirect("room_details.php?id=$room_id");
            } else {
                set_flash_message($upload_result['message'], 'error');
            }
        } else {
            set_flash_message('Please select a photo to upload', 'error');
        }
    }
}

// Handle photo deletion
if (isset($_GET['delete_photo']) && verify_csrf_token($_GET['csrf_token'])) {
    $photo_id = intval($_GET['delete_photo']);
    
    // Get photo path for file deletion
    $photo_stmt = $conn->prepare("SELECT photo_path FROM room_photos WHERE id = ? AND room_id = ?");
    $photo_stmt->bind_param("ii", $photo_id, $room_id);
    $photo_stmt->execute();
    $photo_result = $photo_stmt->get_result();
    
    if ($photo_result->num_rows > 0) {
        $photo_data = $photo_result->fetch_assoc();
        
        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM room_photos WHERE id = ? AND room_id = ?");
        $delete_stmt->bind_param("ii", $photo_id, $room_id);
        
        if ($delete_stmt->execute()) {
            // Try to delete physical file
            $file_path = "../uploads/" . $photo_data['photo_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            set_flash_message('Photo deleted successfully', 'success');
        } else {
            set_flash_message('Failed to delete photo', 'error');
        }
        $delete_stmt->close();
    }
    $photo_stmt->close();
    
    redirect("room_details.php?id=$room_id");
}

// Handle set primary photo
if (isset($_GET['set_primary']) && verify_csrf_token($_GET['csrf_token'])) {
    $photo_id = intval($_GET['set_primary']);
    
    // Unset all primary photos for this room
    $conn->query("UPDATE room_photos SET is_primary = 0 WHERE room_id = $room_id");
    
    // Set new primary
    $primary_stmt = $conn->prepare("UPDATE room_photos SET is_primary = 1 WHERE id = ? AND room_id = ?");
    $primary_stmt->bind_param("ii", $photo_id, $room_id);
    
    if ($primary_stmt->execute()) {
        set_flash_message('Primary photo updated', 'success');
    }
    $primary_stmt->close();
    
    redirect("room_details.php?id=$room_id");
}

// Fetch room photos
$photos_result = $conn->query("
    SELECT * FROM room_photos 
    WHERE room_id = $room_id 
    ORDER BY is_primary DESC, created_at DESC
");

// Get booking statistics for this room
$booking_stats = $conn->query("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as active_bookings
    FROM bookings 
    WHERE room_id = $room_id
")->fetch_assoc();

require_once '../includes/header.php';
?>

<style>
    .room-details-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    /* Breadcrumb */
    .breadcrumb-admin {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .breadcrumb-admin a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .breadcrumb-admin a:hover {
        color: #764ba2;
    }
    
    .breadcrumb-admin span {
        color: #94a3b8;
    }
    
    /* Page Header */
    .details-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2.5rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .details-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .header-content {
        position: relative;
        z-index: 1;
    }
    
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .header-title-section h1 {
        font-size: 2.5rem;
        font-weight: 900;
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .header-subtitle {
        font-size: 1.1rem;
        opacity: 0.95;
        margin: 0;
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .header-btn {
        padding: 0.875rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: 2px solid transparent;
    }
    
    .header-btn.primary {
        background: white;
        color: #667eea;
    }
    
    .header-btn.primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    }
    
    .header-btn.secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border-color: white;
    }
    
    .header-btn.secondary:hover {
        background: white;
        color: #667eea;
    }
    
    .header-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .header-stat-item {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        padding: 1.25rem;
        border-radius: 16px;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    .header-stat-value {
        font-size: 2rem;
        font-weight: 900;
        margin: 0;
        line-height: 1;
    }
    
    .header-stat-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-top: 0.5rem;
        font-weight: 600;
    }
    
    /* Layout */
    .details-layout {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    /* Form Card */
    .form-card {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .form-section-title {
        font-size: 1.5rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 2rem 0;
        padding-bottom: 1rem;
        border-bottom: 3px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .form-group-modern {
        margin-bottom: 1.75rem;
    }
    
    .form-label-modern {
        display: block;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    
    .form-label-modern .required {
        color: #ef4444;
        margin-left: 0.25rem;
    }
    
    .form-input-modern {
        width: 100%;
        padding: 1rem 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
        font-family: inherit;
    }
    
    .form-input-modern:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    textarea.form-input-modern {
        min-height: 120px;
        resize: vertical;
    }
    
    .form-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    
    /* Checkbox Group */
    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .checkbox-item:hover {
        border-color: #667eea;
        transform: translateX(5px);
    }
    
    .checkbox-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .checkbox-label {
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
    }
    
    /* Status Select */
    .status-select-group {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }
    
    .status-option {
        display: none;
    }
    
    .status-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
        padding: 1.25rem;
        border: 3px solid #e2e8f0;
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
    }
    
    .status-label:hover {
        border-color: #cbd5e0;
        transform: translateY(-3px);
    }
    
    .status-option:checked + .status-label {
        border-color: var(--status-color);
        background: var(--status-bg);
    }
    
    .status-icon {
        font-size: 2rem;
    }
    
    .status-text {
        font-weight: 700;
        color: #475569;
        font-size: 0.95rem;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid #e2e8f0;
    }
    
    .btn-modern {
        padding: 1.125rem 2rem;
        border-radius: 14px;
        font-weight: 800;
        font-size: 1.05rem;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }
    
    .btn-modern.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
    }
    
    .btn-modern.primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(16, 185, 129, 0.4);
    }
    
    .btn-modern.secondary {
        background: white;
        color: #64748b;
        border-color: #e2e8f0;
    }
    
    .btn-modern.secondary:hover {
        border-color: #cbd5e0;
        color: #1e293b;
    }
    
    /* Photo Gallery Card */
    .photo-card {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .photo-card-title {
        font-size: 1.25rem;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 1.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .upload-area {
        border: 3px dashed #cbd5e0;
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 1.5rem;
        display: block; /* Changed from inline */
    }
    
    .upload-area:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }

    .upload-area.active {
        border-color: #10b981 !important;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%) !important;
    }
    
    .upload-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .upload-text {
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .upload-hint {
        font-size: 0.875rem;
        color: #94a3b8;
    }
    
    .file-input-hidden {
        display: none;
    }
    
    .primary-checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border: 2px solid #667eea;
        border-radius: 12px;
        margin: 1rem 0;
    }
    
    .primary-checkbox-group input {
        width: 18px;
        height: 18px;
    }
    
    .primary-checkbox-group label {
        font-weight: 700;
        color: #667eea;
        cursor: pointer;
    }
    
    /* Photo Grid */
    .photos-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .photo-item {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .photo-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }
    
    .photo-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    
    .photo-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .photo-item:hover .photo-overlay {
        opacity: 1;
    }
    
    .photo-action-btn {
        padding: 0.75rem 1rem;
        border-radius: 10px;
        font-weight: 700;
        text-decoration: none;
        border: 2px solid white;
        color: white;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }
    
    .photo-action-btn:hover {
        background: white;
        color: #1e293b;
        transform: scale(1.05);
    }
    
    .primary-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .empty-photos {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #94a3b8;
    }
    
    .empty-photos-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .empty-photos-text {
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    /* Alert Box */
    .alert-box {
        padding: 1.25rem 1.5rem;
        border-radius: 14px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: start;
        gap: 1rem;
        border: 2px solid;
    }
    
    .alert-box.info {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
        border-color: #3b82f6;
        color: #1e40af;
    }
    
    .alert-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .alert-content {
        flex: 1;
    }
    
    .alert-title {
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .alert-text {
        margin: 0;
        line-height: 1.6;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .details-layout {
            grid-template-columns: 1fr;
        }
        
        .form-grid-2 {
            grid-template-columns: 1fr;
        }
        
        .status-select-group {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .header-top {
            flex-direction: column;
        }
        
        .header-title-section h1 {
            font-size: 2rem;
        }
        
        .header-actions {
            flex-direction: column;
            width: 100%;
        }
        
        .header-btn {
            width: 100%;
            justify-content: center;
        }
        
        .photos-grid {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn-modern {
            width: 100%;
        }
    }
</style>

<div class="room-details-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-admin">
            <a href="dashboard.php">Dashboard</a>
            <span>→</span>
            <a href="rooms.php">Rooms</a>
            <span>→</span>
            <span>Room <?php echo htmlspecialchars($room['room_number']); ?></span>
        </nav>
        
        <!-- Page Header -->
        <div class="details-header">
            <div class="header-content">
                <div class="header-top">
                    <div class="header-title-section">
                        <h1>
                            <span style="font-size: 3rem;">🏠</span>
                            Room <?php echo htmlspecialchars($room['room_number']); ?>
                        </h1>
                        <p class="header-subtitle">
                            Manage room details, photos, and settings
                        </p>
                    </div>
                    
                    <div class="header-actions">
                        <a href="../public/room_view.php?id=<?php echo $room_id; ?>" 
                           class="header-btn primary"
                           target="_blank">
                            <span>👁️</span>
                            <span>Preview</span>
                        </a>
                        <a href="rooms.php" class="header-btn secondary">
                            <span>←</span>
                            <span>Back to Rooms</span>
                        </a>
                    </div>
                </div>
                
                <div class="header-stats">
                    <div class="header-stat-item">
                        <div class="header-stat-value"><?php echo number_format($booking_stats['total_bookings']); ?></div>
                        <div class="header-stat-label">Total Bookings</div>
                    </div>
                    <div class="header-stat-item">
                        <div class="header-stat-value"><?php echo number_format($booking_stats['pending_bookings']); ?></div>
                        <div class="header-stat-label">Pending</div>
                    </div>
                    <div class="header-stat-item">
                        <div class="header-stat-value"><?php echo number_format($booking_stats['approved_bookings']); ?></div>
                        <div class="header-stat-label">Approved</div>
                    </div>
                    <div class="header-stat-item">
                        <div class="header-stat-value"><?php echo number_format($booking_stats['active_bookings']); ?></div>
                        <div class="header-stat-label">Active</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="details-layout">
            <!-- Left Column - Room Information Form -->
            <div>
                <div class="form-card">
                    <h2 class="form-section-title">
                        <span>📝</span>
                        Room Information
                    </h2>
                    
                    <form method="POST" action="" id="roomUpdateForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="update_room">
                        
                        <!-- Room Number -->
                        <div class="form-group-modern">
                            <label class="form-label-modern" for="room_number">
                                Room Number <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="room_number" 
                                   name="room_number" 
                                   class="form-input-modern" 
                                   value="<?php echo htmlspecialchars($room['room_number']); ?>" 
                                   required>
                        </div>
                        
                        <!-- Type and Floor -->
                        <div class="form-grid-2">
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="room_type">
                                    Room Type <span class="required">*</span>
                                </label>
                                <select id="room_type" name="room_type" class="form-input-modern" required>
                                    <option value="single" <?php echo $room['room_type'] === 'single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="double" <?php echo $room['room_type'] === 'double' ? 'selected' : ''; ?>>Double</option>
                                    <option value="quad" <?php echo $room['room_type'] === 'quad' ? 'selected' : ''; ?>>Quad</option>
                                </select>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="floor_number">
                                    Floor Number <span class="required">*</span>
                                </label>
                                <input type="number" 
                                       id="floor_number" 
                                       name="floor_number" 
                                       class="form-input-modern" 
                                       value="<?php echo htmlspecialchars($room['floor_number'] ?? 1); ?>" 
                                       min="1"
                                       required>
                            </div>
                        </div>
                        
                        <!-- Category and Capacity -->
                        <div class="form-grid-2">
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="category">
                                    Category <span class="required">*</span>
                                </label>
                                <select id="category" name="category" class="form-input-modern" required>
                                    <option value="standard" <?php echo $room['category'] === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                    <option value="deluxe" <?php echo $room['category'] === 'deluxe' ? 'selected' : ''; ?>>Deluxe</option>
                                    <option value="premium" <?php echo $room['category'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                </select>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="capacity">
                                    Capacity (Persons) <span class="required">*</span>
                                </label>
                                <input type="number" 
                                       id="capacity" 
                                       name="capacity" 
                                       class="form-input-modern" 
                                       value="<?php echo $room['capacity']; ?>" 
                                       min="1" 
                                       max="10"
                                       required>
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="form-group-modern">
                            <label class="form-label-modern" for="price">
                                Monthly Price (₱) <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="price" 
                                   name="price" 
                                   class="form-input-modern" 
                                   value="<?php echo $room['price']; ?>" 
                                   step="0.01" 
                                   min="0"
                                   required>
                        </div>
                        
                        <!-- Status -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">
                                Room Status <span class="required">*</span>
                            </label>
                            <div class="status-select-group">
                                <div>
                                    <input type="radio" 
                                           id="status_available" 
                                           name="status" 
                                           value="available" 
                                           class="status-option"
                                           <?php echo $room['status'] === 'available' ? 'checked' : ''; ?>>
                                    <label for="status_available" 
                                           class="status-label" 
                                           style="--status-color: #10b981; --status-bg: rgba(16, 185, 129, 0.1);">
                                        <span class="status-icon">✓</span>
                                        <span class="status-text">Available</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <input type="radio" 
                                           id="status_occupied" 
                                           name="status" 
                                           value="occupied" 
                                           class="status-option"
                                           <?php echo $room['status'] === 'occupied' ? 'checked' : ''; ?>>
                                    <label for="status_occupied" 
                                           class="status-label"
                                           style="--status-color: #3b82f6; --status-bg: rgba(59, 130, 246, 0.1);">
                                        <span class="status-icon">👥</span>
                                        <span class="status-text">Occupied</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <input type="radio" 
                                           id="status_maintenance" 
                                           name="status" 
                                           value="maintenance" 
                                           class="status-option"
                                           <?php echo $room['status'] === 'maintenance' ? 'checked' : ''; ?>>
                                    <label for="status_maintenance" 
                                           class="status-label"
                                           style="--status-color: #f59e0b; --status-bg: rgba(245, 158, 11, 0.1);">
                                        <span class="status-icon">🔧</span>
                                        <span class="status-text">Maintenance</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Features/Amenities -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">Room Features</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" 
                                           name="has_wifi" 
                                           value="1" 
                                           <?php echo $room['has_wifi'] ? 'checked' : ''; ?>>
                                    <span class="checkbox-label">
                                        <span>📶</span>
                                        <span>WiFi Connection</span>
                                    </span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" 
                                           name="has_ac" 
                                           value="1" 
                                           <?php echo $room['has_ac'] ? 'checked' : ''; ?>>
                                    <span class="checkbox-label">
                                        <span>❄️</span>
                                        <span>Air Conditioning</span>
                                    </span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" 
                                           name="has_bathroom" 
                                           value="1" 
                                           <?php echo $room['has_bathroom'] ? 'checked' : ''; ?>>
                                    <span class="checkbox-label">
                                        <span>🚿</span>
                                        <span>Private Bathroom</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group-modern">
                            <label class="form-label-modern" for="description">
                                Room Description
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      class="form-input-modern" 
                                      rows="5"
                                      placeholder="Detailed description of the room, its features, and what makes it special..."><?php echo htmlspecialchars($room['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn-modern primary">
                                <span>💾</span>
                                <span>Save Changes</span>
                            </button>
                            <a href="rooms.php" class="btn-modern secondary">
                                <span>✕</span>
                                <span>Cancel</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Right Column - Photo Management -->
            <div>
                <!-- Upload Section -->
                <div class="photo-card">
                    <h3 class="photo-card-title">
                        <span>📸</span>
                        Upload Room Photo
                    </h3>
                    
                    <div class="alert-box info">
                        <span class="alert-icon">ℹ️</span>
                        <div class="alert-content">
                            <div class="alert-title">Photo Guidelines</div>
                            <p class="alert-text">
                                • Maximum file size: 5MB<br>
                                • Accepted formats: JPG, PNG<br>
                                • Recommended size: 1200x800px<br>
                                • Set one photo as primary for listings
                            </p>
                        </div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="photoUploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="upload_photo">
                        
                        <label for="photo" class="upload-area" id="uploadArea">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">Click to select photo</div>
                            <div class="upload-hint">or drag and drop here</div>
                        </label>
                        <input type="file" 
                               id="photo" 
                               name="photo" 
                               class="file-input-hidden" 
                               accept="image/*"
                               required>
                        
                        <div class="primary-checkbox-group">
                            <input type="checkbox" 
                                   id="is_primary" 
                                   name="is_primary" 
                                   value="1">
                            <label for="is_primary">Set as primary photo</label>
                        </div>
                        
                        <button type="submit" class="btn-modern primary" style="width: 100%;">
                            <span>⬆️</span>
                            <span>Upload Photo</span>
                        </button>
                    </form>
                </div>
                
                <!-- Current Photos -->
                <div class="photo-card">
                    <h3 class="photo-card-title">
                        <span>🖼️</span>
                        Current Photos (<?php echo $photos_result ? $photos_result->num_rows : 0; ?>)
                    </h3>
                    
                    <?php if ($photos_result && $photos_result->num_rows > 0): ?>
                        <div class="photos-grid">
                            <?php while ($photo = $photos_result->fetch_assoc()): ?>
                                <div class="photo-item">
                                    <img src="../uploads/<?php echo htmlspecialchars($photo['photo_path']); ?>" 
                                         alt="Room photo"
                                         class="photo-image">
                                    
                                    <?php if ($photo['is_primary']): ?>
                                        <span class="primary-badge">
                                            <span>⭐</span>
                                            <span>Primary</span>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="photo-overlay">
                                        <?php if (!$photo['is_primary']): ?>
                                            <a href="?id=<?php echo $room_id; ?>&set_primary=<?php echo $photo['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" 
                                               class="photo-action-btn"
                                               onclick="return confirm('Set this as primary photo?');">
                                                <span>⭐</span>
                                                <span>Set Primary</span>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?id=<?php echo $room_id; ?>&delete_photo=<?php echo $photo['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" 
                                           class="photo-action-btn"
                                           onclick="return confirm('⚠️ Delete this photo?');">
                                            <span>🗑️</span>
                                            <span>Delete</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-photos">
                            <div class="empty-photos-icon">📷</div>
                            <div class="empty-photos-text">No photos uploaded yet</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// File upload interaction
const uploadArea = document.getElementById('uploadArea');
const photoInput = document.getElementById('photo');

if (uploadArea && photoInput) {
    // Click to select
    uploadArea.addEventListener('click', function(e) {
        if (e.target !== photoInput) {
            photoInput.click();
        }
    });
    
    // Display selected filename
    photoInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const fileName = this.files[0].name;
            uploadArea.querySelector('.upload-text').textContent = fileName;
            uploadArea.querySelector('.upload-hint').textContent = 'Ready to upload';
            uploadArea.classList.add('active'); // Use class instead of inline styles
        }
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('active');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('active');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('active');
        
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            photoInput.files = e.dataTransfer.files;
            const fileName = e.dataTransfer.files[0].name;
            uploadArea.querySelector('.upload-text').textContent = fileName;
            uploadArea.querySelector('.upload-hint').textContent = 'Ready to upload';
            uploadArea.classList.add('active');
        }
    });
}

// Form submission confirmation
document.getElementById('roomUpdateForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>⏳</span><span>Saving...</span>';
});
</script>

<?php require_once '../includes/footer.php'; ?>