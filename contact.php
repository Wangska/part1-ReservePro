<?php
require_once __DIR__ . '/config/session.php';
$user = isLoggedIn() ? getCurrentUser() : null;

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name))                                      $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($subject))                                   $errors[] = "Subject is required";
    if (empty($message))                                   $errors[] = "Message is required";

    if (empty($errors)) $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact � ReservePro</title>
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/modal.css?v=25.2">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --gold: #D4A574;
            --navy: #06090F;
            --border: rgba(255,255,255,0.08);
            --muted: rgba(203,213,225,0.65);
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            background: var(--navy);
            color: #F1F5F9;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }
        button { cursor: pointer; font-family: inherit; }

        /* -- NAVBAR -- */
        .lp-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 900;
            height: 68px; padding: 0 28px;
            display: flex; align-items: center; justify-content: space-between;
            transition: background .3s, border-bottom .3s;
        }
        .lp-nav.scrolled {
            background: rgba(6,9,15,.9);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(20px);
        }
        .lp-brand { display: flex; align-items: center; gap: 10px; }
        .lp-brand-icon { width: 34px; height: 34px; border-radius: 10px; border: 2px solid rgba(212,165,116,.55); object-fit: contain; }
        .lp-brand-name {
            font-size: 21px; font-weight: 800; letter-spacing: -.5px;
            background: linear-gradient(135deg,#D4A574,#FAD798);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .lp-nav-links { display: flex; align-items: center; gap: 30px; }
        .lp-nav-links a { font-size: 14px; font-weight: 500; color: rgba(226,232,240,.75); transition: color .18s; }
        .lp-nav-links a:hover, .lp-nav-links a.active { color: #D4A574; }
        .lp-nav-actions { display: flex; align-items: center; gap: 10px; }
        .lp-btn-ghost {
            padding: 9px 20px; border: 1px solid rgba(255,255,255,.18); border-radius: 999px;
            background: transparent; color: rgba(241,245,249,.88); font-size: 14px; font-weight: 600;
            transition: background .18s;
        }
        .lp-btn-ghost:hover { background: rgba(255,255,255,.07); }
        .lp-btn-gold {
            display: inline-flex; align-items: center; padding: 9px 22px; border-radius: 999px;
            background: linear-gradient(135deg,#D4A574,#B8935F); color: #fff; font-size: 14px; font-weight: 700; border: none;
            box-shadow: 0 6px 20px rgba(212,165,116,.3); transition: transform .18s, box-shadow .18s;
        }
        .lp-btn-gold:hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(212,165,116,.44); }
        .guest-menu {
            position: relative;
        }
        .guest-menu-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            color: #F3F4F6;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: inherit;
            letter-spacing: -0.01em;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .guest-menu-trigger:hover {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            border-color: rgba(148, 163, 184, 0.3);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }
        .guest-menu.guest-menu-open .guest-menu-trigger {
            border-color: rgba(212, 165, 116, 0.5);
            box-shadow: 0 0 0 1px rgba(212, 165, 116, 0.15);
        }
        .guest-menu-chevron {
            opacity: 0.7;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .guest-menu.guest-menu-open .guest-menu-chevron {
            transform: rotate(180deg);
            opacity: 1;
        }
        .guest-menu-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 180px;
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.97), rgba(15, 23, 39, 0.98));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            box-shadow: 0 18px 60px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.04) inset;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-12px) scale(0.96);
            transform-origin: top right;
            transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.22s;
            z-index: 1100;
        }
        .guest-menu-panel-open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .guest-menu-item {
            display: block;
            padding: 12px 18px;
            color: #E5E7EB;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s ease, color 0.2s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .guest-menu-item:last-child {
            border-bottom: none;
        }
        .guest-menu-item:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #D4A574;
        }
        .guest-menu-item-logout {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 4px;
            padding-top: 12px;
        }
        .guest-menu-item-logout:hover {
            color: #FCA5A5;
        }
        body.light-mode .guest-menu-trigger {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            border-color: rgba(15, 23, 42, 0.1);
            color: #111827;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }
        body.light-mode .guest-menu-trigger:hover {
            background: linear-gradient(145deg, #FFFFFF, #F8FAFC);
            border-color: rgba(184, 147, 95, 0.4);
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        body.light-mode .guest-menu.guest-menu-open .guest-menu-trigger {
            border-color: rgba(184, 147, 95, 0.6);
            box-shadow: 0 0 0 1px rgba(184, 147, 95, 0.2);
        }
        body.light-mode .guest-menu-panel {
            background: linear-gradient(160deg, #FFFFFF, #F8FAFC);
            border-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .guest-menu-item {
            color: rgba(15, 23, 42, 0.90);
            border-bottom-color: rgba(15, 23, 42, 0.06);
            text-align: center;
        }
        body.light-mode .guest-menu-item:hover {
            background: rgba(15, 23, 42, 0.04);
            color: rgba(139, 111, 71, 0.95);
        }
        body.light-mode .guest-menu-item-logout {
            border-top-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .guest-menu-item-logout:hover {
            color: rgba(248, 113, 113, 0.95);
        }

        /* -- HERO -- */
        .ct-hero {
            padding: 160px 24px 100px;
            text-align: center;
        }
        .ct-hero-label {
            display: inline-block; margin-bottom: 24px;
            font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
            color: #D4A574;
        }
        .ct-hero h1 {
            font-size: clamp(42px, 6.5vw, 80px); font-weight: 900;
            line-height: 1.02; letter-spacing: -.04em; color: #fff;
            margin-bottom: 22px;
        }
        .ct-hero h1 span {
            background: linear-gradient(135deg,#D4A574,#FAD798);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .ct-hero p {
            font-size: 18px; line-height: 1.7; color: var(--muted);
            max-width: 480px; margin: 0 auto;
        }

        /* -- LAYOUT -- */
        .ct-wrap { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
        .ct-section { padding: 80px 0; }

        /* -- SECTION HEADING -- */
        .ct-section-label {
            font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
            color: #D4A574; margin-bottom: 14px;
        }
        .ct-section h2 {
            font-size: clamp(26px, 3.5vw, 40px); font-weight: 800;
            line-height: 1.1; letter-spacing: -.03em; color: #fff; margin-bottom: 28px;
        }

        /* -- TWO COLUMN -- */
        .ct-grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 80px;
            align-items: start;
        }

        /* -- CONTACT INFO -- */
        .ct-info-intro {
            font-size: 16px; line-height: 1.85; color: var(--muted); margin-bottom: 48px;
        }
        .ct-items { display: flex; flex-direction: column; gap: 0; }
        .ct-item {
            padding: 22px 0;
            border-bottom: 1px solid var(--border);
            display: grid; grid-template-columns: 120px 1fr; gap: 32px; align-items: baseline;
        }
        .ct-item:first-child { border-top: 1px solid var(--border); }
        .ct-item-label { font-size: 13px; font-weight: 700; color: #fff; }
        .ct-item-value { font-size: 14px; line-height: 1.7; color: var(--muted); }
        .ct-item-value a { color: var(--gold); transition: opacity .15s; }
        .ct-item-value a:hover { opacity: .75; }

        /* -- FORM -- */
        .ct-form-head { margin-bottom: 36px; }
        .ct-form-head p { font-size: 15px; line-height: 1.75; color: var(--muted); margin-top: 10px; }

        .ct-form { display: flex; flex-direction: column; gap: 20px; }
        .ct-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .ct-field { display: flex; flex-direction: column; gap: 7px; }
        .ct-field label { font-size: 13px; font-weight: 600; color: rgba(241,245,249,.75); }
        .ct-field input,
        .ct-field textarea,
        .ct-field select {
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px; font-family: inherit;
            color: #F1F5F9;
            transition: border-color .18s, box-shadow .18s;
            outline: none;
        }
        .ct-field input::placeholder,
        .ct-field textarea::placeholder { color: rgba(203,213,225,.3); }
        .ct-field input:focus,
        .ct-field textarea:focus,
        .ct-field select:focus {
            border-color: rgba(212,165,116,.5);
            box-shadow: 0 0 0 3px rgba(212,165,116,.08);
        }
        .ct-field textarea { resize: vertical; min-height: 130px; }

        .ct-submit {
            padding: 14px 32px; border: none; border-radius: 999px;
            background: linear-gradient(135deg,#D4A574,#B8935F);
            color: #fff; font-size: 15px; font-weight: 700;
            box-shadow: 0 6px 20px rgba(212,165,116,.3);
            transition: transform .18s, box-shadow .18s;
            align-self: flex-start;
        }
        .ct-submit:hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(212,165,116,.44); }

        .ct-success {
            padding: 16px 20px; border-radius: 12px;
            background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.25);
            color: #6EE7B7; font-size: 14px; font-weight: 500;
        }
        .ct-errors {
            padding: 16px 20px; border-radius: 12px;
            background: rgba(248,113,113,.08); border: 1px solid rgba(248,113,113,.2);
            color: #FCA5A5; font-size: 14px;
        }
        .ct-errors ul { padding-left: 18px; }
        .ct-errors ul li { margin-bottom: 4px; }

        /* -- FAQ -- */
        .ct-faq { margin-top: 48px; display: flex; flex-direction: column; gap: 0; }
        .ct-faq-item {
            padding: 26px 0;
            border-bottom: 1px solid var(--border);
            display: grid; grid-template-columns: 260px 1fr; gap: 60px; align-items: baseline;
        }
        .ct-faq-item:first-child { border-top: 1px solid var(--border); }
        .ct-faq-q { font-size: 15px; font-weight: 700; color: #fff; }
        .ct-faq-a { font-size: 15px; line-height: 1.75; color: var(--muted); }

        /* -- FOOTER -- */
        .lp-footer { background: #03050A; border-top: 1px solid rgba(255,255,255,.055); padding: 64px 24px 36px; }
        .lp-footer-inner { max-width: 1180px; margin: 0 auto; }
        .lp-footer-top { display: grid; grid-template-columns: 1.5fr repeat(3,1fr); gap: 44px; margin-bottom: 52px; }
        .lp-footer-brand-desc { margin-top: 14px; font-size: 14px; line-height: 1.7; color: rgba(203,213,225,.6); max-width: 280px; }
        .lp-footer-col h5 { font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgba(248,250,252,.42); margin-bottom: 18px; }
        .lp-footer-col ul { list-style: none; }
        .lp-footer-col ul li { margin-bottom: 11px; }
        .lp-footer-col ul li a { font-size: 14px; color: rgba(203,213,225,.65); transition: color .18s; }
        .lp-footer-col ul li a:hover { color: #D4A574; }
        .lp-footer-bottom { border-top: 1px solid rgba(255,255,255,.055); padding-top: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .lp-footer-bottom p { font-size: 13px; color: rgba(203,213,225,.38); }
        .lp-footer-bottom-links { display: flex; gap: 22px; }
        .lp-footer-bottom-links a { font-size: 13px; color: rgba(203,213,225,.4); transition: color .18s; }
        .lp-footer-bottom-links a:hover { color: rgba(203,213,225,.75); }

        .modal.lp-open { display: flex; align-items: center; justify-content: center; }

        @media (max-width: 1024px) {
            .ct-grid { grid-template-columns: 1fr; gap: 60px; }
            .lp-footer-top { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .lp-nav-links { display: none; }
            .ct-form-row { grid-template-columns: 1fr; }
            .ct-faq-item { grid-template-columns: 1fr; gap: 6px; }
            .ct-item { grid-template-columns: 1fr; gap: 4px; }
            .lp-footer-top { grid-template-columns: 1fr; gap: 32px; }
        }
        @media (max-width: 480px) {
            .lp-nav { padding: 0 18px; }
            .lp-btn-ghost { display: none; }
        }
    </style>
</head>
<body>

<nav class="lp-nav" id="lpNav">
    <a href="index.php" class="lp-brand">
        <img class="lp-brand-icon" src="/part1-ReservePro/background%20image/asd.webp" alt="ReservePro">
        <span class="lp-brand-name">ReservePro</span>
    </a>
    <div class="lp-nav-links">
        <a href="home.php">Browse Stays</a>
        <a href="become-host.php">Become a Host</a>
        <a href="about.php">About</a>
        <a href="contact.php" class="active">Contact</a>
    </div>
    <div class="lp-nav-actions">
        <?php if ($user): ?>
        <div class="guest-menu">
            <button type="button" class="guest-menu-trigger" id="guestMenuTrigger" aria-expanded="false" aria-haspopup="true">
                <span class="guest-menu-name">Hi, <?php echo htmlspecialchars($user['first_name']); ?></span>
                <svg class="guest-menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="guest-menu-panel" id="guestMenuPanel" role="menu" aria-hidden="true">
                <?php if (isset($user['role']) && $user['role'] !== 'admin'): ?>
                <a href="messages.php" role="menuitem" class="guest-menu-item">Messages</a>
                <?php endif; ?>
                <?php if (isset($user['role']) && $user['role'] === 'guest'): ?>
                <a href="my-bookings.php" role="menuitem" class="guest-menu-item">My bookings</a>
                <a href="profile.php" role="menuitem" class="guest-menu-item">Profile</a>
                <?php elseif (isset($user['role']) && $user['role'] === 'host'): ?>
                <a href="host/properties.php" role="menuitem" class="guest-menu-item">Property</a>
                <?php elseif (isset($user['role']) && $user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" role="menuitem" class="guest-menu-item">Admin</a>
                <?php endif; ?>
                <a href="logout.php" role="menuitem" class="guest-menu-item guest-menu-item-logout">Logout</a>
            </div>
        </div>
        <script>
        (function() {
            var trigger = document.getElementById('guestMenuTrigger');
            var panel = document.getElementById('guestMenuPanel');
            var menu = trigger && trigger.closest('.guest-menu');
            if (!trigger || !panel) return;
            function toggle() {
                var open = panel.classList.toggle('guest-menu-panel-open');
                trigger.setAttribute('aria-expanded', open);
                panel.setAttribute('aria-hidden', !open);
                if (menu) menu.classList.toggle('guest-menu-open', open);
            }
            function close() {
                panel.classList.remove('guest-menu-panel-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');
                if (menu) menu.classList.remove('guest-menu-open');
            }
            trigger.addEventListener('click', function(e) { e.stopPropagation(); toggle(); });
            document.addEventListener('click', function() { close(); });
            panel.addEventListener('click', function(e) { e.stopPropagation(); });
        })();
        </script>
        <?php else: ?>
        <a href="login.php" class="lp-btn-ghost" id="openLoginBtn">Sign In</a>
        <a href="register.php" class="lp-btn-gold">Get Started</a>
        <?php endif; ?>
    </div>
</nav>

<section class="ct-hero">
    <div class="ct-hero-label">Contact</div>
    <h1>We'd love to <span>hear from you.</span></h1>
    <p>Questions, feedback, or just want to say hello � reach out and we'll get back to you.</p>
</section>

<div style="border-top:1px solid var(--border);"></div>

<!-- MAIN: INFO + FORM -->
<div class="ct-section ct-wrap">
    <div class="ct-grid">

        <!-- LEFT: contact info -->
        <div>
            <div class="ct-section-label">Reach Us</div>
            <h2>Get in touch</h2>
            <p class="ct-info-intro">We're a small team and we read every message. If you're a host, a guest, or just curious � we're happy to chat.</p>
            <div class="ct-items">
                <div class="ct-item">
                    <div class="ct-item-label">Email</div>
                    <div class="ct-item-value"><a href="mailto:support@reservepro.ph">support@reservepro.ph</a><br>We reply within 24 hours.</div>
                </div>
                <div class="ct-item">
                    <div class="ct-item-label">Location</div>
                    <div class="ct-item-value">Poblacion, Ward II, Minglanilla<br>Cebu 6046, Philippines</div>
                </div>
                <div class="ct-item">
                    <div class="ct-item-label">Hours</div>
                    <div class="ct-item-value">Monday � Friday<br>9:00 AM � 6:00 PM PHT</div>
                </div>
                <div class="ct-item">
                    <div class="ct-item-label">Support</div>
                    <div class="ct-item-value">For urgent booking issues,<br>email us and mark it urgent.</div>
                </div>
            </div>
        </div>

        <!-- RIGHT: form -->
        <div>
            <div class="ct-form-head">
                <div class="ct-section-label">Send a Message</div>
                <h2>Write to us</h2>
                <p>Fill in the form and someone from our team will follow up shortly.</p>
            </div>

            <?php if ($success): ?>
            <div class="ct-success" style="margin-bottom:24px;">Your message has been sent. We'll be in touch soon.</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="ct-errors" style="margin-bottom:24px;">
                <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <form class="ct-form" method="POST">
                <div class="ct-form-row">
                    <div class="ct-field">
                        <label for="ct-name">Name</label>
                        <input type="text" id="ct-name" name="name" placeholder="Your name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="ct-field">
                        <label for="ct-email">Email</label>
                        <input type="email" id="ct-email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="ct-field">
                    <label for="ct-subject">Subject</label>
                    <input type="text" id="ct-subject" name="subject" placeholder="What is this about?" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                </div>
                <div class="ct-field">
                    <label for="ct-message">Message</label>
                    <textarea id="ct-message" name="message" placeholder="Tell us more..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="ct-submit">Send Message</button>
            </form>
        </div>

    </div>
</div>

<div style="border-top:1px solid var(--border);"></div>

<!-- FAQ -->
<div class="ct-section ct-wrap">
    <div class="ct-section-label">FAQ</div>
    <h2>Common questions</h2>
    <div class="ct-faq">
        <div class="ct-faq-item">
            <div class="ct-faq-q">How do I list my property?</div>
            <div class="ct-faq-a">Sign up as a host, complete your profile, and follow the listing steps to add your space. The whole process takes about 15 minutes.</div>
        </div>
        <div class="ct-faq-item">
            <div class="ct-faq-q">What are the fees?</div>
            <div class="ct-faq-a">We take a small platform fee per booking. Hosts keep the majority of their earnings, and there are no hidden charges.</div>
        </div>
        <div class="ct-faq-item">
            <div class="ct-faq-q">Is my payment secure?</div>
            <div class="ct-faq-a">Yes. Payments are processed through PayMongo � a licensed payment processor in the Philippines. We never store your card details.</div>
        </div>
        <div class="ct-faq-item">
            <div class="ct-faq-q">Can I cancel a booking?</div>
            <div class="ct-faq-a">Cancellation terms depend on the property's policy. Check the listing before you book. Most hosts offer flexible options.</div>
        </div>
    </div>
</div>

<footer class="lp-footer">
    <div class="lp-footer-inner">
        <div class="lp-footer-top">
            <div>
                <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                    <img style="width:32px;height:32px;border-radius:10px;border:2px solid rgba(212,165,116,.5);object-fit:contain;" src="/part1-ReservePro/background%20image/asd.webp" alt="ReservePro">
                    <span style="font-size:20px;font-weight:800;background:linear-gradient(135deg,#D4A574,#FAD798);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-.5px;">ReservePro</span>
                </a>
                <p class="lp-footer-brand-desc">Find, compare, and book curated stays across the Philippines. Built for travelers, designed for hosts.</p>
            </div>
            <div class="lp-footer-col">
                <h5>Explore</h5>
                <ul>
                    <li><a href="home.php">Browse Stays</a></li>
                    <li><a href="experiences.php">Experiences</a></li>
                    <li><a href="become-host.php">Become a Host</a></li>
                </ul>
            </div>
            <div class="lp-footer-col">
                <h5>Company</h5>
                <ul>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="#">Careers</a></li>
                </ul>
            </div>
            <div class="lp-footer-col">
                <h5>Support</h5>
                <ul>
                    <li><a href="contact.php">Help Center</a></li>
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="lp-footer-bottom">
            <p>&copy; 2026 ReservePro. All rights reserved.</p>
            <div class="lp-footer-bottom-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
    </div>
</footer>

<?php if (!$user): ?>
<div id="loginModal" class="modal">
    <div class="modal-overlay" id="loginModalOverlay"></div>
    <div class="modal-content">
        <button class="modal-close" id="closeLoginBtn">&times;</button>
        <div class="modal-header">
            <div style="margin-bottom:12px;">
                <img src="background%20image/z.jpg" alt="" style="width:64px;height:64px;border-radius:18px;object-fit:cover;display:block;margin:0 auto;" onerror="this.style.display='none'">
            </div>
            <h2>Welcome Back</h2>
            <p>Log in to your ReservePro account</p>
        </div>
        <form class="modal-form" method="POST" action="login.php">
            <div class="form-group">
                <label for="lp-email">Email</label>
                <input type="email" id="lp-email" name="email" placeholder="john@example.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="lp-password">Password</label>
                <input type="password" id="lp-password" name="password" placeholder="Enter your password" required>
            </div>
            
        </form>
        <div class="modal-divider"><span>or</span></div>
        <button class="modal-btn-social" onclick="window.location.href='google-login.php'">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
        </button>
        <div class="modal-footer">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    var nav=document.getElementById('lpNav');
    function t(){nav.classList.toggle('scrolled',window.scrollY>50);}
    window.addEventListener('scroll',t,{passive:true}); t();
})();
<?php if ($user): ?>
(function(){
    var btn=document.getElementById('userPillBtn'),panel=document.getElementById('userPanel');
    if(!btn)return;
    btn.addEventListener('click',function(e){e.stopPropagation();panel.classList.toggle('open');btn.setAttribute('aria-expanded',panel.classList.contains('open'));});
    document.addEventListener('click',function(){panel.classList.remove('open');btn.setAttribute('aria-expanded','false');});
})();
<?php else: ?>
(function(){
    var ob=document.getElementById('openLoginBtn'),cl=document.getElementById('closeLoginBtn'),ov=document.getElementById('loginModalOverlay'),m=document.getElementById('loginModal');
    if(!ob||!m)return;
    ob.addEventListener('click',function(){m.classList.add('lp-open');});
    cl.addEventListener('click',function(){m.classList.remove('lp-open');});
    ov.addEventListener('click',function(){m.classList.remove('lp-open');});
})();
<?php endif; ?>
</script>
</body>
</html>
