<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/refunds.php';

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
        p.cancellation_policy AS listing_cancellation_policy,
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

$policySnapshot = (string)($r['policy'] ?? '');
$policyLatest = (string)($r['listing_cancellation_policy'] ?? $policySnapshot);
if ($policyLatest === '') {
    $policyLatest = 'moderate';
}
$policyLatestSummary = reservepro_cancellation_policy_human_summary($policyLatest);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$isReviewable = in_array((string)($r['status'] ?? ''), ['pending_review', 'pending'], true);
$needsHostDecision = $isReviewable
    && ((string)($r['host_decision'] ?? 'none') === 'none')
    && ((int)($r['refund_percent'] ?? 0) === 50);
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
                /* Dropdown popup (listbox) background and text color for select (cross-browser) */
                select, select option {
                    background: #181C23;
                    color: #F1F5F9;
                }
                /* Chrome, Edge, Safari */
                select:focus-visible, select:active {
                    background: #181C23;
                    color: #F1F5F9;
                }
                select option:checked, select option:hover {
                    background: #23272F;
                    color: #F1F5F9;
                }
                /* Chrome/Edge/Safari dropdown popup */
                @media screen and (-webkit-min-device-pixel-ratio:0) {
                    select, select option {
                        background: #181C23 !important;
                        color: #F1F5F9 !important;
                    }
                }
                /* Firefox dropdown popup */
                @-moz-document url-prefix() {
                    select, select option {
                        background-color: #181C23 !important;
                        color: #F1F5F9 !important;
                    }
                }
                body.light-mode select, body.light-mode select option {
                    background: #fff !important;
                    color: #1F2937 !important;
                }
                body.light-mode select:focus-visible, body.light-mode select:active {
                    background: #fff !important;
                    color: #1F2937 !important;
                }
                body.light-mode select option:checked, body.light-mode select option:hover {
                    background: #f1f5f9 !important;
                    color: #1F2937 !important;
                }
                @media screen and (-webkit-min-device-pixel-ratio:0) {
                    body.light-mode select, body.light-mode select option {
                        background: #fff !important;
                        color: #1F2937 !important;
                    }
                }
                @-moz-document url-prefix() {
                    body.light-mode select, body-light-mode select option {
                        background-color: #fff !important;
                        color: #1F2937 !important;
                    }
                }
        .rr-grid { display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 0; }
        @media (max-width: 900px) { .rr-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .rr-grid { grid-template-columns: 1fr; } }
        .rr-pill { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10); border-radius: 14px; padding: 16px 18px; }
        .rr-pill small { display:block; color:#94A3B8 !important; font-weight: 500; font-size: 11px; letter-spacing:0.05em; text-transform: uppercase; margin-bottom: 8px; }
        .rr-pill strong { color:#F1F5F9 !important; font-size: 15px; }
        .rr-surface-body { padding: 24px 24px; }
        .rr-form-label { display:block; margin-top: 16px; margin-bottom: 7px; font-size: 13px; font-weight: 600; color:#CBD5E1; }
        .btn {
            display:inline-flex; align-items:center; gap:8px;
            padding: 10px 16px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0; text-decoration:none; font-weight: 500; font-size: 13px;
            cursor:pointer; transition: background 0.2s;
        }
        .btn:hover { background: rgba(255,255,255,0.10); }
        .btn-primary { background: linear-gradient(135deg, #D4A574, #B8935F); color:#0f172a; border-color: transparent; }
        .btn-primary:hover { background: linear-gradient(135deg, #E6C48B, #D4A574); color:#0f172a; }
        .btn-danger { border-color: rgba(239,68,68,0.28); color:#fecaca; }
        textarea, select, input[type="number"] {
            width:100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(148,163,184,0.18);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0;
            font-size: 14px;
        }
        select.rf-host-decision-select {
            color-scheme: dark;
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: rgba(148, 163, 184, 0.4) !important;
        }
        select.rf-host-decision-select option {
            background-color: #1e293b;
            color: #f8fafc;
        }
        textarea { min-height: 90px; resize: vertical; }
        .thumbs { display:flex; gap:10px; flex-wrap:wrap; margin-top: 10px; }
        .thumbs a { display:block; width: 120px; height: 90px; border-radius: 12px; overflow:hidden; border: 1px solid rgba(255,255,255,0.14); }
        .thumbs img { width:100%; height:100%; object-fit: cover; display:block; }
        body.light-mode .rr-pill { background:#F8FAFC !important; border-color:#E2E8F0 !important; }
        body.light-mode .rr-pill small { color:#475569 !important; }
        body.light-mode .rr-pill strong { color:#0f172a !important; }
        body.light-mode textarea, body.light-mode select, body.light-mode input[type="number"] { background:#fff; color:#0f172a; border-color:#E2E8F0; }
        body.light-mode select.rf-host-decision-select {
            color-scheme: light;
            background-color: #fff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        body.light-mode select.rf-host-decision-select option {
            background-color: #fff;
            color: #0f172a;
        }
        body.light-mode .btn { background:#fff !important; color:#0f172a !important; border-color:#E2E8F0 !important; }
        body.light-mode .btn-primary { background: linear-gradient(135deg, #D4A574, #B8935F) !important; color:#0f172a !important; }
        body.light-mode .btn-danger { color:#b91c1c !important; border-color: rgba(185,28,28,0.25) !important; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-refund-request-page">
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
            <a href="profile.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Profile</span></a>
            <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>My Properties</span></a>
            <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span>Bookings</span></a>
            <a href="refund-requests.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span><span>Refund Requests</span></a>
            <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span>Messages</span></a>
            <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span>Home</span></a>
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
            <div class="theme-toggle" style="margin-bottom: 12px;">
                <span class="theme-toggle-icon" aria-hidden="true"></span>
                <span class="theme-toggle-text">Theme</span>
            </div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">
        <?php require __DIR__ . '/../includes/notifications-widget.php'; ?>

            <div class="host-page-hero">
                <div class="host-page-hero-content">
                    <h1 style="margin-top:20px;">Refund Request #<?php echo (int)$r['id']; ?></h1>
                    <p><?php echo h(ucfirst($r['request_type'])); ?> &middot; Status: <strong><?php echo h($r['status']); ?></strong></p>
                </div>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-left:auto;">
                    <a class="btn" href="refund-requests.php"><i class="fa-solid fa-arrow-left"></i> Back</a>
                </div>
            </div>

            <div class="host-surface" style="margin-bottom: 24px;">
                <div class="host-surface-header">
                    <div>
                        <h2>Booking Details</h2>
                    </div>
                </div>
                <div class="rr-surface-body">
                    <div class="rr-grid">
                        <div class="rr-pill"><small>Guest</small><strong><?php echo h(trim(($r['guest_first_name'] ?? '') . ' ' . ($r['guest_last_name'] ?? ''))); ?></strong></div>
                        <div class="rr-pill"><small>Property</small><strong><?php echo h($r['property_title'] ?? ''); ?></strong></div>
                        <div class="rr-pill"><small>Stay</small><strong><?php echo h((string)$r['check_in']); ?> → <?php echo h((string)$r['check_out']); ?></strong></div>
                        <div class="rr-pill"><small>Total amount</small><strong>₱<?php echo number_format((float)$r['total_price'], 2); ?></strong></div>
                        <div class="rr-pill"><small>Suggested refund</small><strong><?php echo (int)$r['refund_percent']; ?>% &middot; ₱<?php echo number_format((float)$r['refund_amount'], 2); ?></strong></div>
                        <div class="rr-pill"><small>Policy</small><strong><?php echo h($r['policy'] ?? ''); ?></strong></div>
                    </div>

                    <?php if (!empty($r['issue_type'])): ?>
                        <div style="margin-top: 16px;" class="rr-pill">
                            <small>Issue type</small>
                            <strong><?php echo h($r['issue_type']); ?></strong>
                            <div style="margin-top:10px; color:#CBD5E1; font-size:14px; line-height:1.6;"><?php echo nl2br(h($r['description'] ?? '')); ?></div>
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
                </div>
            </div>

            <div class="host-surface">
                <div class="host-surface-header">
                    <div>
                        <h2>Your Decision</h2>
                        <p>Review and respond to this refund request</p>
                    </div>
                </div>
                <div class="rr-surface-body">
                    <?php if ($needsHostDecision): ?>
                        <form method="post" action="refund-request-action.php" style="margin-top: 14px;">
                            <input type="hidden" name="refund_request_id" value="<?php echo (int)$r['id']; ?>">
                            <input type="hidden" name="partial_percent" value="50">

                            <label class="rr-form-label">Decision *</label>
                            <select name="decision" class="rf-host-decision-select" required>
                                <option value="">Select</option>
                                <option value="approve_50">Approve 50% refund</option>
                                <option value="reject">Reject</option>
                            </select>

                            <label class="rr-form-label">Note to admin/guest <span style="color:#64748B;">(optional)</span></label>
                            <textarea name="note" maxlength="1000" placeholder="Explain your decision (optional)"></textarea>

                            <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-top: 12px;">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i>Submit decision</button>
                                <a class="btn btn-danger" href="../messages.php"><i class="fa-solid fa-envelope"></i>Message instead</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="rr-pill" style="margin-top: 14px;">
                            <small>Host decision</small>
                            <strong>This request does not require a host decision (only 50% refund cases require host action).</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </main>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
</body>
</html>

