<?php
// filepath: admin/reports.php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Reports & Analytics';
require_once '../includes/header.php';

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'overview';

// Get occupancy statistics
$total_rooms_result = $conn->query("SELECT COUNT(*) as count FROM rooms");
$total_rooms = $total_rooms_result->fetch_assoc()['count'];

$occupied_rooms_result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'occupied'");
$occupied_rooms = $occupied_rooms_result->fetch_assoc()['count'];

$available_rooms_result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
$available_rooms = $available_rooms_result->fetch_assoc()['count'];

$maintenance_rooms_result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'maintenance'");
$maintenance_rooms = $maintenance_rooms_result->fetch_assoc()['count'];

$occupancy_rate = $total_rooms > 0 ? ($occupied_rooms / $total_rooms) * 100 : 0;

// Monthly revenue (last 12 months) - Store the query for reuse
$monthly_revenue_query = "
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        DATE_FORMAT(payment_date, '%b %Y') as month_label,
        SUM(amount) as total,
        COUNT(*) as transaction_count
    FROM payments
    WHERE payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
    AND status = 'confirmed'
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month ASC
";

$monthly_revenue_result = $conn->query($monthly_revenue_query);

$revenue_data = [];
$revenue_labels = [];
$monthly_revenue_array = []; // Store data for later use

while ($row = $monthly_revenue_result->fetch_assoc()) {
    $revenue_labels[] = $row['month_label'];
    $revenue_data[] = $row['total'];
    $monthly_revenue_array[] = $row; // Store for table display
}

// Total revenue for date range
$stmt = $conn->prepare("
    SELECT SUM(amount) as total, COUNT(*) as count
    FROM payments
    WHERE payment_date BETWEEN ? AND ?
    AND status = 'confirmed'
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_revenue_result = $stmt->get_result();
$total_revenue = $total_revenue_result->fetch_assoc();

// Pending payments
$pending_payments_result = $conn->query("
    SELECT COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE status = 'pending'
");
$pending_payments = $pending_payments_result->fetch_assoc();

// Top tenants by payment
$top_tenants_result = $conn->query("
    SELECT 
        u.first_name, 
        u.last_name, 
        u.email,
        COUNT(p.id) as payment_count,
        SUM(p.amount) as total_paid
    FROM users u
    JOIN payments p ON u.id = p.tenant_id
    WHERE p.status = 'confirmed'
    GROUP BY u.id
    ORDER BY total_paid DESC
    LIMIT 5
");

// Store top tenants data
$top_tenants_array = [];
while ($row = $top_tenants_result->fetch_assoc()) {
    $top_tenants_array[] = $row;
}

// Room type distribution
$room_type_result = $conn->query("
    SELECT room_type, COUNT(*) as count
    FROM rooms
    GROUP BY room_type
    ORDER BY count DESC
");

$room_types = [];
$room_counts = [];
while ($row = $room_type_result->fetch_assoc()) {
    $room_types[] = $row['room_type'];
    $room_counts[] = $row['count'];
}
?>

<style>
    .reports-container {
        animation: fadeIn 0.5s ease-out;
    }
    
    .report-filters {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
    }
    
    .filter-row {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .chart-container {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        position: relative;
        height: 400px;
    }
    
    .chart-wrapper {
        position: relative;
        height: 100%;
        width: 100%;
    }
    
    canvas {
        max-height: 350px;
    }
    
    .export-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .report-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0;
        flex-wrap: wrap;
    }
    
    .report-tab {
        padding: 1rem 1.5rem;
        background: none;
        border: none;
        color: #64748b;
        font-weight: 600;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        margin-bottom: -2px;
        font-size: 0.95rem;
    }
    
    .report-tab:hover {
        color: #667eea;
        background: rgba(102, 126, 234, 0.05);
    }
    
    .report-tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
        background: rgba(102, 126, 234, 0.05);
    }
    
    .report-section {
        display: none;
    }
    
    .report-section.active {
        display: block;
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .top-tenants-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .tenant-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border-left: 4px solid #667eea;
    }
    
    .tenant-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .tenant-avatar {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .tenant-details h4 {
        margin: 0 0 0.25rem 0;
        font-size: 1rem;
        color: #1e293b;
    }
    
    .tenant-details p {
        margin: 0;
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .tenant-stats {
        text-align: right;
    }
    
    .tenant-amount {
        font-size: 1.5rem;
        font-weight: 800;
        color: #10b981;
        margin-bottom: 0.25rem;
    }
    
    .tenant-count {
        font-size: 0.875rem;
        color: #64748b;
    }
    
    @media print {
        .report-filters,
        .export-buttons,
        .report-tabs,
        .navbar,
        .footer {
            display: none !important;
        }
        
        .report-section {
            display: block !important;
        }
        
        .card-enhanced,
        .stat-card-enhanced {
            box-shadow: none;
            page-break-inside: avoid;
        }
    }
    
    @media (max-width: 768px) {
        .export-buttons {
            width: 100%;
        }
        
        .export-buttons button {
            flex: 1;
            font-size: 0.875rem;
            padding: 0.75rem 1rem;
        }
        
        .page-header-enhanced > div > div {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start !important;
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>📊 Reports & Analytics</h1>
                <p class="subtitle">Comprehensive insights and financial reports</p>
            </div>
            <div class="export-buttons">
                <button onclick="exportToPDF()" class="btn-enhanced primary">
                    📄 Export PDF
                </button>
                <button onclick="exportToExcel()" class="btn-enhanced success">
                    📊 Export Excel
                </button>
                <button onclick="window.print()" class="btn-enhanced outline">
                    🖨️ Print
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container reports-container">
    
    <!-- Report Filters -->
    <div class="report-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-group">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-control">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                        <option value="occupancy" <?php echo $report_type === 'occupancy' ? 'selected' : ''; ?>>Occupancy</option>
                        <option value="tenants" <?php echo $report_type === 'tenants' ? 'selected' : ''; ?>>Tenants</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-enhanced primary" style="width: 100%;">
                        🔍 Generate Report
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Report Tabs -->
    <div class="report-tabs">
        <button class="report-tab active" onclick="switchTab('overview')">📈 Overview</button>
        <button class="report-tab" onclick="switchTab('revenue')">💰 Revenue</button>
        <button class="report-tab" onclick="switchTab('occupancy')">🏠 Occupancy</button>
        <button class="report-tab" onclick="switchTab('tenants')">👥 Tenants</button>
    </div>
    
    <!-- Overview Tab -->
    <div id="overview-tab" class="report-section active">
        <!-- Key Metrics -->
        <div class="grid grid-4 mb-4">
            <div class="stat-card-enhanced primary animate-slide-in">
                <div class="stat-icon-wrapper">🏠</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($occupancy_rate, 1); ?>%</div>
                    <div class="stat-label">Occupancy Rate</div>
                    <div class="stat-change"><?php echo $occupied_rooms; ?>/<?php echo $total_rooms; ?> Rooms</div>
                </div>
            </div>
            
            <div class="stat-card-enhanced success animate-slide-in">
                <div class="stat-icon-wrapper">💰</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo format_currency($total_revenue['total'] ?? 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-change positive"><?php echo $total_revenue['count'] ?? 0; ?> Transactions</div>
                </div>
            </div>
            
            <div class="stat-card-enhanced warning animate-slide-in">
                <div class="stat-icon-wrapper">⏳</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $pending_payments['count'] ?? 0; ?></div>
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-change negative"><?php echo format_currency($pending_payments['total'] ?? 0); ?></div>
                </div>
            </div>
            
            <div class="stat-card-enhanced info animate-slide-in">
                <div class="stat-icon-wrapper">🔧</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $maintenance_rooms; ?></div>
                    <div class="stat-label">Maintenance</div>
                    <div class="stat-change">Rooms Under Repair</div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-2 mb-4">
            <!-- Monthly Revenue Chart -->
            <div class="card-enhanced">
                <div class="card-header-enhanced">
                    <h2>
                        <div class="header-icon">📈</div>
                        Monthly Revenue Trend
                    </h2>
                </div>
                <div class="card-body-enhanced">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Room Type Distribution -->
            <div class="card-enhanced">
                <div class="card-header-enhanced">
                    <h2>
                        <div class="header-icon">🏠</div>
                        Room Type Distribution
                    </h2>
                </div>
                <div class="card-body-enhanced">
                    <div class="chart-container">
                        <canvas id="roomTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Room Status Summary -->
        <div class="card-enhanced mb-4">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">📊</div>
                    Room Status Overview
                </h2>
            </div>
            <div class="card-body-enhanced">
                <div class="grid grid-4">
                    <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #d4f4dd 0%, #c6f6d5 100%); border-radius: 12px;">
                        <div style="font-size: 2.5rem; font-weight: 800; color: #22543d; margin-bottom: 0.5rem;">
                            <?php echo $available_rooms; ?>
                        </div>
                        <div style="color: #22543d; font-weight: 600;">Available Rooms</div>
                        <div style="font-size: 0.875rem; color: #38a169; margin-top: 0.25rem;">
                            <?php echo $total_rooms > 0 ? number_format(($available_rooms / $total_rooms) * 100, 1) : 0; ?>% of Total
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px;">
                        <div style="font-size: 2.5rem; font-weight: 800; color: #1e3a8a; margin-bottom: 0.5rem;">
                            <?php echo $occupied_rooms; ?>
                        </div>
                        <div style="color: #1e3a8a; font-weight: 600;">Occupied Rooms</div>
                        <div style="font-size: 0.875rem; color: #3b82f6; margin-top: 0.25rem;">
                            <?php echo number_format($occupancy_rate, 1); ?>% Occupancy
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px;">
                        <div style="font-size: 2.5rem; font-weight: 800; color: #78350f; margin-bottom: 0.5rem;">
                            <?php echo $maintenance_rooms; ?>
                        </div>
                        <div style="color: #78350f; font-weight: 600;">Under Maintenance</div>
                        <div style="font-size: 0.875rem; color: #d97706; margin-top: 0.25rem;">
                            <?php echo $total_rooms > 0 ? number_format(($maintenance_rooms / $total_rooms) * 100, 1) : 0; ?>% of Total
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); border-radius: 12px;">
                        <div style="font-size: 2.5rem; font-weight: 800; color: #4338ca; margin-bottom: 0.5rem;">
                            <?php echo $total_rooms; ?>
                        </div>
                        <div style="color: #4338ca; font-weight: 600;">Total Rooms</div>
                        <div style="font-size: 0.875rem; color: #6366f1; margin-top: 0.25rem;">
                            All Property Rooms
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Tab -->
    <div id="revenue-tab" class="report-section">
        <div class="grid grid-2 mb-4">
            <div class="card-enhanced">
                <div class="card-header-enhanced">
                    <h2>
                        <div class="header-icon">📊</div>
                        Revenue Analytics
                    </h2>
                </div>
                <div class="card-body-enhanced">
                    <div class="chart-container">
                        <canvas id="revenueDetailChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card-enhanced">
                <div class="card-header-enhanced">
                    <h2>
                        <div class="header-icon">💳</div>
                        Payment Status
                    </h2>
                </div>
                <div class="card-body-enhanced">
                    <div class="chart-container">
                        <canvas id="paymentStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-enhanced">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">📅</div>
                    Monthly Revenue Breakdown
                </h2>
            </div>
            <div class="card-body-enhanced">
                <div class="table-responsive">
                    <table class="table-enhanced">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Transactions</th>
                                <th>Total Revenue</th>
                                <th>Average</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $prev_total = 0;
                            foreach ($monthly_revenue_array as $row): 
                                $avg = $row['transaction_count'] > 0 ? $row['total'] / $row['transaction_count'] : 0;
                                $trend = $prev_total > 0 ? (($row['total'] - $prev_total) / $prev_total) * 100 : 0;
                                $prev_total = $row['total'];
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['month_label']); ?></strong></td>
                                    <td><?php echo $row['transaction_count']; ?></td>
                                    <td><strong><?php echo format_currency($row['total']); ?></strong></td>
                                    <td><?php echo format_currency($avg); ?></td>
                                    <td>
                                        <?php if ($trend > 0): ?>
                                            <span class="badge-enhanced success">↑ <?php echo number_format($trend, 1); ?>%</span>
                                        <?php elseif ($trend < 0): ?>
                                            <span class="badge-enhanced danger">↓ <?php echo number_format(abs($trend), 1); ?>%</span>
                                        <?php else: ?>
                                            <span class="badge-enhanced info">→ 0%</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Occupancy Tab -->
    <div id="occupancy-tab" class="report-section">
        <div class="grid grid-2 mb-4">
            <div class="card-enhanced">
                <div class="card-header-enhanced">
                    <h2>
                        <div class="header-icon">📊</div>
                        Occupancy Trends
                    </h2>
                </div>
                <div class="card-body-enhanced">
                    <div class="chart-container">
                        <canvas id="occupancyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card-enhanced">
                <div class="card-header-enhanced">
                    <h2>
                        <div class="header-icon">🏠</div>
                        Room Status Distribution
                    </h2>
                </div>
                <div class="card-body-enhanced">
                    <div class="chart-container">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-enhanced">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">📋</div>
                    Detailed Room Status
                </h2>
            </div>
            <div class="card-body-enhanced">
                <?php
                $detailed_rooms = $conn->query("
                    SELECT r.*, 
                           CASE 
                               WHEN r.status = 'occupied' THEN (
                                   SELECT CONCAT(u.first_name, ' ', u.last_name)
                                   FROM bookings b
                                   JOIN users u ON b.tenant_id = u.id
                                   WHERE b.room_id = r.id AND b.status = 'checked_in'
                                   LIMIT 1
                               )
                               ELSE NULL
                           END as tenant_name
                    FROM rooms r
                    ORDER BY r.room_number ASC
                ");
                ?>
                <div class="table-responsive">
                    <table class="table-enhanced">
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Type</th>
                                <th>Floor</th>
                                <th>Capacity</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Current Tenant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($room = $detailed_rooms->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>Room <?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                    <td><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($room['floor']); ?></td>
                                    <td><?php echo htmlspecialchars($room['capacity']); ?> person(s)</td>
                                    <td><?php echo format_currency($room['price']); ?></td>
                                    <td>
                                        <span class="badge-enhanced <?php 
                                            echo $room['status'] === 'available' ? 'success' : 
                                                ($room['status'] === 'occupied' ? 'info' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($room['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($room['tenant_name'] ?? '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tenants Tab -->
    <div id="tenants-tab" class="report-section">
        <div class="card-enhanced mb-4">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">🏆</div>
                    Top Paying Tenants
                </h2>
            </div>
            <div class="card-body-enhanced">
                <?php if (count($top_tenants_array) > 0): ?>
                    <div class="top-tenants-list">
                        <?php 
                        $rank = 1;
                        foreach ($top_tenants_array as $tenant): 
                        ?>
                            <div class="tenant-item">
                                <div class="tenant-info">
                                    <div class="tenant-avatar">
                                        <?php echo strtoupper(substr($tenant['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="tenant-details">
                                        <h4><?php echo $rank++; ?>. <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($tenant['email']); ?></p>
                                    </div>
                                </div>
                                <div class="tenant-stats">
                                    <div class="tenant-amount"><?php echo format_currency($tenant['total_paid']); ?></div>
                                    <div class="tenant-count"><?php echo $tenant['payment_count']; ?> payments</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-enhanced">
                        <div class="icon">👥</div>
                        <h3>No Payment Data</h3>
                        <p>No tenant payment records found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-enhanced">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">📋</div>
                    All Tenants Summary
                </h2>
            </div>
            <div class="card-body-enhanced">
                <?php
                $all_tenants = $conn->query("
                    SELECT 
                        u.id,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.phone,
                        COUNT(DISTINCT b.id) as booking_count,
                        COUNT(DISTINCT p.id) as payment_count,
                        COALESCE(SUM(p.amount), 0) as total_paid
                    FROM users u
                    LEFT JOIN bookings b ON u.id = b.tenant_id
                    LEFT JOIN payments p ON u.id = p.tenant_id AND p.status = 'confirmed'
                    WHERE u.role = 'tenant'
                    GROUP BY u.id
                    ORDER BY u.first_name ASC
                ");
                ?>
                <div class="table-responsive">
                    <table class="table-enhanced">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Bookings</th>
                                <th>Payments</th>
                                <th>Total Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tenant = $all_tenants->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                    <td><?php echo htmlspecialchars($tenant['phone']); ?></td>
                                    <td><?php echo $tenant['booking_count']; ?></td>
                                    <td><?php echo $tenant['payment_count']; ?></td>
                                    <td><strong><?php echo format_currency($tenant['total_paid']); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // Tab Switching
    function switchTab(tabName) {
        document.querySelectorAll('.report-section').forEach(section => {
            section.classList.remove('active');
        });
        
        document.querySelectorAll('.report-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        document.getElementById(tabName + '-tab').classList.add('active');
        event.target.classList.add('active');
    }
    
    // Chart Data
    const revenueLabels = <?php echo json_encode($revenue_labels); ?>;
    const revenueData = <?php echo json_encode($revenue_data); ?>;
    const roomTypes = <?php echo json_encode($room_types); ?>;
    const roomCounts = <?php echo json_encode($room_counts); ?>;
    
    // Common Chart Options
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12,
                        weight: '600'
                    }
                }
            }
        }
    };
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Monthly Revenue',
                data: revenueData,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Room Type Chart
    const roomTypeCtx = document.getElementById('roomTypeChart');
    new Chart(roomTypeCtx, {
        type: 'doughnut',
        data: {
            labels: roomTypes,
            datasets: [{
                data: roomCounts,
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(17, 153, 142, 0.8)',
                    'rgba(240, 147, 251, 0.8)',
                    'rgba(79, 172, 254, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: commonOptions
    });
    
    // Revenue Detail Chart
    const revenueDetailCtx = document.getElementById('revenueDetailChart');
    new Chart(revenueDetailCtx, {
        type: 'bar',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Revenue',
                data: revenueData,
                backgroundColor: 'rgba(17, 153, 142, 0.8)',
                borderColor: 'rgb(17, 153, 142)',
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Payment Status Chart
    const paymentStatusCtx = document.getElementById('paymentStatusChart');
    new Chart(paymentStatusCtx, {
        type: 'pie',
        data: {
            labels: ['Confirmed', 'Pending'],
            datasets: [{
                data: [<?php echo $total_revenue['total'] ?? 0; ?>, <?php echo $pending_payments['total'] ?? 0; ?>],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: commonOptions
    });
    
    // Occupancy Chart
    const occupancyCtx = document.getElementById('occupancyChart');
    new Chart(occupancyCtx, {
        type: 'bar',
        data: {
            labels: ['Occupied', 'Available', 'Maintenance'],
            datasets: [{
                label: 'Number of Rooms',
                data: [<?php echo $occupied_rooms; ?>, <?php echo $available_rooms; ?>, <?php echo $maintenance_rooms; ?>],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Status Pie Chart
    const statusPieCtx = document.getElementById('statusPieChart');
    new Chart(statusPieCtx, {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Occupied', 'Maintenance'],
            datasets: [{
                data: [<?php echo $available_rooms; ?>, <?php echo $occupied_rooms; ?>, <?php echo $maintenance_rooms; ?>],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: commonOptions
    });
    
    // Export to PDF
    function exportToPDF() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = function() {
            const element = document.querySelector('.reports-container');
            const opt = {
                margin: 10,
                filename: 'dormitory-report-' + new Date().toISOString().split('T')[0] + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Show loading message
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Generating PDF...';
            btn.disabled = true;
            
            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        };
        document.head.appendChild(script);
    }
    
    // Export to Excel
    function exportToExcel() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = function() {
            const wb = XLSX.utils.book_new();
            
            // Export all tables
            const tables = document.querySelectorAll('.table-enhanced');
            tables.forEach((table, index) => {
                const ws = XLSX.utils.table_to_sheet(table);
                XLSX.utils.book_append_sheet(wb, ws, 'Sheet' + (index + 1));
            });
            
            XLSX.writeFile(wb, 'dormitory-report-' + new Date().toISOString().split('T')[0] + '.xlsx');
        };
        document.head.appendChild(script);
    }
</script>

<?php require_once '../includes/footer.php'; ?>