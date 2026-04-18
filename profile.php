<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$user = getCurrentUser();

// Profile page is for guest accounts only; hosts and admins are redirected
if ($user['role'] !== 'guest') {
    if ($user['role'] === 'host') {
        header('Location: host/dashboard.php');
        exit();
    }
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    }
    header('Location: dashboard.php');
    exit();
}

$errors = isset($_SESSION['profile_errors']) ? $_SESSION['profile_errors'] : [];
$old = isset($_SESSION['profile_old']) ? $_SESSION['profile_old'] : null;
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';
if (isset($_SESSION['profile_errors'])) unset($_SESSION['profile_errors']);
if (isset($_SESSION['profile_old'])) unset($_SESSION['profile_old']);
if (isset($_SESSION['profile_updated'])) unset($_SESSION['profile_updated']);

$first_name = $old['first_name'] ?? $user['first_name'];
$last_name = $old['last_name'] ?? $user['last_name'];
$email = $old['email'] ?? $user['email'];

$role_label = isset($user['role']) ? ucfirst($user['role']) : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Profile - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=12.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.5">
    <style>
        body.profile-page-body {
            background: #06090F !important;
        }
        body.profile-page-body::before,
        body.profile-page-body::after {
            display: none !important;
        }

        .pf-form-group { margin-bottom: 16px; }
        .pf-form-group label {
            display: block; margin-bottom: 6px;
            font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: #94A3B8;
        }
        .pf-form-group input {
            width: 100%; padding: 11px 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 12px; color: #E2E8F0; font-size: 14px;
            font-weight: 600; box-sizing: border-box;
            transition: border-color 0.2s ease;
        }
        .pf-form-group input:focus { outline: none; border-color: rgba(212,165,116,0.5); }

        .pf-info-row {
            display: flex; align-items: center; gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(148,163,184,0.08);
        }
        .pf-info-row:last-child { border-bottom: none; }
        .pf-info-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #94A3B8; width: 90px; flex-shrink: 0; }
        .pf-info-value { font-size: 14px; color: #E2E8F0; font-weight: 600; }


        .pf-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; font-size: 14px; font-weight: 600; }
        .pf-alert-error { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); color: #fca5a5; }
        .pf-alert-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.35); color: #86efac; }
        .pf-alert ul { margin: 0; padding-left: 18px; }

        .pf-submit {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F; border: none; border-radius: 12px;
            font-weight: 800; font-size: 14px; cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .pf-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(212,165,116,0.3); }

        /* Light mode overrides */
        body.light-mode .pf-form-group label { color: #64748B; }
        body.light-mode .pf-form-group input { background: #F8FAFC !important; border-color: #E2E8F0 !important; color: #0f172a !important; }
        body.light-mode .pf-form-group input:focus { border-color: rgba(184,147,95,0.5) !important; }
        body.light-mode .pf-info-label { color: #64748B; }
        body.light-mode .pf-info-value { color: #0f172a; }
        body.light-mode .pf-info-row { border-bottom-color: #F1F5F9; }

        /* ── Profile Banner ── */
        .pf-banner {
            background: linear-gradient(135deg, #0f1a2e 0%, #111827 50%, #0a0f1e 100%);
            border: 1px solid rgba(148,163,184,0.10);
            border-radius: 20px;
            padding: 36px 36px 32px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        .pf-banner::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(212,165,116,0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .pf-banner::after {
            content: '';
            position: absolute;
            bottom: -40px; left: 40px;
            width: 160px; height: 160px;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .pf-banner-inner {
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }
        .pf-banner-avatar {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 800; color: #fff;
            border: 3px solid rgba(59,130,246,0.4);
            box-shadow: 0 8px 24px rgba(59,130,246,0.25);
            flex-shrink: 0;
        }
        .pf-banner-info { flex: 1; min-width: 0; }
        .pf-banner-name {
            font-size: 22px; font-weight: 800; color: #F1F5F9;
            margin: 0 0 4px; line-height: 1.2;
        }
        .pf-banner-email {
            font-size: 13px; color: #94A3B8; font-weight: 500;
            margin: 0 0 10px;
        }
        .pf-banner-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

        body.light-mode .pf-banner { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-color: #E2E8F0; }
        body.light-mode .pf-banner-name { color: #0f172a; }
        body.light-mode .pf-banner-email { color: #64748B; }

        /* ── Info Card ── */
        .pf-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.10);
            border-radius: 16px;
            overflow: hidden;
        }
        .pf-card-head {
            display: flex; align-items: center; gap: 10px;
            padding: 16px 22px;
            border-bottom: 1px solid rgba(148,163,184,0.08);
        }
        .pf-card-head-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: rgba(212,165,116,0.12);
            display: flex; align-items: center; justify-content: center;
            color: #D4A574; font-size: 14px;
        }
        .pf-card-head h3 { margin: 0; font-size: 14px; font-weight: 800; color: #E2E8F0; }
        .pf-card-body { padding: 4px 0; }
        .pf-detail-row {
            display: flex; align-items: center;
            padding: 14px 22px;
            border-bottom: 1px solid rgba(148,163,184,0.06);
            gap: 16px;
        }
        .pf-detail-row:last-child { border-bottom: none; }
        .pf-detail-icon {
            width: 34px; height: 34px; border-radius: 9px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(148,163,184,0.10);
            display: flex; align-items: center; justify-content: center;
            color: #64748B; font-size: 13px; flex-shrink: 0;
        }
        .pf-detail-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748B; margin-bottom: 2px; }
        .pf-detail-value { font-size: 14px; font-weight: 600; color: #E2E8F0; }

        body.light-mode .pf-card { background: #fff; border-color: #E2E8F0; }
        body.light-mode .pf-card-head { border-bottom-color: #E2E8F0; }
        body.light-mode .pf-card-head h3 { color: #0f172a; }
        body.light-mode .pf-detail-row { border-bottom-color: #F1F5F9; }
        body.light-mode .pf-detail-label { color: #94A3B8; }
        body.light-mode .pf-detail-value { color: #0f172a; }
        body.light-mode .pf-detail-icon { background: #F8FAFC; border-color: #E2E8F0; }

        /* ── Modal ── */
        .pf-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .pf-modal-overlay.open { display: flex; }
        .pf-modal {
            background: #0F172A;
            border: 1px solid rgba(148,163,184,0.16);
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: pf-modal-in 0.2s ease;
        }
        @keyframes pf-modal-in {
            from { opacity: 0; transform: translateY(16px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .pf-modal-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px 16px;
            border-bottom: 1px solid rgba(148,163,184,0.10);
        }
        .pf-modal-head h3 { margin: 0; font-size: 16px; font-weight: 800; color: #E2E8F0; }
        .pf-modal-close {
            background: rgba(255,255,255,0.06); border: none;
            color: #94A3B8; width: 32px; height: 32px; border-radius: 8px;
            cursor: pointer; font-size: 15px;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }
        .pf-modal-close:hover { background: rgba(255,255,255,0.12); color: #E2E8F0; }
        .pf-modal-body { padding: 22px 24px 24px; }

        body.light-mode .pf-modal { background: #fff; border-color: #E2E8F0; }
        body.light-mode .pf-modal-head { border-bottom-color: #E2E8F0; }
        body.light-mode .pf-modal-head h3 { color: #0f172a; }

        /* ── Edit button ── */
        .pf-edit-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px;
            background: rgba(212,165,116,0.12);
            border: 1px solid rgba(212,165,116,0.30);
            color: #D4A574; border-radius: 10px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            transition: background 0.15s, transform 0.15s;
            text-decoration: none;
        }
        .pf-edit-btn:hover { background: rgba(212,165,116,0.22); transform: translateY(-1px); }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page profile-page-body">
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="profile.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span>Profile</span>
                </a>
                <a href="my-bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>My Bookings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
                <a href="home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>Home</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($role_label); ?></div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">

            <?php if ($updated): ?>
            <div class="pf-alert pf-alert-success"><i class="fa-solid fa-circle-check"></i> Your profile has been updated successfully.</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="pf-alert pf-alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Profile Banner -->
            <div class="pf-banner">
                <div class="pf-banner-inner">
                    <div class="pf-banner-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="pf-banner-info">
                        <h2 class="pf-banner-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <p class="pf-banner-email"><?php echo htmlspecialchars($user['email']); ?></p>

                    </div>
                    <div class="pf-banner-actions">
                        <button class="pf-edit-btn" onclick="openEditModal()">
                            <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Account Details Card -->
            <div class="pf-card">
                <div class="pf-card-head">
                    <div class="pf-card-head-icon"><i class="fa-solid fa-circle-info"></i></div>
                    <h3>Account Details</h3>
                </div>
                <div class="pf-card-body">
                    <div class="pf-detail-row">
                        <div class="pf-detail-icon"><i class="fa-solid fa-user"></i></div>
                        <div>
                            <div class="pf-detail-label">Full Name</div>
                            <div class="pf-detail-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        </div>
                    </div>
                    <div class="pf-detail-row">
                        <div class="pf-detail-icon"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <div class="pf-detail-label">Email Address</div>
                            <div class="pf-detail-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Edit Profile Modal -->
    <div class="pf-modal-overlay" id="editModal" onclick="handleOverlayClick(event)">
        <div class="pf-modal">
            <div class="pf-modal-head">
                <h3><i class="fa-solid fa-pen-to-square" style="color:#D4A574; margin-right:8px;"></i>Edit Profile</h3>
                <button class="pf-modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="pf-modal-body">
                <form method="post" action="update-profile.php">
                    <div class="pf-form-group">
                        <label for="first_name">First name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="pf-form-group">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                    <div class="pf-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
                        <button type="button" onclick="closeEditModal()" style="padding:10px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(148,163,184,0.18); border-radius:10px; color:#94A3B8; font-weight:700; font-size:13px; cursor:pointer;">Cancel</button>
                        <button type="submit" class="pf-submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js?v=27.5"></script>
    <script>
        function openEditModal() {
            document.getElementById('editModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('open');
            document.body.style.overflow = '';
        }
        function handleOverlayClick(e) {
            if (e.target === document.getElementById('editModal')) closeEditModal();
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditModal();
        });
        <?php if (!empty($errors)): ?>
        window.addEventListener('DOMContentLoaded', function() { openEditModal(); });
        <?php endif; ?>
    </script>
</body>
</html>