<?php
// Quick Admin Account Creator
require_once __DIR__ . '/config/database.php';

$message = '';
$success = false;

// Admin details
$email = 'admin@servepro.com';
$password = 'admin123';
$first_name = 'Admin';
$last_name = 'ReservePro';
$role = 'admin';

try {
    $conn = getDBConnection();
    
    // First, check if role column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($result->num_rows == 0) {
        // Add role column
        $conn->query("ALTER TABLE users ADD COLUMN role ENUM('guest', 'host', 'admin') DEFAULT 'guest' AFTER password");
        $message .= "✅ Role column added!<br>";
    }
    
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Admin exists, update password and role
        $existing = $result->fetch_assoc();
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE email = ?");
        $stmt->bind_param("sss", $hashed, $role, $email);
        $stmt->execute();
        
        $message .= "✅ Admin account updated!<br>";
        $message .= "📧 Email: {$email}<br>";
        $message .= "🔑 Password: {$password}<br>";
        $message .= "👑 Role: {$role}";
        $success = true;
    } else {
        // Create new admin
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $first_name, $last_name, $email, $hashed, $role);
        
        if ($stmt->execute()) {
            $message .= "✅ Admin account created!<br>";
            $message .= "📧 Email: {$email}<br>";
            $message .= "🔑 Password: {$password}<br>";
            $message .= "👑 Role: {$role}";
            $success = true;
        } else {
            $message = "❌ Error: " . $conn->error;
        }
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $message = "❌ Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/asd.webp" type="image/webp">
    <title>Create Admin - ReservePro</title>
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
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 32px;
            background: linear-gradient(135deg, #EF4444, #DC2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #6B7280;
            font-size: 14px;
        }
        .message-box {
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 2;
        }
        .message-box.success {
            background: #D1FAE5;
            color: #065F46;
            border: 2px solid #10B981;
        }
        .message-box.error {
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
            color: #1F2937;
            margin-bottom: 16px;
            font-size: 18px;
        }
        .cred-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #E5E7EB;
        }
        .cred-item:last-child {
            border-bottom: none;
        }
        .cred-label {
            color: #6B7280;
            font-weight: 600;
        }
        .cred-value {
            color: #1F2937;
            font-family: monospace;
            background: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
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
            transition: transform 0.2s;
            display: inline-block;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }
        .btn-secondary {
            background: white;
            color: #6366F1;
            border: 2px solid #6366F1;
        }
        .note {
            text-align: center;
            margin-top: 20px;
            color: #6B7280;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">👑</div>
            <h1>Admin Account Setup</h1>
            <p class="subtitle">ReservePro Platform</p>
        </div>

        <div class="message-box <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>

        <?php if ($success): ?>
            <div class="credentials">
                <h3>
                    <img src="background%20image/z.jpg"
                         alt="Secure"
                         style="width:28px; height:28px; border-radius:8px; object-fit:cover; vertical-align:middle; margin-right:8px;">
                    Login Credentials
                </h3>
                <div class="cred-item">
                    <span class="cred-label">Email:</span>
                    <span class="cred-value">admin@servepro.com</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Password:</span>
                    <span class="cred-value">admin123</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Role:</span>
                    <span class="cred-value">ADMIN</span>
                </div>
            </div>

            <div class="actions">
                <a href="login.php" class="btn btn-secondary">🔓 Go to Login</a>
                <a href="admin/dashboard.php" class="btn btn-primary">👑 Admin Dashboard</a>
            </div>

            <p class="note">✅ You can now login and approve properties!</p>
        <?php endif; ?>
    </div>
</body>
</html>
