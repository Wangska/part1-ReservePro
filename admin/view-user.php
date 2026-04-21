<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$currentUser = getCurrentUser();

// Only admins may view user details
if (!$currentUser || ($currentUser['role'] ?? null) !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: users.php');
    exit();
}

$conn = getDBConnection();

// Load main user record with some aggregate stats
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role,
        u.email_verified,
        u.host_verified,
        u.host_verification_status,
        u.created_at,
        (SELECT COUNT(*) FROM properties WHERE host_id = u.id)               AS total_properties,
        (SELECT COUNT(*) FROM bookings  WHERE guest_id = u.id)              AS total_bookings_as_guest,
        (SELECT COUNT(*) 
           FROM bookings b 
           JOIN properties p ON b.property_id = p.id 
          WHERE p.host_id = u.id)                                           AS total_bookings_as_host
    FROM users u
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$userResult = $stmt->get_result();
$viewUser = $userResult->fetch_assoc();
$stmt->close();

if (!$viewUser) {
    $conn->close();
    header('Location: users.php');
    exit();
}

// Properties owned (if host)
$properties = [];
if ($viewUser['role'] === 'host') {
    $stmt = $conn->prepare("
        SELECT id, title, city, country, status, price_per_night, created_at
        FROM properties
        WHERE host_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Recent bookings as guest
$bookings_guest = [];
if ($viewUser['role'] === 'guest') {
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            p.title  AS property_title,
            p.city   AS property_city,
            p.country AS property_country
        FROM bookings b
        JOIN properties p ON b.property_id = p.id
        WHERE b.guest_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $bookings_guest = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Recent bookings for this host's properties
$bookings_host = [];
if ($viewUser['role'] === 'host') {
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            p.title  AS property_title,
            g.first_name AS guest_first_name,
            g.last_name  AS guest_last_name
        FROM bookings b
        JOIN properties p ON b.property_id = p.id
        JOIN users g      ON b.guest_id    = g.id
        WHERE p.host_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $bookings_host = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

function bool_label($value) {
    return $value ? 'Yes' : 'No';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>User Details – <?php echo htmlspecialchars($viewUser['first_name'] . ' ' . $viewUser['last_name']); ?> – Admin – ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) { background: #06090F !important; }
        body.admin-page::before, body.admin-page::after { display: none !important; }

        /* ── Hero ── */
        .vu-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            background: linear-gradient(135deg, rgba(17,24,39,0.96), rgba(30,41,59,0.88));
            border: 1px solid rgba(212,165,116,0.22);
            border-radius: 24px;
            padding: 28px 30px;
            margin-bottom: 24px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.28);
        }
        .vu-hero-left { display: flex; align-items: flex-start; gap: 20px; flex: 1; min-width: 0; }
        .vu-avatar {
            width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 700; color: #0F0F0F; flex-shrink: 0;
        }
        .vu-avatar-guest  { background: linear-gradient(135deg, #3B82F6, #2563EB); color: #fff; }
        .vu-avatar-host   { background: linear-gradient(135deg, #D4A574, #B8935F); }
        .vu-avatar-admin  { background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; }
        .vu-eyebrow {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            color: #D4A574; background: rgba(212,165,116,0.12); border: 1px solid rgba(212,165,116,0.22);
            border-radius: 999px; padding: 5px 14px; margin-bottom: 10px;
        }
        .vu-hero h1 {
            font-size: 26px; font-weight: 700; color: #F1F5F9 !important;
            margin: 0 0 10px 0; line-height: 1.25;
        }
        .vu-role {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 11px; border-radius: 999px; font-size: 12px; font-weight: 600;
        }
        .vu-role-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .vu-role-guest  { background: rgba(59,130,246,0.12);  color: #93C5FD !important; border: 1px solid rgba(59,130,246,0.25); }
        .vu-role-guest  .vu-role-dot { background: #3B82F6; }
        .vu-role-host   { background: rgba(212,165,116,0.12); color: #D4A574 !important; border: 1px solid rgba(212,165,116,0.25); }
        .vu-role-host   .vu-role-dot { background: #D4A574; }
        .vu-role-admin  { background: rgba(239,68,68,0.12);   color: #FCA5A5 !important; border: 1px solid rgba(239,68,68,0.25); }
        .vu-role-admin  .vu-role-dot { background: #EF4444; }
        .vu-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; }
        .vu-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #64748B !important; }
        .vu-meta-item i { color: #64748B; font-size: 11px; }
        .vu-hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; flex-shrink: 0; }
        .btn-vu-back {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 700;
            text-decoration: none; border: 1px solid rgba(148,163,184,0.22);
            background: rgba(255,255,255,0.05); color: #CBD5E1 !important;
            transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.2s;
        }
        .btn-vu-back:hover {
            background: rgba(212,165,116,0.12); border-color: rgba(212,165,116,0.38);
            color: #D4A574 !important; transform: translateY(-1px);
        }

        /* ── Layout grid ── */
        .vu-grid { display: grid; grid-template-columns: 320px 1fr; gap: 20px; align-items: start; }
        @media (max-width: 900px) { .vu-grid { grid-template-columns: 1fr; } }

        /* ── Card ── */
        .vu-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(148,163,184,0.1); border-radius: 14px; padding: 18px 20px; margin-bottom: 14px; }
        .vu-card:last-child { margin-bottom: 0; }
        .vu-card-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
            color: #94A3B8 !important; margin-bottom: 14px;
        }
        .vu-card-title i { font-size: 12px; }

        /* ── Stats row ── */
        .vu-stats-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .vu-stat-cell { text-align: center; padding: 12px 8px; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px solid rgba(148,163,184,0.08); }
        .vu-stat-num { font-size: 20px; font-weight: 700; color: #E2E8F0 !important; line-height: 1; }
        .vu-stat-sub { font-size: 11px; color: #64748B !important; margin-top: 3px; }

        /* ── Chips ── */
        .vu-chips { display: flex; flex-direction: column; }
        .vu-chip { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(148,163,184,0.08); }
        .vu-chip:last-child { border-bottom: none; }
        .vu-chip-icon { width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .vu-chip-icon i { color: #64748B; font-size: 12px; }
        .vu-chip-body { flex: 1; min-width: 0; }
        .vu-chip-label { font-size: 11px; color: #64748B !important; font-weight: 500; }
        .vu-chip-value { font-size: 13px; color: #CBD5E1 !important; font-weight: 500; margin-top: 1px; }
        .vu-verify-yes { display: inline-flex; align-items: center; gap: 5px; color: #86EFAC !important; font-size: 13px; }
        .vu-verify-no  { display: inline-flex; align-items: center; gap: 5px; color: #64748B !important; font-size: 13px; }

        /* ── Table ── */
        .vu-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .vu-table thead tr { border-bottom: 1px solid rgba(148,163,184,0.12); }
        .vu-table th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: #64748B !important; }
        .vu-table td { padding: 10px 10px; color: #CBD5E1 !important; border-bottom: 1px solid rgba(148,163,184,0.06); }
        .vu-table tbody tr:last-child td { border-bottom: none; }
        .vu-table tbody tr:hover td { background: rgba(255,255,255,0.02); }

        /* ── Status badges ── */
        .vu-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .vu-badge-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .vu-badge-confirmed, .vu-badge-approved  { background: rgba(34,197,94,0.1);  color: #86EFAC !important; border: 1px solid rgba(34,197,94,0.2); }
        .vu-badge-confirmed .vu-badge-dot, .vu-badge-approved .vu-badge-dot { background: #22C55E; }
        .vu-badge-pending   { background: rgba(234,179,8,0.1);  color: #FDE047 !important; border: 1px solid rgba(234,179,8,0.2); }
        .vu-badge-pending   .vu-badge-dot { background: #EAB308; }
        .vu-badge-cancelled, .vu-badge-rejected { background: rgba(244,63,94,0.1); color: #FDA4AF !important; border: 1px solid rgba(244,63,94,0.2); }
        .vu-badge-cancelled .vu-badge-dot, .vu-badge-rejected .vu-badge-dot { background: #F43F5E; }
        .vu-badge-completed { background: rgba(99,102,241,0.12); color: #C7D2FE !important; border: 1px solid rgba(99,102,241,0.2); }
        .vu-badge-completed .vu-badge-dot { background: #6366F1; }

        .vu-empty { color: #64748B !important; font-size: 13px; margin: 0; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page admin-view-user-page">
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
                <a href="dashboard.php" class="nav-item">
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
                <a href="users.php" class="nav-item active">
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
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>View Site</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <div class="theme-toggle" style="margin-bottom: 12px;">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">

            <!-- Hero -->
            <div class="vu-hero">
                <div class="vu-hero-left">
                    <div class="vu-avatar vu-avatar-<?php echo $viewUser['role']; ?>">
                        <?php echo strtoupper(substr($viewUser['first_name'], 0, 1) . substr($viewUser['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <!-- vu-eyebrow removed -->
                        <h1><?php echo htmlspecialchars($viewUser['first_name'] . ' ' . $viewUser['last_name']); ?></h1>
                        <span class="vu-role vu-role-<?php echo $viewUser['role']; ?>">
                            <span class="vu-role-dot"></span>
                            <?php echo ucfirst($viewUser['role']); ?>
                        </span>
                        <div class="vu-meta">
                            <span class="vu-meta-item"><i class="fa-solid fa-envelope"></i><?php echo htmlspecialchars($viewUser['email']); ?></span>
                            <span class="vu-meta-item"><i class="fa-solid fa-hashtag"></i>ID <?php echo $viewUser['id']; ?></span>
                            <span class="vu-meta-item"><i class="fa-solid fa-calendar"></i>Joined <?php echo date('M j, Y', strtotime($viewUser['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="vu-hero-right">
                    <a href="users.php" class="btn-vu-back"><i class="fa-solid fa-arrow-left"></i> Back to Users</a>
                </div>
            </div>

            <!-- Content grid -->
            <div class="vu-grid">

                <!-- Left column: stats + account details -->
                <div>
                    <div class="vu-card">
                        <div class="vu-card-title"><i class="fa-solid fa-chart-simple"></i> Activity</div>
                        <div class="vu-stats-row">
                            <div class="vu-stat-cell">
                                <div class="vu-stat-num"><?php echo (int)($viewUser['total_bookings_as_guest'] ?? 0); ?></div>
                                <div class="vu-stat-sub">Bookings</div>
                            </div>
                            <div class="vu-stat-cell">
                                <div class="vu-stat-num"><?php echo (int)($viewUser['total_properties'] ?? 0); ?></div>
                                <div class="vu-stat-sub">Properties</div>
                            </div>
                            <div class="vu-stat-cell">
                                <div class="vu-stat-num"><?php echo (int)($viewUser['total_bookings_as_host'] ?? 0); ?></div>
                                <div class="vu-stat-sub">Host Bkgs</div>
                            </div>
                        </div>
                    </div>

                    <div class="vu-card">
                        <div class="vu-card-title"><i class="fa-solid fa-circle-info"></i> Account</div>
                        <div class="vu-chips">
                            <div class="vu-chip">
                                <div class="vu-chip-icon"><i class="fa-solid fa-envelope-circle-check"></i></div>
                                <div class="vu-chip-body">
                                    <div class="vu-chip-label">Email Verified</div>
                                    <div class="vu-chip-value">
                                        <?php if (!empty($viewUser['email_verified'])): ?>
                                            <span class="vu-verify-yes"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="vu-verify-no"><i class="fa-solid fa-circle-xmark"></i> Not verified</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($viewUser['role'] === 'host'): ?>
                            <div class="vu-chip">
                                <div class="vu-chip-icon"><i class="fa-solid fa-shield-check"></i></div>
                                <div class="vu-chip-body">
                                    <div class="vu-chip-label">Host Verified</div>
                                    <div class="vu-chip-value">
                                        <?php if (!empty($viewUser['host_verified'])): ?>
                                            <span class="vu-verify-yes"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="vu-verify-no"><i class="fa-solid fa-circle-xmark"></i> Not verified</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="vu-chip">
                                <div class="vu-chip-icon"><i class="fa-solid fa-file-circle-check"></i></div>
                                <div class="vu-chip-body">
                                    <div class="vu-chip-label">Verification Status</div>
                                    <div class="vu-chip-value">
                                        <?php $hvs = $viewUser['host_verification_status'] ?? 'pending'; ?>
                                        <span class="vu-badge vu-badge-<?php echo $hvs; ?>">
                                            <span class="vu-badge-dot"></span>
                                            <?php echo ucfirst($hvs); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right column: activity tables -->
                <div>
                    <?php if ($viewUser['role'] === 'host'): ?>

                        <div class="vu-card">
                            <div class="vu-card-title"><i class="fa-solid fa-house"></i> Host Properties</div>
                            <?php if (empty($properties)): ?>
                                <p class="vu-empty">No properties listed yet.</p>
                            <?php else: ?>
                                <table class="vu-table">
                                    <thead><tr><th>Property</th><th>Location</th><th>Price/night</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($properties as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['title']); ?></td>
                                            <td><?php echo htmlspecialchars($p['city'] . ', ' . $p['country']); ?></td>
                                            <td>₱<?php echo number_format($p['price_per_night'], 0); ?></td>
                                            <td>
                                                <span class="vu-badge vu-badge-<?php echo $p['status']; ?>">
                                                    <span class="vu-badge-dot"></span>
                                                    <?php echo ucfirst($p['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="vu-card">
                            <div class="vu-card-title"><i class="fa-solid fa-calendar-days"></i> Bookings on Host Properties</div>
                            <?php if (empty($bookings_host)): ?>
                                <p class="vu-empty">No bookings yet.</p>
                            <?php else: ?>
                                <table class="vu-table">
                                    <thead><tr><th>Property</th><th>Guest</th><th>Dates</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($bookings_host as $b): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($b['property_title']); ?></td>
                                            <td><?php echo htmlspecialchars($b['guest_first_name'] . ' ' . $b['guest_last_name']); ?></td>
                                            <td><?php echo date('M j', strtotime($b['check_in'])) . ' – ' . date('M j, Y', strtotime($b['check_out'])); ?></td>
                                            <td>
                                                <span class="vu-badge vu-badge-<?php echo $b['status']; ?>">
                                                    <span class="vu-badge-dot"></span>
                                                    <?php echo ucfirst($b['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($viewUser['role'] === 'guest'): ?>

                        <div class="vu-card">
                            <div class="vu-card-title"><i class="fa-solid fa-calendar-days"></i> Guest Bookings</div>
                            <?php if (empty($bookings_guest)): ?>
                                <p class="vu-empty">No bookings yet.</p>
                            <?php else: ?>
                                <table class="vu-table">
                                    <thead><tr><th>Property</th><th>Location</th><th>Dates</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($bookings_guest as $b): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($b['property_title']); ?></td>
                                            <td><?php echo htmlspecialchars($b['property_city'] . ', ' . $b['property_country']); ?></td>
                                            <td><?php echo date('M j', strtotime($b['check_in'])) . ' – ' . date('M j, Y', strtotime($b['check_out'])); ?></td>
                                            <td>
                                                <span class="vu-badge vu-badge-<?php echo $b['status']; ?>">
                                                    <span class="vu-badge-dot"></span>
                                                    <?php echo ucfirst($b['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>

                        <div class="vu-card">
                            <div class="vu-card-title"><i class="fa-solid fa-shield-halved"></i> Administrator</div>
                            <p class="vu-empty">This account is an administrator. Activity is not listed here.</p>
                        </div>

                    <?php endif; ?>
                </div>

            </div><!-- /.vu-grid -->
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
</body>
</html>
