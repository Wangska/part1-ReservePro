<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database_schema.php';

initializeHostTables();

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$status = null;

if ($token === '') {
    $status = 'missing';
} else {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, email_verified FROM users WHERE verification_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $status = 'invalid';
    } else {
        if ((int)$user['email_verified'] === 1) {
            $status = 'already';
        } else {
            $stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $stmt->close();
            $_SESSION['user_id'] = $user['id'];
            $status = 'success';
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Email verification - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=15.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=15.0">
    <style>
        .verify-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .verify-card {
            max-width: 920px;
            width: 100%;
            background: linear-gradient(135deg, rgba(15, 15, 15, 0.96), rgba(24, 32, 50, 0.90));
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 28px 32px 26px;
            box-shadow: 0 26px 80px rgba(0,0,0,0.9), 0 0 0 1px rgba(255,255,255,0.04);
            backdrop-filter: blur(22px) saturate(150%);
            -webkit-backdrop-filter: blur(22px) saturate(150%);
        }
        body.light-mode .verify-card {
            background: #FFFFFF;
            border-color: #E5E7EB;
            box-shadow: 0 18px 45px rgba(0,0,0,0.18);
        }
        .verify-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 18px;
        }
        .verify-logo {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: rgba(0,0,0,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 30px rgba(0,0,0,0.65);
        }
        .verify-logo img {
            width: 100%;
            height: 100%;
            border-radius: 16px;
            object-fit: cover;
        }
        .verify-brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .verify-brand-title {
            font-size: 18px;
            font-weight: 700;
            color: #F9FAFB;
        }
        .verify-brand-sub {
            font-size: 12px;
            color: #9CA3AF;
        }
        .verify-title {
            font-size: 22px;
            font-weight: 600;
            color: #F9FAFB;
            margin-bottom: 8px;
        }
        body.light-mode .verify-title {
            color: #111827;
        }
        .verify-text {
            font-size: 14px;
            color: #D1D5DB;
            margin-bottom: 16px;
        }
        body.light-mode .verify-text {
            color: #4B5563;
        }
        .verify-status {
            margin-top: 4px;
            font-size: 14px;
        }
        .verify-status.success {
            color: #22C55E;
        }
        .verify-status.error {
            color: #F97373;
        }
        .verify-btn {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 999px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="theme-toggle" style="position: fixed; top: 16px; right: 16px; z-index: 1000;">
        <span class="theme-toggle-icon">☀️</span>
        <span class="theme-toggle-text">Light</span>
    </div>

    <div class="verify-wrapper">
        <div class="verify-card">
            <div class="verify-header">
                <div class="verify-logo">
                    <img src="background%20image/asd.webp" alt="ReservePro">
                </div>
                <div class="verify-brand">
                    <div class="verify-brand-title">ReservePro</div>
                    <div class="verify-brand-sub">Stay reservations made simple</div>
                </div>
            </div>
            <div class="verify-title">Email verification</div>
            <?php if ($status === 'success'): ?>
                <p class="verify-text">
                    Your email has been verified successfully. You can now use all ReservePro features.
                </p>
                <p class="verify-status success">✅ Email verified.</p>
                <a href="home.php" class="verify-btn">Go to Home</a>
            <?php elseif ($status === 'already'): ?>
                <p class="verify-text">
                    This email has already been verified.
                </p>
                <p class="verify-status success">✅ Email already verified.</p>
                <a href="home.php" class="verify-btn">Go to Home</a>
            <?php elseif ($status === 'invalid'): ?>
                <p class="verify-text">
                    This verification link is invalid or has already been used.
                </p>
                <p class="verify-status error">❌ Invalid verification link.</p>
                <a href="login.php" class="verify-btn">Log in</a>
            <?php else: ?>
                <p class="verify-text">
                    We could not process this verification request.
                </p>
                <p class="verify-status error">❌ Missing verification token.</p>
                <a href="login.php" class="verify-btn">Log in</a>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

