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
        .rf-hero {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 22px;
            padding: 22px 24px;
            margin-bottom: 16px;
            box-shadow: 0 22px 50px rgba(0,0,0,0.22);
            display:flex; justify-content: space-between; align-items:flex-start; flex-wrap:wrap; gap: 12px;
        }
        .rf-hero h1 { margin:0 0 6px; color:#fff !important; font-size: 24px; }
        .rf-hero p { margin:0; color:#CBD5E1 !important; }
        .rf-pills { display:flex; gap:8px; flex-wrap:wrap; }
        .rf-pill {
            display:inline-flex; gap:8px; align-items:center;
            padding: 10px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0;
            text-decoration:none;
            font-weight: 900;
            font-size: 12px;
        }
        .rf-pill.active { border-color: rgba(212,165,116,0.5); color:#FDE68A; }
        .rf-table { width:100%; border-collapse: collapse; }
        .rf-table th, .rf-table td { padding: 12px 12px; border-bottom: 1px solid rgba(148,163,184,0.12); font-size: 13px; vertical-align: top; }
        .rf-table th { text-align:left; color:#CBD5E1 !important; font-weight: 900; text-transform: uppercase; font-size: 11px; letter-spacing: 0.04em; }
        .rf-table td { color:#F1F5F9 !important; font-weight: 700; }
        .badge { display:inline-flex; align-items:center; gap:6px; padding: 6px 10px; border-radius:999px; font-weight: 900; font-size: 12px; border:1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.06); }
        .badge-pending { color:#FDE68A; border-color: rgba(234,179,8,0.28); }
        .badge-approved { color:#86efac; border-color: rgba(34,197,94,0.28); }
        .badge-rejected { color:#fecaca; border-color: rgba(239,68,68,0.28); }
        .badge-processing { color:#93c5fd; border-color: rgba(59,130,246,0.28); }
        .badge-completed { color:#c7d2fe; border-color: rgba(99,102,241,0.28); }
        .rf-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding: 9px 10px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0; text-decoration:none; font-weight: 900; font-size: 12px;
        }
        .rf-btn:hover { background: rgba(255,255,255,0.09); }
        .rf-card { background: rgba(17,24,39,0.78); border: 1px solid rgba(148,163,184,0.16); border-radius: 18px; overflow:hidden; }
        .rf-head { padding: 14px 16px; border-bottom: 1px solid rgba(148,163,184,0.12); display:flex; justify-content: space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .rf-head h2 { margin:0; color:#fff !important; font-size: 15px; }
        .rf-note { color:#94A3B8 !important; font-size: 13px; margin:0; }
        body.light-mode .rf-card { background:#fff !important; border-color:#E2E8F0 !important; }
        body.light-mode .rf-table th { color:#334155 !important; }
        body.light-mode .rf-table td { color:#0f172a !important; }
        body.light-mode .rf-head h2 { color:#0f172a !important; }
        body.light-mode .rf-note { color:#475569 !important; }
        body.light-mode .rf-btn { background:#fff !important; border-color:#E2E8F0 !important; color:#0f172a !important; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page">
<div class="host-layout">
    <aside class="host-sidebar">
        <div class="sidebar-header">
            <a href="../home.php" class="sidebar-brand">
                <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                <span>ReservePro</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><span>Admin Panel</span></a>
            <a href="analytics.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span><span>Analytics</span></a>
            <a href="refunds.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span><span>Refunds</span></a>
            <a href="host-verifications.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span><span>Host Verifications</span></a>
            <a href="submissions.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span><span>Submissions</span></a>
            <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>All Properties</span></a>
            <a href="users.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span>Users</span></a>
            <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span><span>All Bookings</span></a>
            <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span><span>Earnings</span></a>
            <a href="commission.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span><span>Commission</span></a>
            <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span>View Site</span></a>
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
            <div class="theme-toggle" style="margin-bottom: 12px;">
                <span class="theme-toggle-icon" aria-hidden="true"></span>
                <span class="theme-toggle-text">Theme</span>
            </div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">
        <div style="max-width: 1200px; margin: 0 auto; padding: 24px;">
            <div class="rf-hero">
                <div>
                    <h1>Refunds</h1>
                    <p>Support dashboard for cancellations + issue-based refunds. All actions are logged. Processing time is shown as 5–15 business days (simulation).</p>
                </div>
                <div class="rf-pills">
                    <?php
                        $pills = ['pending' => 'Pending', 'pending_review' => 'Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'processing' => 'Processing', 'completed' => 'Completed', 'all' => 'All'];
                        foreach ($pills as $k => $label):
                            $c = $k === 'all' ? array_sum($counts) : (int)($counts[$k] ?? 0);
                    ?>
                        <a class="rf-pill <?php echo $filter === $k ? 'active' : ''; ?>" href="refunds.php?status=<?php echo h($k); ?>">
                            <?php echo h($label); ?> <span style="opacity:0.85;">(<?php echo (int)$c; ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rf-card">
                <div class="rf-head">
                    <h2>Requests</h2>
                    <p class="rf-note"><?php echo count($rows); ?> shown</p>
                </div>
                <?php if (empty($rows)): ?>
                    <div style="padding: 16px; color:#CBD5E1;">No refund requests.</div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="rf-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Guest</th>
                                    <th>Host</th>
                                    <th>Property</th>
                                    <th>Suggested</th>
                                    <th>Host decision</th>
                                    <th>Status</th>
                                    <th>Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td>#<?php echo (int)$r['id']; ?></td>
                                    <td><?php echo h($r['request_type']); ?></td>
                                    <td><?php echo h(trim(($r['guest_first_name'] ?? '') . ' ' . ($r['guest_last_name'] ?? ''))); ?><br><span style="color:#94A3B8; font-weight:800;"><?php echo h($r['guest_email']); ?></span></td>
                                    <td><?php echo h(trim(($r['host_first_name'] ?? '') . ' ' . ($r['host_last_name'] ?? ''))); ?><br><span style="color:#94A3B8; font-weight:800;"><?php echo h($r['host_email']); ?></span></td>
                                    <td><?php echo h($r['property_title']); ?><br><span style="color:#94A3B8; font-weight:800;"><?php echo h(($r['city'] ?? '') . ', ' . ($r['country'] ?? '')); ?></span></td>
                                    <td><?php echo (int)$r['refund_percent']; ?>%<br><span style="color:#94A3B8; font-weight:800;">₱<?php echo number_format((float)$r['refund_amount'], 2); ?></span></td>
                                    <td><?php echo h($r['host_decision']); ?><br><span style="color:#94A3B8; font-weight:800;"><?php echo $r['host_decision_percent'] !== null ? ((int)$r['host_decision_percent'] . '%') : '—'; ?></span></td>
                                    <td><span class="badge <?php echo badge($r['status']); ?>"><?php echo h($r['status']); ?></span></td>
                                    <td>
                                        <a class="rf-btn" href="refund.php?id=<?php echo (int)$r['id']; ?>"><i class="fa-solid fa-screwdriver-wrench"></i>Open</a>
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

