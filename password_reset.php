<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Reset Password';
$error = '';
$success = '';
$valid_token = false;

// Verify token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = FALSE");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
        $reset_data = $result->fetch_assoc();
    } else {
        $error = 'Invalid or expired reset token';
    }
    
    $stmt->close();
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            $hashed_password = hash_password($password);
            
            // Update user password
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $hashed_password, $reset_data['email']);
            
            if ($update_stmt->execute()) {
                // Mark token as used
                $mark_used = $conn->prepare("UPDATE password_resets SET used = TRUE WHERE id = ?");
                $mark_used->bind_param("i", $reset_data['id']);
                $mark_used->execute();
                $mark_used->close();
                
                $success = 'Password reset successful! You can now login with your new password.';
            } else {
                $error = 'Failed to reset password';
            }
            
            $update_stmt->close();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 500px; margin: 4rem auto;">
        <div class="card-header text-center">
            <h2>Reset Your Password</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="flash-message flash-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="flash-message flash-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <p class="text-center mt-3">
                    <a href="<?php echo LOGIN_URL; ?>" class="btn btn-primary">Go to Login</a>
                </p>
            <?php elseif ($valid_token): ?>
                <form method="POST" action="" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                        <small class="text-muted">Must be at least 8 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Reset Password</button>
                </form>
            <?php else: ?>
                <p class="text-center">
                    <a href="<?php echo BASE_URL; ?>/password_reset_request" class="btn btn-primary">Request New Reset Link</a>
                </p>
            <?php endif; ?>
            
            <p class="text-center mt-3">
                <a href="<?php echo LOGIN_URL; ?>">Back to Login</a>
            </p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>