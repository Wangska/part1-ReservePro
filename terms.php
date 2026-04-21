<?php
require_once __DIR__ . '/config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Terms &amp; Conditions - ReservePro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --gold:#D4A574; --navy:#06090F; --muted:rgba(203,213,225,0.70); --font: Inter, ui-sans-serif, system-ui, sans-serif; }
        body {
            font-family: var(--font);
            background: var(--navy);
            color: #F1F5F9;
            min-height: 100vh;
        }
        body::before {
            content:'';
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(212,165,116,0.07), transparent),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(99,102,241,0.06), transparent);
            pointer-events:none;
        }
        .wrap { position: relative; z-index: 1; max-width: 920px; margin: 0 auto; padding: 40px 18px 64px; }
        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 22px;
            padding: 26px 26px;
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }
        h1 { font-size: 26px; font-weight: 900; letter-spacing: -0.03em; margin-bottom: 8px; }
        p.lead { color: rgba(226,232,240,0.80); line-height: 1.65; margin-bottom: 18px; }
        h2 { font-size: 16px; font-weight: 900; margin: 18px 0 8px; color: #fff; }
        p, li { color: var(--muted); line-height: 1.7; font-size: 14px; }
        ul { padding-left: 18px; margin-top: 6px; }
        a { color: var(--gold); text-decoration: none; font-weight: 800; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom: 14px; }
        .back { display:inline-flex; align-items:center; gap:8px; padding: 10px 12px; border-radius: 999px; border:1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05); color:#E2E8F0; text-decoration:none; font-weight:900; font-size: 13px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <a class="back" href="register.php">Back to Sign Up</a>
            <div style="color: rgba(203,213,225,0.65); font-weight: 800; font-size: 13px;">Last updated: <?php echo date('M j, Y'); ?></div>
        </div>
        <div class="card">
            <h1>Terms &amp; Conditions</h1>
            <p class="lead">By creating an account and using ReservePro, you agree to the terms below.</p>

            <h2>1. Accounts</h2>
            <p>You are responsible for maintaining the confidentiality of your account and for all activities that occur under your account.</p>

            <h2>2. Bookings &amp; Cancellations</h2>
            <p>Booking availability, pricing, and cancellation refunds are subject to listing rules and platform policies shown at the time of booking.</p>

            <h2>3. Messages</h2>
            <p>Use in-app messaging to communicate. Do not share private contact information unless permitted by platform rules.</p>

            <h2>4. Prohibited activity</h2>
            <ul>
                <li>Fraud, abuse, or attempts to bypass platform processes</li>
                <li>Harassment or harmful content</li>
                <li>Unauthorized access or disruption of the service</li>
            </ul>

            <h2>5. Changes</h2>
            <p>We may update these terms from time to time. Continued use of the service means you accept the updated terms.</p>

            <h2>Contact</h2>
            <p>If you have questions, contact support from the app’s contact page.</p>
        </div>
    </div>
</body>
</html>

