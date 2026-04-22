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
                    header('Location: host/properties.php');
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --gold: #D4A574;
            --gold-dark: #B8935F;
            --navy: #06090F;
            --surface: rgba(255,255,255,0.05);
            --border: rgba(255,255,255,0.08);
            --muted: rgba(203,213,225,0.68);
            --font: Inter, ui-sans-serif, system-ui, sans-serif;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background: var(--navy);
            color: #F1F5F9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle radial glow background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(212,165,116,0.07), transparent),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(99,102,241,0.06), transparent);
            pointer-events: none;
        }

        /* Brand inside card */
        .rp-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            margin-bottom: 24px;
        }
        .rp-brand-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            border: 2px solid rgba(212,165,116,0.55);
            box-sizing: border-box;
        }
        .rp-brand-name {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #D4A574, #FAD798);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Page body */
        .rp-page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }

        /* Card */
        .rp-card {
            width: 100%;
            max-width: 480px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 26px;
            padding: 40px 44px;
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
        }

        /* Card header */
        .rp-card-head { margin-bottom: 32px; }
        .rp-card-title {
            font-size: clamp(22px, 4vw, 28px);
            font-weight: 900;
            color: #FFFFFF;
            letter-spacing: -0.04em;
            margin-bottom: 10px;
        }
        .rp-card-sub {
            font-size: 15px;
            line-height: 1.65;
            color: rgba(226,232,240,0.80);
        }

        /* Error alert */
        .rp-alert {
            display: flex;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.22);
            margin-bottom: 22px;
        }
        .rp-alert i { color: #F87171; font-size: 15px; margin-top: 1px; flex-shrink: 0; }
        .rp-alert ul { list-style: none; padding: 0; margin: 0; }
        .rp-alert li { font-size: 13.5px; color: #FCA5A5; margin-bottom: 3px; }
        .rp-alert li:last-child { margin-bottom: 0; }

        /* Form */
        .rp-form { display: flex; flex-direction: column; gap: 14px; }
        .rp-field { display: flex; flex-direction: column; gap: 6px; }
        .rp-field label {
            font-size: 12.5px;
            font-weight: 600;
            color: #94A3B8;
            letter-spacing: 0.02em;
        }
        .rp-input-wrap { position: relative; }
        .rp-input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #475569;
            pointer-events: none;
            transition: color 0.2s;
        }
        .rp-field input {
            width: 100%;
            padding: 11px 14px 11px 38px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 14px;
            color: #F1F5F9;
            font-family: var(--font);
            outline: none;
            transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.03);
        }
        .rp-field input::placeholder { color: #475569; }
        .rp-field input:focus {
            border-color: rgba(212,165,116,0.55);
            background: rgba(212,165,116,0.03);
            box-shadow: 0 0 0 3px rgba(212,165,116,0.1), inset 0 1px 0 rgba(255,255,255,0.05);
            transform: translateY(-1px);
        }
        .rp-input-wrap:focus-within i { color: #D4A574; }

        /* Submit button */
        .rp-btn {
            width: 100%;
            margin-top: 8px;
            padding: 16px 32px;
            border: none;
            border-radius: 999px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            font-family: var(--font);
            cursor: pointer;
            letter-spacing: 0.01em;
            box-shadow: 0 14px 38px rgba(212,165,116,0.4);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }
        .rp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 52px rgba(212,165,116,0.55);
        }
        .rp-btn:active { transform: translateY(0); }

        /* Divider */
        .rp-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0 16px;
        }
        .rp-divider::before, .rp-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.07);
        }
        .rp-divider span { font-size: 12px; color: #475569; white-space: nowrap; }

        /* Social button */
        .rp-social {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 10px;
            color: #E2E8F0;
            font-size: 14px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.18s;
            text-decoration: none;
        }
        .rp-social:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.18);
            transform: translateY(-1px);
        }

        /* Footer */
        .rp-card-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.07);
            text-align: center;
            font-size: 13.5px;
            color: var(--muted);
        }
        .rp-card-footer a {
            color: var(--gold);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.18s;
        }
        .rp-card-footer a:hover { color: #FAD798; }

        /* Show password */
        .rp-showpass {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 2px;
            user-select: none;
            color: rgba(203,213,225,0.78);
            font-size: 13px;
            font-weight: 700;
        }
        .rp-showpass input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--gold);
        }

        @media (max-width: 540px) {
            .rp-card { padding: 32px 22px; border-radius: 20px; }
        }
    </style>
</head>
<body>
    <div class="rp-page">
        <div class="rp-card">

            <div class="rp-card-head">
                <a href="index.php" class="rp-brand">
                    <?php $brand_icon_class = 'rp-brand-icon'; require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                    <span class="rp-brand-name">ReservePro</span>
                </a>
                <h1 class="rp-card-title">Welcome back</h1>
                <p class="rp-card-sub">Log in to continue your journey.</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="rp-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form class="rp-form" method="POST" action="login.php" id="loginForm">

                <div class="rp-field">
                    <label for="email">Email Address</label>
                    <div class="rp-input-wrap">
                        <input type="email" id="email" name="email"
                            placeholder="juan@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required>
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>

                <div class="rp-field">
                    <label for="password">Password</label>
                    <div class="rp-input-wrap">
                        <input type="password" id="password" name="password"
                            placeholder="Enter your password"
                            required>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <label class="rp-showpass" for="show_password">
                        <input type="checkbox" id="show_password" aria-controls="password">
                        Show password
                    </label>
                </div>

                <button type="submit" class="rp-btn" id="submitBtn">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    Log in
                </button>

            </form>

            <div class="rp-divider"><span>or</span></div>

            <a href="google-login.php" class="rp-social">
                <svg width="18" height="18" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>

            <div class="rp-card-footer">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>

        </div>
    </div>

    <script>
        (function () {
            var toggle = document.getElementById('show_password');
            var pass = document.getElementById('password');
            if (!toggle || !pass) return;
            toggle.addEventListener('change', function () {
                pass.type = toggle.checked ? 'text' : 'password';
            });
        })();
    </script>
</body>
</html>
