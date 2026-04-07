<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $result = Auth::login($email, $password);
    
    if ($result['success']) {
        // Get user role and redirect accordingly
        $user = getCurrentUser();
        
        if ($user && isset($user['role'])) {
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'host':
                    header('Location: host/dashboard.php');
                    break;
                default:
                    // Guests go to browse properties
                    header('Location: home.php');
                    break;
            }
        } else {
            // Default to browse properties
            header('Location: home.php');
        }
        exit();
    } else {
        $errors = $result['errors'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Log in - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/landing.css?v=25.0">
    <link rel="stylesheet" href="assets/css/modal.css?v=25.0">
    <link rel="stylesheet" href="assets/css/role-select.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.2">
    <link rel="stylesheet" href="assets/css/theme-toggle-home-static.css?v=1.0">
    <link rel="stylesheet" href="assets/css/animations.css?v=1.0">
</head>
<body class="auth-page">
    <!-- 3D ReservePro loading overlay -->
    <div id="rp-loader">
        <div class="rp-loader-inner">
            <div class="rp-logo-3d">
                <img src="background%20image/asd.webp" alt="ReservePro logo">
            </div>
            <div class="rp-loader-text">ReservePro</div>
            <div class="rp-loader-subtext">Loading your account</div>
        </div>
    </div>
    <a href="home.php" style="position: fixed; top: 20px; left: 20px; z-index: 1000; display: block; line-height: 0;" title="Home" aria-label="Go to Home">
        <img src="background%20image/asd.webp" alt="ReservePro" style="width: 48px; height: 48px; object-fit: contain; border-radius: 12px; border: 2px solid rgba(212, 165, 116, 0.6); box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    </a>
    <!-- Theme Toggle -->
    <div class="theme-toggle theme-toggle-home-static" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
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
                <div style="margin: 16px 0 12px 0;">
                    <img src="background%20image/nobg.png"
                         alt="Secure login"
                         style="width:64px; height:64px; border-radius:18px; object-fit:cover; display:block; margin:0 auto;">
                </div>
                <h1>Welcome back</h1>
                <p>Log in to your account</p>
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

            <form class="auth-form" method="POST" action="login.php" id="loginForm">
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

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    Log in
                </button>
            </form>

            <div class="divider">
                <span>or</span>
            </div>

            <div class="social-buttons">
                <button class="btn-social" onclick="window.location.href='google-login.php'; return false;">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>
                <button class="btn-social" onclick="alert('Social login coming soon!')">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Continue with Facebook
                </button>
            </div>

            <div class="auth-footer">
                <p>Don't have an account? <a href="#" onclick="openModal('registerModal'); return false;">Sign up</a></p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('registerModal')"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('registerModal')">&times;</button>
            
            <div class="modal-header">
                <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
                <h2>Create an account</h2>
                <p>Join ReservePro today</p>
            </div>

            <?php if (isset($_SESSION['register_errors'])): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($_SESSION['register_errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['register_errors']); ?>
            <?php endif; ?>

            <form class="modal-form" method="POST" action="register-handler.php">
                <div class="form-group">
                    <label for="modal_first_name">First Name</label>
                    <input type="text" id="modal_first_name" name="first_name" placeholder="John" required>
                </div>

                <div class="form-group">
                    <label for="modal_last_name">Last Name</label>
                    <input type="text" id="modal_last_name" name="last_name" placeholder="Doe" required>
                </div>

                <div class="form-group">
                    <label for="modal_email">Email</label>
                    <input type="email" id="modal_email" name="email" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="modal_role">I want to</label>
                    <select id="modal_role" name="role" onchange="showRoleInfo(this.value)" required>
                        <option value="guest">Book properties (Guest)</option>
                        <option value="host">List my properties (Host)</option>
                    </select>
                </div>

                <div id="modal_role_info" class="role-info guest" style="display: block;">
                    <strong>As a Guest:</strong> Browse and book amazing properties worldwide. Save favorites and manage your bookings easily.
                </div>

                <div class="form-group">
                    <label for="modal_password">Password</label>
                    <input type="password" id="modal_password" name="password" placeholder="Create a strong password" required>
                </div>

                <div class="form-group">
                    <label for="modal_confirm_password">Confirm Password</label>
                    <input type="password" id="modal_confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="btn-primary">Create Account</button>
            </form>

            <div class="modal-divider">
                <span>or</span>
            </div>

            <div class="social-buttons">
                <button class="modal-btn-social" onclick="alert('Social signup coming soon!')">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/validation.js"></script>
    <script src="assets/js/modal.js"></script>
    <script>
        // Fade out 3D loader on login page when everything is ready
        window.addEventListener('load', function () {
            var loader = document.getElementById('rp-loader');
            if (!loader) return;
            setTimeout(function () {
                loader.classList.add('rp-loader-hide');
                setTimeout(function () {
                    if (loader && loader.parentNode) {
                        loader.parentNode.removeChild(loader);
                    }
                }, 600);
            }, 300);
        });
    </script>
</body>
</html>
