<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\admin\tenants.php

require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Tenants';

// Handle status toggle via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $tenant_id = (int) ($_POST['tenant_id'] ?? 0);
        $is_active = (int) ($_POST['is_active'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'tenant'");
        $stmt->bind_param("ii", $is_active, $tenant_id);
        
        if ($stmt->execute()) {
            set_flash_message('Tenant status updated successfully', 'success');
        } else {
            set_flash_message('Failed to update tenant status', 'error');
        }
        $stmt->close();
    }
    redirect('admin/tenants');
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

// Base query with payment stats
$where_conditions = ["u.role = 'tenant'"];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if ($status_filter === 'active') {
    $where_conditions[] = "u.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "u.is_active = 0";
}

// Order by clause
$order_by = match ($sort) {
    'oldest' => 'u.created_at ASC',
    'name' => 'u.first_name ASC, u.last_name ASC',
    'total_paid' => 'total_paid DESC',
    default => 'u.created_at DESC'
};

$where_clause = implode(' AND ', $where_conditions);

$query = "
    SELECT u.*, 
           COUNT(DISTINCT p.id) as payment_count,
           COALESCE(SUM(CASE WHEN p.status = 'confirmed' THEN p.amount ELSE 0 END), 0) as total_paid,
           COUNT(DISTINCT CASE WHEN p.payment_method = 'paypal' THEN p.id END) as paypal_payments,
           COUNT(DISTINCT CASE WHEN p.payment_method = 'cash' THEN p.id END) as cash_payments,
           (SELECT COUNT(*) FROM bookings WHERE tenant_id = u.id AND status IN ('approved', 'checked_in')) as active_bookings,
           (SELECT COUNT(*) FROM bookings WHERE tenant_id = u.id) as total_bookings
    FROM users u
    LEFT JOIN payments p ON u.id = p.tenant_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY $order_by
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Calculate summary stats
$stats_query = "
    SELECT 
        COUNT(*) as total_tenants,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tenants,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_tenants
    FROM users WHERE role = 'tenant'
";
$stats = $conn->query($stats_query)->fetch_assoc();

$payment_stats_query = "
    SELECT 
        COALESCE(SUM(CASE WHEN p.status = 'confirmed' THEN p.amount ELSE 0 END), 0) as total_revenue
    FROM payments p
    JOIN users u ON p.tenant_id = u.id
    WHERE u.role = 'tenant'
";
$payment_stats = $conn->query($payment_stats_query)->fetch_assoc();

require_once '../includes/header.php';
?>

<style>
    .tenants-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 640px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
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
    
    .stat-card.purple::before { background: linear-gradient(90deg, #667eea, #764ba2); }
    .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
    .stat-card.yellow::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
    .stat-card.blue::before { background: linear-gradient(90deg, #0ea5e9, #0284c7); }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }
    
    .stat-card.purple .stat-icon { background: rgba(102, 126, 234, 0.12); }
    .stat-card.green .stat-icon { background: rgba(16, 185, 129, 0.12); }
    .stat-card.yellow .stat-icon { background: rgba(245, 158, 11, 0.12); }
    .stat-card.blue .stat-icon { background: rgba(14, 165, 233, 0.12); }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 500;
    }
    
    /* Filters Section */
    .filters-section {
        background: white;
        border-radius: 20px;
        padding: 1.5rem 2rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        margin-bottom: 2rem;
    }
    
    .filters-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }
    
    .search-box {
        flex: 1;
        min-width: 280px;
        position: relative;
    }
    
    .search-box input {
        width: 100%;
        padding: 0.9rem 1.25rem 0.9rem 3rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .search-box::before {
        content: '🔍';
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.1rem;
    }
    
    .filter-select {
        padding: 0.9rem 2.5rem 0.9rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E") right 0.75rem center no-repeat;
        background-size: 1.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        -webkit-appearance: none;
        appearance: none;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #667eea;
        background-color: white;
    }
    
    /* View Toggle */
    .view-toggle {
        display: flex;
        background: #f1f5f9;
        border-radius: 12px;
        padding: 4px;
    }
    
    .view-btn {
        padding: 0.65rem 1rem;
        border: none;
        background: transparent;
        cursor: pointer;
        border-radius: 8px;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        color: #64748b;
    }
    
    .view-btn.active {
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        color: #0f172a;
    }
    
    /* Table View */
    .tenants-table-wrapper {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    
    .tenants-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .tenants-table thead {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    
    .tenants-table th {
        padding: 1.25rem 1.5rem;
        text-align: left;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #475569;
    }
    
    .tenants-table td {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    
    .tenants-table tbody tr {
        transition: all 0.3s ease;
    }
    
    .tenants-table tbody tr:hover {
        background: #f8fafc;
    }
    
    .tenant-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .tenant-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.1rem;
    }
    
    .tenant-info h4 {
        margin: 0 0 0.25rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #0f172a;
    }
    
    .tenant-info p {
        margin: 0;
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .status-pill.active {
        background: rgba(16, 185, 129, 0.12);
        color: #059669;
    }
    
    .status-pill.inactive {
        background: rgba(239, 68, 68, 0.12);
        color: #dc2626;
    }
    
    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
    }
    
    .money-value {
        font-weight: 700;
        color: #10b981;
        font-size: 1rem;
    }
    
    .payment-badges {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .payment-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.65rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .payment-chip.paypal {
        background: rgba(0, 112, 186, 0.1);
        color: #0070ba;
    }
    
    .payment-chip.cash {
        background: rgba(139, 92, 246, 0.1);
        color: #7c3aed;
    }
    
    .actions-cell {
        display: flex;
        gap: 0.5rem;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
        background: #f1f5f9;
        color: #64748b;
    }
    
    .action-btn:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateY(-2px);
    }
    
    .action-btn.danger:hover {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    /* Grid View */
    .tenants-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.5rem;
    }
    
    .tenant-card {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        border: 2px solid transparent;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    .tenant-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .tenant-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }
    
    .tenant-card:hover::before {
        opacity: 1;
    }
    
    .card-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    
    .card-avatar {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .card-info {
        flex: 1;
        min-width: 0;
    }
    
    .card-info h3 {
        margin: 0 0 0.35rem 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
    }
    
    .card-info .email {
        font-size: 0.875rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .card-info .phone {
        font-size: 0.85rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }
    
    .card-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    
    .card-stat {
        padding: 0.9rem;
        background: #f8fafc;
        border-radius: 12px;
        text-align: center;
    }
    
    .card-stat-value {
        font-size: 1.25rem;
        font-weight: 800;
        margin-bottom: 0.15rem;
    }
    
    .card-stat-value.success { color: #10b981; }
    .card-stat-value.primary { color: #667eea; }
    
    .card-stat-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        font-weight: 600;
    }
    
    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1.25rem;
        border-top: 1px solid #e2e8f0;
    }
    
    .view-details-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .view-details-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1.5rem;
    }
    
    .empty-state h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }
    
    .empty-state p {
        color: #64748b;
        max-width: 400px;
        margin: 0 auto;
    }
    
    /* Result count */
    .results-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        color: #64748b;
    }
    
    .results-count {
        font-weight: 600;
    }
    
    .clear-filters {
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    
    .clear-filters:hover {
        text-decoration: underline;
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <h1>👥 Manage Tenants</h1>
        <p class="subtitle">View and manage all registered tenants and their payment history</p>
    </div>
</div>

<div class="container tenants-page">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo $stats['total_tenants'] ?? 0; ?></div>
            <div class="stat-label">Total Tenants</div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon">✓</div>
            <div class="stat-value"><?php echo $stats['active_tenants'] ?? 0; ?></div>
            <div class="stat-label">Active Accounts</div>
        </div>
        
        <div class="stat-card yellow">
            <div class="stat-icon">⏸️</div>
            <div class="stat-value"><?php echo $stats['inactive_tenants'] ?? 0; ?></div>
            <div class="stat-label">Inactive Accounts</div>
        </div>
        
        <div class="stat-card blue">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?php echo format_currency($payment_stats['total_revenue'] ?? 0); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    
    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" action="" class="filters-row" id="filterForm">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <select name="status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
            </select>
            
            <select name="sort" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>By Name</option>
                <option value="total_paid" <?php echo $sort === 'total_paid' ? 'selected' : ''; ?>>Highest Paid</option>
            </select>
            
            <div class="view-toggle">
                <button type="button" class="view-btn active" onclick="switchView('grid')" id="gridBtn">⊞</button>
                <button type="button" class="view-btn" onclick="switchView('table')" id="tableBtn">☰</button>
            </div>
        </form>
    </div>
    
    <?php if (!empty($search) || $status_filter !== 'all'): ?>
    <div class="results-info">
        <span class="results-count">
            Found <?php echo $result ? $result->num_rows : 0; ?> tenant(s)
            <?php if (!empty($search)): ?>for "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
        </span>
        <a href="<?php echo ADMIN_URL; ?>/tenants" class="clear-filters">✕ Clear Filters</a>
    </div>
    <?php endif; ?>
    
    <?php if ($result && $result->num_rows > 0): ?>
        <!-- Grid View -->
        <div class="tenants-grid" id="gridView">
            <?php 
            $tenants_data = [];
            while ($tenant = $result->fetch_assoc()): 
                $tenants_data[] = $tenant;
            ?>
                <div class="tenant-card">
                    <div class="card-header">
                        <div class="card-avatar">
                            <?php echo strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)); ?>
                        </div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h3>
                            <div class="email"><?php echo htmlspecialchars($tenant['email']); ?></div>
                            <?php if ($tenant['phone']): ?>
                                <div class="phone">📱 <?php echo htmlspecialchars($tenant['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="status-pill <?php echo $tenant['is_active'] ? 'active' : 'inactive'; ?>">
                            <span class="status-dot"></span>
                            <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <div class="card-stats">
                        <div class="card-stat">
                            <div class="card-stat-value success"><?php echo format_currency($tenant['total_paid']); ?></div>
                            <div class="card-stat-label">Total Paid</div>
                        </div>
                        <div class="card-stat">
                            <div class="card-stat-value primary"><?php echo $tenant['active_bookings']; ?></div>
                            <div class="card-stat-label">Active Bookings</div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="payment-badges">
                            <?php if ($tenant['paypal_payments'] > 0): ?>
                                <span class="payment-chip paypal">🅿️ <?php echo $tenant['paypal_payments']; ?></span>
                            <?php endif; ?>
                            <?php if ($tenant['cash_payments'] > 0): ?>
                                <span class="payment-chip cash">💵 <?php echo $tenant['cash_payments']; ?></span>
                            <?php endif; ?>
                            <?php if ($tenant['paypal_payments'] == 0 && $tenant['cash_payments'] == 0): ?>
                                <span style="color: #94a3b8; font-size: 0.85rem;">No payments</span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo ADMIN_URL; ?>/tenant_details?id=<?php echo $tenant['id']; ?>" class="view-details-btn">
                            View Details →
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Table View -->
        <div class="tenants-table-wrapper" id="tableView" style="display: none;">
            <table class="tenants-table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Status</th>
                        <th>Total Paid</th>
                        <th>Bookings</th>
                        <th>Payments</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants_data as $tenant): ?>
                        <tr>
                            <td>
                                <div class="tenant-cell">
                                    <div class="tenant-avatar">
                                        <?php echo strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="tenant-info">
                                        <h4><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($tenant['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $tenant['is_active'] ? 'active' : 'inactive'; ?>">
                                    <span class="status-dot"></span>
                                    <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="money-value"><?php echo format_currency($tenant['total_paid']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo $tenant['active_bookings']; ?></strong> active
                                <span style="color: #94a3b8;">/ <?php echo $tenant['total_bookings']; ?> total</span>
                            </td>
                            <td>
                                <div class="payment-badges">
                                    <?php if ($tenant['paypal_payments'] > 0): ?>
                                        <span class="payment-chip paypal">🅿️ <?php echo $tenant['paypal_payments']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($tenant['cash_payments'] > 0): ?>
                                        <span class="payment-chip cash">💵 <?php echo $tenant['cash_payments']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="color: #64748b; font-size: 0.9rem;">
                                <?php echo date('M j, Y', strtotime($tenant['created_at'])); ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="<?php echo ADMIN_URL; ?>/tenant_details?id=<?php echo $tenant['id']; ?>" 
                                       class="action-btn" title="View Details">👁️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <h3>No Tenants Found</h3>
            <p>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    No tenants match your search criteria. Try adjusting your filters.
                <?php else: ?>
                    No tenants have registered yet. Tenants will appear here once they sign up.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
    function switchView(view) {
        const gridView = document.getElementById('gridView');
        const tableView = document.getElementById('tableView');
        const gridBtn = document.getElementById('gridBtn');
        const tableBtn = document.getElementById('tableBtn');
        
        if (view === 'grid') {
            gridView.style.display = 'grid';
            tableView.style.display = 'none';
            gridBtn.classList.add('active');
            tableBtn.classList.remove('active');
            localStorage.setItem('tenantsView', 'grid');
        } else {
            gridView.style.display = 'none';
            tableView.style.display = 'block';
            gridBtn.classList.remove('active');
            tableBtn.classList.add('active');
            localStorage.setItem('tenantsView', 'table');
        }
    }
    
    // Restore saved view preference
    document.addEventListener('DOMContentLoaded', function() {
        const savedView = localStorage.getItem('tenantsView') || 'grid';
        switchView(savedView);
    });
</script>

<?php require_once '../includes/footer.php'; ?>