<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Password Reset';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $email = sanitize_input($_POST['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token
                $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("sss", $email, $token, $expires_at);
                
                if ($insert_stmt->execute()) {
                    // In production, send email here
                    // For now, we'll show the reset link
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/password_reset.php?token=" . $token;
                    
                    $success = "Password reset instructions have been sent to your email. <br><br>
                               <small>For testing: <a href='$reset_link'>Click here to reset password</a></small>";
                } else {
                    $error = 'Failed to process request';
                }
                
                $insert_stmt->close();
            } else {
                // Don't reveal if email doesn't exist (security)
                $success = "If an account exists with this email, you will receive password reset instructions.";
            }
            
            $stmt->close();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 500px; margin: 4rem auto;">
        <div class="card-header text-center">
            <h2>Reset Password</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="flash-message flash-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="flash-message flash-success">
                    <?php echo $success; ?>
                </div>
            <?php else: ?>
                <p class="text-center mb-3">Enter your email address and we'll send you instructions to reset your password.</p>
                
                <form method="POST" action="" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Send Reset Link</button>
                </form>
            <?php endif; ?>
            
            <p class="text-center mt-3">
                <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>