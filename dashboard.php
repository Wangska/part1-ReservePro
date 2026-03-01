<?php
require_once __DIR__ . '/config/session.php';

// Require login
requireLogin();

$user = getCurrentUser();

// Hosts and admins go to their own dashboards; only guests see this page
if ($user && isset($user['role'])) {
    if ($user['role'] === 'host') {
        header('Location: host/dashboard.php');
        exit();
    }
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=8.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="logo">
                <?php $brand_icon_class = 'logo-icon'; require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                <span class="logo-text">ReservePro</span>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                
                <!-- Theme Toggle -->
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                
                <a href="logout.php" class="btn-logout">Log out</a>
            </div>
        </div>

        <div class="welcome-card">
            <h2>🎉 Welcome to your dashboard!</h2>
            <p>You've successfully logged in. Your authentication system is working perfectly.</p>
            
            <div style="margin-top: 40px; display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <a href="home.php" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #6366F1, #4F46E5); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: transform 0.2s;">
                    🌐 Browse Properties
                </a>
                <a href="host/dashboard.php" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #10B981, #059669); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: transform 0.2s;">
                    🏠 Become a Host
                </a>
                
                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #EF4444, #DC2626); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: transform 0.2s;">
                    👑 Admin Panel
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>
