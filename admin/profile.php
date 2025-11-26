<?php
// filepath: admin/profile.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Admin Profile';
$admin_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile']) && verify_csrf_token($_POST['csrf_token'])) {
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        
        $errors = [];
        
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        // Check email uniqueness
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $admin_id);
        $email_check->execute();
        if ($email_check->get_result()->num_rows > 0) {
            $errors[] = "Email already in use";
        }
        $email_check->close();
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $admin_id);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                set_flash_message('Profile updated successfully! 🎉', 'success');
                redirect("profile.php");
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
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_result = upload_file($_FILES['profile_photo'], ['jpg', 'jpeg', 'png'], 5242880);
            
            if ($upload_result['success']) {
                $photo_path = 'profiles/' . $upload_result['filename'];
                
                // Delete old photo
                $old_query = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
                $old_query->bind_param("i", $admin_id);
                $old_query->execute();
                $old_photo = $old_query->get_result()->fetch_assoc()['profile_photo'];
                $old_query->close();
                
                if ($old_photo && file_exists('../uploads/' . $old_photo)) {
                    unlink('../uploads/' . $old_photo);
                }
                
                // Update database
                $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $stmt->bind_param("si", $photo_path, $admin_id);
                
                if ($stmt->execute()) {
                    set_flash_message('Profile photo updated successfully! 📸', 'success');
                    redirect("profile.php");
                }
                $stmt->close();
            } else {
                set_flash_message($upload_result['message'], 'error');
            }
        } else {
            set_flash_message('Please select a photo to upload', 'error');
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
        $stmt->bind_param("i", $admin_id);
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
            $stmt->bind_param("si", $hashed, $admin_id);
            
            if ($stmt->execute()) {
                set_flash_message('Password changed successfully! 🔒', 'success');
                redirect("profile.php");
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

// Get admin data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get system statistics
$stats = [
    'total_tenants' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tenant'")->fetch_assoc()['count'],
    'total_rooms' => $conn->query("SELECT COUNT(*) as count FROM rooms")->fetch_assoc()['count'],
    'total_bookings' => $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'],
    'pending_bookings' => $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")->fetch_assoc()['count']
];

// Get activity log (recent actions)
$recent_activity = $conn->query("
    SELECT 
        'booking' as type,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        b.status,
        b.created_at as action_date
    FROM bookings b
    JOIN users u ON b.tenant_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<style>
    .admin-profile-page {
        animation: fadeIn 0.5s ease-out;
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
    
    /* Profile Header - Admin Version */
    .admin-profile-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        border-radius: 24px;
        padding: 3rem 2.5rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(30, 41, 59, 0.4);
    }
    
    .admin-profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(102, 126, 234, 0.2);
        border-radius: 50%;
    }
    
    .admin-profile-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: rgba(118, 75, 162, 0.15);
        border-radius: 50%;
    }
    
    .header-content-admin {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 2.5rem;
    }
    
    .admin-photo-section {
        position: relative;
    }
    
    .admin-photo-large {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid rgba(102, 126, 234, 0.5);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }
    
    .admin-photo-placeholder {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4.5rem;
        font-weight: 900;
        border: 5px solid rgba(102, 126, 234, 0.5);
        box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4);
    }
    
    .admin-badge-overlay {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 4px solid #1e293b;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.5);
    }
    
    .admin-info-section h1 {
        margin: 0 0 0.75rem 0;
        font-size: 2.75rem;
        font-weight: 900;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .admin-role-badge {
        display: inline-block;
        padding: 0.5rem 1.25rem;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .admin-meta {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        font-size: 1.1rem;
        opacity: 0.95;
        margin-top: 1rem;
    }
    
    .admin-meta-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .admin-since-badge {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        font-weight: 700;
        margin-top: 1.25rem;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Admin Stats Grid */
    .admin-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .admin-stat-card {
        background: white;
        padding: 2.25rem;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .admin-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: var(--stat-gradient);
        border-radius: 0 0 0 100%;
        opacity: 0.1;
    }
    
    .admin-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        border-color: var(--stat-color);
    }
    
    .admin-stat-card.tenants {
        --stat-color: #3b82f6;
        --stat-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }
    
    .admin-stat-card.rooms {
        --stat-color: #10b981;
        --stat-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .admin-stat-card.bookings {
        --stat-color: #f59e0b;
        --stat-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .admin-stat-card.pending {
        --stat-color: #ef4444;
        --stat-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .admin-stat-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 1.25rem;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        background: var(--stat-gradient);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 1;
    }
    
    .admin-stat-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--stat-color);
        margin-bottom: 0.5rem;
        text-align: center;
        position: relative;
        z-index: 1;
    }
    
    .admin-stat-label {
        font-size: 1rem;
        color: #64748b;
        font-weight: 700;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: relative;
        z-index: 1;
    }
    
    /* Layout Grid */
    .admin-profile-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }
    
    /* Tab Navigation */
    .admin-tabs {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
        border-bottom: 3px solid #e2e8f0;
        overflow-x: auto;
    }
    
    .admin-tab {
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
    
    .admin-tab:hover {
        color: #1e293b;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.05) 0%, rgba(51, 65, 85, 0.05) 100%);
    }
    
    .admin-tab.active {
        color: #1e293b;
        border-bottom-color: #1e293b;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.1) 0%, rgba(51, 65, 85, 0.1) 100%);
    }
    
    .tab-content-admin {
        display: none;
    }
    
    .tab-content-admin.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    /* Form Cards - Admin Style */
    .admin-form-card {
        background: white;
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .admin-form-card h2 {
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
    
    .admin-section-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
    }
    
    /* Activity Feed */
    .activity-feed {
        background: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 2px solid #e2e8f0;
    }
    
    .activity-feed h3 {
        margin: 0 0 1.5rem 0;
        font-size: 1.5rem;
        font-weight: 900;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .activity-item {
        padding: 1.25rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 14px;
        border: 2px solid #e2e8f0;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .activity-item:hover {
        transform: translateX(5px);
        border-color: #1e293b;
        box-shadow: 0 4px 12px rgba(30, 41, 59, 0.15);
    }
    
    .activity-item:last-child {
        margin-bottom: 0;
    }
    
    .activity-user {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .activity-action {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }
    
    .activity-time {
        font-size: 0.875rem;
        color: #94a3b8;
        font-weight: 600;
    }
    
    /* Photo Upload - Admin Style */
    .admin-photo-upload {
        display: flex;
        align-items: center;
        gap: 2.5rem;
        padding: 2.5rem;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.05) 0%, rgba(51, 65, 85, 0.05) 100%);
        border-radius: 20px;
        border: 3px dashed #cbd5e0;
    }
    
    .admin-current-photo {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid #1e293b;
        box-shadow: 0 6px 20px rgba(30, 41, 59, 0.2);
    }
    
    .admin-photo-placeholder-small {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        font-weight: 900;
        color: white;
        border: 5px solid #e2e8f0;
        box-shadow: 0 6px 20px rgba(30, 41, 59, 0.2);
    }
    
    .admin-upload-info h3 {
        margin: 0 0 0.75rem 0;
        font-size: 1.5rem;
        color: #1e293b;
        font-weight: 800;
    }
    
    .admin-upload-info p {
        margin: 0 0 1.25rem 0;
        color: #64748b;
        font-size: 1rem;
        line-height: 1.6;
    }
    
    /* Buttons - Admin Style */
    .btn-admin {
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
    
    .btn-admin.primary {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(30, 41, 59, 0.3);
    }
    
    .btn-admin.primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(30, 41, 59, 0.4);
    }
    
    .btn-admin.secondary {
        background: white;
        color: #64748b;
        border-color: #e2e8f0;
    }
    
    .btn-admin.secondary:hover {
        border-color: #1e293b;
        color: #1e293b;
    }
    
    .btn-admin.outline {
        background: white;
        color: #1e293b;
        border-color: #1e293b;
    }
    
    .btn-admin.outline:hover {
        background: #1e293b;
        color: white;
    }
    
    /* Security Info Box - Admin Style */
    .admin-security-box {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.05) 0%, rgba(51, 65, 85, 0.05) 100%);
        border: 2px solid #1e293b;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .admin-security-box h4 {
        margin: 0 0 1rem 0;
        color: #1e293b;
        font-weight: 800;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .admin-security-box ul {
        margin: 0;
        padding-left: 1.75rem;
        color: #475569;
    }
    
    .admin-security-box li {
        margin-bottom: 0.75rem;
        font-weight: 600;
    }
    
    /* Account Info Grid */
    .admin-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .admin-info-field {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 14px;
        border: 2px solid #e2e8f0;
    }
    
    .admin-info-label {
        display: block;
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }
    
    .admin-info-value {
        display: block;
        font-size: 1.1rem;
        color: #1e293b;
        font-weight: 800;
    }
    
    /* Status Badge */
    .admin-status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .admin-status-badge.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    
    /* Form Enhancements */
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
        border-color: #1e293b;
        box-shadow: 0 0 0 4px rgba(30, 41, 59, 0.1);
    }
    
    .form-input-enhanced:disabled {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }
    
    .file-input-wrapper {
        position: relative;
        display: inline-block;
    }
    
    .file-input-hidden {
        display: none;
    }
    
    .file-input-label {
        padding: 0.875rem 1.75rem;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        border-radius: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        box-shadow: 0 4px 12px rgba(30, 41, 59, 0.3);
    }
    
    .file-input-label:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(30, 41, 59, 0.4);
    }
    
    .file-name-display {
        margin-top: 0.75rem;
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 600;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .admin-profile-layout {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .header-content-admin {
            flex-direction: column;
            text-align: center;
        }
        
        .admin-info-section h1 {
            font-size: 2rem;
            flex-direction: column;
        }
        
        .admin-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .admin-info-grid {
            grid-template-columns: 1fr;
        }
        
        .admin-photo-upload {
            flex-direction: column;
            text-align: center;
        }
        
        .form-grid-two {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-profile-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="dashboard.php">Dashboard</a>
            <span>→</span>
            <span>My Profile</span>
        </nav>
        
        <!-- Profile Header -->
        <div class="admin-profile-header">
            <div class="header-content-admin">
                <div class="admin-photo-section">
                    <?php if ($admin['profile_photo']): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($admin['profile_photo']); ?>" 
                             alt="Admin Profile" class="admin-photo-large">
                    <?php else: ?>
                        <div class="admin-photo-placeholder">
                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="admin-badge-overlay">👑</div>
                </div>
                <div class="admin-info-section">
                    <h1>
                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                        <span class="admin-role-badge">ADMINISTRATOR</span>
                    </h1>
                    <div class="admin-meta">
                        <span class="admin-meta-item">
                            📧 <?php echo htmlspecialchars($admin['email']); ?>
                        </span>
                        <?php if ($admin['phone']): ?>
                        <span class="admin-meta-item">
                            📱 <?php echo htmlspecialchars($admin['phone']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-since-badge">
                        👑 Admin since <?php echo date('F Y', strtotime($admin['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Statistics -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card tenants">
                <div class="admin-stat-icon">👥</div>
                <div class="admin-stat-value"><?php echo number_format($stats['total_tenants']); ?></div>
                <div class="admin-stat-label">Total Tenants</div>
            </div>
            <div class="admin-stat-card rooms">
                <div class="admin-stat-icon">🏠</div>
                <div class="admin-stat-value"><?php echo number_format($stats['total_rooms']); ?></div>
                <div class="admin-stat-label">Total Rooms</div>
            </div>
            <div class="admin-stat-card bookings">
                <div class="admin-stat-icon">📋</div>
                <div class="admin-stat-value"><?php echo number_format($stats['total_bookings']); ?></div>
                <div class="admin-stat-label">All Bookings</div>
            </div>
            <div class="admin-stat-card pending">
                <div class="admin-stat-icon">⏳</div>
                <div class="admin-stat-value"><?php echo number_format($stats['pending_bookings']); ?></div>
                <div class="admin-stat-label">Pending Requests</div>
            </div>
        </div>
        
        <div class="admin-profile-layout">
            <!-- Left Column - Settings -->
            <div>
                <!-- Tab Navigation -->
                <div class="admin-tabs">
                    <button class="admin-tab active" onclick="switchAdminTab('personal')">
                        <span>👤</span>
                        <span>Personal Info</span>
                    </button>
                    <button class="admin-tab" onclick="switchAdminTab('photo')">
                        <span>📸</span>
                        <span>Profile Photo</span>
                    </button>
                    <button class="admin-tab" onclick="switchAdminTab('security')">
                        <span>🔒</span>
                        <span>Security</span>
                    </button>
                </div>
                
                <!-- Personal Information Tab -->
                <div id="personal-tab" class="tab-content-admin active">
                    <div class="admin-form-card">
                        <h2>
                            <span class="admin-section-icon">👤</span>
                            Personal Information
                        </h2>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="form-grid-two">
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">First Name *</label>
                                    <input type="text" name="first_name" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Last Name *</label>
                                    <input type="text" name="last_name" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Email Address *</label>
                                    <input type="email" name="email" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                                
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Phone Number</label>
                                    <input type="tel" name="phone" class="form-input-enhanced" 
                                           value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" 
                                           placeholder="+1234567890">
                                </div>
                            </div>
                            
                            <div class="form-group-enhanced" style="margin-top: 1.5rem;">
                                <label class="form-label-enhanced">Role</label>
                                <input type="text" class="form-input-enhanced" 
                                       value="System Administrator" disabled>
                            </div>
                            
                            <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                                <a href="dashboard.php" class="btn-admin secondary">Cancel</a>
                                <button type="submit" name="update_profile" class="btn-admin primary">
                                    <span>💾</span>
                                    <span>Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Profile Photo Tab -->
                <div id="photo-tab" class="tab-content-admin">
                    <div class="admin-form-card">
                        <h2>
                            <span class="admin-section-icon">📸</span>
                            Profile Photo
                        </h2>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="admin-photo-upload">
                                <?php if ($admin['profile_photo']): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($admin['profile_photo']); ?>" 
                                         alt="Current Photo" class="admin-current-photo">
                                <?php else: ?>
                                    <div class="admin-photo-placeholder-small">
                                        <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="admin-upload-info">
                                    <h3>Upload New Photo</h3>
                                    <p>
                                        • JPG, JPEG, or PNG format<br>
                                        • Maximum file size: 5MB<br>
                                        • Recommended: 500x500px or larger
                                    </p>
                                    
                                    <div class="file-input-wrapper">
                                        <input type="file" name="profile_photo" id="admin_photo" 
                                               class="file-input-hidden" accept="image/*"
                                               onchange="updateAdminFileName(this)">
                                        <label for="admin_photo" class="file-input-label">
                                            Choose Photo
                                        </label>
                                    </div>
                                    <div id="adminFileName" class="file-name-display"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                                <a href="dashboard.php" class="btn-admin secondary">Cancel</a>
                                <button type="submit" name="update_photo" class="btn-admin primary">
                                    <span>⬆️</span>
                                    <span>Upload Photo</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="security-tab" class="tab-content-admin">
                    <div class="admin-form-card">
                        <h2>
                            <span class="admin-section-icon">🔒</span>
                            Change Password
                        </h2>
                        
                        <div class="admin-security-box">
                            <h4>🛡️ Strong Password Guidelines</h4>
                            <ul>
                                <li>Use at least 8 characters</li>
                                <li>Include uppercase and lowercase letters</li>
                                <li>Add numbers and special characters</li>
                                <li>Avoid personal information</li>
                                <li>Use unique passwords for different accounts</li>
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
                                <a href="dashboard.php" class="btn-admin secondary">Cancel</a>
                                <button type="submit" name="change_password" class="btn-admin primary">
                                    <span>🔐</span>
                                    <span>Change Password</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="admin-form-card">
                        <h2>
                            <span class="admin-section-icon">📋</span>
                            Account Information
                        </h2>
                        
                        <div class="admin-info-grid">
                            <div class="admin-info-field">
                                <span class="admin-info-label">Account Status</span>
                                <span class="admin-info-value">
                                    <span class="admin-status-badge active">ACTIVE</span>
                                </span>
                            </div>
                            <div class="admin-info-field">
                                <span class="admin-info-label">Account Type</span>
                                <span class="admin-info-value">System Administrator</span>
                            </div>
                            <div class="admin-info-field">
                                <span class="admin-info-label">Member Since</span>
                                <span class="admin-info-value"><?php echo date('F d, Y', strtotime($admin['created_at'])); ?></span>
                            </div>
                            <div class="admin-info-field">
                                <span class="admin-info-label">Last Login</span>
                                <span class="admin-info-value">
                                    <?php echo $admin['last_login'] ? date('M d, Y g:i A', strtotime($admin['last_login'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <div class="admin-info-field">
                                <span class="admin-info-label">User ID</span>
                                <span class="admin-info-value">#<?php echo str_pad($admin['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="admin-info-field">
                                <span class="admin-info-label">Access Level</span>
                                <span class="admin-info-value">Full System Access</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Activity Feed -->
            <div>
                <div class="activity-feed">
                    <h3>
                        <span>📊</span>
                        Recent Activity
                    </h3>
                    
                    <?php if (!empty($recent_activity)): ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></div>
                                <div class="activity-action">
                                    <?php 
                                        $status_text = [
                                            'pending' => 'submitted a new booking request',
                                            'approved' => 'booking was approved',
                                            'rejected' => 'booking was rejected',
                                            'checked_in' => 'checked in',
                                            'checked_out' => 'checked out',
                                            'cancelled' => 'cancelled booking'
                                        ];
                                        echo $status_text[$activity['status']] ?? 'updated booking';
                                    ?>
                                </div>
                                <div class="activity-time">
                                    ⏰ <?php echo time_elapsed_string($activity['action_date']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item" style="text-align: center; padding: 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">📭</div>
                            <div style="color: #94a3b8; font-weight: 600;">No recent activity</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchAdminTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content-admin').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.admin-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.closest('.admin-tab').classList.add('active');
}

function updateAdminFileName(input) {
    const fileNameDiv = document.getElementById('adminFileName');
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