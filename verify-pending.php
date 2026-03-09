<?php
require_once __DIR__ . '/config/session.php';

// If already verified and logged in, just send them home
if (isLoggedIn()) {
    header('Location: home.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check your email - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=25.0">
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
            /* Match global glassy + gold border style */
            background:
                linear-gradient(150deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04)),
                linear-gradient(135deg, rgba(15, 15, 15, 0.78), rgba(24, 32, 50, 0.66));
            border-radius: 24px;
            border: 1px solid rgba(212, 165, 116, 0.9);
            padding: 28px 32px 26px;
            box-shadow:
                0 26px 80px rgba(0,0,0,0.9),
                0 0 0 2px rgba(212, 165, 116, 0.7);
            backdrop-filter: blur(22px) saturate(160%);
            -webkit-backdrop-filter: blur(22px) saturate(160%);
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
            <div class="verify-title">Check your email</div>
            <p class="verify-text">
                We’ve sent a verification link to your email address. Please open that email and click
                <strong>“Verify my email”</strong> to finish creating your ReservePro account.
            </p>
            <p class="verify-text" style="font-size: 13px;">
                Once your email is verified, you can sign in using your email and password.
            </p>
            <a href="login.php" class="verify-btn">Back to Login</a>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>

