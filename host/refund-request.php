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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: refund-requests.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("
    SELECT
        rr.*,
        b.check_in, b.check_out, b.total_price, b.status AS booking_status, b.booking_date,
        p.title AS property_title, p.city, p.country, p.host_id,
        g.first_name AS guest_first_name, g.last_name AS guest_last_name, g.email AS guest_email
    FROM refund_requests rr
    JOIN bookings b ON b.id = rr.booking_id
    JOIN properties p ON p.id = rr.property_id
    JOIN users g ON g.id = rr.requester_user_id
    WHERE rr.id = ? AND p.host_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
    $conn->close();
    header('Location: refund-requests.php?error=notfound');
    exit();
}

$evidence = [];
if (!empty($r['evidence_json'])) {
    $j = json_decode((string)$r['evidence_json'], true);
    if (is_array($j) && isset($j['photos']) && is_array($j['photos'])) {
        $evidence = $j['photos'];
    }
}

$conn->close();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Refund Request #<?php echo (int)$r['id']; ?> - Host - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .one { max-width: 980px; margin: 0 auto; padding: 24px; }
        .hero {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 22px;
            padding: 20px 22px;
            margin-bottom: 14px;
            display:flex; justify-content: space-between; align-items:flex-start; gap: 12px; flex-wrap:wrap;
        }
        .hero h1 { margin:0 0 6px; color:#fff !important; font-size: 20px; }
        .hero p { margin:0; color:#CBD5E1 !important; }
        .card { background: rgba(17,24,39,0.78); border: 1px solid rgba(148,163,184,0.16); border-radius: 18px; padding: 16px; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
        .pill { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10); border-radius: 14px; padding: 10px 12px; }
        .pill small { display:block; color:#94A3B8 !important; font-weight: 900; font-size: 10px; letter-spacing:0.04em; text-transform: uppercase; margin-bottom: 6px; }
        .pill strong { color:#F1F5F9 !important; font-size: 13px; }
        .btn {
            display:inline-flex; align-items:center; gap:8px;
            padding: 10px 12px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0; text-decoration:none; font-weight: 900; font-size: 13px;
            cursor:pointer;
        }
        .btn-primary { background: linear-gradient(135deg, #D4A574, #B8935F); color:#0f172a; border-color: transparent; }
        .btn-danger { border-color: rgba(239,68,68,0.28); color:#fecaca; }
        textarea, select, input[type="number"] {
            width:100%;
            padding: 12px 12px;
            border-radius: 12px;
            border: 1px solid rgba(148,163,184,0.18);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0;
        }
        textarea { min-height: 90px; resize: vertical; }
        .thumbs { display:flex; gap:10px; flex-wrap:wrap; margin-top: 10px; }
        .thumbs a { display:block; width: 120px; height: 90px; border-radius: 12px; overflow:hidden; border: 1px solid rgba(255,255,255,0.14); }
        .thumbs img { width:100%; height:100%; object-fit: cover; display:block; }
        body.light-mode .card { background:#fff !important; border-color:#E2E8F0 !important; }
        body.light-mode .pill { background:#F8FAFC !important; border-color:#E2E8F0 !important; }
        body.light-mode .pill small { color:#475569 !important; }
        body.light-mode .pill strong { color:#0f172a !important; }
        body.light-mode textarea, body.light-mode select, body.light-mode input[type="number"] { background:#fff; color:#0f172a; border-color:#E2E8F0; }
        body.light-mode .btn { background:#fff !important; color:#0f172a !important; border-color:#E2E8F0 !important; }
        body.light-mode .btn-danger { color:#b91c1c !important; border-color: rgba(185,28,28,0.25) !important; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-dashboard-page">
<div class="host-layout">
    <aside class="host-sidebar">
        <div class="sidebar-header">
            <a href="../home.php" class="sidebar-brand">
                <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                <span>ReservePro</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><span>Dashboard</span></a>
            <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>My Properties</span></a>
            <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span>Bookings</span></a>
            <a href="refund-requests.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span><span>Refund Requests</span></a>
            <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span>Messages</span></a>
            <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span>View Site</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Host</div>
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
        <div class="one">
            <div class="hero">
                <div>
                    <h1>Refund request #<?php echo (int)$r['id']; ?></h1>
                    <p><?php echo h($r['request_type']); ?> · status: <strong><?php echo h($r['status']); ?></strong></p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn" href="refund-requests.php"><i class="fa-solid fa-arrow-left"></i>Back</a>
                    <a class="btn" href="../messages.php"><i class="fa-solid fa-envelope"></i>Message guest</a>
                </div>
            </div>

            <div class="card">
                <div class="grid">
                    <div class="pill"><small>Guest</small><strong><?php echo h(trim(($r['guest_first_name'] ?? '') . ' ' . ($r['guest_last_name'] ?? ''))); ?></strong></div>
                    <div class="pill"><small>Property</small><strong><?php echo h($r['property_title'] ?? ''); ?></strong></div>
                    <div class="pill"><small>Stay</small><strong><?php echo h((string)$r['check_in']); ?> → <?php echo h((string)$r['check_out']); ?></strong></div>
                    <div class="pill"><small>Amount</small><strong>₱<?php echo number_format((float)$r['total_price'], 2); ?></strong></div>
                    <div class="pill"><small>Suggested refund</small><strong><?php echo (int)$r['refund_percent']; ?>% · ₱<?php echo number_format((float)$r['refund_amount'], 2); ?></strong></div>
                    <div class="pill"><small>Policy</small><strong><?php echo h($r['policy'] ?? ''); ?></strong></div>
                </div>

                <?php if (!empty($r['issue_type'])): ?>
                    <div style="margin-top: 12px;" class="pill">
                        <small>Issue</small>
                        <strong><?php echo h($r['issue_type']); ?></strong>
                        <div style="margin-top:8px; color:#CBD5E1; font-weight:700; line-height:1.55;"><?php echo nl2br(h($r['description'] ?? '')); ?></div>
                        <?php if (!empty($evidence)): ?>
                            <div class="thumbs">
                                <?php foreach ($evidence as $p): ?>
                                    <a href="../<?php echo h(ltrim((string)$p, '/')); ?>" target="_blank" rel="noopener">
                                        <img src="../<?php echo h(ltrim((string)$p, '/')); ?>" alt="Evidence">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="refund-request-action.php" style="margin-top: 14px;">
                    <input type="hidden" name="refund_request_id" value="<?php echo (int)$r['id']; ?>">

                    <label style="display:block; margin-top: 10px; margin-bottom: 6px; font-weight:900; color:#CBD5E1;">Decision *</label>
                    <select name="decision" required>
                        <option value="">Select</option>
                        <option value="approve_full">Approve full refund (100%)</option>
                        <option value="approve_partial">Approve partial refund</option>
                        <option value="reject">Reject</option>
                    </select>

                    <label style="display:block; margin-top: 10px; margin-bottom: 6px; font-weight:900; color:#CBD5E1;">Partial percent (only if partial)</label>
                    <input type="number" name="partial_percent" min="1" max="100" step="1" placeholder="e.g. 50">

                    <label style="display:block; margin-top: 10px; margin-bottom: 6px; font-weight:900; color:#CBD5E1;">Note to admin/guest (optional)</label>
                    <textarea name="note" maxlength="1000" placeholder="Explain your decision (optional)"></textarea>

                    <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top: 12px;">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i>Submit decision</button>
                        <a class="btn btn-danger" href="../messages.php"><i class="fa-solid fa-envelope"></i>Message instead</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
</body>
</html>

