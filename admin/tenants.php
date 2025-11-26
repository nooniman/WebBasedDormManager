<?php
// filepath: c:\xampp\htdocs\dormitory-management-system\admin\tenants.php

require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Tenants';
require_once '../includes/header.php';

// Fetch all tenants with payment stats
$query = "
    SELECT u.*, 
           COUNT(DISTINCT p.id) as payment_count,
           COALESCE(SUM(CASE WHEN p.status = 'confirmed' THEN p.amount ELSE 0 END), 0) as total_paid,
           COUNT(DISTINCT CASE WHEN p.payment_method = 'paypal' THEN p.id END) as paypal_payments,
           (SELECT COUNT(*) FROM bookings WHERE tenant_id = u.id AND status IN ('approved', 'checked_in')) as active_bookings
    FROM users u
    LEFT JOIN payments p ON u.id = p.tenant_id
    WHERE u.role = 'tenant' 
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$result = $conn->query($query);
?>

<style>
    .tenants-page { animation: fadeIn 0.5s ease-out; }
    
    .tenant-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .tenant-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: #667eea;
    }
    
    .tenant-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.5rem;
    }
    
    .payment-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .payment-badge.paypal {
        background: rgba(0, 112, 186, 0.1);
        color: #0070ba;
    }
</style>

<div class="page-header-enhanced">
    <div class="container">
        <h1>👥 Manage Tenants</h1>
        <p class="subtitle">View and manage all registered tenants</p>
    </div>
</div>

<div class="container tenants-page">
    <?php if ($result && $result->num_rows > 0): ?>
        <div class="grid grid-3" style="gap: 1.5rem;">
            <?php while ($tenant = $result->fetch_assoc()): ?>
                <div class="tenant-card">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div class="tenant-avatar">
                            <?php echo strtoupper(substr($tenant['first_name'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0; font-size: 1.1rem;">
                                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                            </h3>
                            <div style="font-size: 0.875rem; color: #64748b;">
                                <?php echo htmlspecialchars($tenant['email']); ?>
                            </div>
                        </div>
                        <span class="badge-enhanced <?php echo $tenant['is_active'] ? 'success' : 'danger'; ?>">
                            <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="padding: 0.75rem; background: #f8fafc; border-radius: 8px;">
                            <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Total Paid</div>
                            <div style="font-weight: 700; color: #10b981;"><?php echo format_currency($tenant['total_paid']); ?></div>
                        </div>
                        <div style="padding: 0.75rem; background: #f8fafc; border-radius: 8px;">
                            <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Bookings</div>
                            <div style="font-weight: 700; color: #667eea;"><?php echo $tenant['active_bookings']; ?> active</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <?php if ($tenant['paypal_payments'] > 0): ?>
                                <span class="payment-badge paypal">🅿️ <?php echo $tenant['paypal_payments']; ?> PayPal</span>
                            <?php endif; ?>
                        </div>
                        <a href="tenant_details.php?id=<?php echo $tenant['id']; ?>" class="btn-enhanced primary sm">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state-enhanced">
            <div class="icon">👥</div>
            <h3>No Tenants Found</h3>
            <p>No tenants have registered yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>