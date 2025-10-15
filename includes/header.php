<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Dormitory Management System</title>
    <link rel="stylesheet" href="/dormitory-management-system/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/dormitory-management-system/">Dormitory Management</a>
            </div>
            <ul class="nav-menu">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li><a href="/dormitory-management-system/admin/dashboard.php">Dashboard</a></li>
                    <li><a href="/dormitory-management-system/admin/tenants.php">Tenants</a></li>
                    <li><a href="/dormitory-management-system/admin/rooms.php">Rooms</a></li>
                    <li><a href="/dormitory-management-system/admin/payments.php">Payments</a></li>
                    <li><a href="/dormitory-management-system/admin/bookings.php">Bookings</a></li>
                    <li><a href="/dormitory-management-system/admin/announcements.php">Announcements</a></li>
                    <li><a href="/dormitory-management-system/admin/reports.php">Reports</a></li>
                    <li><a href="/dormitory-management-system/logout.php">Logout</a></li>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'tenant'): ?>
                    <li><a href="/dormitory-management-system/tenant/portal.php">Portal</a></li>
                    <li><a href="/dormitory-management-system/tenant/profile.php">Profile</a></li>
                    <li><a href="/dormitory-management-system/public/rooms.php">Browse Rooms</a></li>
                    <li><a href="/dormitory-management-system/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="/dormitory-management-system/">Home</a></li>
                    <li><a href="/dormitory-management-system/public/rooms.php">Rooms</a></li>
                    <li><a href="/dormitory-management-system/login.php">Login</a></li>
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
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="main-content">
