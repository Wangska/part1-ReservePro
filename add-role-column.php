<?php
require_once __DIR__ . '/config/database.php';

$message = '';
$success = false;

try {
    $conn = getDBConnection();
    
    // Check if role column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    
    if ($result->num_rows == 0) {
        // Role column doesn't exist, add it
        $sql = "ALTER TABLE users ADD COLUMN role ENUM('guest', 'host', 'admin') DEFAULT 'guest' AFTER password";
        
        if ($conn->query($sql)) {
            $message = "✅ Role column added successfully!";
            $success = true;
        } else {
            $message = "❌ Error adding role column: " . $conn->error;
        }
    } else {
        $message = "✅ Role column already exists!";
        $success = true;
    }
    
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
    <title>Add Role Column - ReservePro</title>
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
            font-size: 28px;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #6B7280;
            font-size: 14px;
        }
        .status {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 18px;
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
        .next-steps {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .next-steps h3 {
            color: #1F2937;
            margin-bottom: 16px;
            font-size: 18px;
        }
        .next-steps ol {
            padding-left: 20px;
            color: #4B5563;
        }
        .next-steps li {
            margin-bottom: 12px;
            line-height: 1.6;
        }
        .next-steps code {
            background: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: #EF4444;
            font-size: 13px;
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
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366F1, #4F46E5);
            color: white;
        }
        .btn-success {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon"><?php echo $success ? '✅' : '⚠️'; ?></div>
            <h1>Database Setup</h1>
            <p class="subtitle">Adding role column to users table</p>
        </div>

        <div class="status <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>

        <?php if ($success): ?>
            <div class="next-steps">
                <h3>🎉 What's Next?</h3>
                <ol>
                    <li>Now you can create an admin account</li>
                    <li>Visit <code>create-admin.php</code> to create your admin account</li>
                    <li>Or run the SQL to create admin manually</li>
                    <li>Login and start managing properties!</li>
                </ol>
            </div>

            <div class="actions">
                <a href="create-admin.php" class="btn btn-success">👑 Create Admin Account</a>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
