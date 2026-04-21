<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$filter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
$allowed = ['pending','pending_review','approved','rejected','processing','completed','all'];
if (!in_array($filter, $allowed, true)) $filter = 'pending';

$where = '';
if ($filter !== 'all') {
    $where = "WHERE rr.status = '" . $conn->real_escape_string($filter) . "'";
}

$q = "
    SELECT
        rr.*,
        b.check_in, b.check_out, b.total_price, b.status AS booking_status, b.booking_date,
        p.title AS property_title, p.city, p.country, p.host_id, p.cancellation_policy,
        h.first_name AS host_first_name, h.last_name AS host_last_name, h.email AS host_email,
        g.first_name AS guest_first_name, g.last_name AS guest_last_name, g.email AS guest_email
    FROM refund_requests rr
    JOIN bookings b ON b.id = rr.booking_id
    JOIN properties p ON p.id = rr.property_id
    JOIN users g ON g.id = rr.requester_user_id
    JOIN users h ON h.id = p.host_id
    $where
    ORDER BY rr.created_at DESC
";
$res = $conn->query($q);
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$counts = [];
$cr = $conn->query("SELECT status, COUNT(*) AS c FROM refund_requests GROUP BY status");
if ($cr) {
    while ($r = $cr->fetch_assoc()) $counts[(string)$r['status']] = (int)$r['c'];
}
$conn->close();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badge($s) {
    $s = strtolower((string)$s);
    if ($s === 'approved') return 'badge-approved';
    if ($s === 'rejected') return 'badge-rejected';
    if ($s === 'processing') return 'badge-processing';
    if ($s === 'completed') return 'badge-completed';
    if ($s === 'pending_review') return 'badge-pending';
    return 'badge-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Refunds - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        /* === Refunds Page – Modern Admin === */
        .admin-refunds-page .host-main {
            background: linear-gradient(180deg, rgba(15,23,42,0.18) 0%, rgba(15,15,15,0) 260px);
        }

        /* Hero */
        .refunds-hero {
            display: flex;
            align-items: stretch;
            gap: 20px;
            background: linear-gradient(135deg, rgba(17,24,39,0.96), rgba(30,41,59,0.88));
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 24px;
            padding: 28px 30px;
            margin-bottom: 28px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.24);
        }
        .refunds-hero-content { flex: 1; }
        .refunds-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(212,165,116,0.14);
            color: #F3D9B4;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .refunds-hero h1 {
            margin: 0 0 10px;
            color: #fff !important;
            font-size: 32px;
            font-weight: 700;
        }
        .refunds-hero .subtitle { color: #CBD5E1; font-size: 14px; line-height: 1.6; margin: 0; max-width: 580px; }
        .refunds-summary-card {
            min-width: 196px;
            padding: 22px;
            border-radius: 20px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
        }
        .refunds-summary-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #94A3B8; font-weight: 700; }
        .refunds-summary-card strong { font-size: 38px; line-height: 1; color: #FFFFFF; }
        .refunds-summary-desc { font-size: 13px; color: #CBD5E1; }

        /* Status stat cards */
        .refunds-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }
        .refund-stat-card {
            padding: 18px 20px;
            border-radius: 18px;
            background: rgba(17,24,39,0.86);
            border: 1px solid rgba(148,163,184,0.16);
            box-shadow: 0 12px 24px rgba(0,0,0,0.14);
            transition: transform 0.18s, border-color 0.18s, box-shadow 0.18s;
            text-decoration: none;
            display: block;
        }
        .refund-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 32px rgba(0,0,0,0.2);
            border-color: rgba(212,165,116,0.32);
        }
        .refund-stat-card.active-stat {
        }
        .refund-stat-icon {
            width: 44px; height: 44px;
            border-radius: 13px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 17px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(148,163,184,0.14);
            margin-bottom: 13px;
        }
        .refund-stat-label { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #94A3B8; margin-bottom: 4px; }
        .refund-stat-value { font-size: 28px; font-weight: 800; color: #fff; line-height: 1; }

        /* Table card */
        .refunds-table-card {
            background: rgba(17,24,39,0.86);
            border: 1px solid rgba(148,163,184,0.16);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 36px rgba(0,0,0,0.18);
        }
        .refunds-table-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(148,163,184,0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .refunds-table-header h2 { margin: 0 0 6px; color: #fff !important; }


        /* Filter tabs */
        .refunds-filter-tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .refunds-filter-tab {
            display: inline-flex; align-items: center; gap: 7px;
            appearance: none;
            border: 1px solid rgba(148,163,184,0.16);
            background: rgba(255,255,255,0.04);
            color: #CBD5E1;
            min-height: 38px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.2s, box-shadow 0.2s;
        }
        .refunds-filter-tab:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(212,165,116,0.38);
            color: #FFFFFF;
            transform: translateY(-1px);
        }
        .refunds-filter-tab.active {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border-color: transparent;
            box-shadow: 0 10px 24px rgba(212,165,116,0.22);
        }
        /* Table */
        .rf-table-wrap { overflow-x: auto; width: 100%; }
        .rf-new-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .rf-new-table thead { background: rgba(255,255,255,0.04); }
        .rf-new-table th {
            padding: 14px 18px;
            text-align: center;
            color: #94A3B8;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(148,163,184,0.12);
        }
        .rf-new-table th:first-child {
            text-align: left;
            width: 1%;
            min-width: 36px;
            max-width: 48px;
        }
        .rf-new-table td {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148,163,184,0.1);
            vertical-align: middle;
            color: #E2E8F0;
            text-align: center;
        }
        .rf-new-table td:first-child {
            text-align: left;
            padding-left: 4px;
            padding-right: 4px;
            width: 1%;
            min-width: 36px;
            max-width: 48px;
        }
        .rf-new-table tbody tr { transition: background 0.15s; }
        .rf-new-table tbody tr:hover { background: rgba(255,255,255,0.04); }
        .rf-new-table tbody tr:last-child td { border-bottom: none; }

        /* User cell */
        .rf-user-cell {
            display: block;
            text-align: center;
        }
        .rf-user-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800;
            color: #fff; flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .rf-user-name { color: #F1F5F9 !important; font-weight: 700; font-size: 13px; }
        .rf-user-email { color: #475569 !important; font-size: 12px; font-weight: 600; }

        /* Type badge */
        .rf-type-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 8px;
            font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .rf-type-cancellation { background: rgba(99,102,241,0.12); color: #A5B4FC; border: 1px solid rgba(99,102,241,0.2); }
        .rf-type-issue { background: rgba(245,158,11,0.12); color: #FCD34D; border: 1px solid rgba(245,158,11,0.2); }

        /* Property cell */
        .rf-prop-title { color: #F1F5F9 !important; font-weight: 700; font-size: 13px; }
        .rf-prop-loc { color: #475569 !important; font-size: 12px; font-weight: 600; margin-top: 2px; }

        /* Amount */
        .rf-amount-pct {
            color: #fff !important;
            font-weight: 800;
            font-size: 14px;
        }
        .rf-amount-val {
            color: #fff !important;
            font-size: 12px;
            font-weight: 700;
            margin-top: 2px;
        }

        /* Status badge */
        .rf-status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .rf-status-pending      { background: rgba(234,179,8,0.2);   color: #FDE68A; }
        .rf-status-pending_review { background: rgba(245,158,11,0.2); color: #FCD34D; }
        .rf-status-approved     { background: rgba(34,197,94,0.2);   color: #86EFAC; }
        .rf-status-rejected     { background: rgba(239,68,68,0.2);   color: #FCA5A5; }
        .rf-status-processing   { background: rgba(59,130,246,0.2);  color: #93C5FD; }
        .rf-status-completed    { background: rgba(139,92,246,0.2);  color: #C4B5FD; }

        /* Action button */
        .rf-manage-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 14px; border-radius: 10px;
            background: rgba(212,165,116,0.1);
            border: 1px solid rgba(212,165,116,0.24);
            color: #F3D9B4 !important;
            text-decoration: none;
            font-weight: 700; font-size: 12px;
            transition: background 0.15s, border-color 0.15s;
            white-space: nowrap;
        }
        .rf-manage-btn:hover { background: rgba(212,165,116,0.2); border-color: rgba(212,165,116,0.4); }

        /* ID chip */
        .rf-id {
            color: #fff !important;
            font-size: 15px;
            font-weight: 400;
            font-family: monospace;
            background: rgba(255,255,255,0.10);
            padding: 3px 10px;
            border-radius: 7px;
            border: none;
            letter-spacing: 0.5px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.10);
            transition: color 0.2s, background 0.2s;
        }

        /* Host decision */
        .rf-decision-val { color: #F1F5F9 !important; font-weight: 700; font-size: 13px; text-transform: capitalize; }
        .rf-decision-pct { color: #475569 !important; font-size: 12px; font-weight: 600; margin-top: 2px; }

        /* Empty state */
        .rf-empty { padding: 64px 36px; text-align: center; }
        .rf-empty-icon {
            width: 72px; height: 72px;
            margin: 0 auto 18px;
            border-radius: 20px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 28px; color: #FDE68A;
        }
        .rf-empty h3 { color: #F1F5F9 !important; margin: 0 0 8px; font-size: 18px; }
        .rf-empty p  { color: #475569 !important; font-size: 14px; margin: 0; }

        /* Light mode */
        body.light-mode.admin-refunds-page .host-main { background: linear-gradient(180deg,rgba(248,250,252,.9) 0%,rgba(248,250,252,0) 260px); }
        body.light-mode .refunds-hero { background:#fff; border-color:rgba(15,23,42,.08); box-shadow:0 16px 32px rgba(15,23,42,.08); }
        body.light-mode .refunds-hero h1 { color:#0F172A !important; }
        body.light-mode .refunds-hero .subtitle { color:#475569; }
        body.light-mode .refunds-eyebrow { background:rgba(184,147,95,.12); color:#8B6F47; }
        body.light-mode .refunds-summary-card { background:#F8FAFC; border-color:rgba(15,23,42,.08); }
        body.light-mode .refunds-summary-label { color:#64748B; }
        body.light-mode .refunds-summary-card strong { color:#0F172A; }
        body.light-mode .refunds-summary-desc { color:#475569; }
        body.light-mode .refund-stat-card { background:#fff; border-color:rgba(15,23,42,.08); box-shadow:0 8px 16px rgba(15,23,42,.06); }
        body.light-mode .refund-stat-card.active-stat { background: unset; border-color: unset; }
        body.light-mode .refund-stat-icon { background:#F8FAFC; border-color:rgba(15,23,42,.08); }
        body.light-mode .refund-stat-label { color:#64748B; }
        body.light-mode .refund-stat-value { color:#0F172A; }
        body.light-mode .refunds-table-card { background:#fff; border-color:rgba(15,23,42,.08); box-shadow:0 16px 32px rgba(15,23,42,.08); }
        body.light-mode .refunds-table-header h2 { color:#0F172A !important; }

        body.light-mode .refunds-filter-tabs { gap: 8px; }
        body.light-mode .refunds-filter-tab { background:rgba(15,23,42,.04); border-color:rgba(15,23,42,.12); color:#475569; }
        body.light-mode .refunds-filter-tab:hover { background:rgba(15,23,42,.08); border-color:rgba(184,147,95,.4); color:#334155; transform:translateY(-1px); }
        body.light-mode .refunds-filter-tab.active { background:linear-gradient(135deg,#D4A574,#B8935F); color:#0F0F0F; border-color:transparent; box-shadow:0 10px 24px rgba(212,165,116,.22); }

        body.light-mode .rf-new-table th { color:#64748B !important; background:rgba(15,23,42,.02); border-bottom-color:rgba(15,23,42,.08); }
        body.light-mode .rf-new-table td { border-bottom-color:rgba(15,23,42,.06); }
        body.light-mode .rf-new-table tbody tr:hover { background:rgba(15,23,42,.02); }
        body.light-mode .rf-user-name { color:#0F172A !important; }
        body.light-mode .rf-user-email { color:#64748B !important; }
        body.light-mode .rf-prop-title { color:#0F172A !important; }
        body.light-mode .rf-prop-loc { color:#64748B !important; }
        body.light-mode .rf-amount-val {
            color: #fff !important;
        }
        body.light-mode .rf-id {
            color: #0F172A !important;
            background: #fff;
            border: none;
            font-weight: 400;
        }
        body.light-mode .rf-decision-val { color:#0F172A !important; }
        body.light-mode .rf-decision-pct { color:#64748B !important; }
        body.light-mode .rf-manage-btn { background:rgba(184,147,95,.1); border-color:rgba(184,147,95,.22); color:#8B6F47 !important; }
        body.light-mode .rf-manage-btn:hover { background:rgba(184,147,95,.18); }
        body.light-mode .rf-empty h3 { color:#0F172A !important; }
        body.light-mode .rf-empty p { color:#64748B !important; }

        @media (max-width: 1100px) {
            .refunds-stats { grid-template-columns: repeat(3,1fr); }
            .refunds-hero { flex-direction: column; }
            .refunds-summary-card { min-width: 0; }
        }
        @media (max-width: 768px) {
            .refunds-stats { grid-template-columns: repeat(2,1fr); }
            .refunds-hero { padding: 22px; }
            .refunds-table-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-refunds-page">
<div class="host-layout">
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
            <a href="refunds.php" class="nav-item active">
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
            <a href="../home.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                <span>View Site</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>

            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">
        <?php require __DIR__ . '/../includes/notifications-widget.php'; ?>
        <div>

            <!-- Hero -->
            <div class="refunds-hero">
                <div class="refunds-hero-content">
                    <h1>Refunds</h1>
                    <p class="subtitle"></p>
                </div>
                <!-- refunds-summary-card removed -->
            </div>

            <!-- Status overview cards (unified with properties-stats) -->
            <div class="admin-metric-grid refunds-stats">
                <?php
                $statItems = [
                    ['key' => 'pending',        'label' => 'Pending',       'icon' => 'fa-clock',           'class' => 'is-amber'],
                    ['key' => 'pending_review', 'label' => 'Under Review',  'icon' => 'fa-magnifying-glass','class' => 'is-sky'],
                    ['key' => 'processing',     'label' => 'Processing',    'icon' => 'fa-spinner',         'class' => 'is-indigo'],
                    ['key' => 'approved',       'label' => 'Approved',      'icon' => 'fa-circle-check',    'class' => 'is-emerald'],
                    ['key' => 'completed',      'label' => 'Completed',     'icon' => 'fa-flag-checkered',  'class' => 'is-gold'],
                ];
                foreach ($statItems as $si):
                    $cnt = (int)($counts[$si['key']] ?? 0);
                    $isActive = $filter === $si['key'];
                ?>
                <a href="refunds.php?status=<?php echo h($si['key']); ?>" class="admin-metric-card<?php echo $isActive ? ' active-stat' : ''; ?>">
                    <div class="admin-metric-icon <?php echo $si['class']; ?>"><i class="fa-solid <?php echo $si['icon']; ?>" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <p><?php echo $si['label']; ?></p>
                        <h3><?php echo $cnt; ?></h3>
                        <span class="admin-metric-note"></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Table card -->
            <div class="refunds-table-card admin-surface">
                <div class="refunds-table-header admin-surface-header">
                    <div>
                        <h2>Refund Requests</h2>
                        <p></p>
                    </div>
                    <div class="filter-tabs">
                        <?php
                        $tabs = [
                            'all'            => 'All',
                            'pending'        => 'Pending',
                            'pending_review' => 'Review',
                            'processing'     => 'Processing',
                            'approved'       => 'Approved',
                            'rejected'       => 'Rejected',
                            'completed'      => 'Completed',
                        ];
                        foreach ($tabs as $k => $label):
                        ?>
                        <a class="filter-tab <?php echo $filter === $k ? 'active' : ''; ?>" href="refunds.php?status=<?php echo h($k); ?>">
                            <?php echo h($label); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (empty($rows)): ?>
                <div class="rf-empty">
                    <div class="rf-empty-icon"><i class="fa-solid fa-rotate-left"></i></div>
                    <h3>No refund requests</h3>

                </div>
                <?php else: ?>
                <div class="rf-table-wrap">
                    <table class="rf-new-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Guest</th>
                                <th>Host</th>
                                <th>Property</th>
                                <th>Suggested</th>
                                <th>Host Decision</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r):
                                $guestInitials = strtoupper(substr($r['guest_first_name'] ?? '', 0, 1) . substr($r['guest_last_name'] ?? '', 0, 1));
                                $hostInitials  = strtoupper(substr($r['host_first_name']  ?? '', 0, 1) . substr($r['host_last_name']  ?? '', 0, 1));
                                $type = strtolower((string)$r['request_type']);
                                $typeClass = (str_contains($type, 'issue')) ? 'rf-type-issue' : 'rf-type-cancellation';
                                $typeIcon  = (str_contains($type, 'issue')) ? 'fa-triangle-exclamation' : 'fa-rotate-left';
                                $statusKey   = strtolower(str_replace(' ', '_', (string)$r['status']));
                                $statusLabel = ucfirst(str_replace('_', ' ', $r['status']));
                                $hostDec    = !empty($r['host_decision']) ? h($r['host_decision']) : '—';
                                $hostDecPct = $r['host_decision_percent'] !== null ? ((int)$r['host_decision_percent'] . '%') : '';
                            ?>
                            <tr>
                                <td><span class="rf-id">#<?php echo (int)$r['id']; ?></span></td>
                                <td>
                                    <span class="rf-type-badge <?php echo $typeClass; ?>">
                                        <i class="fa-solid <?php echo $typeIcon; ?>"></i>
                                        <?php echo h($r['request_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="rf-user-cell">
                                        <div>
                                            <div class="rf-user-name"><?php echo h(trim(($r['guest_first_name'] ?? '') . ' ' . ($r['guest_last_name'] ?? ''))); ?></div>
                                            <div class="rf-user-email"><?php echo h($r['guest_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="rf-user-cell">
                                        <div>
                                            <div class="rf-user-name"><?php echo h(trim(($r['host_first_name'] ?? '') . ' ' . ($r['host_last_name'] ?? ''))); ?></div>
                                            <div class="rf-user-email"><?php echo h($r['host_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="rf-prop-title"><?php echo h($r['property_title']); ?></div>
                                    <div class="rf-prop-loc"><i class="fa-solid fa-location-dot" style="font-size:10px;margin-right:3px;"></i><?php echo h(($r['city'] ?? '') . ', ' . ($r['country'] ?? '')); ?></div>
                                </td>
                                <td>
                                    <div class="rf-amount-pct"><?php echo (int)$r['refund_percent']; ?>%</div>
                                    <div class="rf-amount-val">₱<?php echo number_format((float)$r['refund_amount'], 2); ?></div>
                                </td>
                                <td>
                                    <div class="rf-decision-val"><?php echo $hostDec; ?></div>
                                    <?php if ($hostDecPct): ?><div class="rf-decision-pct"><?php echo $hostDecPct; ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <span class="rf-status rf-status-<?php echo h($statusKey); ?>">
                                        <?php echo h($statusLabel); ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="rf-manage-btn" href="refund.php?id=<?php echo (int)$r['id']; ?>">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
</body>
</html>

