<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

// Only guests should see this page
requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // This page always creates a Host account
    $role             = 'host';

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        $result = Auth::register($first_name, $last_name, $email, $password, $role);

        if ($result['success']) {
            // After signup, show email verification instructions
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
    <title>Become a Host - ReservePro</title>
    <link rel="icon" href="background%20image/asd.webp" type="image/webp">
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/landing.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=25.0">
    <link rel="stylesheet" href="assets/css/animations.css?v=1.0">
</head>
<body class="auth-page">
    <a href="home.php" style="position: fixed; top: 20px; left: 20px; z-index: 1000; display: block; line-height: 0;" title="Home" aria-label="Go to Home">
        <img src="background%20image/asd.webp" alt="ReservePro" style="width: 48px; height: 48px; object-fit: contain; border-radius: 12px; border: 2px solid rgba(212, 165, 116, 0.6); box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    </a>
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
                <h1>Become a Host</h1>
                <p>Create a host account to list your properties</p>
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

            <form class="auth-form" method="POST" action="become-host.php" id="hostRegisterForm">
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
                        placeholder="host@example.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>

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

                <button type="submit" class="btn-primary">
                    Sign up as Host
                </button>
            </form>

            <div class="auth-footer">
                <p>Want to book stays instead? <a href="register.php">Sign up as Guest</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/validation.js"></script>
</body>
</html>

