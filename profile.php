<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$user = getCurrentUser();

// Guests only (hosts have their own profile page)
if (($user['role'] ?? '') !== 'guest') {
    header('Location: ' . (($user['role'] ?? '') === 'host' ? 'host/profile.php' : 'admin/dashboard.php'));
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
$date_of_birth = $old['date_of_birth'] ?? ($user['date_of_birth'] ?? '');
$profile_photo = (string)($user['profile_photo'] ?? '');

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
        .pf-form-group input[type="file"] { padding: 10px 12px; }

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
            overflow: hidden;
        }
        .pf-banner-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
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

        /* ── Notification Button ── */
        .adm-notif-wrap {
            position: relative;
        }
        .adm-notif-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.06);
            color: #A3A3A3;
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s;
        }
        .adm-notif-btn:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(212, 165, 116, 0.4);
        }
        .adm-notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: #EF4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            pointer-events: none;
        }
        .adm-notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 340px;
            max-width: calc(100vw - 32px);
            border-radius: 18px;
            background: rgba(17, 24, 39, 0.97);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.42);
            z-index: 9999;
            overflow: hidden;
        }
        .adm-notif-dropdown-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 14px 11px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .adm-notif-dropdown-title {
            font-size: 13px;
            font-weight: 900;
            color: #F1F5F9;
            letter-spacing: -0.01em;
        }
        .adm-notif-markall {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 10px;
            cursor: pointer;
        }
        .adm-notif-markall:hover {
            background: rgba(255, 255, 255, 0.11);
        }
        .adm-notif-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            max-height: 340px;
            overflow-y: auto;
        }
        .adm-notif-item {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 9px 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(255, 255, 255, 0.03);
        }
        .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.32);
            background: rgba(212, 165, 116, 0.07);
        }
        .adm-notif-item-body {
            flex: 1;
            min-width: 0;
        }
        .adm-notif-item strong {
            font-size: 12px;
            font-weight: 700;
            color: #E2E8F0;
            display: block;
        }
        .adm-notif-item small {
            display: block;
            font-size: 11px;
            color: #94A3B8;
            margin-top: 2px;
            line-height: 1.4;
        }
        .adm-notif-item-actions {
            display: flex;
            gap: 4px;
        }
        .adm-notif-mark {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #CBD5E1;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 8px;
            cursor: pointer;
        }
        .adm-notif-mark:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .adm-notif-empty {
            padding: 14px 10px;
            color: #94A3B8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        /* Light mode overrides */
        body.light-mode .adm-notif-btn {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.10);
            color: #6B7280;
        }
        body.light-mode .adm-notif-btn:hover {
            background: #F1F5F9;
        }
        body.light-mode .adm-notif-dropdown {
            background: #FFFFFF;
            border-color: rgba(15, 23, 42, 0.10);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
        }
        body.light-mode .adm-notif-dropdown-head {
            border-bottom-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .adm-notif-dropdown-title {
            color: #0f172a;
        }
        body.light-mode .adm-notif-item {
            border-color: rgba(15, 23, 42, 0.08);
            background: rgba(15, 23, 42, 0.02);
        }
        body.light-mode .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.22);
            background: rgba(212, 165, 116, 0.04);
        }
        body.light-mode .adm-notif-item strong {
            color: #0f172a;
        }
        body.light-mode .adm-notif-item small {
            color: #64748B;
        }
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
                    <div class="user-avatar" style="background: linear-gradient(135deg, #3B82F6, #2563EB); overflow:hidden;">
                        <?php if (!empty($profile_photo)): ?>
                            <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile photo" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="this.style.display='none'">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        <?php endif; ?>
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
                        <?php if (!empty($profile_photo)): ?>
                            <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile photo" onerror="this.style.display='none'">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="pf-banner-info">
                        <h2 class="pf-banner-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <p class="pf-banner-email"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="pf-banner-actions" style="display: flex; gap: 10px; align-items: center;">
                        <div class="adm-notif-wrap" id="admNotifWrap">
                            <button class="adm-notif-btn" id="admNotifBtn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="admNotifDropdown">
                                <i class="fa-solid fa-bell" aria-hidden="true" style="font-size: 17px;"></i>
                                <span class="adm-notif-badge" id="admNotifBadge" hidden></span>
                            </button>
                            <div class="adm-notif-dropdown" id="admNotifDropdown" hidden>
                                <div class="adm-notif-dropdown-head">
                                    <span class="adm-notif-dropdown-title">Notifications</span>
                                    <button class="adm-notif-markall" id="admNotifMarkAll" type="button">Mark all read</button>
                                </div>
                                <div class="adm-notif-list" id="admNotifList">
                                    <div class="adm-notif-empty">Loading&hellip;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Details Card -->
            <div class="pf-card">
                <div class="pf-card-head" style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="pf-card-head-icon"><i class="fa-solid fa-circle-info"></i></div>
                        <h3>Account Details</h3>
                    </div>
                    <button class="pf-edit-btn" onclick="openEditModal()" style="margin: 0;">
                        <i class="fa-solid fa-edit"></i> Edit Profile
                    </button>
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
                    <div class="pf-detail-row">
                        <div class="pf-detail-icon"><i class="fa-solid fa-cake-candles"></i></div>
                        <div>
                            <div class="pf-detail-label">Date of Birth</div>
                            <div class="pf-detail-value"><?php echo !empty($user['date_of_birth']) ? htmlspecialchars($user['date_of_birth']) : '—'; ?></div>
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
                <h3>Edit Profile</h3>
                <button class="pf-modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="pf-modal-body">
                <form method="post" action="update-profile.php" enctype="multipart/form-data">
                    <div class="pf-form-group">
                        <label for="first_name">First name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="pf-form-group">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                    <div class="pf-form-group">
                        <label for="date_of_birth">Date of birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars((string)$date_of_birth); ?>" required>
                    </div>
                    <div class="pf-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="pf-form-group">
                        <label for="profile_photo">Profile photo (optional)</label>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
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

        // Notification system
        (function(){
            var btn = document.getElementById('admNotifBtn');
            var dropdown = document.getElementById('admNotifDropdown');
            var badge = document.getElementById('admNotifBadge');
            var list = document.getElementById('admNotifList');
            var markAllBtn = document.getElementById('admNotifMarkAll');
            if (!btn || !dropdown) return;

            function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

            function render(items){
                if (!items || !items.length){
                    list.innerHTML = '<div class="adm-notif-empty">No notifications yet.</div>';
                    return;
                }
                list.innerHTML = items.map(function(n){
                    var unread = String(n.is_read)==='0';
                    var link = n.link ? String(n.link) : '';
                    var body = n.body ? String(n.body) : '';
                    var attrs = '';
                    if (link) attrs = ' data-link="'+esc(link)+'" style="cursor:pointer"';
                    return '<div class="adm-notif-item'+(unread?' unread':'')+'"'+attrs+'>'+ 
                        '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+ (body?'<small>'+esc(n.body)+'</small>':'')+'</div>'+ '<div class="adm-notif-item-actions">'+ (unread?'<button class="adm-notif-mark" data-mark="'+esc(n.id)+'">Mark read</button>':'')+'</div></div>';
                }).join('');}
            }

            function load(){
                fetch('api/notifications-list.php?limit=8', {credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data||!data.ok) return;
                        var unread = parseInt(data.unread||0, 10);
                        var items = data.items||[];
                        if (items.length > 0) {
                            if (unread > 0) {
                                badge.textContent = unread > 99 ? '99+' : String(unread);
                                badge.hidden = false;
                            } else {
                                badge.hidden = true;
                            }
                        } else {
                            badge.hidden = true;
                        }
                        render(items);
                    })
                    .catch(function(){ list.innerHTML='<div class="adm-notif-empty">Failed to load.</div>'; badge.hidden = true; });
            }

            function mark(id){
                var fd = new FormData();
                if (id) fd.append('id', String(id));
                fetch('api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){ if(data&&data.ok) load(); })
                    .catch(function(){});
            }

            list.addEventListener('click', function(e){
                var item = e.target && e.target.closest && e.target.closest('.adm-notif-item');
                if (!item) return;
                
                var hasMarkAttr = item.hasAttribute('data-mark');
                var hasLinkAttr = item.hasAttribute('data-link');
                
                if (hasMarkAttr) {
                    var id = parseInt(item.getAttribute('data-mark'), 10);
                    if (id) {
                        var url = hasLinkAttr ? item.getAttribute('data-link') : null;
                        var fd = new FormData();
                        fd.append('id', String(id));
                        fetch('api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                            .then(function(r){ return r.json(); })
                            .then(function(data){ 
                                if(data&&data.ok) {
                                    if (url) window.location.href = url;
                                    else load();
                                }
                            })
                            .catch(function(){});
                        return;
                    }
                }
                
                if (hasLinkAttr) {
                    var url = item.getAttribute('data-link');
                    if (url) window.location.href = url;
                }
            });

            markAllBtn.addEventListener('click', function(){ mark(0); });

            btn.addEventListener('click', function(e){
                e.stopPropagation();
                var open = !dropdown.hidden;
                dropdown.hidden = open;
                btn.setAttribute('aria-expanded', String(!open));
                if (!open) load();
            });

            document.addEventListener('click', function(e){
                if (!document.getElementById('admNotifWrap').contains(e.target)){
                    dropdown.hidden = true;
                    btn.setAttribute('aria-expanded','false');
                }
            });

            load();
        })();
    </script>
</body>
</html>