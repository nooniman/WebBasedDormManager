<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\admin\tenant_details.php

require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

// Get tenant ID from URL
$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tenant_id) {
    set_flash_message('Invalid tenant ID', 'error');
    redirect('admin/tenants');
}

// Fetch tenant details
$tenant_query = $conn->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM bookings WHERE tenant_id = u.id) as total_bookings,
           (SELECT COUNT(*) FROM bookings WHERE tenant_id = u.id AND status IN ('approved', 'checked_in')) as active_bookings,
           (SELECT COUNT(*) FROM payments WHERE tenant_id = u.id) as total_payments,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = u.id AND status = 'confirmed') as total_paid
    FROM users u
    WHERE u.id = ? AND u.role = 'tenant'
");
$tenant_query->bind_param("i", $tenant_id);
$tenant_query->execute();
$tenant = $tenant_query->get_result()->fetch_assoc();
$tenant_query->close();

if (!$tenant) {
    set_flash_message('Tenant not found', 'error');
    redirect('admin/tenants');
}

$page_title = 'Tenant Details - ' . $tenant['first_name'] . ' ' . $tenant['last_name'];

// Fetch tenant's bookings
$bookings_query = $conn->prepare("
    SELECT b.*, r.room_number, r.room_type, r.price
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.tenant_id = ?
    ORDER BY b.created_at DESC
");
$bookings_query->bind_param("i", $tenant_id);
$bookings_query->execute();
$bookings_result = $bookings_query->get_result();

// Fetch tenant's payments
$payments_query = $conn->prepare("
    SELECT p.*, r.room_number
    FROM payments p
    LEFT JOIN rooms r ON p.room_id = r.id
    WHERE p.tenant_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 20
");
$payments_query->bind_param("i", $tenant_id);
$payments_query->execute();
$payments_result = $payments_query->get_result();

// Get payment statistics
$payment_stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_payments,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as confirmed_total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_total,
        COUNT(CASE WHEN payment_method = 'paypal' THEN 1 END) as paypal_count,
        COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_count
    FROM payments
    WHERE tenant_id = ?
");
$payment_stats_query->bind_param("i", $tenant_id);
$payment_stats_query->execute();
$payment_stats = $payment_stats_query->get_result()->fetch_assoc();
$payment_stats_query->close();

require_once '../includes/header.php';
?>

<style>
    .tenant-details-page {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: white;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 1rem;
        opacity: 0.9;
        transition: all 0.3s ease;
    }
    
    .back-link:hover {
        opacity: 1;
        transform: translateX(-4px);
    }
    
    .profile-header {
        display: flex;
        align-items: center;
        gap: 2rem;
        flex-wrap: wrap;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 800;
        color: white;
        border: 3px solid rgba(255, 255, 255, 0.3);
    }
    
    .profile-info h1 {
        font-size: 2rem;
        font-weight: 800;
        margin: 0 0 0.5rem 0;
        color: white;
    }
    
    .profile-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-top: 1rem;
    }
    
    .profile-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
    }
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-pill.active {
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    
    .status-pill.inactive {
        background: rgba(239, 68, 68, 0.2);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-box {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        position: relative;
        overflow: hidden;
    }
    
    .stat-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }
    
    .stat-box.primary::before { background: linear-gradient(90deg, #667eea, #764ba2); }
    .stat-box.success::before { background: linear-gradient(90deg, #10b981, #059669); }
    .stat-box.warning::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
    .stat-box.info::before { background: linear-gradient(90deg, #0ea5e9, #0284c7); }
    
    .stat-box-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }
    
    .stat-box.primary .stat-box-icon { background: rgba(102, 126, 234, 0.1); }
    .stat-box.success .stat-box-icon { background: rgba(16, 185, 129, 0.1); }
    .stat-box.warning .stat-box-icon { background: rgba(245, 158, 11, 0.1); }
    .stat-box.info .stat-box-icon { background: rgba(14, 165, 233, 0.1); }
    
    .stat-box-value {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.25rem;
    }
    
    .stat-box-label {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 500;
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .section-card {
        background: white;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    
    .section-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .section-header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-body {
        padding: 1.5rem 2rem;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }
    
    .info-item {
        padding: 1rem;
        background: #f8fafc;
        border-radius: 12px;
    }
    
    .info-item.full {
        grid-column: 1 / -1;
    }
    
    .info-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        margin-bottom: 0.35rem;
        font-weight: 600;
    }
    
    .info-value {
        font-size: 1rem;
        font-weight: 600;
        color: #0f172a;
    }
    
    .booking-item, .payment-item {
        padding: 1.25rem;
        border-radius: 14px;
        background: #f8fafc;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .booking-item:hover, .payment-item:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
    }
    
    .booking-item:last-child, .payment-item:last-child {
        margin-bottom: 0;
    }
    
    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .room-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.85rem;
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border-radius: 8px;
        font-weight: 700;
        color: #667eea;
        font-size: 0.9rem;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.3rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-badge.approved, .status-badge.confirmed, .status-badge.checked_in {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
    }
    
    .status-badge.pending {
        background: rgba(245, 158, 11, 0.15);
        color: #b45309;
    }
    
    .status-badge.rejected, .status-badge.failed, .status-badge.cancelled {
        background: rgba(239, 68, 68, 0.15);
        color: #dc2626;
    }
    
    .status-badge.checked_out {
        background: rgba(99, 102, 241, 0.15);
        color: #4f46e5;
    }
    
    .item-details {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.875rem;
        color: #64748b;
    }
    
    .item-detail {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    
    .amount-display {
        font-size: 1.1rem;
        font-weight: 700;
        color: #10b981;
    }
    
    .method-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        font-size: 0.75rem;
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
    
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #94a3b8;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .empty-state h4 {
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
    
    .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }
        
        .profile-meta {
            justify-content: center;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Enhanced Page Header -->
<div class="page-header-enhanced">
    <div class="container">
        <a href="<?php echo ADMIN_URL; ?>/tenants" class="back-link">← Back to Tenants</a>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h1>
                <span class="status-pill <?php echo $tenant['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $tenant['is_active'] ? '✓ Active Account' : '✗ Inactive Account'; ?>
                </span>
                
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        📧 <?php echo htmlspecialchars($tenant['email']); ?>
                    </div>
                    <?php if ($tenant['phone']): ?>
                    <div class="profile-meta-item">
                        📱 <?php echo htmlspecialchars($tenant['phone']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="profile-meta-item">
                        📅 Joined <?php echo date('M j, Y', strtotime($tenant['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container tenant-details-page">
    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-box primary">
            <div class="stat-box-icon">🏠</div>
            <div class="stat-box-value"><?php echo $tenant['active_bookings']; ?></div>
            <div class="stat-box-label">Active Bookings</div>
        </div>
        
        <div class="stat-box success">
            <div class="stat-box-icon">💰</div>
            <div class="stat-box-value"><?php echo format_currency($tenant['total_paid']); ?></div>
            <div class="stat-box-label">Total Paid</div>
        </div>
        
        <div class="stat-box warning">
            <div class="stat-box-icon">⏳</div>
            <div class="stat-box-value"><?php echo format_currency($payment_stats['pending_total'] ?? 0); ?></div>
            <div class="stat-box-label">Pending Payments</div>
        </div>
        
        <div class="stat-box info">
            <div class="stat-box-icon">📊</div>
            <div class="stat-box-value"><?php echo $tenant['total_bookings']; ?></div>
            <div class="stat-box-label">Total Bookings</div>
        </div>
    </div>
    
    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div>
            <!-- Personal Information -->
            <div class="section-card" style="margin-bottom: 2rem;">
                <div class="section-header">
                    <h2>👤 Personal Information</h2>
                </div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">First Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($tenant['first_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($tenant['last_name']); ?></div>
                        </div>
                        <div class="info-item full">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($tenant['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($tenant['phone'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Account Status</div>
                            <div class="info-value">
                                <span class="status-badge <?php echo $tenant['is_active'] ? 'approved' : 'rejected'; ?>">
                                    <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($tenant['address']): ?>
                        <div class="info-item full">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($tenant['address']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-outline" onclick="toggleAccountStatus(<?php echo $tenant['id']; ?>, <?php echo $tenant['is_active'] ? 0 : 1; ?>)">
                            <?php echo $tenant['is_active'] ? '🚫 Deactivate Account' : '✓ Activate Account'; ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bookings -->
            <div class="section-card">
                <div class="section-header">
                    <h2>🏠 Bookings</h2>
                    <span style="color: #64748b; font-size: 0.9rem;"><?php echo $bookings_result->num_rows; ?> total</span>
                </div>
                <div class="section-body">
                    <?php if ($bookings_result->num_rows > 0): ?>
                        <?php while ($booking = $bookings_result->fetch_assoc()): ?>
                            <div class="booking-item">
                                <div class="item-header">
                                    <span class="room-badge">🚪 Room <?php echo htmlspecialchars($booking['room_number']); ?></span>
                                    <span class="status-badge <?php echo $booking['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                    </span>
                                </div>
                                <div class="item-details">
                                    <span class="item-detail">📅 <?php echo date('M j, Y', strtotime($booking['start_date'])); ?> - <?php echo date('M j, Y', strtotime($booking['end_date'])); ?></span>
                                    <span class="item-detail">🏷️ <?php echo ucfirst($booking['room_type']); ?></span>
                                    <span class="item-detail">💵 <?php echo format_currency($booking['price']); ?>/mo</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🏠</div>
                            <h4>No Bookings Yet</h4>
                            <p>This tenant hasn't made any bookings.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Payments -->
        <div>
            <div class="section-card">
                <div class="section-header">
                    <h2>💳 Payment History</h2>
                    <span style="color: #64748b; font-size: 0.9rem;"><?php echo $payment_stats['total_payments'] ?? 0; ?> payments</span>
                </div>
                <div class="section-body">
                    <!-- Payment Method Summary -->
                    <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <?php if (($payment_stats['paypal_count'] ?? 0) > 0): ?>
                            <span class="method-badge paypal">🅿️ <?php echo $payment_stats['paypal_count']; ?> PayPal</span>
                        <?php endif; ?>
                        <?php if (($payment_stats['cash_count'] ?? 0) > 0): ?>
                            <span class="method-badge cash">💵 <?php echo $payment_stats['cash_count']; ?> Cash</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($payments_result->num_rows > 0): ?>
                        <?php while ($payment = $payments_result->fetch_assoc()): ?>
                            <div class="payment-item">
                                <div class="item-header">
                                    <span class="amount-display"><?php echo format_currency($payment['amount']); ?></span>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>
                                <div class="item-details">
                                    <span class="item-detail">📅 <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></span>
                                    <span class="item-detail">🚪 Room <?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?></span>
                                    <span class="item-detail">
                                        <span class="method-badge <?php echo $payment['payment_method']; ?>">
                                            <?php 
                                            $icons = ['paypal' => '🅿️', 'cash' => '💵', 'bank' => '🏦'];
                                            echo ($icons[$payment['payment_method']] ?? '💳') . ' ' . ucfirst($payment['payment_method']);
                                            ?>
                                        </span>
                                    </span>
                                </div>
                                <?php if ($payment['payment_period']): ?>
                                    <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #94a3b8;">
                                        Period: <?php echo htmlspecialchars($payment['payment_period']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                        
                        <?php if ($payment_stats['total_payments'] > 20): ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="<?php echo ADMIN_URL; ?>/payments?search=<?php echo urlencode($tenant['email']); ?>" class="btn btn-outline" style="padding: 0.65rem 1.25rem; font-size: 0.9rem;">
                                    View All Payments →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💳</div>
                            <h4>No Payments Yet</h4>
                            <p>This tenant hasn't made any payments.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAccountStatus(tenantId, newStatus) {
    const action = newStatus === 1 ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this account?`)) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo ADMIN_URL; ?>/tenants';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo generate_csrf_token(); ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'toggle_status';
        actionInput.value = '1';
        
        const tenantInput = document.createElement('input');
        tenantInput.type = 'hidden';
        tenantInput.name = 'tenant_id';
        tenantInput.value = tenantId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'is_active';
        statusInput.value = newStatus;
        
        form.appendChild(csrfInput);
        form.appendChild(actionInput);
        form.appendChild(tenantInput);
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
