<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

if (!$user || ($user['role'] ?? '') !== 'host') {
    header('Location: ../home.php');
    exit();
}
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

// Host's refund requests for bookings under their properties
$stmt = $conn->prepare("
    SELECT
        rr.*,
        b.check_in,
        b.check_out,
        b.total_price,
        b.status AS booking_status,
        b.booking_date,
        p.title AS property_title,
        p.city,
        p.country,
        p.host_id,
        g.first_name AS guest_first_name,
        g.last_name AS guest_last_name,
        g.email AS guest_email
    FROM refund_requests rr
    JOIN bookings b ON b.id = rr.booking_id
    JOIN properties p ON p.id = rr.property_id AND p.host_id = ?
    JOIN users g ON g.id = rr.requester_user_id
    ORDER BY rr.created_at DESC
");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
function hostDecisionLabel($decision, $percent) {
    $d = strtolower((string)$decision);
    $pct = ($percent !== null) ? (int)$percent : null;
    if ($d === '' || $d === 'none') return 'None';
    if ($d === 'reject') return 'Rejected';
    if ($d === 'approve_full') return 'Approved (100%)';
    if ($d === 'approve_partial') return 'Approved' . ($pct !== null ? (' (' . $pct . '%)') : '');
    return ucfirst(str_replace('_', ' ', $d)) . ($pct !== null ? (' (' . $pct . '%)') : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Refund Requests - Host - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .rr-hero {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 22px;
            padding: 22px 24px;
            margin-bottom: 16px;
            box-shadow: 0 22px 50px rgba(0,0,0,0.22);
            display:flex; justify-content: space-between; align-items:flex-start; flex-wrap:wrap; gap: 12px;
        }
        .rr-hero h1 { margin:0 0 6px; color:#fff !important; font-size: 24px; }
        .rr-hero p { margin:0; color:#CBD5E1 !important; }
        .rr-table { width: 100%; border-collapse: collapse; }
        .rr-table thead { background: rgba(255, 255, 255, 0.04); }
        .rr-table th {
            padding: 14px 18px;
            text-align: left;
            font-weight: 500;
            font-size: 12px;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .rr-table td {
            padding: 16px 18px;
            color: #E2E8F0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.10);
            vertical-align: middle;
            font-size: 13px;
        }
        .rr-table tbody tr { transition: background 0.2s ease; }
        .rr-table tbody tr:hover { background: rgba(255, 255, 255, 0.04); }
        .badge { display:inline-flex; align-items:center; gap:6px; padding: 6px 10px; border-radius:999px; font-weight: 500; font-size: 12px; border:1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.06); }
        .badge-pending { color:#FDE68A; border-color: rgba(234,179,8,0.28); }
        .badge-approved { color:#86efac; border-color: rgba(34,197,94,0.28); }
        .badge-rejected { color:#fecaca; border-color: rgba(239,68,68,0.28); }
        .badge-processing { color:#93c5fd; border-color: rgba(59,130,246,0.28); }
        .badge-completed { color:#c7d2fe; border-color: rgba(99,102,241,0.28); }
        .rr-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .rr-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding: 9px 10px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0; text-decoration:none; font-weight: 500; font-size: 12px;
        }
        .rr-btn:hover { background: rgba(255,255,255,0.09); }
        .rr-btn-danger { border-color: rgba(239,68,68,0.28); color:#fecaca; }
        .rr-btn-primary { background: linear-gradient(135deg, #D4A574, #B8935F); color:#0f172a; border-color: transparent; transition: background 0.2s, color 0.2s; }
        .rr-btn-primary:hover, .rr-btn-primary:focus {
            background: linear-gradient(135deg, #E6C48B, #D4A574);
            color: #0f172a;
            border-color: transparent;
        }
        .rr-card {
            background: rgba(17, 24, 39, 0.86);
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 20px 36px rgba(0, 0, 0, 0.18);
        }
        .rr-head { padding: 14px 16px; border-bottom: 1px solid rgba(148,163,184,0.12); display:flex; justify-content: space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .rr-head h2 { margin:0; color:#fff !important; font-size: 15px; }
        .rr-note { color:#94A3B8 !important; font-size: 13px; margin:0; }
        body.light-mode .rr-card { background:#fff !important; border-color:#E2E8F0 !important; }
        body.light-mode .rr-table th { color:#334155 !important; }
        body.light-mode .rr-table td { color:#0f172a !important; }
        body.light-mode .rr-head h2 { color:#0f172a !important; }
        body.light-mode .rr-note { color:#475569 !important; }
        body.light-mode .rr-btn { background:#fff !important; border-color:#E2E8F0 !important; color:#0f172a !important; }
        body.light-mode .rr-btn-danger { color:#b91c1c !important; border-color: rgba(185,28,28,0.25) !important; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-refund-requests-page">
<div class="host-layout">
    <aside class="host-sidebar">
        <div class="sidebar-header">
            <a href="../home.php" class="sidebar-brand">
                <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                <span>ReservePro</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            
            <a href="profile.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Profile</span></a>
            <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>My Properties</span></a>
            <a href="add-property.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span><span>Add Property</span></a>
            <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span>Bookings</span></a>
            <a href="refund-requests.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span><span>Refund Requests</span></a>
            <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span><span>Earnings</span></a>
            <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span>Messages</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar" style="overflow:hidden;">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img
                            src="<?php echo htmlspecialchars('../' . ltrim((string)$user['profile_photo'], '/')); ?>"
                            alt="Profile photo"
                            style="width:100%;height:100%;object-fit:cover;display:block;"
                            onerror="this.style.display='none'"
                        >
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Host</div>
                </div>
            </div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">
            <div class="host-page-hero">
                <div class="host-page-hero-content">
                    <h1 style="margin-top: 20px;">Refund Requests</h1>
                </div>
                <div style="display:flex; align-items:flex-start; gap:14px; margin-left:auto;">
                    <div class="host-page-summary">
                        <span class="host-page-summary-label">Total Requests</span>
                        <strong><?php echo count($requests); ?></strong>
                    </div>
                </div>
            </div>

            <div class="host-surface">
                <div class="host-surface-header">
                    <div>
                        <h2>All Requests</h2>
                    </div>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="host-empty-state">
                        <span class="host-empty-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                        <h3>No refund requests yet</h3>
                    </div>
                <?php else: ?>
                    <div class="host-table-scroll">
                        <table class="rr-table host-table">
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Guest</th>
                                    <th>Property</th>
                                    <th>Stay</th>
                                    <th>Suggested</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td>
                                        #<?php echo (int)$r['id']; ?><br>
                                        <span style="color:#94A3B8;"><?php echo h($r['request_type']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo h(trim(($r['guest_first_name'] ?? '') . ' ' . ($r['guest_last_name'] ?? ''))); ?><br>
                                        <span style="color:#94A3B8;"><?php echo h($r['guest_email'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <?php echo h($r['property_title'] ?? ''); ?><br>
                                        <span style="color:#94A3B8;"><?php echo h(($r['city'] ?? '') . ', ' . ($r['country'] ?? '')); ?></span>
                                    </td>
                                    <td>
                                        <?php echo h((string)$r['check_in']); ?> → <?php echo h((string)$r['check_out']); ?>
                                    </td>
                                    <td>
                                        <?php echo (int)$r['refund_percent']; ?>%<br>
                                        <span style="color:#94A3B8;">₱<?php echo number_format((float)$r['refund_amount'], 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo badge($r['status']); ?>"><?php echo h($r['status']); ?></span><br>
                                        <span style="color:#94A3B8;">Host: <?php echo h(hostDecisionLabel($r['host_decision'] ?? '', $r['host_decision_percent'] ?? null)); ?></span>
                                    </td>
                                    <td>
                                        <div class="rr-actions">
                                            <a class="rr-btn" href="../messages.php" title="Open messages">
                                                <i class="fa-solid fa-envelope"></i>Message
                                            </a>
                                            <a class="rr-btn rr-btn-primary" href="refund-request.php?id=<?php echo (int)$r['id']; ?>">
                                                Review
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
    </main>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
<script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
</body>
</html>

