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
    $role = $_POST['role'] ?? 'guest'; // Get selected role
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        $result = Auth::register($first_name, $last_name, $email, $password, $role);
        
        if ($result['success']) {
            // Redirect based on selected role
            if ($role === 'host') {
                header('Location: host/dashboard.php');
            } else {
                // Guests go to browse properties (home page)
                header('Location: home.php');
            }
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
    <title>Sign up - ServePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=8.0">
    <link rel="stylesheet" href="assets/css/role-select.css?v=8.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
</head>
<body>
    <!-- Theme Toggle -->
    <div class="theme-toggle" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <span class="theme-toggle-icon">☀️</span>
        <span class="theme-toggle-text">Light</span>
    </div>
    
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="logo">
                    <svg class="logo-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1c2 0 3.46 1.63 3.46 3.41 0 1.78-1.46 3.41-3.46 3.41s-3.46-1.63-3.46-3.41C12.54 2.63 14 1 16 1zm0 6.82c2.52 0 4.61-1.84 4.61-4.41C20.61 1.84 18.52 0 16 0s-4.61 1.84-4.61 4.41c0 2.57 2.09 4.41 4.61 4.41zM13.96 28.85l6.72-11.87c-1.41-.83-3.07-1.33-4.86-1.33-1.79 0-3.45.5-4.86 1.33l6.72 11.87h.28zm-1.27-1.89l-5.12-9.04C8.47 16.02 9.99 15 11.71 15h8.58c1.72 0 3.24 1.02 4.14 2.92l-5.12 9.04h-7.62z"/>
                    </svg>
                    <span class="logo-text">ServePro</span>
                </div>
                <div style="font-size: 48px; margin: 20px 0;">🎉</div>
                <h1>Join ServePro</h1>
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

                <div class="form-group">
                    <label for="role">I want to</label>
                    <select 
                        id="role" 
                        name="role" 
                        required
                        style="padding: 12px 16px; border: 1px solid #DDDDDD; border-radius: 8px; font-size: 16px;"
                        onchange="showRoleInfo(this.value)"
                    >
                        <option value="guest" <?php echo (($_POST['role'] ?? '') === 'guest') ? 'selected' : ''; ?>>🏖️ Browse & Book Properties (Guest)</option>
                        <option value="host" <?php echo (($_POST['role'] ?? '') === 'host') ? 'selected' : ''; ?>>🏠 List My Properties (Host)</option>
                    </select>
                    <div id="role-description" class="role-info guest">
                        <strong>🏖️ Guest Account</strong>
                        Browse properties, make bookings, and enjoy amazing experiences. After signup, you'll go to the <strong>Guest Dashboard</strong>.
                    </div>
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

                <button type="submit" class="btn-primary" id="submitBtn">
                    Sign up
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Log in</a></p>
            </div>
        </div>
    </div>

    <script>
        // Show role description
        function showRoleInfo(role) {
            const roleDesc = document.getElementById('role-description');
            
            if (roleDesc) {
                roleDesc.style.display = 'block';
                
                if (role === 'guest') {
                    roleDesc.className = 'role-info guest';
                    roleDesc.innerHTML = '<strong>🏖️ Guest Account</strong><br>Browse properties, make bookings, and enjoy amazing experiences. After signup, you\'ll go to the <strong>Guest Dashboard</strong>.';
                } else if (role === 'host') {
                    roleDesc.className = 'role-info host';
                    roleDesc.innerHTML = '<strong>🏠 Host Account</strong><br>List your properties, manage bookings, and earn money as a host. After signup, you\'ll go to the <strong>Host Dashboard</strong> to add your first property.';
                }
            }
        }
        
        // Show role info on page load
        window.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            if (roleSelect && roleSelect.value) {
                showRoleInfo(roleSelect.value);
            }
        });
    </script>
    
    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/validation.js"></script>
</body>
</html>
