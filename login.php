<?php
/**
 * User Login Page - SavorBite Online Food Ordering System
 * Demonstrates: Secure password_verify(), Session Regeneration, Guest Cart Sync
 */
$pageTitle = 'Sign In - SavorBite';
require_once __DIR__ . '/includes/header.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$error = null;
$redirect = $_GET['redirect'] ?? $_SESSION['redirect_after_login'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            // Prepared statement against SQL injection
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Password match verified
                log_in_user($user);
                
                // Sync any guest cart items into database cart_items table
                sync_guest_cart_to_db($pdo, $user['user_id']);

                set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!');
                
                $destination = ($user['role'] === 'admin') ? 'admin/dashboard.php' : $redirect;
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $destination);
                exit();
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}
?>

<div class="auth-page-container">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-brand-logo">🍽️</span>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your account to place orders & track meals</p>
        </div>

        <?php if ($error): ?>
            <div class="flash-alert flash-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="flash-alert flash-success">
                <span>Account created successfully! Please sign in with your credentials.</span>
            </div>
        <?php endif; ?>

        <form action="login.php<?= !empty($redirect) && $redirect !== 'index.php' ? '?redirect=' . urlencode($redirect) : '' ?>" method="POST" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block auth-btn">Sign In &rarr;</button>
        </form>

        <!-- Demo Accounts Box for Evaluators / Quick Testing -->
        <div class="demo-credentials-box">
            <h4>💡 Demo Login Credentials:</h4>
            <div class="demo-acc-item">
                <strong>Administrator Account:</strong>
                <code>Email: admin@savorbite.com | Pass: Admin@123</code>
                <button type="button" class="btn-fill-demo" onclick="fillDemo('admin@savorbite.com', 'Admin@123')">Autofill Admin</button>
            </div>
            <div class="demo-acc-item">
                <strong>Customer Account:</strong>
                <code>Email: john@example.com | Pass: Customer@123</code>
                <button type="button" class="btn-fill-demo" onclick="fillDemo('john@example.com', 'Customer@123')">Autofill Customer</button>
            </div>
        </div>

        <div class="auth-footer-prompt">
            <p>Don't have an account yet? <a href="register.php">Create an account</a></p>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
