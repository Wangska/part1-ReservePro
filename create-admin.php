<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';

// Admin account details
$admin_email = 'admin@servepro.com';
$admin_password = 'admin123';  // Change this after first login!
$admin_first_name = 'Admin';
$admin_last_name = 'ServePro';

$message = '';
$success = false;

// Check if admin already exists
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $message = "Admin account already exists!";
    $admin_user = $result->fetch_assoc();
    
    // Make sure they have admin role
    $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $stmt->bind_param("i", $admin_user['id']);
    $stmt->execute();
    $message .= " Role updated to admin.";
    $success = true;
} else {
    // Create admin account
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
    $stmt->bind_param("ssss", $admin_first_name, $admin_last_name, $admin_email, $hashed_password);
    
    if ($stmt->execute()) {
        $message = "Admin account created successfully!";
        $success = true;
    } else {
        $message = "Error creating admin account: " . $conn->error;
    }
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - ServePro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 32px;
            background: linear-gradient(135deg, #EF4444, #DC2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .logo p {
            color: #6B7280;
            font-size: 14px;
        }
        .status {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
        .status.success {
            background: #D1FAE5;
            color: #065F46;
            border: 2px solid #10B981;
        }
        .status.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 2px solid #EF4444;
        }
        .credentials {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .credentials h3 {
            font-size: 18px;
            color: #1F2937;
            margin-bottom: 16px;
        }
        .credential-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #E5E7EB;
        }
        .credential-row:last-child {
            border-bottom: none;
        }
        .credential-label {
            color: #6B7280;
            font-weight: 600;
        }
        .credential-value {
            color: #1F2937;
            font-family: monospace;
            background: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 14px;
        }
        .warning {
            background: #FEF3C7;
            border: 2px solid #F59E0B;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .warning h4 {
            color: #92400E;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .warning p {
            color: #92400E;
            font-size: 13px;
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            min-width: 200px;
            padding: 14px 24px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        .btn-secondary {
            background: white;
            color: #6366F1;
            border: 2px solid #6366F1;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .delete-notice {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #E5E7EB;
        }
        .delete-notice p {
            color: #EF4444;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="icon">👑</div>
            <h1>Admin Account Setup</h1>
            <p>ServePro Platform</p>
        </div>

        <div class="status <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>

        <?php if ($success): ?>
            <div class="credentials">
                <h3>🔐 Admin Login Credentials</h3>
                <div class="credential-row">
                    <span class="credential-label">Email:</span>
                    <span class="credential-value"><?php echo $admin_email; ?></span>
                </div>
                <div class="credential-row">
                    <span class="credential-label">Password:</span>
                    <span class="credential-value"><?php echo $admin_password; ?></span>
                </div>
            </div>

            <div class="warning">
                <h4>⚠️ Important Security Notice</h4>
                <p>Please change your password after logging in for the first time! This is a default password and should not be used in production.</p>
            </div>

            <div class="actions">
                <a href="login.php" class="btn btn-secondary">Go to Login</a>
                <a href="admin/dashboard.php" class="btn btn-primary">👑 Admin Dashboard</a>
            </div>

            <div class="delete-notice">
                <p>⚠️ Delete this file (create-admin.php) after setup for security!</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
