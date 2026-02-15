<?php
require_once __DIR__ . '/config/session.php';

// Require login
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ServePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=8.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="logo">
                <svg class="logo-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 1c2 0 3.46 1.63 3.46 3.41 0 1.78-1.46 3.41-3.46 3.41s-3.46-1.63-3.46-3.41C12.54 2.63 14 1 16 1zm0 6.82c2.52 0 4.61-1.84 4.61-4.41C20.61 1.84 18.52 0 16 0s-4.61 1.84-4.61 4.41c0 2.57 2.09 4.41 4.61 4.41zM13.96 28.85l6.72-11.87c-1.41-.83-3.07-1.33-4.86-1.33-1.79 0-3.45.5-4.86 1.33l6.72 11.87h.28zm-1.27-1.89l-5.12-9.04C8.47 16.02 9.99 15 11.71 15h8.58c1.72 0 3.24 1.02 4.14 2.92l-5.12 9.04h-7.62z"/>
                </svg>
                <span class="logo-text">ServePro</span>
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
