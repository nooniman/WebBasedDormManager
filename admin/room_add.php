<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\admin\room_add.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Add New Room';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $room_number = sanitize_input($_POST['room_number']);
        $room_type = sanitize_input($_POST['room_type']);
        $category = sanitize_input($_POST['category']);
        $floor_number = intval($_POST['floor_number']);
        $capacity = intval($_POST['capacity']);
        $price = floatval($_POST['price']);
        $status = sanitize_input($_POST['status']);
        $description = sanitize_input($_POST['description']);
        $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $has_bathroom = isset($_POST['has_bathroom']) ? 1 : 0;
        
        // Bedspace fields
        $is_bedspace = isset($_POST['is_bedspace']) ? 1 : 0;
        $total_bedspaces = $is_bedspace ? intval($_POST['total_bedspaces']) : 0;
        $price_per_bedspace = $is_bedspace ? floatval($_POST['price_per_bedspace']) : null;
        
        $errors = [];
        
        // Validate room number uniqueness
        $check_stmt = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
        $check_stmt->bind_param("s", $room_number);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Room number already exists";
        }
        $check_stmt->close();
        
        if (empty($room_number)) $errors[] = "Room number is required";
        if ($capacity < 1) $errors[] = "Capacity must be at least 1";
        if ($price <= 0 && !$is_bedspace) $errors[] = "Price must be greater than 0";
        if ($is_bedspace && $total_bedspaces < 2) $errors[] = "Bedspace rooms must have at least 2 bedspaces";
        if ($is_bedspace && $price_per_bedspace <= 0) $errors[] = "Price per bedspace must be greater than 0";
        
        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO rooms 
                (room_number, room_type, category, floor_number, capacity, price, status, description, has_wifi, has_ac, has_bathroom, is_bedspace, total_bedspaces, occupied_bedspaces, price_per_bedspace, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())
            ");
            $stmt->bind_param("sssiidssiiiiiid", 
                $room_number, $room_type, $category, $floor_number,
                $capacity, $price, $status, $description,
                $has_wifi, $has_ac, $has_bathroom,
                $is_bedspace, $total_bedspaces, $price_per_bedspace
            );
            
            if ($stmt->execute()) {
                $new_room_id = $stmt->insert_id;
                
                // Create bedspaces if it's a bedspace room
                if ($is_bedspace) {
                    require_once '../includes/bedspace_functions.php';
                    create_bedspaces($conn, $new_room_id, $total_bedspaces);
                }
                
                // Handle photo upload if provided
                if (isset($_FILES['room_photo']) && $_FILES['room_photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = upload_file($_FILES['room_photo'], ['jpg', 'jpeg', 'png'], 5242880);
                    
                    if ($upload_result['success']) {
                        $photo_path = $upload_result['filename'];
                        $photo_stmt = $conn->prepare("INSERT INTO room_photos (room_id, photo_path, is_primary) VALUES (?, ?, 1)");
                        $photo_stmt->bind_param("is", $new_room_id, $photo_path);
                        $photo_stmt->execute();
                        $photo_stmt->close();
                    }
                }
                
                set_flash_message('Room added successfully! 🎉', 'success');
                redirect(ADMIN_URL . '/room_details?id=' . $new_room_id);
            } else {
                $errors[] = "Failed to add room: " . $stmt->error;
            }
            $stmt->close();
        }
        
        if (!empty($errors)) {
            set_flash_message(implode('<br>', $errors), 'error');
        }
    }
}

require_once '../includes/header.php';
?>

<style>
    .add-room-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Breadcrumb */
    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .breadcrumb-nav a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .breadcrumb-nav a:hover {
        color: #764ba2;
    }
    
    .breadcrumb-nav span {
        color: #94a3b8;
    }
    
    /* Page Header */
    .add-room-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2.5rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .add-room-header::before {
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .header-title h1 {
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
    }
    
    .header-btn.secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 2px solid white;
    }
    
    .header-btn.secondary:hover {
        background: white;
        color: #667eea;
    }
    
    /* Form Container */
    .form-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
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
    
    .section-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
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
        accent-color: #667eea;
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
    
    /* Status Radio Group */
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
        border-width: 3px;
    }
    
    .status-option:checked + .status-label.available {
        border-color: #10b981;
        background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%);
    }
    
    .status-option:checked + .status-label.occupied {
        border-color: #3b82f6;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }
    
    .status-option:checked + .status-label.maintenance {
        border-color: #f59e0b;
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    }
    
    .status-icon {
        font-size: 2rem;
    }
    
    .status-text {
        font-weight: 700;
        color: #475569;
        font-size: 0.95rem;
    }
    
    /* Photo Upload */
    .photo-upload-area {
        border: 3px dashed #cbd5e0;
        border-radius: 16px;
        padding: 2.5rem;
        text-align: center;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .photo-upload-area:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }
    
    .photo-upload-area.dragover {
        border-color: #10b981;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    }
    
    .upload-icon {
        font-size: 3.5rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .upload-text {
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }
    
    .upload-hint {
        font-size: 0.875rem;
        color: #94a3b8;
    }
    
    .file-input-hidden {
        display: none;
    }
    
    .selected-file {
        margin-top: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%);
        border-radius: 10px;
        color: #065f46;
        font-weight: 700;
        display: none;
    }
    
    .selected-file.show {
        display: block;
    }
    
    /* Tips Card */
    .tips-card {
        background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        border-radius: 16px;
        padding: 1.5rem;
        border: 2px solid #a78bfa;
    }
    
    .tips-card h4 {
        margin: 0 0 1rem 0;
        color: #5b21b6;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .tips-card ul {
        margin: 0;
        padding-left: 1.25rem;
        color: #6d28d9;
    }
    
    .tips-card li {
        margin-bottom: 0.5rem;
        font-weight: 500;
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
        flex: 1;
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
    
    /* Price Preview */
    .price-preview {
        margin-top: 1rem;
        padding: 1.25rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        color: white;
        text-align: center;
    }
    
    .price-preview-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .price-preview-value {
        font-size: 2rem;
        font-weight: 900;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .form-container {
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
        .header-content {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .header-title h1 {
            font-size: 2rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
</style>

<div class="add-room-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="<?php echo ADMIN_URL; ?>/dashboard">Dashboard</a>
            <span>→</span>
            <a href="<?php echo ADMIN_URL; ?>/rooms">Rooms</a>
            <span>→</span>
            <span>Add New Room</span>
        </nav>
        
        <!-- Page Header -->
        <div class="add-room-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>
                        <span style="font-size: 2.5rem;">➕</span>
                        Add New Room
                    </h1>
                    <p class="header-subtitle">Create a new room for your dormitory</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo ADMIN_URL; ?>/rooms" class="header-btn secondary">
                        <span>←</span>
                        <span>Back to Rooms</span>
                    </a>
                </div>
            </div>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data" id="addRoomForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-container">
                <!-- Main Form -->
                <div>
                    <div class="form-card">
                        <h2 class="form-section-title">
                            <span class="section-icon">🏠</span>
                            Room Information
                        </h2>
                        
                        <!-- Room Number -->
                        <div class="form-group-modern">
                            <label class="form-label-modern" for="room_number">
                                Room Number <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="room_number" 
                                   name="room_number" 
                                   class="form-input-modern" 
                                   placeholder="e.g., 101, A-201, B-301"
                                   required>
                        </div>
                        
                        <!-- Type and Floor -->
                        <div class="form-grid-2">
                            <div class="form-group-modern">
                                <label class="form-label-modern" for="room_type">
                                    Room Type <span class="required">*</span>
                                </label>
                                <select id="room_type" name="room_type" class="form-input-modern" required>
                                    <option value="single">Single (1 bed)</option>
                                    <option value="double">Double (2 beds)</option>
                                    <option value="quad">Quad (4 beds)</option>
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
                                       value="1"
                                       min="1"
                                       max="50"
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
                                    <option value="standard">Standard</option>
                                    <option value="deluxe">Deluxe</option>
                                    <option value="premium">Premium</option>
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
                                       value="1"
                                       min="1" 
                                       max="10"
                                       required>
                        </div>
                        
                        <!-- Price -->
                        <div class="form-group-modern" id="regularPriceField">
                            <label class="form-label-modern" for="price">
                                Monthly Price (₱) <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="price" 
                                   name="price" 
                                   class="form-input-modern" 
                                   placeholder="e.g., 5000"
                                   step="0.01" 
                                   min="0">
                            <div class="price-preview" id="pricePreview" style="display: none;">
                                <div class="price-preview-label">Monthly Rate</div>
                                <div class="price-preview-value" id="pricePreviewValue">₱0.00</div>
                            </div>
                        </div>
                        
                        <!-- Bedspacing Section -->
                        <div class="form-group-modern">
                            <label class="checkbox-item">
                                <input type="checkbox" 
                                       name="is_bedspace" 
                                       id="is_bedspace"
                                       value="1"
                                       onchange="toggleBedspaceFields()">
                                <span class="checkbox-label">
                                    <span>🛏️</span>
                                    <span>Enable Bedspacing (Rent by Bed)</span>
                                </span>
                            </label>
                        </div>
                        
                        <div id="bedspaceFields" style="display: none; padding: 1.5rem; background: #f8fafc; border-radius: 8px; margin: 1rem 0;">
                            <h4 style="margin: 0 0 1rem 0; color: #667eea; font-size: 1rem;">
                                🛏️ Bedspace Configuration
                            </h4>
                            
                            <div class="form-grid-2">
                                <div class="form-group-modern">
                                    <label class="form-label-modern" for="total_bedspaces">
                                        Number of Bedspaces <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="total_bedspaces" 
                                           name="total_bedspaces" 
                                           class="form-input-modern" 
                                           placeholder="e.g., 4"
                                           min="2" 
                                           max="12"
                                           value="4">
                                    <small style="color: #64748b; font-size: 0.85rem;">Bedspaces will be labeled A, B, C, etc.</small>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label class="form-label-modern" for="price_per_bedspace">
                                        Price per Bedspace (₱) <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="price_per_bedspace" 
                                           name="price_per_bedspace" 
                                           class="form-input-modern" 
                                           placeholder="e.g., 1500"
                                           step="0.01" 
                                           min="0">
                                    <small style="color: #64748b; font-size: 0.85rem;">Monthly rate per bed</small>
                                </div>
                            </div>
                            
                            <div class="alert-box info" style="margin-top: 1rem;">
                                <span class="alert-icon">ℹ️</span>
                                <div class="alert-content">
                                    <div class="alert-title">Bedspacing Info</div>
                                    <p class="alert-text">
                                        Bedspaces allow multiple tenants to rent individual beds in the same room. Each bedspace is tracked separately for bookings and payments.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">
                                Initial Status <span class="required">*</span>
                            </label>
                            <div class="status-select-group">
                                <div>
                                    <input type="radio" 
                                           name="status" 
                                           id="status_available" 
                                           value="available" 
                                           class="status-option"
                                           checked>
                                    <label for="status_available" class="status-label available">
                                        <span class="status-icon">✅</span>
                                        <span class="status-text">Available</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <input type="radio" 
                                           name="status" 
                                           id="status_occupied" 
                                           value="occupied" 
                                           class="status-option">
                                    <label for="status_occupied" class="status-label occupied">
                                        <span class="status-icon">👥</span>
                                        <span class="status-text">Occupied</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <input type="radio" 
                                           name="status" 
                                           id="status_maintenance" 
                                           value="maintenance" 
                                           class="status-option">
                                    <label for="status_maintenance" class="status-label maintenance">
                                        <span class="status-icon">🔧</span>
                                        <span class="status-text">Maintenance</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Amenities -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">Room Amenities</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="has_wifi" value="1" checked>
                                    <span class="checkbox-label">
                                        <span>📶</span>
                                        <span>WiFi Internet</span>
                                    </span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" name="has_ac" value="1">
                                    <span class="checkbox-label">
                                        <span>❄️</span>
                                        <span>Air Conditioning</span>
                                    </span>
                                </label>
                                
                                <label class="checkbox-item">
                                    <input type="checkbox" name="has_bathroom" value="1">
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
                                      placeholder="Describe the room, its features, view, and what makes it special..."></textarea>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn-modern primary">
                                <span>💾</span>
                                <span>Add Room</span>
                            </button>
                            <a href="<?php echo ADMIN_URL; ?>/rooms" class="btn-modern secondary">
                                <span>✕</span>
                                <span>Cancel</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Photo Upload -->
                    <div class="form-card" style="margin-bottom: 2rem;">
                        <h3 class="form-section-title" style="font-size: 1.25rem;">
                            <span class="section-icon" style="width: 40px; height: 40px; font-size: 1.25rem;">📸</span>
                            Room Photo
                        </h3>
                        
                        <label for="room_photo" class="photo-upload-area" id="uploadArea">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">Click to upload photo</div>
                            <div class="upload-hint">or drag and drop here<br>JPG, PNG • Max 5MB</div>
                        </label>
                        <input type="file" 
                               id="room_photo" 
                               name="room_photo" 
                               class="file-input-hidden" 
                               accept="image/*">
                        <div class="selected-file" id="selectedFile"></div>
                        
                        <p style="margin-top: 1rem; font-size: 0.875rem; color: #64748b; text-align: center;">
                            You can add more photos after creating the room
                        </p>
                    </div>
                    
                    <!-- Tips -->
                    <div class="tips-card">
                        <h4>💡 Quick Tips</h4>
                        <ul>
                            <li>Use descriptive room numbers (e.g., A-101)</li>
                            <li>Set accurate capacity for proper booking</li>
                            <li>Add amenities to help tenants choose</li>
                            <li>Upload a clear, well-lit photo</li>
                            <li>Write detailed descriptions for better visibility</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle bedspace fields
function toggleBedspaceFields() {
    const checkbox = document.getElementById('is_bedspace');
    const bedspaceFields = document.getElementById('bedspaceFields');
    const regularPriceField = document.getElementById('regularPriceField');
    const priceInput = document.getElementById('price');
    const totalBedspacesInput = document.getElementById('total_bedspaces');
    const pricePerBedspaceInput = document.getElementById('price_per_bedspace');
    
    if (checkbox.checked) {
        bedspaceFields.style.display = 'block';
        regularPriceField.style.display = 'none';
        priceInput.removeAttribute('required');
        totalBedspacesInput.setAttribute('required', 'required');
        pricePerBedspaceInput.setAttribute('required', 'required');
    } else {
        bedspaceFields.style.display = 'none';
        regularPriceField.style.display = 'block';
        priceInput.setAttribute('required', 'required');
        totalBedspacesInput.removeAttribute('required');
        pricePerBedspaceInput.removeAttribute('required');
    }
}

// Price preview
const priceInput = document.getElementById('price');
const pricePreview = document.getElementById('pricePreview');
const pricePreviewValue = document.getElementById('pricePreviewValue');

priceInput.addEventListener('input', function() {
    const price = parseFloat(this.value) || 0;
    if (price > 0) {
        pricePreview.style.display = 'block';
        pricePreviewValue.textContent = '₱' + price.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        pricePreview.style.display = 'none';
    }
});

// File upload
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('room_photo');
const selectedFile = document.getElementById('selectedFile');

fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        selectedFile.textContent = '📎 ' + this.files[0].name;
        selectedFile.classList.add('show');
    } else {
        selectedFile.classList.remove('show');
    }
});

// Drag and drop
uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        selectedFile.textContent = '📎 ' + e.dataTransfer.files[0].name;
        selectedFile.classList.add('show');
    }
});

// Auto-set capacity based on room type
document.getElementById('room_type').addEventListener('change', function() {
    const capacities = {
        'single': 1,
        'double': 2,
        'quad': 4
    };
    document.getElementById('capacity').value = capacities[this.value] || 1;
});

// Form submission loading state
document.getElementById('addRoomForm').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>⏳</span><span>Adding Room...</span>';
});
</script>

<?php require_once '../includes/footer.php'; ?>