<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
requireGuest();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // All new signups are guests by default
    $role = 'guest';
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        $result = Auth::register($first_name, $last_name, $email, $password, $role);
        
        if ($result['success']) {
            // After signup, show "check your email" instructions
            header('Location: verify-pending.php');
            exit();
        } else {
            $errors = $result['errors'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=8.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
    <link rel="stylesheet" href="assets/css/animations.css?v=1.0">
</head>
<body class="auth-page">
    <!-- Theme Toggle -->
    <div class="theme-toggle" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <span class="theme-toggle-icon">☀️</span>
        <span class="theme-toggle-text">Light</span>
    </div>
    
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="logo">
                    <?php $brand_icon_class = 'logo-icon'; require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                    <span class="logo-text">ReservePro</span>
                </div>
                <div style="font-size: 48px; margin: 20px 0;">🎉</div>
                <h1>Join ReservePro</h1>
                <p>Create an account to get started</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="register.php" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            placeholder="John"
                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            placeholder="Doe"
                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="john@example.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>

                <!-- Role selection removed: all new accounts register as Guests -->

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Must be at least 8 characters"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Re-enter your password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    Sign up
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Log in</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/validation.js"></script>
</body>
</html>

    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/validation.js"></script>
</body>
</html>
