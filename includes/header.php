<?php
// filepath: includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

// Get user profile picture if logged in
$user_profile_pic = null;
$has_user_pic = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $pic_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $pic_query->bind_param("i", $user_id);
    $pic_query->execute();
    $pic_result = $pic_query->get_result();
    if ($pic_row = $pic_result->fetch_assoc()) {
        $user_profile_pic = $pic_row['profile_picture'];
        $pic_path = __DIR__ . '/../uploads/' . $user_profile_pic;
        $has_user_pic = $user_profile_pic && file_exists($pic_path);
    }
    $pic_query->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Dormitory Management System</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f7 100%);
            margin: 0;
            padding: 0;
            color: #1e293b;
            min-height: 100vh;
        }
        
        /* Enhanced Navbar with Glassmorphism */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04),
                        0 8px 32px rgba(31, 38, 135, 0.08);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.6);
        }
        
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2.5rem;
            height: 75px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Brand Logo with Glow Effect */
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .nav-brand .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3),
                        0 0 20px rgba(118, 75, 162, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .nav-brand .logo-icon::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, transparent 100%);
            top: 0;
            left: -100%;
            transition: left 0.5s ease;
        }
        
        .nav-brand:hover .logo-icon::before {
            left: 100%;
        }
        
        .nav-brand .logo-icon svg {
            width: 24px;
            height: 24px;
            fill: white;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }
        
        .nav-brand a {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }
        
        .nav-brand a:hover {
            color: #667eea;
            text-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }
        
        .nav-brand .brand-text {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }
        
        .nav-brand .brand-title {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .nav-brand .brand-subtitle {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        
        /* Navigation Menu with Refined Style */
        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 0.25rem;
            align-items: center;
        }
        
        .nav-menu li {
            position: relative;
        }
        
        .nav-menu li a {
            padding: 0.7rem 1.2rem;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            letter-spacing: -0.01em;
        }
        
        .nav-menu li a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transform: translateX(-50%);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .nav-menu li a:hover {
            color: #667eea;
            background: linear-gradient(135deg, 
                rgba(102, 126, 234, 0.08) 0%, 
                rgba(118, 75, 162, 0.08) 100%);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.12);
            transform: translateY(-1px);
        }
        
        .nav-menu li a:hover::before {
            width: 80%;
        }
        
        .nav-menu li a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3),
                        0 0 20px rgba(118, 75, 162, 0.2);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }
        
        .nav-menu li a.active::before {
            display: none;
        }
        
        /* User Profile Button - Enhanced with Profile Picture */
        .user-profile-btn {
            padding: 0.5rem 1rem 0.5rem 0.5rem !important;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
            border: 1.5px solid #e2e8f0 !important;
            color: #1e293b !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
            border-radius: 50px !important;
        }
        
        .user-profile-btn:hover {
            border-color: #667eea !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2) !important;
            transform: translateY(-2px) !important;
        }
        
        .user-profile-btn::before {
            display: none !important;
        }
        
        /* User Avatar - Now supports images */
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid white;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar-initial {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Admin avatar special style */
        .user-avatar.admin-avatar {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-color: #f59e0b;
        }
        
        /* Status indicator */
        .user-status {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }
        
        .avatar-wrapper {
            position: relative;
        }
        
        /* Logout Button Special Style */
        .nav-menu li a.logout-btn {
            color: #dc2626 !important;
            border: 1.5px solid transparent;
        }
        
        .nav-menu li a.logout-btn:hover {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%) !important;
            border-color: #fca5a5 !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15) !important;
        }
        
        /* Flash Messages Enhanced */
        .flash-message {
            padding: 1.1rem 0;
            margin: 0;
            font-weight: 500;
            animation: slideInDown 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
            position: relative;
        }
        
        .flash-message .container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2.5rem;
        }
        
        .flash-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .flash-message.flash-success {
            background: linear-gradient(135deg, 
                rgba(212, 244, 221, 0.95) 0%, 
                rgba(198, 246, 213, 0.95) 100%);
            color: #065f46;
            border-bottom: 2px solid #10b981;
        }
        
        .flash-message.flash-success .flash-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .flash-message.flash-error {
            background: linear-gradient(135deg, 
                rgba(254, 215, 215, 0.95) 0%, 
                rgba(254, 202, 202, 0.95) 100%);
            color: #991b1b;
            border-bottom: 2px solid #ef4444;
        }
        
        .flash-message.flash-error .flash-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .flash-message.flash-warning {
            background: linear-gradient(135deg, 
                rgba(254, 243, 199, 0.95) 0%, 
                rgba(253, 230, 138, 0.95) 100%);
            color: #78350f;
            border-bottom: 2px solid #f59e0b;
        }
        
        .flash-message.flash-warning .flash-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .flash-message.flash-info {
            background: linear-gradient(135deg, 
                rgba(219, 234, 254, 0.95) 0%, 
                rgba(191, 219, 254, 0.95) 100%);
            color: #1e3a8a;
            border-bottom: 2px solid #3b82f6;
        }
        
        .flash-message.flash-info .flash-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .flash-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        
        .flash-content strong {
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: -0.01em;
        }
        
        .flash-close {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.06);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: currentColor;
            opacity: 0.6;
            transition: all 0.2s ease;
        }
        
        .flash-close:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.1);
            transform: scale(1.1);
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-100%);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Main Content Area */
        .main-content {
            min-height: calc(100vh - 145px);
            padding: 0;
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            font-size: 1.3rem;
            cursor: pointer;
            color: #475569;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        
        .mobile-menu-toggle:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .navbar .container {
                padding: 0 1.5rem;
            }
            
            .nav-menu {
                display: none;
                position: absolute;
                top: 75px;
                left: 0;
                right: 0;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(20px);
                flex-direction: column;
                padding: 1rem;
                gap: 0.5rem;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                border-top: 1px solid rgba(0, 0, 0, 0.06);
                animation: slideInDown 0.3s ease-out;
            }
            
            .nav-menu.show {
                display: flex;
            }
            
            .nav-menu li {
                width: 100%;
            }
            
            .nav-menu li a {
                width: 100%;
                justify-content: flex-start;
                padding: 1rem 1.25rem;
            }
            
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .user-profile-btn {
                border-radius: 12px !important;
            }
        }
        
        @media (max-width: 768px) {
            .navbar .container {
                padding: 0 1rem;
                height: 65px;
            }
            
            .nav-brand .brand-title {
                font-size: 1.1rem;
            }
            
            .nav-brand .brand-subtitle {
                font-size: 0.65rem;
            }
            
            .nav-brand .logo-icon {
                width: 38px;
                height: 38px;
            }
            
            .flash-message .container {
                padding: 0 1rem;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
            }
            
            .user-name {
                max-width: 100px;
            }
        }
        
        /* SVG Icons */
        .nav-icon {
            width: 18px;
            height: 18px;
            opacity: 0.85;
        }
        
        .nav-menu li a:hover .nav-icon,
        .nav-menu li a.active .nav-icon {
            opacity: 1;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3L2 9v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V9l-10-6zM12 5.84L19 10v10H5V10l7-4.16zM7 12h10v2H7v-2zm0 4h10v2H7v-2z"/>
                    </svg>
                </div>
                <a href="<?php echo BASE_URL; ?>/">
                    <div class="brand-text">
                        <div class="brand-title">DormitoryMS</div>
                        <div class="brand-subtitle">Management System</div>
                    </div>
                </a>
            </div>
            
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span>☰</span>
            </button>
            
            <ul class="nav-menu" id="navMenu">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li><a href="<?php echo ADMIN_URL; ?>/dashboard" <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                        Dashboard
                    </a></li>
                    <li><a href="<?php echo ADMIN_URL; ?>/tenants" <?php echo basename($_SERVER['PHP_SELF']) === 'tenants.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Tenants
                    </a></li>
                    <li><a href="<?php echo ADMIN_URL; ?>/rooms" <?php echo basename($_SERVER['PHP_SELF']) === 'rooms.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9.3V4h-3v2.6L12 3 2 12h3v8h6v-6h2v6h6v-8h3l-3-2.7zM17 18h-2v-6H9v6H7v-7.81l5-4.5 5 4.5V18z"/></svg>
                        Rooms
                    </a></li>
                    <li><a href="<?php echo ADMIN_URL; ?>/bookings" <?php echo basename($_SERVER['PHP_SELF']) === 'bookings.php' || basename($_SERVER['PHP_SELF']) === 'view_booking.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                        Bookings
                    </a></li>
                    <li><a href="<?php echo ADMIN_URL; ?>/payments" <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        Payments
                    </a></li>
                    <li><a href="<?php echo ADMIN_URL; ?>/announcements" <?php echo basename($_SERVER['PHP_SELF']) === 'announcements.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                        Announcements
                    </a></li>
                    <li><a href="<?php echo ADMIN_URL; ?>/reports" <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        Reports
                    </a></li>
                    <li>
                        <a href="<?php echo ADMIN_URL; ?>/profile" class="user-profile-btn">
                            <div class="avatar-wrapper">
                                <div class="user-avatar admin-avatar">
                                    <?php if ($has_user_pic): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/<?php echo htmlspecialchars($user_profile_pic); ?>?v=<?php echo time(); ?>" alt="Profile">
                                    <?php else: ?>
                                        <span class="user-avatar-initial"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="user-status"></span>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                        </a>
                    </li>
                    <li><a href="<?php echo LOGOUT_URL; ?>" class="logout-btn">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                        Logout
                    </a></li>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'tenant'): ?>
                    <li><a href="<?php echo TENANT_URL; ?>/portal" <?php echo basename($_SERVER['PHP_SELF']) === 'portal.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                        Portal
                    </a></li>
                    <li><a href="<?php echo PUBLIC_URL; ?>/rooms" <?php echo basename($_SERVER['PHP_SELF']) === 'rooms.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        Browse Rooms
                    </a></li>
                    <li><a href="<?php echo TENANT_URL; ?>/bookings" <?php echo basename($_SERVER['PHP_SELF']) === 'bookings.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                        My Bookings
                    </a></li>
                    <li><a href="<?php echo TENANT_URL; ?>/payments" <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        Payments
                    </a></li>
                    <li>
                        <a href="<?php echo TENANT_URL; ?>/profile" class="user-profile-btn">
                            <div class="avatar-wrapper">
                                <div class="user-avatar">
                                    <?php if ($has_user_pic): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/<?php echo htmlspecialchars($user_profile_pic); ?>?v=<?php echo time(); ?>" alt="Profile">
                                    <?php else: ?>
                                        <span class="user-avatar-initial"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'T', 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="user-status"></span>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Tenant'); ?></span>
                        </a>
                    </li>
                    <li><a href="<?php echo LOGOUT_URL; ?>" class="logout-btn">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                        Logout
                    </a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>/" <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'public') !== false ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                        Home
                    </a></li>
                    <li><a href="<?php echo PUBLIC_URL; ?>/rooms" <?php echo basename($_SERVER['PHP_SELF']) === 'rooms.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        Rooms
                    </a></li>
                    <li><a href="<?php echo LOGIN_URL; ?>" <?php echo basename($_SERVER['PHP_SELF']) === 'login.php' ? 'class="active"' : ''; ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/></svg>
                        Login
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    
    <?php
    // Display flash messages
    $flash = get_flash_message();
    if ($flash):
    ?>
    <div class="flash-message flash-<?php echo htmlspecialchars($flash['type']); ?>">
        <div class="container">
            <div class="flash-icon">
                <?php 
                if ($flash['type'] === 'success') echo '✓';
                elseif ($flash['type'] === 'error') echo '✕';
                elseif ($flash['type'] === 'warning') echo '!';
                else echo 'i';
                ?>
            </div>
            <div class="flash-content">
                <strong><?php echo htmlspecialchars($flash['message']); ?></strong>
            </div>
            <button class="flash-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="main-content">
    
    <script>
        function toggleMobileMenu() {
            document.getElementById('navMenu').classList.toggle('show');
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('navMenu');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (menu.classList.contains('show') && 
                !menu.contains(event.target) && 
                !toggle.contains(event.target)) {
                menu.classList.remove('show');
            }
        });
        
        // Auto-hide flash messages after 6 seconds
        setTimeout(() => {
            const flash = document.querySelector('.flash-message');
            if (flash) {
                flash.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                flash.style.opacity = '0';
                flash.style.transform = 'translateY(-20px)';
                setTimeout(() => flash.remove(), 500);
            }
        }, 6000);
    </script>