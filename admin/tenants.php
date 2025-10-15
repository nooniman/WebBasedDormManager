<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/functions.php';

$page_title = 'Manage Tenants';
require_once '../includes/header.php';

// Fetch all tenants
$query = "SELECT * FROM users WHERE role = 'tenant' ORDER BY created_at DESC";
$result = $conn->query($query);
?>

<div class="container">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Manage Tenants</h2>
        </div>
        <div class="card-body">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tenant = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $tenant['id']; ?></td>
                                    <td><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                    <td><?php echo htmlspecialchars($tenant['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $tenant['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($tenant['created_at']); ?></td>
                                    <td>
                                        <a href="tenant_details.php?id=<?php echo $tenant['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No tenants found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
