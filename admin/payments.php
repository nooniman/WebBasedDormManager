<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Payments';
require_once '../includes/header.php';

// Fetch all payments
$query = "
    SELECT p.*, u.first_name, u.last_name, r.room_number 
    FROM payments p 
    JOIN users u ON p.tenant_id = u.id 
    LEFT JOIN rooms r ON p.room_id = r.id 
    ORDER BY p.payment_date DESC
";
$result = $conn->query($query);
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Payment Records</h2>
        </div>
        <div class="card-body">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tenant</th>
                                <th>Room</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                    <td>Room <?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_currency($payment['amount']); ?></td>
                                    <td><?php echo format_date($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_period'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="payment_details.php?id=<?php echo $payment['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No payment records found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
