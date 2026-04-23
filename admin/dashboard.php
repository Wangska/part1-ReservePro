<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Check if user is admin (for now, you can manually set this in database)
// UPDATE users SET role='admin' WHERE id=1;

// Get pending properties
$conn = getDBConnection();
$result = $conn->query("
    SELECT p.*, u.first_name, u.last_name, u.email,
    COALESCE(
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
        (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
    ) as primary_photo
    FROM properties p
    JOIN users u ON p.host_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");
$pending_properties = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_properties'] = $conn->query("SELECT COUNT(*) as count FROM properties")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM properties WHERE status='pending'")->fetch_assoc()['count'];
$stats['approved'] = $conn->query("SELECT COUNT(*) as count FROM properties WHERE status='approved'")->fetch_assoc()['count'];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['total_bookings'] = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$stats['pending_host_verifications'] = $conn->query("SELECT COUNT(*) as count FROM host_documents WHERE verification_status='pending'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Admin Dashboard - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-dashboard-page {
            background: #06090F !important;
        }
        body.admin-dashboard-page::before,
        body.admin-dashboard-page::after {
            display: none !important;
        }
        .admin-dashboard-page .host-main {
            background: transparent;
        }

        .admin-dashboard-page .dashboard-hero {
            align-items: stretch;
            gap: 20px;
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 24px;
            padding: 28px 30px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.28);
        }

        .admin-dashboard-page .dashboard-eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(212, 165, 116, 0.14);
            color: #F3D9B4;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admin-dashboard-page .dashboard-hero h1 {
            margin-bottom: 10px;
        }

        .admin-dashboard-page .dashboard-hero .subtitle {
            max-width: 680px;
            line-height: 1.6;
            color: #CBD5E1;
        }

        .admin-dashboard-page .dashboard-summary-card {
            min-width: 220px;
            margin-left: auto;
            padding: 22px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
        }

        .admin-dashboard-page .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94A3B8;
            font-weight: 700;
        }

        .admin-dashboard-page .dashboard-summary-card strong {
            font-size: 36px;
            line-height: 1;
            color: #FFFFFF;
        }

        .admin-dashboard-page .summary-text {
            font-size: 14px;
            color: #CBD5E1;
        }

        .admin-dashboard-page .stats-grid {
            gap: 18px;
            margin-top: 28px;
        }

        .admin-dashboard-page .stat-card {
            padding: 22px;
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .admin-dashboard-page .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 24px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(212, 165, 116, 0.3);
        }

        .admin-dashboard-page .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #E2E8F0;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.14);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .admin-dashboard-page .stat-icon i {
            font-size: 20px;
        }

        .admin-dashboard-page .stat-icon-indigo {
            color: #C7D2FE;
        }

        .admin-dashboard-page .stat-icon-amber {
            color: #FDE68A;
        }

        .admin-dashboard-page .stat-icon-sky {
            color: #BAE6FD;
        }

        .admin-dashboard-page .stat-icon-emerald {
            color: #A7F3D0;
        }

        .admin-dashboard-page .stat-content p {
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #94A3B8;
        }

        .admin-dashboard-page .stat-content h3 {
            margin-bottom: 6px;
            font-size: 30px;
        }

        .admin-dashboard-page .stat-meta {
            display: block;
            font-size: 13px;
            line-height: 1.5;
            color: #CBD5E1;
        }

        .admin-dashboard-page .dashboard-section-header {
            margin-top: 8px;
            margin-bottom: 18px;
            align-items: flex-end;
        }

        .admin-dashboard-page .dashboard-section-header h2 {
            margin-bottom: 6px;
            color: #FFFFFF;
        }

        .admin-dashboard-page .dashboard-section-header p {
            margin: 0;
            color: #94A3B8;
            font-size: 14px;
        }



        .admin-dashboard-page .empty-state {
            padding: 52px 36px;
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.16);
        }

        .admin-dashboard-page .empty-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #FBBF24;
        }

        .admin-dashboard-page .review-list {
            gap: 18px;
        }

        .admin-dashboard-page .review-card {
            gap: 20px;
            padding: 20px;
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .admin-dashboard-page .review-image {
            width: 260px;
            height: 190px;
            border-radius: 16px;
        }

        .admin-dashboard-page .review-header {
            gap: 20px;
        }

        .admin-dashboard-page .review-header h3 {
            margin-bottom: 6px;
        }

        .admin-dashboard-page .review-host,
        .admin-dashboard-page .review-location,
        .admin-dashboard-page .review-description,
        .admin-dashboard-page .review-details {
            color: #CBD5E1;
        }

        .admin-dashboard-page .review-description {
            font-size: 14px;
            line-height: 1.7;
        }

        .admin-dashboard-page .review-location i {
            margin-right: 8px;
            color: #FBBF24;
        }

        .admin-dashboard-page .review-details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .admin-dashboard-page .review-detail-item {
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .admin-dashboard-page .review-detail-label {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #94A3B8;
        }

        .admin-dashboard-page .review-detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #FFFFFF;
        }

        .admin-dashboard-page .review-actions {
            gap: 10px;
        }

        .admin-dashboard-page .btn-approve,
        .admin-dashboard-page .btn-reject,
        .admin-dashboard-page .btn-view {
            min-height: 38px;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 13px;
        }

        body.light-mode.admin-dashboard-page .host-main {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.9) 0%, rgba(248, 250, 252, 0) 260px);
        }

        body.light-mode.admin-dashboard-page .dashboard-hero,
        body.light-mode.admin-dashboard-page .stat-card,
        body.light-mode.admin-dashboard-page .empty-state,
        body.light-mode.admin-dashboard-page .review-card {
            background: #FFFFFF;
            border-color: rgba(15, 23, 42, 0.08);
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
        }

        body.light-mode.admin-dashboard-page .dashboard-eyebrow {
            background: rgba(184, 147, 95, 0.12);
            color: #8B6F47;
        }

        body.light-mode.admin-dashboard-page .dashboard-hero .subtitle,
        body.light-mode.admin-dashboard-page .summary-text,
        body.light-mode.admin-dashboard-page .stat-meta,
        body.light-mode.admin-dashboard-page .dashboard-section-header p,
        body.light-mode.admin-dashboard-page .review-host,
        body.light-mode.admin-dashboard-page .review-location,
        body.light-mode.admin-dashboard-page .review-description,
        body.light-mode.admin-dashboard-page .review-details {
            color: #475569 !important;
        }

        body.light-mode.admin-dashboard-page .summary-label,
        body.light-mode.admin-dashboard-page .stat-content p,
        body.light-mode.admin-dashboard-page .review-detail-label {
            color: #64748B !important;
        }

        body.light-mode.admin-dashboard-page .dashboard-summary-card {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.08);
        }

        body.light-mode.admin-dashboard-page .stat-icon {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        body.light-mode.admin-dashboard-page .stat-icon-indigo {
            color: #4F46E5;
        }

        body.light-mode.admin-dashboard-page .stat-icon-amber {
            color: #B45309;
        }

        body.light-mode.admin-dashboard-page .stat-icon-sky {
            color: #0369A1;
        }

        body.light-mode.admin-dashboard-page .stat-icon-emerald {
            color: #047857;
        }

        body.light-mode.admin-dashboard-page .dashboard-summary-card strong,
        body.light-mode.admin-dashboard-page .dashboard-section-header h2,
        body.light-mode.admin-dashboard-page .review-detail-value {
            color: #0F172A;
        }

        body.light-mode.admin-dashboard-page .review-detail-item {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.06);
        }

        @media (max-width: 1024px) {
            .admin-dashboard-page .dashboard-hero {
                flex-direction: column;
            }

            .admin-dashboard-page .dashboard-summary-card {
                margin-left: 0;
                min-width: 0;
            }

            .admin-dashboard-page .review-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .admin-dashboard-page .host-main {
                padding: 20px;
            }

            .admin-dashboard-page .dashboard-hero {
                padding: 22px;
            }

            .admin-dashboard-page .review-details {
                grid-template-columns: 1fr;
            }

            .admin-dashboard-page .review-image {
                width: 100%;
                height: 210px;
            }
        }

        /* ── Notification bell dropdown ─────────────────────────────── */
        .admin-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }

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
            display: block;
            color: #F1F5F9;
            font-weight: 800;
            font-size: 12px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .adm-notif-item small {
            display: block;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
        }

        .adm-notif-item-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
            flex-shrink: 0;
        }

        .adm-notif-link {
            color: #FDE68A;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
        }

        .adm-notif-mark {
            border: 0;
            background: transparent;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
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
            border-color: rgba(15, 23, 42, 0.08);
        }

        body.light-mode .adm-notif-dropdown-title {
            color: #0F172A;
        }

        body.light-mode .adm-notif-markall {
            background: #F8FAFC;
            color: #0F172A;
            border-color: rgba(15, 23, 42, 0.10);
        }

        body.light-mode .adm-notif-item {
            background: #F8FAFC;
            border-color: #E2E8F0;
        }

        body.light-mode .adm-notif-item.unread {
            background: rgba(212, 165, 116, 0.10);
            border-color: rgba(212, 165, 116, 0.40);
        }

        body.light-mode .adm-notif-item strong {
            color: #0F172A;
        }

        body.light-mode .adm-notif-item small {
            color: #475569;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-dashboard-page">
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                    <span>Dashboard</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span>
                    <span>Analytics</span>
                </a>
                <a href="refunds.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                    <span>Refunds</span>
                </a>
                <a href="host-verifications.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <span>Host Verifications</span>
                </a>
                <a href="submissions.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                    <span>Submissions</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span>Users</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                    <span>All Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="commission.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
                    <span>Commission</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                
                <!-- Theme Toggle -->

                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="properties-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <h1>Admin Dashboard</h1>
                    <p></p>
                </div>
                <div class="admin-hero-actions">
                    <div class="adm-notif-wrap" id="admNotifWrap">
                        <button class="adm-notif-btn" id="admNotifBtn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="admNotifDropdown">
                            <i class="fa-solid fa-bell" aria-hidden="true"></i>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-indigo"><i class="fa-solid fa-house" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Total Properties</p>
                        <h3><?php echo $stats['total_properties']; ?></h3>
                        <span class="stat-meta"></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Pending Review</p>
                        <h3><?php echo $stats['pending']; ?></h3>
                        <span class="stat-meta"></span>
                    </div>
                </div>
                
                <a href="submissions.php" class="stat-card stat-card-link" title="Review user submissions">
                    <div class="stat-icon stat-icon-sky"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>User Submissions</p>
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <span class="stat-meta"></span>
                    </div>
                </a>

                <div class="stat-card">
                    <div class="stat-icon stat-icon-sky"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Total Users</p>
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <span class="stat-meta"></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-emerald"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Total Bookings</p>
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <span class="stat-meta"></span>
                    </div>
                </div>
            </div>

            <!-- Pending Reviews -->
            <div class="section-header dashboard-section-header">
                <div>
                    <h2>Pending Property Reviews</h2>
                </div>

            </div>

            <?php if (empty($pending_properties)): ?>
                <div class="empty-state">
                    <span class="empty-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                    <h3>No pending reviews</h3>

                </div>
            <?php else: ?>
                <div class="review-list">
                    <?php foreach ($pending_properties as $property): 
                        $raw_photo = $property['primary_photo'] ?? '';
                        if (!empty($raw_photo) && strpos($raw_photo, 'http') !== 0) {
                            $photo_url = htmlspecialchars('../' . ltrim($raw_photo, '/'));
                        } elseif (!empty($raw_photo)) {
                            $photo_url = htmlspecialchars($raw_photo);
                        } else {
                            // Clear "no photo" placeholder so it's obvious the host hasn't uploaded images yet
                            $photo_url = 'https://via.placeholder.com/400x260?text=No+Photo';
                        }
                    ?>
                        <div class="review-card">
                            <div class="review-image">
                                <img src="<?php echo $photo_url; ?>" alt="Property" onerror="this.src='https://via.placeholder.com/400x260?text=No+Photo'">
                            </div>
                            <div class="review-content">
                                <div class="review-header">
                                    <div>
                                        <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                        <p class="review-host">Hosted by <?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></p>
                                        <p class="review-location"><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                                    </div>
                                    <div class="review-price">
                                        <strong>₱<?php echo number_format($property['price_per_night'], 2); ?></strong>
                                    </div>
                                </div>
                                
                                <p class="review-description"><?php echo htmlspecialchars(substr($property['description'], 0, 200)) . '...'; ?></p>
                                
                                <div class="review-details">
                                    <div class="review-detail-item">
                                        <span class="review-detail-label">Property Type</span>
                                        <span class="review-detail-value"><?php echo ucfirst($property['property_type']); ?></span>
                                    </div>
                                    <div class="review-detail-item">
                                        <span class="review-detail-label">Bedrooms</span>
                                        <span class="review-detail-value"><?php echo $property['bedrooms']; ?> beds</span>
                                    </div>
                                    <div class="review-detail-item">
                                        <span class="review-detail-label">Bathrooms</span>
                                        <span class="review-detail-value"><?php echo $property['bathrooms']; ?> baths</span>
                                    </div>
                                    <div class="review-detail-item">
                                        <span class="review-detail-label">Guest Capacity</span>
                                        <span class="review-detail-value"><?php echo $property['max_guests']; ?> guests</span>
                                    </div>
                                </div>
                                
                                <div class="review-actions">
                                    <form method="POST" action="review-property.php" style="display: inline;">
                                        <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">Approve</button>
                                    </form>
                                    <form method="POST" action="review-property.php" style="display: inline;">
                                        <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject">Reject</button>
                                    </form>
                                    <a href="view-property.php?id=<?php echo $property['id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
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
                    '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+
                    (body?'<small>'+esc(body)+'</small>':'')+
                    '</div>'+
                    '<div class="adm-notif-item-actions">'+
                    (unread?'<button class="adm-notif-mark" data-mark="'+esc(n.id)+'">Mark read</button>':'')+
                    '</div></div>';
            }).join('');
        }

        function load(){
            fetch('../api/notifications-list.php?limit=8', {credentials:'same-origin'})
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
            fetch('../api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){ if(data&&data.ok) load(); })
                .catch(function(){});
        }

        list.addEventListener('click', function(e){
            var b = e.target && e.target.closest && e.target.closest('[data-mark]');
            if (b) {
                var id = parseInt(b.getAttribute('data-mark'), 10);
                if (!id) return;
                mark(id);
                return;
            }
            var item = e.target && e.target.closest && e.target.closest('.adm-notif-item[data-link]');
            if (item) {
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
