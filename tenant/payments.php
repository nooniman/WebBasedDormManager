<?php
// filepath: tenant/payments.php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'My Payments';
require_once '../includes/header.php';

$tenant_id = $_SESSION['user_id'];

// Get all payments
$query = "SELECT * FROM payments WHERE tenant_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$payments = $stmt->get_result();
$stmt->close();

// Get payment summary
$summary_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as confirmed_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM payments 
    WHERE tenant_id = ?
";
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="container">
    <h1 class="mb-4">My Payments</h1>
    
    <!-- Payment Summary -->
    <div class="grid grid-3 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <h3 style="color: var(--primary-color); font-size: 2rem; margin-bottom: 0.5rem;">
                    <?php echo $summary['total_count'] ?? 0; ?>
                </h3>
                <p style="color: var(--text-light);">Total Payments</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body text-center">
                <h3 style="color: #10b981; font-size: 2rem; margin-bottom: 0.5rem;">
                    <?php echo format_currency($summary['confirmed_amount'] ?? 0); ?>
                </h3>
                <p style="color: var(--text-light);">Confirmed</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body text-center">
                <h3 style="color: #f59e0b; font-size: 2rem; margin-bottom: 0.5rem;">
                    <?php echo format_currency($summary['pending_amount'] ?? 0); ?>
                </h3>
                <p style="color: var(--text-light);">Pending</p>
            </div>
        </div>
    </div>
    
    <!-- Payments List -->
    <div class="card">
        <div class="card-header">
            <h2>Payment History</h2>
        </div>
        <div class="card-body">
            <?php if ($payments && $payments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Amount</th>
                                <th>Payment Period</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Confirmed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo format_date($payment['payment_date']); ?></td>
                                    <td><strong><?php echo format_currency($payment['amount']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $payment['confirmed_by'] ? 'Admin' : '-'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center" style="padding: 2rem; color: var(--text-light);">
                    No payment records found.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="portal.php" class="btn btn-outline">← Back to Portal</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>