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

// Format dates for display
$start_date_formatted = date('F j, Y', strtotime($start_date));
$end_date_formatted = date('F j, Y', strtotime($end_date));
$report_generated = date('F j, Y \a\t g:i A');

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

$payment_method_query = "
    SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount
    FROM payments
    WHERE payment_date BETWEEN ? AND ?
    AND status = 'confirmed'
    GROUP BY payment_method
";
$method_stmt = $conn->prepare($payment_method_query);
$method_stmt->bind_param("ss", $start_date, $end_date);
$method_stmt->execute();
$payment_methods = $method_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$method_stmt->close();

// PayPal transaction success rate
$paypal_stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM paypal_transactions
    WHERE created_at BETWEEN ? AND ?
";
$pp_stmt = $conn->prepare($paypal_stats_query);
$pp_stmt->bind_param("ss", $start_date, $end_date);
$pp_stmt->execute();
$paypal_stats = $pp_stmt->get_result()->fetch_assoc();
$pp_stmt->close();

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
    
    /* PDF Preview Modal */
    .pdf-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        animation: fadeIn 0.3s ease;
    }
    
    .pdf-modal-overlay.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .pdf-modal {
        background: white;
        width: 95%;
        max-width: 900px;
        max-height: 90vh;
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    .pdf-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .pdf-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
    }
    
    .pdf-modal-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .pdf-modal-actions button {
        padding: 0.6rem 1.25rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    
    .pdf-modal-actions .btn-download {
        background: white;
        color: #667eea;
    }
    
    .pdf-modal-actions .btn-download:hover {
        background: #f0f0f0;
        transform: translateY(-1px);
    }
    
    .pdf-modal-actions .btn-close {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .pdf-modal-actions .btn-close:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .pdf-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 0;
        background: #f1f5f9;
    }
    
    /* PDF Report Styles */
    .pdf-report {
        background: white;
        max-width: 800px;
        margin: 2rem auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .pdf-page {
        padding: 40px;
        background: white;
        min-height: 1000px;
        position: relative;
    }
    
    .pdf-header {
        text-align: center;
        padding-bottom: 25px;
        border-bottom: 3px solid #667eea;
        margin-bottom: 30px;
    }
    
    .pdf-logo {
        font-size: 3rem;
        margin-bottom: 10px;
    }
    
    .pdf-title {
        font-size: 28px;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 8px 0;
        letter-spacing: -0.5px;
    }
    
    .pdf-subtitle {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }
    
    .pdf-meta {
        display: flex;
        justify-content: space-between;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        font-size: 13px;
    }
    
    .pdf-meta-item {
        text-align: center;
    }
    
    .pdf-meta-label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }
    
    .pdf-meta-value {
        color: #1e293b;
        font-weight: 700;
        font-size: 13px;
    }
    
    .pdf-section {
        margin-bottom: 30px;
    }
    
    .pdf-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 15px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pdf-section-title span {
        font-size: 18px;
    }
    
    .pdf-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .pdf-kpi-card {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        padding: 18px 15px;
        border-radius: 12px;
        text-align: center;
        border-left: 4px solid #667eea;
    }
    
    .pdf-kpi-card.success { border-left-color: #10b981; }
    .pdf-kpi-card.warning { border-left-color: #f59e0b; }
    .pdf-kpi-card.info { border-left-color: #3b82f6; }
    
    .pdf-kpi-value {
        font-size: 26px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 4px;
        line-height: 1;
    }
    
    .pdf-kpi-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .pdf-kpi-sub {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 4px;
    }
    
    .pdf-chart-container {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .pdf-chart-title {
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .pdf-chart-canvas {
        height: 220px;
        width: 100%;
    }
    
    .pdf-charts-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .pdf-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    .pdf-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .pdf-table th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .pdf-table th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .pdf-table td {
        padding: 10px 15px;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
    }
    
    .pdf-table tr:last-child td {
        border-bottom: none;
    }
    
    .pdf-table tr:nth-child(even) {
        background: #f8fafc;
    }
    
    .pdf-table .text-right {
        text-align: right;
    }
    
    .pdf-table .font-bold {
        font-weight: 700;
        color: #1e293b;
    }
    
    .pdf-footer {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #e2e8f0;
        text-align: center;
        font-size: 11px;
        color: #94a3b8;
    }
    
    .pdf-footer strong {
        color: #64748b;
    }
    
    .pdf-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .pdf-badge.success {
        background: #d1fae5;
        color: #065f46;
    }
    
    .pdf-badge.warning {
        background: #fef3c7;
        color: #92400e;
    }
    
    .pdf-badge.info {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .pdf-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
    
    .pdf-summary-card {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        padding: 15px;
        border-radius: 10px;
        text-align: center;
    }
    
    .pdf-summary-icon {
        font-size: 24px;
        margin-bottom: 8px;
    }
    
    .pdf-summary-value {
        font-size: 20px;
        font-weight: 800;
        color: #1e293b;
    }
    
    .pdf-summary-label {
        font-size: 11px;
        color: #64748b;
        margin-top: 3px;
    }
    
    .pdf-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #64748b;
    }
    
    .pdf-loading-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #e2e8f0;
        border-top-color: #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media print {
        .report-filters,
        .export-buttons,
        .report-tabs,
        .navbar,
        .footer,
        .page-header-enhanced {
            display: none !important;
        }
        
        .reports-container {
            padding: 0 !important;
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
        
        .pdf-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .pdf-charts-row {
            grid-template-columns: 1fr;
        }
        
        .pdf-summary-grid {
            grid-template-columns: repeat(2, 1fr);
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
                <button onclick="openPDFPreview()" class="btn-enhanced primary">
                    📄 Generate PDF Report
                </button>
                <button onclick="exportToExcel()" class="btn-enhanced success">
                    📊 Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PDF Preview Modal -->
<div id="pdfModal" class="pdf-modal-overlay">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <h3>📄 Report Preview</h3>
            <div class="pdf-modal-actions">
                <button onclick="downloadPDF()" class="btn-download">⬇️ Download PDF</button>
                <button onclick="closePDFPreview()" class="btn-close">✕ Close</button>
            </div>
        </div>
        <div class="pdf-modal-body">
            <div id="pdfContent" class="pdf-report">
                <!-- PDF content will be generated here -->
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

        <!-- Payment Methods Breakdown -->
        <div class="card-enhanced mb-4">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon">💳</div>
                    Payment Methods Breakdown
                </h2>
            </div>
            <div class="card-body-enhanced">
                <div class="grid grid-3">
                    <?php 
                    $method_icons = ['paypal' => '🅿️', 'cash' => '💵', 'bank' => '🏦'];
                    $method_colors = ['paypal' => '#0070ba', 'cash' => '#10b981', 'bank' => '#3b82f6'];
                    foreach ($payment_methods as $method): 
                    ?>
                        <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border-left: 4px solid <?php echo $method_colors[$method['payment_method']] ?? '#667eea'; ?>;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                                <?php echo $method_icons[$method['payment_method']] ?? '💳'; ?>
                            </div>
                            <div style="font-size: 1.75rem; font-weight: 800; color: <?php echo $method_colors[$method['payment_method']] ?? '#667eea'; ?>;">
                                <?php echo format_currency($method['total_amount']); ?>
                            </div>
                            <div style="font-weight: 600; color: #64748b; text-transform: uppercase;">
                                <?php echo ucfirst($method['payment_method']); ?>
                            </div>
                            <div style="font-size: 0.875rem; color: #94a3b8; margin-top: 0.25rem;">
                                <?php echo $method['transaction_count']; ?> transactions
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- PayPal Transaction Stats -->
        <?php if ($paypal_stats['total'] > 0): ?>
        <div class="card-enhanced mb-4">
            <div class="card-header-enhanced">
                <h2>
                    <div class="header-icon" style="background: linear-gradient(135deg, #0070ba 0%, #003087 100%);">🅿️</div>
                    PayPal Transaction Analytics
                </h2>
            </div>
            <div class="card-body-enhanced">
                <div class="grid grid-4">
                    <div style="text-align: center; padding: 1.25rem; background: #dbeafe; border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 800; color: #1e40af;"><?php echo $paypal_stats['total']; ?></div>
                        <div style="font-weight: 600; color: #1e40af;">Total Transactions</div>
                    </div>
                    <div style="text-align: center; padding: 1.25rem; background: #d1fae5; border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 800; color: #065f46;"><?php echo $paypal_stats['completed']; ?></div>
                        <div style="font-weight: 600; color: #065f46;">Completed</div>
                    </div>
                    <div style="text-align: center; padding: 1.25rem; background: #fef3c7; border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 800; color: #92400e;"><?php echo $paypal_stats['pending']; ?></div>
                        <div style="font-weight: 600; color: #92400e;">Pending</div>
                    </div>
                    <div style="text-align: center; padding: 1.25rem; background: #fee2e2; border-radius: 10px;">
                        <div style="font-size: 2rem; font-weight: 800; color: #991b1b;"><?php echo $paypal_stats['failed'] + $paypal_stats['cancelled']; ?></div>
                        <div style="font-weight: 600; color: #991b1b;">Failed/Cancelled</div>
                    </div>
                </div>
                
                <?php 
                $success_rate = $paypal_stats['total'] > 0 ? ($paypal_stats['completed'] / $paypal_stats['total']) * 100 : 0;
                ?>
                <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 600;">Success Rate</span>
                        <span style="font-weight: 700; color: <?php echo $success_rate >= 80 ? '#10b981' : ($success_rate >= 50 ? '#f59e0b' : '#ef4444'); ?>">
                            <?php echo number_format($success_rate, 1); ?>%
                        </span>
                    </div>
                    <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo $success_rate; ?>%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 4px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    // Report Data from PHP
    const reportData = {
        dateRange: {
            start: '<?php echo $start_date_formatted; ?>',
            end: '<?php echo $end_date_formatted; ?>',
            generated: '<?php echo $report_generated; ?>'
        },
        occupancy: {
            rate: <?php echo number_format($occupancy_rate, 1); ?>,
            total: <?php echo $total_rooms; ?>,
            occupied: <?php echo $occupied_rooms; ?>,
            available: <?php echo $available_rooms; ?>,
            maintenance: <?php echo $maintenance_rooms; ?>
        },
        revenue: {
            total: <?php echo $total_revenue['total'] ?? 0; ?>,
            totalFormatted: '<?php echo format_currency($total_revenue['total'] ?? 0); ?>',
            transactions: <?php echo $total_revenue['count'] ?? 0; ?>,
            pending: <?php echo $pending_payments['total'] ?? 0; ?>,
            pendingFormatted: '<?php echo format_currency($pending_payments['total'] ?? 0); ?>',
            pendingCount: <?php echo $pending_payments['count'] ?? 0; ?>
        },
        monthly: {
            labels: <?php echo json_encode($revenue_labels); ?>,
            data: <?php echo json_encode($revenue_data); ?>,
            details: <?php echo json_encode($monthly_revenue_array); ?>
        },
        roomTypes: {
            labels: <?php echo json_encode($room_types); ?>,
            data: <?php echo json_encode($room_counts); ?>
        },
        paymentMethods: <?php echo json_encode($payment_methods); ?>,
        topTenants: <?php echo json_encode($top_tenants_array); ?>,
        paypal: {
            total: <?php echo $paypal_stats['total'] ?? 0; ?>,
            completed: <?php echo $paypal_stats['completed'] ?? 0; ?>,
            pending: <?php echo $paypal_stats['pending'] ?? 0; ?>,
            failed: <?php echo ($paypal_stats['failed'] ?? 0) + ($paypal_stats['cancelled'] ?? 0); ?>
        }
    };

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
    const revenueLabels = reportData.monthly.labels;
    const revenueData = reportData.monthly.data;
    const roomTypes = reportData.roomTypes.labels;
    const roomCounts = reportData.roomTypes.data;
    
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
                data: [reportData.revenue.total, reportData.revenue.pending],
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
                data: [reportData.occupancy.occupied, reportData.occupancy.available, reportData.occupancy.maintenance],
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
                data: [reportData.occupancy.available, reportData.occupancy.occupied, reportData.occupancy.maintenance],
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

    // PDF Chart instances
    let pdfCharts = {};

    // Generate PDF Content
    function generatePDFContent() {
        const methodIcons = { paypal: '🅿️', cash: '💵', bank_transfer: '🏦', gcash: '📱' };
        
        // Build payment methods HTML
        let paymentMethodsHTML = '';
        if (reportData.paymentMethods.length > 0) {
            paymentMethodsHTML = reportData.paymentMethods.map(m => `
                <div class="pdf-summary-card">
                    <div class="pdf-summary-icon">${methodIcons[m.payment_method] || '💳'}</div>
                    <div class="pdf-summary-value">₱${parseFloat(m.total_amount).toLocaleString()}</div>
                    <div class="pdf-summary-label">${m.payment_method.replace('_', ' ').toUpperCase()} (${m.transaction_count})</div>
                </div>
            `).join('');
        }

        // Build top tenants HTML
        let topTenantsHTML = '';
        if (reportData.topTenants.length > 0) {
            topTenantsHTML = reportData.topTenants.map((t, i) => `
                <tr>
                    <td class="font-bold">${i + 1}. ${t.first_name} ${t.last_name}</td>
                    <td>${t.email}</td>
                    <td class="text-right">${t.payment_count}</td>
                    <td class="text-right font-bold">₱${parseFloat(t.total_paid).toLocaleString()}</td>
                </tr>
            `).join('');
        }

        // Build monthly revenue table
        let monthlyTableHTML = '';
        if (reportData.monthly.details.length > 0) {
            monthlyTableHTML = reportData.monthly.details.map(m => `
                <tr>
                    <td class="font-bold">${m.month_label}</td>
                    <td class="text-right">${m.transaction_count}</td>
                    <td class="text-right font-bold">₱${parseFloat(m.total).toLocaleString()}</td>
                </tr>
            `).join('');
        }

        return `
            <div class="pdf-page">
                <!-- Header -->
                <div class="pdf-header">
                    <div class="pdf-logo">🏠</div>
                    <h1 class="pdf-title">Dormitory Management Report</h1>
                    <p class="pdf-subtitle">Comprehensive Financial & Occupancy Analysis</p>
                </div>

                <!-- Meta Information -->
                <div class="pdf-meta">
                    <div class="pdf-meta-item">
                        <div class="pdf-meta-label">Report Period</div>
                        <div class="pdf-meta-value">${reportData.dateRange.start} - ${reportData.dateRange.end}</div>
                    </div>
                    <div class="pdf-meta-item">
                        <div class="pdf-meta-label">Generated On</div>
                        <div class="pdf-meta-value">${reportData.dateRange.generated}</div>
                    </div>
                    <div class="pdf-meta-item">
                        <div class="pdf-meta-label">Report Type</div>
                        <div class="pdf-meta-value">Full Analytics Report</div>
                    </div>
                </div>

                <!-- Key Performance Indicators -->
                <div class="pdf-section">
                    <h2 class="pdf-section-title"><span>📊</span> Key Performance Indicators</h2>
                    <div class="pdf-kpi-grid">
                        <div class="pdf-kpi-card">
                            <div class="pdf-kpi-value">${reportData.occupancy.rate}%</div>
                            <div class="pdf-kpi-label">Occupancy Rate</div>
                            <div class="pdf-kpi-sub">${reportData.occupancy.occupied}/${reportData.occupancy.total} Rooms</div>
                        </div>
                        <div class="pdf-kpi-card success">
                            <div class="pdf-kpi-value">${reportData.revenue.totalFormatted}</div>
                            <div class="pdf-kpi-label">Total Revenue</div>
                            <div class="pdf-kpi-sub">${reportData.revenue.transactions} Transactions</div>
                        </div>
                        <div class="pdf-kpi-card warning">
                            <div class="pdf-kpi-value">${reportData.revenue.pendingCount}</div>
                            <div class="pdf-kpi-label">Pending Payments</div>
                            <div class="pdf-kpi-sub">${reportData.revenue.pendingFormatted}</div>
                        </div>
                        <div class="pdf-kpi-card info">
                            <div class="pdf-kpi-value">${reportData.occupancy.available}</div>
                            <div class="pdf-kpi-label">Available Rooms</div>
                            <div class="pdf-kpi-sub">${reportData.occupancy.maintenance} In Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="pdf-section">
                    <h2 class="pdf-section-title"><span>📈</span> Revenue & Occupancy Charts</h2>
                    <div class="pdf-charts-row">
                        <div class="pdf-chart-container">
                            <div class="pdf-chart-title">Monthly Revenue Trend</div>
                            <canvas id="pdfRevenueChart" class="pdf-chart-canvas"></canvas>
                        </div>
                        <div class="pdf-chart-container">
                            <div class="pdf-chart-title">Room Status Distribution</div>
                            <canvas id="pdfOccupancyChart" class="pdf-chart-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                ${reportData.paymentMethods.length > 0 ? `
                <div class="pdf-section">
                    <h2 class="pdf-section-title"><span>💳</span> Payment Methods Breakdown</h2>
                    <div class="pdf-summary-grid">
                        ${paymentMethodsHTML}
                    </div>
                </div>
                ` : ''}

                <!-- Monthly Revenue Table -->
                ${reportData.monthly.details.length > 0 ? `
                <div class="pdf-section">
                    <h2 class="pdf-section-title"><span>📅</span> Monthly Revenue Summary</h2>
                    <table class="pdf-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-right">Transactions</th>
                                <th class="text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${monthlyTableHTML}
                        </tbody>
                    </table>
                </div>
                ` : ''}

                <!-- Top Tenants -->
                ${reportData.topTenants.length > 0 ? `
                <div class="pdf-section">
                    <h2 class="pdf-section-title"><span>🏆</span> Top Paying Tenants</h2>
                    <table class="pdf-table">
                        <thead>
                            <tr>
                                <th>Tenant Name</th>
                                <th>Email</th>
                                <th class="text-right">Payments</th>
                                <th class="text-right">Total Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${topTenantsHTML}
                        </tbody>
                    </table>
                </div>
                ` : ''}

                <!-- Room Summary -->
                <div class="pdf-section">
                    <h2 class="pdf-section-title"><span>🏠</span> Room Inventory Summary</h2>
                    <div class="pdf-summary-grid">
                        <div class="pdf-summary-card" style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);">
                            <div class="pdf-summary-icon">✅</div>
                            <div class="pdf-summary-value" style="color: #065f46;">${reportData.occupancy.available}</div>
                            <div class="pdf-summary-label">Available</div>
                        </div>
                        <div class="pdf-summary-card" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
                            <div class="pdf-summary-icon">👤</div>
                            <div class="pdf-summary-value" style="color: #1e40af;">${reportData.occupancy.occupied}</div>
                            <div class="pdf-summary-label">Occupied</div>
                        </div>
                        <div class="pdf-summary-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
                            <div class="pdf-summary-icon">🔧</div>
                            <div class="pdf-summary-value" style="color: #92400e;">${reportData.occupancy.maintenance}</div>
                            <div class="pdf-summary-label">Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="pdf-footer">
                    <p><strong>Dormitory Management System</strong> — Confidential Report</p>
                    <p>Generated on ${reportData.dateRange.generated} | Page 1 of 1</p>
                </div>
            </div>
        `;
    }

    // Initialize PDF Charts
    function initPDFCharts() {
        // Destroy existing charts if any
        Object.values(pdfCharts).forEach(chart => chart.destroy());
        pdfCharts = {};

        // Revenue Chart for PDF
        const pdfRevenueCtx = document.getElementById('pdfRevenueChart');
        if (pdfRevenueCtx) {
            pdfCharts.revenue = new Chart(pdfRevenueCtx, {
                type: 'bar',
                data: {
                    labels: reportData.monthly.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: reportData.monthly.data,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgb(102, 126, 234)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => '₱' + value.toLocaleString(),
                                font: { size: 10 }
                            },
                            grid: { color: '#e2e8f0' }
                        },
                        x: {
                            ticks: { font: { size: 9 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // Occupancy Chart for PDF
        const pdfOccupancyCtx = document.getElementById('pdfOccupancyChart');
        if (pdfOccupancyCtx) {
            pdfCharts.occupancy = new Chart(pdfOccupancyCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Occupied', 'Maintenance'],
                    datasets: [{
                        data: [reportData.occupancy.available, reportData.occupancy.occupied, reportData.occupancy.maintenance],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(245, 158, 11, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 11, weight: '600' },
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }
    }

    // Open PDF Preview
    function openPDFPreview() {
        const modal = document.getElementById('pdfModal');
        const content = document.getElementById('pdfContent');
        
        // Show loading
        content.innerHTML = `
            <div class="pdf-loading">
                <div class="pdf-loading-spinner"></div>
                <p>Generating report preview...</p>
            </div>
        `;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Generate content after a brief delay
        setTimeout(() => {
            content.innerHTML = generatePDFContent();
            // Initialize charts after content is rendered
            setTimeout(initPDFCharts, 100);
        }, 300);
    }

    // Close PDF Preview
    function closePDFPreview() {
        const modal = document.getElementById('pdfModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Destroy PDF charts
        Object.values(pdfCharts).forEach(chart => chart.destroy());
        pdfCharts = {};
    }

    // Download PDF
    function downloadPDF() {
        const element = document.getElementById('pdfContent');
        const downloadBtn = document.querySelector('.btn-download');
        const originalText = downloadBtn.innerHTML;
        
        downloadBtn.innerHTML = '⏳ Generating...';
        downloadBtn.disabled = true;
        
        const opt = {
            margin: [10, 10, 10, 10],
            filename: 'dormitory-report-' + new Date().toISOString().split('T')[0] + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                logging: false,
                useCORS: true,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { mode: 'avoid-all' }
        };
        
        html2pdf().set(opt).from(element).save().then(() => {
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }).catch(err => {
            console.error('PDF generation error:', err);
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
            alert('Error generating PDF. Please try again.');
        });
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFPreview();
        }
    });

    // Close modal on backdrop click
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFPreview();
        }
    });
    
    // Export to Excel
    function exportToExcel() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = function() {
            const wb = XLSX.utils.book_new();
            
            // Summary Sheet
            const summaryData = [
                ['Dormitory Management Report'],
                ['Generated: ' + reportData.dateRange.generated],
                ['Period: ' + reportData.dateRange.start + ' to ' + reportData.dateRange.end],
                [''],
                ['KEY METRICS'],
                ['Occupancy Rate', reportData.occupancy.rate + '%'],
                ['Total Rooms', reportData.occupancy.total],
                ['Occupied Rooms', reportData.occupancy.occupied],
                ['Available Rooms', reportData.occupancy.available],
                ['Maintenance', reportData.occupancy.maintenance],
                [''],
                ['REVENUE'],
                ['Total Revenue', reportData.revenue.total],
                ['Transactions', reportData.revenue.transactions],
                ['Pending Amount', reportData.revenue.pending],
                ['Pending Count', reportData.revenue.pendingCount]
            ];
            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'Summary');
            
            // Monthly Revenue Sheet
            if (reportData.monthly.details.length > 0) {
                const monthlyData = [['Month', 'Transactions', 'Total Revenue']];
                reportData.monthly.details.forEach(m => {
                    monthlyData.push([m.month_label, m.transaction_count, parseFloat(m.total)]);
                });
                const monthlyWs = XLSX.utils.aoa_to_sheet(monthlyData);
                XLSX.utils.book_append_sheet(wb, monthlyWs, 'Monthly Revenue');
            }
            
            // Top Tenants Sheet
            if (reportData.topTenants.length > 0) {
                const tenantsData = [['Rank', 'Name', 'Email', 'Payments', 'Total Paid']];
                reportData.topTenants.forEach((t, i) => {
                    tenantsData.push([i + 1, t.first_name + ' ' + t.last_name, t.email, t.payment_count, parseFloat(t.total_paid)]);
                });
                const tenantsWs = XLSX.utils.aoa_to_sheet(tenantsData);
                XLSX.utils.book_append_sheet(wb, tenantsWs, 'Top Tenants');
            }
            
            // Payment Methods Sheet
            if (reportData.paymentMethods.length > 0) {
                const methodsData = [['Payment Method', 'Transactions', 'Total Amount']];
                reportData.paymentMethods.forEach(m => {
                    methodsData.push([m.payment_method, m.transaction_count, parseFloat(m.total_amount)]);
                });
                const methodsWs = XLSX.utils.aoa_to_sheet(methodsData);
                XLSX.utils.book_append_sheet(wb, methodsWs, 'Payment Methods');
            }
            
            // Export all tables from the page
            const tables = document.querySelectorAll('.table-enhanced');
            tables.forEach((table, index) => {
                const ws = XLSX.utils.table_to_sheet(table);
                XLSX.utils.book_append_sheet(wb, ws, 'Data ' + (index + 1));
            });
            
            XLSX.writeFile(wb, 'dormitory-report-' + new Date().toISOString().split('T')[0] + '.xlsx');
        };
        document.head.appendChild(script);
    }
</script>

<?php require_once '../includes/footer.php'; ?>