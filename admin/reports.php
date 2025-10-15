<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Reports';
require_once '../includes/header.php';

// Get report data
$occupancy_rate = 0;
$total_rooms_result = $conn->query("SELECT COUNT(*) as count FROM rooms");
$total_rooms = $total_rooms_result->fetch_assoc()['count'];

$occupied_rooms_result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'occupied'");
$occupied_rooms = $occupied_rooms_result->fetch_assoc()['count'];

if ($total_rooms > 0) {
    $occupancy_rate = ($occupied_rooms / $total_rooms) * 100;
}

// Monthly revenue
$monthly_revenue_result = $conn->query("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount) as total
    FROM payments
    WHERE payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
");

// Pending payments
$pending_payments_result = $conn->query("
    SELECT COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE status = 'pending'
");
$pending_payments = $pending_payments_result->fetch_assoc();
?>

<div class="container">
    <h1 class="mb-4">Reports & Analytics</h1>
    
    <!-- Key Metrics -->
    <div class="grid grid-3 mb-4">
        <div class="stat-card">
            <h3><?php echo number_format($occupancy_rate, 1); ?>%</h3>
            <p>Occupancy Rate</p>
        </div>
        
        <div class="stat-card">
            <h3><?php echo $occupied_rooms; ?>/<?php echo $total_rooms; ?></h3>
            <p>Occupied Rooms</p>
        </div>
        
        <div class="stat-card">
            <h3><?php echo $pending_payments['count'] ?? 0; ?></h3>
            <p>Pending Payments</p>
        </div>
    </div>
    
    <!-- Monthly Revenue Report -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Monthly Revenue (Last 6 Months)</h2>
        </div>
        <div class="card-body">
            <?php if ($monthly_revenue_result && $monthly_revenue_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $monthly_revenue_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                    <td><?php echo format_currency($row['total']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No revenue data available.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Room Status Summary -->
    <div class="card">
        <div class="card-header">
            <h2>Room Status Summary</h2>
        </div>
        <div class="card-body">
            <?php
            $status_query = "
                SELECT status, COUNT(*) as count 
                FROM rooms 
                GROUP BY status
            ";
            $status_result = $conn->query($status_query);
            ?>
            
            <?php if ($status_result && $status_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $status_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo ucfirst($row['status']); ?></td>
                                    <td><?php echo $row['count']; ?></td>
                                    <td><?php echo number_format(($row['count'] / $total_rooms) * 100, 1); ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No data available.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
