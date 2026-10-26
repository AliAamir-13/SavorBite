<?php
/**
 * User Registration Page - SavorBite Online Food Ordering System
 * Demonstrates: Server-side Input Validation, Unique Constraints, Password Hashing, Multi-table Insertion
 */
$pageTitle = 'Create an Account - SavorBite';
require_once __DIR__ . '/includes/header.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security token invalid. Please refresh the page.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $addressLine = trim($_POST['address_line'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');

        // Validation
        if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
            $error = 'Please fill in all required user profile fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists (enforcing Unique constraint gracefully)
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address already exists. Please sign in instead.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Hash password securely with PHP bcrypt
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user record
                    $userStmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, phone, password_hash, role)
                        VALUES (?, ?, ?, ?, 'customer')
                    ");
                    $userStmt->execute([$fullName, $email, $phone, $passwordHash]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // If address provided, insert into addresses table
                    if (!empty($addressLine) && !empty($city)) {
                        $addrStmt = $pdo->prepare("
                            INSERT INTO addresses (user_id, address_label, address_line, city, postal_code, is_default)
                            VALUES (?, 'Home', ?, ?, ?, 1)
                        ");
                        $addrStmt->execute([$newUserId, $addressLine, $city, $postalCode]);
                    }

                    $pdo->commit();

                    // Automatically log in the user
                    $newUser = [
                        'user_id'   => $newUserId,
                        'full_name' => $fullName,
                        'email'     => $email,
                        'role'      => 'customer'
                    ];
                    log_in_user($newUser);
                    sync_guest_cart_to_db($pdo, $newUserId);

                    set_flash_message('success', 'Welcome to SavorBite, ' . htmlspecialchars($fullName) . '! Your account is ready.');
                    header("Location: index.php");
                    exit();

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="auth-page-container">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <span class="auth-brand-logo">🍽️</span>
            <h1 class="auth-title">Create an Account</h1>
            <p class="auth-subtitle">Join thousands enjoying chef-curated meals delivered fresh</p>
        </div>

        <?php if ($error): ?>
            <div class="flash-alert flash-error">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-section-title">Personal Information</div>

            <div class="form-group-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group-row">
                <div class="form-group">
                    <label for="password">Password * (min 6 chars)</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="form-section-title">Delivery Address (Optional)</div>

            <div class="form-group">
                <label for="address_line">Street Address</label>
                <input type="text" id="address_line" name="address_line" placeholder="742 Evergreen Terrace, Apt 4B" value="<?= htmlspecialchars($_POST['address_line'] ?? '') ?>">
            </div>

            <div class="form-group-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" placeholder="Springfield" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="postal_code">Postal / ZIP Code</label>
                    <input type="text" id="postal_code" name="postal_code" placeholder="97477" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block auth-btn">Register & Start Ordering &rarr;</button>
        </form>

        <div class="auth-footer-prompt">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
