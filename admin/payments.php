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
        redirect('admin/payments');
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
            redirect('admin/payments');
        }    // Handle manual payment recording
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
        redirect('admin/payments');
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
    
    /* Receipt Modal Styles */
    .modal.modal-lg {
        max-width: 600px;
    }
    
    .receipt-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .receipt-header-section {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .receipt-header-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .receipt-logo {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }
    
    .receipt-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
    }
    
    .receipt-subtitle {
        opacity: 0.9;
        margin-top: 0.25rem;
    }
    
    .receipt-body-section {
        padding: 1.5rem;
    }
    
    .receipt-id-box {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1.25rem;
        border: 1px dashed #cbd5e1;
        text-align: center;
    }
    
    .receipt-id-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    
    .receipt-id-value {
        font-family: monospace;
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .receipt-amount-section {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        border: 2px solid #a7f3d0;
        text-align: center;
    }
    
    .receipt-amount-label {
        font-size: 0.85rem;
        color: #047857;
        margin-bottom: 0.25rem;
    }
    
    .receipt-amount-value {
        font-size: 2rem;
        font-weight: 800;
        color: #059669;
    }
    
    .receipt-details-section {
        margin-bottom: 1rem;
    }
    
    .receipt-section-title {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .receipt-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        font-size: 0.9rem;
    }
    
    .receipt-row .label {
        color: #64748b;
    }
    
    .receipt-row .value {
        font-weight: 600;
        color: #1e293b;
        text-align: right;
    }
    
    .receipt-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
        margin: 1rem 0;
    }
    
    .receipt-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .receipt-status-badge.confirmed {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
    }
    
    .receipt-status-badge.pending {
        background: rgba(245, 158, 11, 0.15);
        color: #b45309;
    }
    
    .receipt-status-badge.failed {
        background: rgba(239, 68, 68, 0.15);
        color: #b91c1c;
    }
    
    .receipt-method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .receipt-method-badge.paypal {
        background: rgba(0, 112, 186, 0.1);
        color: #0070ba;
    }
    
    .receipt-method-badge.cash {
        background: rgba(139, 92, 246, 0.1);
        color: #7c3aed;
    }
    
    .receipt-method-badge.bank {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }
    
    .receipt-footer-section {
        background: #f8fafc;
        padding: 1rem 1.5rem;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: center;
        gap: 0.75rem;
    }
    
    .btn-print {
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        color: white;
    }
    
    .btn-print:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
    }
    
    /* Print Styles */
    @media print {
        /* Hide everything first */
        body * {
            visibility: hidden !important;
        }
        
        /* Show only the receipt container and its children */
        #receiptModal,
        #receiptModal .modal,
        #receiptModal .receipt-container,
        #receiptModal .receipt-container * {
            visibility: visible !important;
        }
        
        /* Reset the modal overlay */
        #receiptModal.modal-overlay {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: auto !important;
            background: white !important;
            display: block !important;
            opacity: 1 !important;
            overflow: visible !important;
        }
        
        /* Reset the modal */
        #receiptModal .modal {
            position: relative !important;
            transform: none !important;
            max-width: 100% !important;
            width: 100% !important;
            max-height: none !important;
            box-shadow: none !important;
            margin: 0 !important;
            border-radius: 0 !important;
        }
        
        /* Position the receipt */
        #receiptModal .receipt-container {
            position: relative !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 600px !important;
            margin: 0 auto !important;
            box-shadow: none !important;
        }
        
        /* Hide non-printable elements */
        .receipt-footer-section,
        .modal-close,
        .modal-header {
            display: none !important;
        }
        
        /* Preserve colors */
        .receipt-header-section {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            background: #10b981 !important;
        }
        
        .receipt-amount-section {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            background: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
        }
        
        .receipt-status-badge,
        .receipt-method-badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        
        .receipt-status-badge.confirmed {
            background: rgba(16, 185, 129, 0.15) !important;
            color: #059669 !important;
        }
        
        .receipt-status-badge.pending {
            background: rgba(245, 158, 11, 0.15) !important;
            color: #b45309 !important;
        }
        
        .receipt-method-badge.paypal {
            background: rgba(0, 112, 186, 0.1) !important;
            color: #0070ba !important;
        }
        
        .receipt-method-badge.cash {
            background: rgba(139, 92, 246, 0.1) !important;
            color: #7c3aed !important;
        }
        
        .receipt-method-badge.bank {
            background: rgba(59, 130, 246, 0.1) !important;
            color: #2563eb !important;
        }
        
        .receipt-id-box {
            background: #f8fafc !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        /* Page settings */
        @page {
            size: A4;
            margin: 20mm;
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
                    <input type="text" name="search" placeholder="Search tenant, room, transaction..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Search</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('payments', this)">💳 All Payments</button>
            <button class="tab-btn" onclick="switchTab('paypal', this)">🅿️ PayPal Transactions</button>
        </div>
    </div>
    
    <!-- Payments Tab -->
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
                                            <?php echo strtoupper(substr($payment['first_name'] ?? 'U', 0, 1) . substr($payment['last_name'] ?? 'N', 0, 1)); ?>
                                        </div>
                                        <div class="tenant-details">
                                            <h4><?php echo htmlspecialchars(($payment['first_name'] ?? 'Unknown') . ' ' . ($payment['last_name'] ?? '')); ?></h4>
                                            <span><?php echo htmlspecialchars($payment['email'] ?? ''); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-badge">🚪 <?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="amount"><?php echo format_currency($payment['amount']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="method-badge <?php echo $payment['payment_method']; ?>">
                                        <?php 
                                        $method_icons = ['paypal' => '🅿️', 'cash' => '💵', 'bank' => '🏦'];
                                        echo ($method_icons[$payment['payment_method']] ?? '💳') . ' ' . ucfirst($payment['payment_method']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="action-btn" onclick="toggleDropdown(this)">⋯</button>
                                        <div class="dropdown-menu">
                                            <button class="dropdown-item" onclick="viewReceipt(<?php echo htmlspecialchars(json_encode([
                                                'id' => $payment['id'],
                                                'tenant_name' => ($payment['first_name'] ?? 'Unknown') . ' ' . ($payment['last_name'] ?? ''),
                                                'tenant_email' => $payment['email'] ?? '',
                                                'room_number' => $payment['room_number'] ?? 'N/A',
                                                'room_type' => $payment['room_type'] ?? 'N/A',
                                                'amount' => $payment['amount'],
                                                'period' => $payment['payment_period'] ?? 'N/A',
                                                'method' => $payment['payment_method'],
                                                'status' => $payment['status'],
                                                'date' => date('F j, Y', strtotime($payment['payment_date'])),
                                                'time' => date('g:i A', strtotime($payment['payment_date'])),
                                                'transaction_id' => $payment['paypal_transaction_id'] ?? '',
                                                'capture_id' => $payment['paypal_capture_id'] ?? '',
                                                'notes' => $payment['notes'] ?? ''
                                            ])); ?>)">🧾 View Receipt</button>
                                            <button class="dropdown-item" onclick="openStatusModal(<?php echo $payment['id']; ?>, '<?php echo $payment['status']; ?>')">✏️ Update Status</button>
                                            <?php if ($payment['paypal_transaction_id']): ?>
                                                <span class="dropdown-item" style="cursor: default; color: #94a3b8;">
                                                    <span class="transaction-id"><?php echo htmlspecialchars($payment['paypal_transaction_id']); ?></span>
                                                </span>
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
                    <h3>No payments found</h3>
                    <p>Try adjusting your filters or record a new payment.</p>
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
                            <th>Order ID</th>
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
                                            <?php echo strtoupper(substr($txn['first_name'] ?? 'U', 0, 1) . substr($txn['last_name'] ?? 'N', 0, 1)); ?>
                                        </div>
                                        <div class="tenant-details">
                                            <h4><?php echo htmlspecialchars(($txn['first_name'] ?? 'Unknown') . ' ' . ($txn['last_name'] ?? '')); ?></h4>
                                            <span><?php echo htmlspecialchars($txn['payer_email'] ?? ''); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-badge">🚪 <?php echo htmlspecialchars($txn['room_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="amount"><?php echo format_currency($txn['amount']); ?></span>
                                </td>
                                <td>
                                    <span class="transaction-id"><?php echo htmlspecialchars($txn['paypal_order_id']); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $txn['status']; ?>">
                                        <?php echo ucfirst($txn['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($txn['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🅿️</div>
                    <h3>No PayPal transactions</h3>
                    <p>PayPal transactions will appear here once tenants make payments.</p>
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

<!-- Receipt Modal -->
<div id="receiptModal" class="modal-overlay">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>🧾 Payment Receipt</h3>
            <button class="modal-close" onclick="closeModal('receiptModal')">&times;</button>
        </div>
        <div class="receipt-container" id="receiptContent">
            <!-- Receipt Header -->
            <div class="receipt-header-section">
                <div class="receipt-logo">🏠</div>
                <h2 class="receipt-title">Dormitory Management</h2>
                <p class="receipt-subtitle">Official Payment Receipt</p>
            </div>
            
            <!-- Receipt Body -->
            <div class="receipt-body-section">
                <!-- Receipt ID -->
                <div class="receipt-id-box">
                    <div class="receipt-id-label">Receipt Number</div>
                    <div class="receipt-id-value" id="receipt-id">RCP-00000000</div>
                </div>
                
                <!-- Amount Box -->
                <div class="receipt-amount-section">
                    <div class="receipt-amount-label">Amount Paid</div>
                    <div class="receipt-amount-value" id="receipt-amount">₱0.00</div>
                </div>
                
                <!-- Payment Details -->
                <div class="receipt-details-section">
                    <div class="receipt-section-title">Payment Information</div>
                    <div class="receipt-row">
                        <span class="label">Date</span>
                        <span class="value" id="receipt-date">-</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Time</span>
                        <span class="value" id="receipt-time">-</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Payment Period</span>
                        <span class="value" id="receipt-period">-</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Status</span>
                        <span class="value" id="receipt-status">-</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Method</span>
                        <span class="value" id="receipt-method">-</span>
                    </div>
                </div>
                
                <div class="receipt-divider"></div>
                
                <div class="receipt-details-section">
                    <div class="receipt-section-title">Room Details</div>
                    <div class="receipt-row">
                        <span class="label">Room Number</span>
                        <span class="value" id="receipt-room">-</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Room Type</span>
                        <span class="value" id="receipt-room-type">-</span>
                    </div>
                </div>
                
                <div class="receipt-divider"></div>
                
                <div class="receipt-details-section">
                    <div class="receipt-section-title">Tenant Information</div>
                    <div class="receipt-row">
                        <span class="label">Name</span>
                        <span class="value" id="receipt-tenant-name">-</span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Email</span>
                        <span class="value" id="receipt-tenant-email">-</span>
                    </div>
                </div>
                
                <div id="receipt-transaction-section" style="display: none;">
                    <div class="receipt-divider"></div>
                    <div class="receipt-details-section">
                        <div class="receipt-section-title">Transaction Details</div>
                        <div class="receipt-row" id="receipt-txn-row">
                            <span class="label">Transaction ID</span>
                            <span class="value" id="receipt-transaction-id" style="font-family: monospace; font-size: 0.85rem;">-</span>
                        </div>
                        <div class="receipt-row" id="receipt-capture-row" style="display: none;">
                            <span class="label">Capture ID</span>
                            <span class="value" id="receipt-capture-id" style="font-family: monospace; font-size: 0.85rem;">-</span>
                        </div>
                    </div>
                </div>
                
                <div id="receipt-notes-section" style="display: none;">
                    <div class="receipt-divider"></div>
                    <div class="receipt-details-section">
                        <div class="receipt-section-title">Notes</div>
                        <p id="receipt-notes" style="color: #64748b; font-size: 0.9rem; margin: 0;">-</p>
                    </div>
                </div>
            </div>
            
            <!-- Receipt Footer -->
            <div class="receipt-footer-section">
                <button onclick="printReceipt()" class="btn btn-print">🖨️ Print Receipt</button>
                <button onclick="closeModal('receiptModal')" class="btn btn-outline">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    btn.classList.add('active');
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

// Format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// View Receipt
function viewReceipt(payment) {
    // Populate receipt fields
    document.getElementById('receipt-id').textContent = 'RCP-' + String(payment.id).padStart(8, '0');
    document.getElementById('receipt-amount').textContent = formatCurrency(payment.amount);
    document.getElementById('receipt-date').textContent = payment.date;
    document.getElementById('receipt-time').textContent = payment.time;
    document.getElementById('receipt-period').textContent = payment.period;
    
    // Status badge
    const statusBadgeClass = payment.status === 'confirmed' ? 'confirmed' : 
                             payment.status === 'pending' ? 'pending' : 'failed';
    document.getElementById('receipt-status').innerHTML = 
        `<span class="receipt-status-badge ${statusBadgeClass}">✓ ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</span>`;
    
    // Method badge
    const methodIcons = { paypal: '🅿️', cash: '💵', bank: '🏦' };
    const methodIcon = methodIcons[payment.method] || '💳';
    document.getElementById('receipt-method').innerHTML = 
        `<span class="receipt-method-badge ${payment.method}">${methodIcon} ${payment.method.charAt(0).toUpperCase() + payment.method.slice(1)}</span>`;
    
    // Room details
    document.getElementById('receipt-room').textContent = payment.room_number;
    document.getElementById('receipt-room-type').textContent = payment.room_type.charAt(0).toUpperCase() + payment.room_type.slice(1);
    
    // Tenant info
    document.getElementById('receipt-tenant-name').textContent = payment.tenant_name;
    document.getElementById('receipt-tenant-email').textContent = payment.tenant_email || '-';
    
    // Transaction details (for PayPal)
    if (payment.transaction_id) {
        document.getElementById('receipt-transaction-section').style.display = 'block';
        document.getElementById('receipt-transaction-id').textContent = payment.transaction_id;
        
        if (payment.capture_id) {
            document.getElementById('receipt-capture-row').style.display = 'flex';
            document.getElementById('receipt-capture-id').textContent = payment.capture_id;
        } else {
            document.getElementById('receipt-capture-row').style.display = 'none';
        }
    } else {
        document.getElementById('receipt-transaction-section').style.display = 'none';
    }
    
    // Notes
    if (payment.notes) {
        document.getElementById('receipt-notes-section').style.display = 'block';
        document.getElementById('receipt-notes').textContent = payment.notes;
    } else {
        document.getElementById('receipt-notes-section').style.display = 'none';
    }
    
    openModal('receiptModal');
}

// Print Receipt
function printReceipt() {
    // Ensure the modal is shown before printing
    const modal = document.getElementById('receiptModal');
    modal.classList.add('show');
    
    // Small delay to ensure modal is rendered
    setTimeout(() => {
        window.print();
    }, 100);
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