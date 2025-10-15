<?php
require_once '../config/database.php';
require_once '../includes/tenant_auth.php';
require_once '../includes/functions.php';

$page_title = 'My Profile';
require_once '../includes/header.php';

$tenant_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $phone = sanitize_input($_POST['phone']);
        $address = sanitize_input($_POST['address']);
        
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $address, $tenant_id);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $first_name . ' ' . $last_name;
            set_flash_message('Profile updated successfully', 'success');
        } else {
            set_flash_message('Failed to update profile', 'error');
        }
        
        $stmt->close();
        redirect('profile.php');
    }
}

// Fetch tenant details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$tenant = $result->fetch_assoc();
$stmt->close();
?>

<div class="container">
    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <div class="card-header">
            <h2>My Profile</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($tenant['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($tenant['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($tenant['email']); ?>" disabled>
                    <small style="color: var(--text-light);">Email cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($tenant['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($tenant['address'] ?? ''); ?></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                    <a href="portal.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mt-4" style="max-width: 800px; margin: 2rem auto;">
        <div class="card-header">
            <h2>Account Information</h2>
        </div>
        <div class="card-body">
            <p><strong>Account Created:</strong> <?php echo format_date($tenant['created_at']); ?></p>
            <p><strong>Last Login:</strong> <?php echo $tenant['last_login'] ? format_date($tenant['last_login']) : 'N/A'; ?></p>
            <p><strong>Account Status:</strong> 
                <span class="badge badge-<?php echo $tenant['is_active'] ? 'success' : 'danger'; ?>">
                    <?php echo $tenant['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </p>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
