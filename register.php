<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
requireGuest();

$errors = [];
$success = false;
$fieldErrors = [
    'first_name' => false,
    'last_name' => false,
    'date_of_birth' => false,
    'email' => false,
    'password' => false,
    'confirm_password' => false,
    'terms' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']) ? trim((string)$_POST['terms']) : '';
    // All new signups are guests by default
    $role = 'guest';
    
    if ($terms !== '1') {
        $errors[] = "You must agree to the Terms & Conditions";
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        $result = Auth::register($first_name, $last_name, $email, $password, $role, $date_of_birth);
        
        if ($result['success']) {
            // After signup, show "check your email" instructions
            header('Location: verify-pending.php');
            exit();
        } else {
            $errors = $result['errors'];
        }
    }
}

// Map generic error messages to specific fields for red borders
foreach ($errors as $error) {
    $msg = strtolower((string)$error);

    if (strpos($msg, 'passwords do not match') !== false) {
        $fieldErrors['password'] = true;
        $fieldErrors['confirm_password'] = true;
        continue;
    }

    if (strpos($msg, 'first name') !== false) {
        $fieldErrors['first_name'] = true;
        continue;
    }

    if (strpos($msg, 'last name') !== false) {
        $fieldErrors['last_name'] = true;
        continue;
    }

    if (strpos($msg, 'date of birth') !== false) {
        $fieldErrors['date_of_birth'] = true;
        continue;
    }

    if (strpos($msg, 'email') !== false) {
        $fieldErrors['email'] = true;
        continue;
    }

    if (strpos($msg, 'password') !== false) {
        $fieldErrors['password'] = true;
        continue;
    }

    if (strpos($msg, 'terms') !== false) {
        $fieldErrors['terms'] = true;
        continue;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Sign Up - ReservePro</title>
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
            max-width: 520px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 26px;
            padding: 40px 44px;
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            transition: transform 0.28s ease, background 0.28s ease, border-color 0.28s ease;
        }

        /* Card header */
        .rp-card-head {
            margin-bottom: 32px;
        }
        .rp-card-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(212,165,116,0.12);
            border: 1px solid rgba(212,165,116,0.28);
            color: #D4A574;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 30px;
        }
        .rp-card-eyebrow i { font-size: 10px; }
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
        .rp-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
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
        .rp-field input:focus ~ i,
        .rp-input-wrap:focus-within i { color: #D4A574; }
        .rp-field input.is-error {
            border-color: rgba(239,68,68,0.55) !important;
            box-shadow: 0 0 0 3px rgba(239,68,68,0.1) !important;
        }

        /* Submit button — matches index.php .lp-cta-primary */
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
            margin: 20px 0 4px;
        }
        .rp-divider::before, .rp-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.07);
        }
        .rp-divider span { font-size: 12px; color: #475569; white-space: nowrap; }

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
            .rp-row { grid-template-columns: 1fr; }
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
                <h1 class="rp-card-title">Create your account</h1>
                <p class="rp-card-sub">Join thousands of travelers discovering the Philippines.</p>
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

            <form class="rp-form" method="POST" action="register.php" id="registerForm">

                <div class="rp-row">
                    <div class="rp-field">
                        <label for="first_name">First Name</label>
                        <div class="rp-input-wrap">
                            <input type="text" id="first_name" name="first_name"
                                placeholder="Juan"
                                value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                class="<?php echo $fieldErrors['first_name'] ? 'is-error' : ''; ?>"
                                required>
                            <i class="fa-solid fa-user"></i>
                        </div>
                    </div>
                    <div class="rp-field">
                        <label for="last_name">Last Name</label>
                        <div class="rp-input-wrap">
                            <input type="text" id="last_name" name="last_name"
                                placeholder="dela Cruz"
                                value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                class="<?php echo $fieldErrors['last_name'] ? 'is-error' : ''; ?>"
                                required>
                            <i class="fa-solid fa-user"></i>
                        </div>
                    </div>
                </div>

                <div class="rp-field">
                    <label for="email">Email Address</label>
                    <div class="rp-input-wrap">
                        <input type="email" id="email" name="email"
                            placeholder="juan@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            class="<?php echo $fieldErrors['email'] ? 'is-error' : ''; ?>"
                            required>
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>

                <div class="rp-field">
                    <label for="date_of_birth">Date of Birth</label>
                    <div class="rp-input-wrap">
                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>"
                            class="<?php echo $fieldErrors['date_of_birth'] ? 'is-error' : ''; ?>"
                            required
                        >
                        <i class="fa-solid fa-cake-candles"></i>
                    </div>
                </div>

                <div class="rp-field">
                    <label for="password">Password</label>
                    <div class="rp-input-wrap">
                        <input type="password" id="password" name="password"
                            placeholder="At least 8 characters"
                            class="<?php echo $fieldErrors['password'] ? 'is-error' : ''; ?>"
                            required>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>

                <div class="rp-field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="rp-input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Re-enter your password"
                            class="<?php echo $fieldErrors['confirm_password'] ? 'is-error' : ''; ?>"
                            required>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <label class="rp-showpass" for="show_password">
                        <input type="checkbox" id="show_password" aria-controls="password confirm_password">
                        Show password
                    </label>
                </div>

                <div style="display:flex; gap:10px; align-items:flex-start; margin-top: 4px;">
                    <input
                        type="checkbox"
                        id="terms"
                        name="terms"
                        value="1"
                        <?php echo (($_POST['terms'] ?? '') === '1') ? 'checked' : ''; ?>
                        style="margin-top:4px;"
                        required
                    >
                    <label for="terms" style="margin:0; color: #CBD5E1; font-size: 13px; font-weight: 600; line-height: 1.45;">
                        I agree to the <a href="terms.php" style="color: var(--gold); text-decoration:none; font-weight:800;">Terms &amp; Conditions</a>.
                    </label>
                </div>

                <button type="submit" class="rp-btn" id="submitBtn">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    Create Account
                </button>

            </form>

            <div class="rp-card-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </div>

        </div>
    </div>

    <script src="assets/js/validation.js?v=1.2"></script>
    <script>
        (function () {
            var toggle = document.getElementById('show_password');
            var pass = document.getElementById('password');
            var confirm = document.getElementById('confirm_password');
            if (!toggle || !pass) return;

            function apply() {
                var show = !!toggle.checked;
                pass.type = show ? 'text' : 'password';
                if (confirm) confirm.type = show ? 'text' : 'password';
            }
            toggle.addEventListener('change', apply);
        })();
    </script>
</body>
</html>
