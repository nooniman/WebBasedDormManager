<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth0_functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('tenant/portal.php');
    }
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
$success = '';

// Handle Auth0 Login - Must be before any output
if (isset($_GET['auth0_login'])) {
    try {
        $auth0 = get_auth0_instance();
        
        // Generate the Auth0 login URL and redirect
        header('Location: ' . $auth0->login());
        exit();
    } catch (Exception $e) {
        error_log('Auth0 Login Error: ' . $e->getMessage());
        $_SESSION['error'] = 'Unable to connect to Auth0. Please try again.';
        header('Location: login.php');
        exit();
    }
}

// Initialize Auth0 for other uses
$auth0 = get_auth0_instance();

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        
        // Query user from database
        $stmt = $conn->prepare("SELECT id, email, password, role, first_name, last_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (verify_password($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } else {
                    redirect('tenant/portal.php');
                }
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
        
        $stmt->close();
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
            $error = 'All fields are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Email address already registered';
            } else {
                // Hash password
                $hashed_password = hash_password($password);
                
                // Insert new user (as tenant by default)
                $insert_stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 'tenant', 1, NOW())");
                $insert_stmt->bind_param("sssss", $first_name, $last_name, $email, $phone, $hashed_password);
                
                if ($insert_stmt->execute()) {
                    $success = 'Account created successfully! You can now login.';
                    // Auto-switch to login tab
                    echo "<script>setTimeout(function(){ document.querySelector('[data-tab=\"login\"]').click(); }, 2000);</script>";
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                
                $insert_stmt->close();
            }
            
            $check_stmt->close();
        }
    }
}

$page_title = 'Login';
require_once 'includes/header.php';
?>

<style>
.auth-tabs {
    display: flex;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 2rem;
}

.auth-tab {
    flex: 1;
    padding: 1rem;
    text-align: center;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    color: #666;
    transition: all 0.3s ease;
}

.auth-tab.active {
    color: var(--primary-color);
    border-bottom: 3px solid var(--primary-color);
}

.auth-tab:hover {
    background-color: #f5f5f5;
}

.divider {
    text-align: center;
    margin: 1.5rem 0;
    position: relative;
}

.divider::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    width: 100%;
    height: 1px;
    background: #e0e0e0;
}

.divider span {
    background: white;
    padding: 0 1rem;
    position: relative;
    color: #666;
}

.btn-auth0 {
    background: #eb5424;
    color: white;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s;
    text-decoration: none;
}

.btn-auth0:hover {
    background: #d14820;
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.password-requirements {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.5rem;
}

.password-requirements ul {
    margin: 0.5rem 0 0 1.5rem;
    padding: 0;
}
</style>

<div class="container">
    <div class="card" style="max-width: 500px; margin: 4rem auto;">
        <div class="card-header text-center">
            <h2>Welcome</h2>
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
            <?php endif; ?>

            <!-- Auth0 SSO Button -->
            <a href="?auth0_login=1" class="btn-auth0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M21.98 7.448L19.62 0H4.347L2.02 7.448c-1.352 4.312.03 9.206 3.815 12.015L12.007 24l6.157-4.537c3.786-2.81 5.167-7.703 3.815-12.015z"/>
                </svg>
                Continue with Auth0 SSO
            </a>
            
            <div class="divider">
                <span>OR</span>
            </div>
            
            <!-- Auth Tabs -->
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login" onclick="switchTab('login')">Login</button>
                <button class="auth-tab" data-tab="register" onclick="switchTab('register')">Create Account</button>
            </div>
            
            <!-- Login Form -->
            <div id="login-tab" class="tab-content active">
                <form method="POST" action="" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label class="form-label" for="login_email">Email Address</label>
                        <input type="email" id="login_email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" class="form-control" required>
                        <small class="text-right mt-1" style="display: block;">
                            <a href="<?php echo BASE_URL; ?>/password_reset_request">Forgot password?</a>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
            </div>
            
            <!-- Register Form -->
            <div id="register-tab" class="tab-content">
                <form method="POST" action="" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="register_email">Email Address</label>
                        <input type="email" id="register_email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="register_password">Password</label>
                        <input type="password" id="register_password" name="password" class="form-control" minlength="8" required>
                        <div class="password-requirements">
                            <ul>
                                <li>At least 8 characters long</li>
                                <li>Mix of letters and numbers recommended</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
                </form>
            </div>
            
            <p class="text-center mt-3">
                <a href="<?php echo PUBLIC_URL; ?>/index">Back to Home</a>
            </p>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    // Add active class to selected tab and content
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById(`${tab}-tab`).classList.add('active');
}

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.querySelector('#register-tab form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('register_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>