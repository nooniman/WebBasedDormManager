<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\admin\payments.php

require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Payment Management';

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('Invalid request', 'error');
        redirect('payments.php');
    }
    
    if (isset($_POST['update_status'])) {
        $payment_id = (int)$_POST['payment_id'];
        $new_status = sanitize_input($_POST['status']);
        
        $valid_statuses = ['pending', 'confirmed', 'failed', 'refunded'];
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $payment_id);
            if ($stmt->execute()) {
                set_flash_message('Payment status updated successfully', 'success');
            } else {
                set_flash_message('Failed to update payment status', 'error');
            }
            $stmt->close();
        }
        redirect('payments.php');
    }
    
    // Handle manual payment recording
    if (isset($_POST['record_payment'])) {
        $tenant_id = (int)$_POST['tenant_id'];
        $room_id = (int)$_POST['room_id'];
        $amount = (float)$_POST['amount'];
        $payment_period = sanitize_input($_POST['payment_period']);
        $payment_method = sanitize_input($_POST['payment_method']);
        $notes = sanitize_input($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("
            INSERT INTO payments (tenant_id, room_id, amount, payment_date, payment_period, status, payment_method, notes) 
            VALUES (?, ?, ?, NOW(), ?, 'confirmed', ?, ?)
        ");
        $stmt->bind_param("iidsss", $tenant_id, $room_id, $amount, $payment_period, $payment_method, $notes);
        
        if ($stmt->execute()) {
            set_flash_message('Payment recorded successfully', 'success');
        } else {
            set_flash_message('Failed to record payment', 'error');
        }
        $stmt->close();
        redirect('payments.php');
    }
}

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$method_filter = isset($_GET['method']) ? $_GET['method'] : 'all';
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_clauses[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($method_filter !== 'all') {
    $where_clauses[] = "p.payment_method = ?";
    $params[] = $method_filter;
    $types .= 's';
}

if ($month_filter) {
    $where_clauses[] = "DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= 's';
}

if ($search) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR r.room_number LIKE ? OR p.paypal_transaction_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get payments with tenant and room info
$query = "
    SELECT p.*, 
           u.first_name, u.last_name, u.email,
           r.room_number, r.room_type
    FROM payments p
    LEFT JOIN users u ON p.tenant_id = u.id
    LEFT JOIN rooms r ON p.room_id = r.id
    $where_sql
    ORDER BY p.payment_date DESC, p.id DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $payments_result = $stmt->get_result();
} else {
    $payments_result = $conn->query($query);
}

// Get payment statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as total_confirmed,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN payment_method = 'paypal' THEN amount ELSE 0 END) as total_paypal,
        SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as total_cash,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN payment_method = 'paypal' THEN 1 END) as paypal_count
    FROM payments
    WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $month_filter);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get recent PayPal transactions
$paypal_transactions_query = "
    SELECT pt.*, 
           u.first_name, u.last_name,
           r.room_number
    FROM paypal_transactions pt
    LEFT JOIN users u ON pt.tenant_id = u.id
    LEFT JOIN rooms r ON pt.room_id = r.id
    ORDER BY pt.created_at DESC
    LIMIT 10
";
$paypal_transactions = $conn->query($paypal_transactions_query);

// Get tenants with active bookings for manual payment form
$tenants_query = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, b.room_id, r.room_number, r.price
    FROM users u
    JOIN bookings b ON u.id = b.tenant_id
    JOIN rooms r ON b.room_id = r.id
    WHERE b.status IN ('approved', 'checked_in')
    ORDER BY u.last_name, u.first_name
";
$tenants_result = $conn->query($tenants_query);

require_once '../includes/header.php';
?>

<style>
    .payments-page {
        padding: 2rem 0;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
    }
    
    .header-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .btn-paypal {
        background: linear-gradient(135deg, #0070ba 0%, #003087 100%);
        color: white;
    }
    
    .btn-outline {
        background: white;
        color: #475569;
        border: 2px solid #e2e8f0;
    }
    
    .btn-outline:hover {
        border-color: #667eea;
        color: #667eea;
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }
    
    .stat-card.confirmed::before {
        background: linear-gradient(90deg, #10b981, #059669);
    }
    
    .stat-card.pending::before {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }
    
    .stat-card.paypal::before {
        background: linear-gradient(90deg, #0070ba, #003087);
    }
    
    .stat-card.cash::before {
        background: linear-gradient(90deg, #8b5cf6, #7c3aed);
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .stat-card.confirmed .stat-icon {
        background: rgba(16, 185, 129, 0.1);
    }
    
    .stat-card.pending .stat-icon {
        background: rgba(245, 158, 11, 0.1);
    }
    
    .stat-card.paypal .stat-icon {
        background: rgba(0, 112, 186, 0.1);
    }
    
    .stat-card.cash .stat-icon {
        background: rgba(139, 92, 246, 0.1);
    }
    
    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .stat-count {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-top: 0.5rem;
    }
    
    /* Filters */
    .filters-section {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .filter-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .filter-group select:focus,
    .filter-group input:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Tabs */
    .tabs-container {
        margin-bottom: 1.5rem;
    }
    
    .tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0;
    }
    
    .tab-btn {
        padding: 1rem 1.5rem;
        background: none;
        border: none;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .tab-btn.active {
        color: #667eea;
    }
    
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    .tab-btn:hover:not(.active) {
        color: #1e293b;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Payments Table */
    .table-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .payments-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .payments-table th {
        background: #f8fafc;
        padding: 1rem 1.25rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .payments-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    
    .payments-table tr:hover {
        background: #f8fafc;
    }
    
    .tenant-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .tenant-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
    }
    
    .tenant-details h4 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
    }
    
    .tenant-details span {
        font-size: 0.8rem;
        color: #64748b;
    }
    
    .amount {
        font-weight: 700;
        font-size: 1rem;
        color: #10b981;
    }
    
    .room-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .status-badge.confirmed {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }
    
    .status-badge.pending {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }
    
    .status-badge.failed {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }
    
    .status-badge.refunded {
        background: rgba(107, 114, 128, 0.1);
        color: #4b5563;
    }
    
    .status-badge.cancelled {
        background: rgba(107, 114, 128, 0.1);
        color: #6b7280;
    }
    
    .status-badge.completed {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }
    
    .method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .method-badge.paypal {
        background: rgba(0, 112, 186, 0.1);
        color: #0070ba;
    }
    
    .method-badge.cash {
        background: rgba(139, 92, 246, 0.1);
        color: #7c3aed;
    }
    
    .method-badge.bank {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }
    
    .transaction-id {
        font-family: monospace;
        font-size: 0.75rem;
        color: #64748b;
        background: #f1f5f9;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: inline-block;
    }
    
    .action-dropdown {
        position: relative;
    }
    
    .action-btn {
        padding: 0.5rem 0.75rem;
        background: #f1f5f9;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .action-btn:hover {
        background: #e2e8f0;
    }
    
    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        min-width: 180px;
        z-index: 100;
        display: none;
        overflow: hidden;
    }
    
    .dropdown-menu.show {
        display: block;
    }
    
    .dropdown-item {
        display: block;
        padding: 0.75rem 1rem;
        color: #475569;
        text-decoration: none;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
    }
    
    .dropdown-item:hover {
        background: #f8fafc;
        color: #667eea;
    }
    
    .dropdown-item.danger {
        color: #dc2626;
    }
    
    .dropdown-item.danger:hover {
        background: #fef2f2;
    }
    
    /* PayPal Transactions Section */
    .paypal-section {
        margin-top: 2rem;
    }
    
    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .section-header h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    
    .paypal-icon {
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #0070ba 0%, #003087 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
    }
    
    /* Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    .modal {
        background: white;
        border-radius: 20px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        transform: translateY(20px);
        transition: all 0.3s ease;
    }
    
    .modal-overlay.show .modal {
        transform: translateY(0);
    }
    
    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .modal-close {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: none;
        background: #f1f5f9;
        cursor: pointer;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background: #e2e8f0;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
    }
    
    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }
    
    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }
    
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .empty-state h3 {
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .empty-state p {
        color: #64748b;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .payments-table {
            display: block;
            overflow-x: auto;
        }
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .header-actions {
            flex-wrap: wrap;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="container payments-page">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>💳 Payment Management</h1>
            <p style="color: #64748b; margin-top: 0.25rem;">Track and manage all payment transactions</p>
        </div>
        <div class="header-actions">
            <button onclick="openModal('recordPaymentModal')" class="btn btn-primary">
                ➕ Record Payment
            </button>
            <button onclick="window.print()" class="btn btn-outline">
                🖨️ Print Report
            </button>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card confirmed">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo format_currency($stats['total_confirmed'] ?? 0); ?></div>
            <div class="stat-label">Confirmed Payments</div>
            <div class="stat-count"><?php echo $stats['confirmed_count'] ?? 0; ?> transactions</div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo format_currency($stats['total_pending'] ?? 0); ?></div>
            <div class="stat-label">Pending Payments</div>
            <div class="stat-count"><?php echo $stats['pending_count'] ?? 0; ?> transactions</div>
        </div>
        
        <div class="stat-card paypal">
            <div class="stat-icon">🅿️</div>
            <div class="stat-value"><?php echo format_currency($stats['total_paypal'] ?? 0); ?></div>
            <div class="stat-label">PayPal Payments</div>
            <div class="stat-count"><?php echo $stats['paypal_count'] ?? 0; ?> transactions</div>
        </div>
        
        <div class="stat-card cash">
            <div class="stat-icon">💵</div>
            <div class="stat-value"><?php echo format_currency($stats['total_cash'] ?? 0); ?></div>
            <div class="stat-label">Cash Payments</div>
            <div class="stat-count"><?php echo ($stats['total_payments'] ?? 0) - ($stats['paypal_count'] ?? 0); ?> transactions</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Payment Method</label>
                    <select name="method" onchange="this.form.submit()">
                        <option value="all" <?php echo $method_filter === 'all' ? 'selected' : ''; ?>>All Methods</option>
                        <option value="paypal" <?php echo $method_filter === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                        <option value="cash" <?php echo $method_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="bank" <?php echo $method_filter === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Month</label>
                    <input type="month" name="month" value="<?php echo $month_filter; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name, room, or transaction ID...">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        🔍 Search
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('payments')">All Payments</button>
            <button class="tab-btn" onclick="switchTab('paypal')">PayPal Transactions</button>
        </div>
    </div>
    
    <!-- All Payments Tab -->
    <div id="payments-tab" class="tab-content active">
        <div class="table-container">
            <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Room</th>
                            <th>Amount</th>
                            <th>Period</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="tenant-info">
                                        <div class="tenant-avatar">
                                            <?php echo strtoupper(substr($payment['first_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div class="tenant-details">
                                            <h4><?php echo htmlspecialchars(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')); ?></h4>
                                            <span><?php echo htmlspecialchars($payment['email'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-badge">
                                        🏠 <?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="amount"><?php echo format_currency($payment['amount']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_period'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                    $method = $payment['payment_method'] ?? 'cash';
                                    $method_icons = ['paypal' => '🅿️', 'cash' => '💵', 'bank' => '🏦'];
                                    ?>
                                    <span class="method-badge <?php echo $method; ?>">
                                        <?php echo $method_icons[$method] ?? '💳'; ?>
                                        <?php echo ucfirst($method); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['paypal_capture_id']): ?>
                                        <span class="transaction-id" title="<?php echo htmlspecialchars($payment['paypal_capture_id']); ?>">
                                            <?php echo htmlspecialchars($payment['paypal_capture_id']); ?>
                                        </span>
                                    <?php elseif ($payment['paypal_transaction_id']): ?>
                                        <span class="transaction-id" title="<?php echo htmlspecialchars($payment['paypal_transaction_id']); ?>">
                                            <?php echo htmlspecialchars($payment['paypal_transaction_id']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?><br>
                                    <small style="color: #94a3b8;"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="action-btn" onclick="toggleDropdown(this)">⋮</button>
                                        <div class="dropdown-menu">
                                            <button class="dropdown-item" onclick="openStatusModal(<?php echo $payment['id']; ?>, '<?php echo $payment['status']; ?>')">
                                                ✏️ Update Status
                                            </button>
                                            <button class="dropdown-item" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                👁️ View Details
                                            </button>
                                            <?php if ($payment['status'] === 'confirmed'): ?>
                                                <button class="dropdown-item">
                                                    📄 Generate Receipt
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">💳</div>
                    <h3>No Payments Found</h3>
                    <p>No payment records match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- PayPal Transactions Tab -->
    <div id="paypal-tab" class="tab-content">
        <div class="table-container">
            <?php if ($paypal_transactions && $paypal_transactions->num_rows > 0): ?>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Room</th>
                            <th>Amount</th>
                            <th>PayPal Order ID</th>
                            <th>Capture ID</th>
                            <th>Payer Email</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($txn = $paypal_transactions->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="tenant-info">
                                        <div class="tenant-avatar" style="background: linear-gradient(135deg, #0070ba 0%, #003087 100%);">
                                            <?php echo strtoupper(substr($txn['first_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div class="tenant-details">
                                            <h4><?php echo htmlspecialchars(($txn['first_name'] ?? '') . ' ' . ($txn['last_name'] ?? '')); ?></h4>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-badge">
                                        🏠 <?php echo htmlspecialchars($txn['room_number'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="amount"><?php echo format_currency($txn['amount']); ?></span>
                                </td>
                                <td>
                                    <span class="transaction-id" title="<?php echo htmlspecialchars($txn['paypal_order_id']); ?>">
                                        <?php echo htmlspecialchars($txn['paypal_order_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($txn['capture_id']): ?>
                                        <span class="transaction-id" title="<?php echo htmlspecialchars($txn['capture_id']); ?>">
                                            <?php echo htmlspecialchars($txn['capture_id']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($txn['payer_email'] ?? '-'); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $txn['status']; ?>">
                                        <?php echo ucfirst($txn['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y h:i A', strtotime($txn['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🅿️</div>
                    <h3>No PayPal Transactions</h3>
                    <p>No PayPal transactions have been recorded yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>➕ Record Manual Payment</h3>
            <button class="modal-close" onclick="closeModal('recordPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tenant & Room</label>
                    <select name="tenant_id" id="tenantSelect" required onchange="updateRoomInfo()">
                        <option value="">Select Tenant</option>
                        <?php 
                        $tenants_result->data_seek(0);
                        while ($tenant = $tenants_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $tenant['id']; ?>" 
                                    data-room-id="<?php echo $tenant['room_id']; ?>"
                                    data-room="<?php echo htmlspecialchars($tenant['room_number']); ?>"
                                    data-price="<?php echo $tenant['price']; ?>">
                                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?> 
                                - Room <?php echo htmlspecialchars($tenant['room_number']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <input type="hidden" name="room_id" id="roomIdInput">
                
                <div class="form-group">
                    <label>Amount (₱)</label>
                    <input type="number" name="amount" id="amountInput" step="0.01" min="0" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Payment Period</label>
                    <input type="text" name="payment_period" placeholder="e.g., January 2025" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="paypal">PayPal (Manual Entry)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('recordPaymentModal')">Cancel</button>
                <button type="submit" name="record_payment" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Update Payment Status</h3>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="payment_id" id="statusPaymentId">
            <div class="modal-body">
                <div class="form-group">
                    <label>New Status</label>
                    <select name="status" id="statusSelect" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
}

// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Dropdown toggle
function toggleDropdown(btn) {
    // Close all other dropdowns
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        if (menu !== btn.nextElementSibling) {
            menu.classList.remove('show');
        }
    });
    btn.nextElementSibling.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Update room info when tenant is selected
function updateRoomInfo() {
    const select = document.getElementById('tenantSelect');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        document.getElementById('roomIdInput').value = option.dataset.roomId;
        document.getElementById('amountInput').value = option.dataset.price;
    } else {
        document.getElementById('roomIdInput').value = '';
        document.getElementById('amountInput').value = '';
    }
}

// Open status modal
function openStatusModal(paymentId, currentStatus) {
    document.getElementById('statusPaymentId').value = paymentId;
    document.getElementById('statusSelect').value = currentStatus;
    openModal('statusModal');
}

// View payment details (placeholder)
function viewPaymentDetails(paymentId) {
    alert('Payment details for ID: ' + paymentId + '\n(Full modal can be implemented)');
}

// Close modal when clicking overlay
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>