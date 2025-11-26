<?php
// filepath: tenant/profile.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';


echo "<!-- DEBUG START -->";
echo "<!-- DB Value: " . ($tenant['profile_picture'] ?? 'NULL') . " -->";
$test_path = __DIR__ . '/../uploads/' . ($tenant['profile_picture'] ?? '');
echo "<!-- Full Path: " . $test_path . " -->";
echo "<!-- File Exists: " . (file_exists($test_path) ? 'YES' : 'NO') . " -->";
echo "<!-- DEBUG END -->";


$page_title = 'My Profile';
$tenant_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile']) && verify_csrf_token($_POST['csrf_token'])) {
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $emergency_contact = sanitize_input($_POST['emergency_contact']);
        $emergency_phone = sanitize_input($_POST['emergency_phone']);
        
        $errors = [];
        
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        // Check email uniqueness
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $tenant_id);
        $email_check->execute();
        if ($email_check->get_result()->num_rows > 0) {
            $errors[] = "Email already in use";
        }
        $email_check->close();
        
        if (empty($errors)) {
            $stmt = $conn->prepare("
                UPDATE users SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                emergency_contact = ?, emergency_phone = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", 
                $first_name, $last_name, $email, $phone, 
                $emergency_contact, $emergency_phone, $tenant_id
            );
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                set_flash_message('Profile updated successfully! 🎉', 'success');
                redirect("tenant/profile");
            } else {
                $errors[] = "Failed to update profile";
            }
            $stmt->close();
        }
        
        if (!empty($errors)) {
            set_flash_message(implode('<br>', $errors), 'error');
        }
    }
    
    // Handle profile photo upload
    if (isset($_POST['update_photo']) && verify_csrf_token($_POST['csrf_token'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        
        // Create profiles directory if not exists
        $profiles_dir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($profiles_dir)) {
            mkdir($profiles_dir, 0755, true);
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file = $_FILES['profile_picture'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_ext, $allowed_types)) {
            set_flash_message('Invalid file type. Please upload JPG, JPEG, PNG, or GIF.', 'error');
            redirect("tenant/profile");
        }
        
        // Validate file size
        if ($file['size'] > $max_size) {
            set_flash_message('File is too large. Maximum size is 5MB.', 'error');
            redirect("tenant/profile");
        }
        
        // Generate unique filename
        $new_filename = 'tenant_' . $tenant_id . '_' . time() . '.' . $file_ext;
        $upload_path = $profiles_dir . $new_filename;
        $db_path = 'profiles/' . $new_filename;
        
        // Delete old photo if exists
        $old_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $old_query->bind_param("i", $tenant_id);
        $old_query->execute();
        $result = $old_query->get_result();
        $old_data = $result->fetch_assoc();
        $old_photo = $old_data['profile_picture'] ?? null;
        $old_query->close();
        
        if ($old_photo && file_exists(__DIR__ . '/../uploads/' . $old_photo)) {
            unlink(__DIR__ . '/../uploads/' . $old_photo);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Update database
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $db_path, $tenant_id);
            
            if ($stmt->execute()) {
                set_flash_message('Profile photo updated successfully! 📸', 'success');
            } else {
                set_flash_message('Failed to update database.', 'error');
                if (file_exists($upload_path)) {
                    unlink($upload_path);
                }
            }
            $stmt->close();
        } else {
            set_flash_message('Failed to upload file. Please check folder permissions.', 'error');
        }
        
        redirect("tenant/profile");
    } else {
        $error_msg = 'Please select a photo to upload.';
        if (isset($_FILES['profile_picture']['error'])) {
            $error_codes = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file selected',
            ];
            $error_msg = $error_codes[$_FILES['profile_picture']['error']] ?? $error_msg;
        }
        set_flash_message($error_msg, 'error');
        redirect("tenant/profile");
    }
    }
    
    // Handle password change
    if (isset($_POST['change_password']) && verify_csrf_token($_POST['csrf_token'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $tenant_id);
        $stmt->execute();
        $current_hash = $stmt->get_result()->fetch_assoc()['password'];
        $stmt->close();
        
        if (!password_verify($current_password, $current_hash)) {
            $errors[] = "Current password is incorrect";
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        if (empty($errors)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $tenant_id);
            
            if ($stmt->execute()) {
                set_flash_message('Password changed successfully! 🔒', 'success');
                redirect("tenant/profile");
            } else {
                $errors[] = "Failed to change password";
            }
            $stmt->close();
        }
        
        if (!empty($errors)) {
            set_flash_message(implode('<br>', $errors), 'error');
        }
    }
}

// Get tenant data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get tenant's active booking
$booking_query = $conn->prepare("
    SELECT b.*, r.room_number, r.room_type, r.price 
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ? AND b.status IN ('approved', 'checked_in')
    ORDER BY b.created_at DESC
    LIMIT 1
");
$booking_query->bind_param("i", $tenant_id);
$booking_query->execute();
$active_booking = $booking_query->get_result()->fetch_assoc();
$booking_query->close();

// Get payment history
$payment_history = $conn->prepare("
    SELECT * FROM payments 
    WHERE tenant_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 5
");
$payment_history->bind_param("i", $tenant_id);
$payment_history->execute();
$payments = $payment_history->get_result()->fetch_all(MYSQLI_ASSOC);
$payment_history->close();

require_once '../includes/header.php';
?>

<style>
    .tenant-profile-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    /* Breadcrumb */
    .breadcrumb-tenant {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .breadcrumb-tenant a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .breadcrumb-tenant a:hover {
        color: #764ba2;
    }
    
    .breadcrumb-tenant span {
        color: #94a3b8;
    }

    .file-input-hidden {
        display: none;
    }
    
    /* Tenant Profile Header */
    .tenant-profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 24px;
        padding: 3rem 2.5rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4);
    }
    
    .tenant-profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .tenant-profile-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
    }
    
    .tenant-header-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 2.5rem;
    }
    
    .tenant-photo-section {
        position: relative;
    }
    
    .tenant-photo-large {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }
    
    .tenant-photo-placeholder {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        font-weight: 900;
        border: 5px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }
    
    .tenant-verified-badge {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 4px solid #667eea;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
    }
    
    .tenant-info-section h1 {
        margin: 0 0 0.75rem 0;
        font-size: 2.5rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .tenant-role-badge {
        display: inline-block;
        padding: 0.5rem 1.25rem;
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .tenant-meta-info {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        font-size: 1.05rem;
        opacity: 0.95;
        margin-top: 1rem;
    }
    
    .tenant-meta-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    /* Current Booking Card */
    .current-booking-card {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .booking-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.25rem;
        border-bottom: 3px solid #e2e8f0;
    }
    
    .booking-card-title {
        font-size: 1.75rem;
        font-weight: 900;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin: 0;
    }
    
    .booking-status-badge {
        padding: 0.625rem 1.25rem;
        border-radius: 10px;
        font-weight: 800;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .booking-status-badge.approved {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    
    .booking-status-badge.checked_in {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .booking-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    
    .booking-detail-item {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 16px;
        border: 2px solid #e2e8f0;
    }
    
    .booking-detail-label {
        display: block;
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }
    
    .booking-detail-value {
        display: block;
        font-size: 1.25rem;
        color: #1e293b;
        font-weight: 800;
    }
    
    .no-booking-card {
        text-align: center;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        border-radius: 20px;
        border: 3px dashed #cbd5e0;
    }
    
    .no-booking-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .no-booking-text {
        font-size: 1.25rem;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 1.5rem;
    }
    
    /* Profile Layout */
    .tenant-profile-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    /* Tab System */
    .profile-tabs {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
        border-bottom: 3px solid #e2e8f0;
        overflow-x: auto;
    }
    
    .profile-tab {
        padding: 1rem 2rem;
        background: transparent;
        border: none;
        color: #64748b;
        font-weight: 700;
        font-size: 1.05rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border-bottom: 4px solid transparent;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .profile-tab:hover {
        color: #1e293b;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }
    
    .profile-tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    }
    
    .tab-content-profile {
        display: none;
    }
    
    .tab-content-profile.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    /* Form Card */
    .profile-form-card {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .profile-form-card h2 {
        margin: 0 0 2rem 0;
        font-size: 1.75rem;
        font-weight: 900;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-bottom: 1.25rem;
        border-bottom: 3px solid #e2e8f0;
    }
    
    .form-section-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
    }
    
    /* Enhanced Form Styles */
    .form-grid-two {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    
    .form-group-enhanced {
        margin-bottom: 1.75rem;
    }
    
    .form-label-enhanced {
        display: block;
        font-weight: 700;
        color: #475569;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    
    .form-input-enhanced {
        width: 100%;
        padding: 1rem 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
        font-family: inherit;
    }
    
    .form-input-enhanced:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .form-input-enhanced:disabled {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }
    
    /* Photo Upload Section */
    .photo-upload-section {
        display: flex;
        align-items: center;
        gap: 2.5rem;
        padding: 2.5rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        border-radius: 20px;
        border: 3px dashed #cbd5e0;
    }
    
    .current-photo-display {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid #667eea;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }
    
    .photo-placeholder-display {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        font-weight: 900;
        color: white;
        border: 5px solid #e2e8f0;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }
    
    .upload-instructions h3 {
        margin: 0 0 0.75rem 0;
        font-size: 1.5rem;
        color: #1e293b;
        font-weight: 800;
    }
    
    .upload-instructions p {
        margin: 0 0 1.25rem 0;
        color: #64748b;
        font-size: 1rem;
        line-height: 1.6;
    }
    
    .file-input-wrapper {
        position: relative;
        display: inline-block;
    }
    
    .file-input-label {
        padding: 0.875rem 1.75rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .file-input-label:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }
    
    .file-name-display {
        margin-top: 0.75rem;
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
    }
    
    /* Buttons */
    .btn-profile {
        padding: 1rem 2rem;
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
    
    .btn-profile.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }
    
    .btn-profile.primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(102, 126, 234, 0.4);
    }
    
    .btn-profile.secondary {
        background: white;
        color: #64748b;
        border-color: #e2e8f0;
    }
    
    .btn-profile.secondary:hover {
        border-color: #cbd5e0;
        color: #1e293b;
    }
    
    .btn-profile.outline {
        background: white;
        color: #667eea;
        border-color: #667eea;
    }
    
    .btn-profile.outline:hover {
        background: #667eea;
        color: white;
    }
    
    /* Security Box */
    .security-info-box {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        border: 2px solid #667eea;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .security-info-box h4 {
        margin: 0 0 1rem 0;
        color: #667eea;
        font-weight: 800;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .security-info-box ul {
        margin: 0;
        padding-left: 1.75rem;
        color: #475569;
    }
    
    .security-info-box li {
        margin-bottom: 0.75rem;
        font-weight: 600;
    }
    
    /* Payment History */
    .payment-history-card {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .payment-history-card h3 {
        margin: 0 0 1.5rem 0;
        font-size: 1.5rem;
        font-weight: 900;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .payment-item {
        padding: 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 14px;
        border: 2px solid #e2e8f0;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .payment-item:hover {
        transform: translateX(5px);
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    
    .payment-item:last-child {
        margin-bottom: 0;
    }
    
    .payment-amount {
        font-size: 1.5rem;
        font-weight: 900;
        color: #10b981;
        margin-bottom: 0.5rem;
    }
    
    .payment-date {
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .payment-status {
        display: inline-block;
        padding: 0.375rem 0.875rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 700;
        margin-top: 0.5rem;
    }
    
    .payment-status.completed {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    
    .payment-status.pending {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }
    
    .empty-payments {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #94a3b8;
    }
    
    .empty-payments-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .empty-payments-text {
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .tenant-profile-layout {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .tenant-header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .tenant-info-section h1 {
            font-size: 2rem;
            flex-direction: column;
        }
        
        .form-grid-two {
            grid-template-columns: 1fr;
        }
        
        .photo-upload-section {
            flex-direction: column;
            text-align: center;
        }
        
        .booking-details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="tenant-profile-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-tenant">
            <a href="<?php echo TENANT_URL; ?>/portal">Dashboard</a>
            <span>→</span>
            <span>My Profile</span>
        </nav>
        
        <!-- Profile Header -->
        <div class="tenant-profile-header">
            <div class="tenant-header-content">
                <div class="tenant-photo-section">
    <?php 
    $profile_pic = $tenant['profile_picture'] ?? null;
    $pic_path = $profile_pic ? __DIR__ . '/../uploads/' . $profile_pic : null;
    $pic_exists = $pic_path && file_exists($pic_path);
    ?>
    <?php if ($pic_exists): ?>
        <img src="../uploads/<?php echo htmlspecialchars($profile_pic); ?>" 
             alt="Profile Photo" class="tenant-photo-large">
    <?php else: ?>
        <div class="tenant-photo-placeholder">
            <?php echo strtoupper(substr($tenant['first_name'], 0, 1)); ?>
        </div>
    <?php endif; ?>
    <div class="tenant-verified-badge">✓</div>
</div>
                <div class="tenant-info-section">
                    <h1>
                        <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                        <span class="tenant-role-badge">TENANT</span>
                    </h1>
                    <div class="tenant-meta-info">
                        <span class="tenant-meta-item">
                            📧 <?php echo htmlspecialchars($tenant['email']); ?>
                        </span>
                        <?php if ($tenant['phone']): ?>
                        <span class="tenant-meta-item">
                            📱 <?php echo htmlspecialchars($tenant['phone']); ?>
                        </span>
                        <?php endif; ?>
                        <span class="tenant-meta-item">
                            📅 Member since <?php echo date('F Y', strtotime($tenant['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Current Booking -->
        <div class="current-booking-card">
            <?php if ($active_booking): ?>
                <div class="booking-card-header">
                    <h2 class="booking-card-title">
                        <span>🏠</span>
                        Current Accommodation
                    </h2>
                    <span class="booking-status-badge <?php echo $active_booking['status']; ?>">
                        <?php echo ucfirst($active_booking['status']); ?>
                    </span>
                </div>
                
                <div class="booking-details-grid">
                    <div class="booking-detail-item">
                        <span class="booking-detail-label">Room Number</span>
                        <span class="booking-detail-value"><?php echo htmlspecialchars($active_booking['room_number']); ?></span>
                    </div>
                    <div class="booking-detail-item">
                        <span class="booking-detail-label">Room Type</span>
                        <span class="booking-detail-value"><?php echo ucfirst($active_booking['room_type']); ?></span>
                    </div>
                    <div class="booking-detail-item">
                        <span class="booking-detail-label">Check-In Date</span>
                        <span class="booking-detail-value"><?php echo date('M d, Y', strtotime($active_booking['check_in_date'])); ?></span>
                    </div>
                    <div class="booking-detail-item">
                        <span class="booking-detail-label">Check-Out Date</span>
                        <span class="booking-detail-value"><?php echo date('M d, Y', strtotime($active_booking['check_out_date'])); ?></span>
                    </div>
                    <div class="booking-detail-item">
                        <span class="booking-detail-label">Monthly Rent</span>
                        <span class="booking-detail-value">₱<?php echo number_format($active_booking['price'], 2); ?></span>
                    </div>
                    <div class="booking-detail-item">
                        <span class="booking-detail-label">Duration</span>
                        <span class="booking-detail-value">
                            <?php
                                $start = new DateTime($active_booking['check_in_date']);
                                $end = new DateTime($active_booking['check_out_date']);
                                $diff = $start->diff($end);
                                echo $diff->days . ' days';
                            ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-booking-card">
                    <div class="no-booking-icon">🏠</div>
                    <div class="no-booking-text">You don't have an active booking</div>
                    <a href="<?php echo PUBLIC_URL; ?>/rooms" class="btn-profile primary">
                        <span>🔍</span>
                        <span>Browse Available Rooms</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tenant-profile-layout">
            <!-- Left Column - Profile Settings -->
            <div>
                <!-- Tab Navigation -->
                <div class="profile-tabs">
                    <button class="profile-tab active" onclick="switchProfileTab('personal')">
                        <span>👤</span>
                        <span>Personal Info</span>
                    </button>
                    <button class="profile-tab" onclick="switchProfileTab('photo')">
                        <span>📸</span>
                        <span>Profile Photo</span>
                    </button>
                    <button class="profile-tab" onclick="switchProfileTab('security')">
                        <span>🔒</span>
                        <span>Security</span>
                    </button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personal-tab" class="tab-content-profile active">
                    <div class="profile-form-card">
                        <h2>
                            <span class="form-section-icon">👤</span>
                            Personal Information
                        </h2>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="form-grid-two">
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">First Name *</label>
                                    <input type="text" name="first_name" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($tenant['first_name']); ?>" required>
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Last Name *</label>
                                    <input type="text" name="last_name" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($tenant['last_name']); ?>" required>
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Email Address *</label>
                                    <input type="email" name="email" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($tenant['email']); ?>" required>
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Phone Number</label>
                                    <input type="tel" name="phone" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($tenant['phone'] ?? ''); ?>" 
                                           placeholder="+1234567890">
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Emergency Contact Name</label>
                                    <input type="text" name="emergency_contact" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($tenant['emergency_contact'] ?? ''); ?>" 
                                           placeholder="Full name">
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Emergency Contact Phone</label>
                                    <input type="tel" name="emergency_phone" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($tenant['emergency_phone'] ?? ''); ?>" 
                                           placeholder="+1234567890">
                                </div>
                            </div>
                            
                            <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                                <a href="<?php echo TENANT_URL; ?>/portal" class="btn-profile secondary">Cancel</a>
                                <button type="submit" name="update_profile" class="btn-profile primary">
                                    <span>💾</span>
                                    <span>Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Profile Photo Tab -->
                <div id="photo-tab" class="tab-content-profile">
                    <div class="profile-form-card">
                        <h2>
                            <span class="form-section-icon">📸</span>
                            Profile Photo
                        </h2>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="photo-upload-section">
    <?php if ($pic_exists): ?>
        <img src="../uploads/<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" 
             alt="Current Photo" class="current-photo-display">
    <?php else: ?>
        <div class="photo-placeholder-display">
            <?php echo strtoupper(substr($tenant['first_name'], 0, 1)); ?>
        </div>
    <?php endif; ?>
                                
                                <div class="upload-instructions">
                                    <h3>Upload New Photo</h3>
                                    <p>
                                        • JPG, JPEG, or PNG format<br>
                                        • Maximum file size: 5MB<br>
                                        • Recommended: 500x500px or larger
                                    </p>
                                    
                                    <div class="file-input-wrapper">
                                        <input type="file" name="profile_picture" id="tenant_photo" 
                                               class="file-input-hidden" accept="image/*"
                                               onchange="updateTenantFileName(this)">
                                        <label for="tenant_photo" class="file-input-label">
                                            Choose Photo
                                        </label>
                                    </div>
                                    <div id="tenantFileName" class="file-name-display"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                                <a href="<?php echo TENANT_URL; ?>/portal" class="btn-profile secondary">Cancel</a>
                                <button type="submit" name="update_photo" class="btn-profile primary">
                                    <span>⬆️</span>
                                    <span>Upload Photo</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="security-tab" class="tab-content-profile">
                    <div class="profile-form-card">
                        <h2>
                            <span class="form-section-icon">🔒</span>
                            Change Password
                        </h2>
                        
                        <div class="security-info-box">
                            <h4>🛡️ Password Security Tips</h4>
                            <ul>
                                <li>Use at least 8 characters</li>
                                <li>Include uppercase and lowercase letters</li>
                                <li>Add numbers and special characters</li>
                                <li>Don't use personal information</li>
                                <li>Change your password regularly</li>
                            </ul>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced">Current Password *</label>
                                <input type="password" name="current_password" class="form-input-enhanced" 
                                       required placeholder="Enter your current password">
                            </div>
                            
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced">New Password *</label>
                                <input type="password" name="new_password" class="form-input-enhanced" 
                                       required placeholder="Enter new password (min. 8 characters)">
                            </div>
                            
                            <div class="form-group-enhanced">
                                <label class="form-label-enhanced">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-input-enhanced" 
                                       required placeholder="Re-enter new password">
                            </div>
                            
                            <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                                <a href="<?php echo TENANT_URL; ?>/portal" class="btn-profile secondary">Cancel</a>
                                <button type="submit" name="change_password" class="btn-profile primary">
                                    <span>🔐</span>
                                    <span>Change Password</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Payment History -->
            <div>
                <div class="payment-history-card">
                    <h3>
                        <span>💳</span>
                        Payment History
                    </h3>
                    
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-amount">
                                    ₱<?php echo number_format($payment['amount'], 2); ?>
                                </div>
                                <div class="payment-date">
                                    📅 <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </div>
                                <span class="payment-status <?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 1.5rem; text-align: center;">
                            <a href="<?php echo TENANT_URL; ?>/payments" class="btn-profile outline">
                                <span>📋</span>
                                <span>View All Payments</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-payments">
                            <div class="empty-payments-icon">💳</div>
                            <div class="empty-payments-text">No payment history yet</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchProfileTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content-profile').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.profile-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.closest('.profile-tab').classList.add('active');
}

function updateTenantFileName(input) {
    const fileNameDiv = document.getElementById('tenantFileName');
    if (input.files && input.files[0]) {
        fileNameDiv.textContent = '📎 Selected: ' + input.files[0].name;
        fileNameDiv.style.color = '#10b981';
        fileNameDiv.style.fontWeight = '700';
    } else {
        fileNameDiv.textContent = '';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>