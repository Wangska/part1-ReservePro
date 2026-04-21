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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: refunds.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("
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
    WHERE rr.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
    $conn->close();
    header('Location: refunds.php?error=notfound');
    exit();
}

$logs = [];
$ls = $conn->prepare("SELECT * FROM refund_logs WHERE refund_request_id = ? ORDER BY id DESC LIMIT 50");
$ls->bind_param('i', $id);
$ls->execute();
$logs = $ls->get_result()->fetch_all(MYSQLI_ASSOC);
$ls->close();

$evidence = [];
if (!empty($r['evidence_json'])) {
    $j = json_decode((string)$r['evidence_json'], true);
    if (is_array($j) && isset($j['photos']) && is_array($j['photos'])) $evidence = $j['photos'];
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
    <title>Refund #<?php echo (int)$r['id']; ?> - Admin - ReservePro</title>
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
        .vu-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; }
        .vu-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #64748B !important; }
        .vu-meta-item i { color: #64748B; font-size: 11px; }
        .vu-hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; flex-shrink: 0; }
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

        /* ── Status badges ── */
        .vu-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .vu-badge-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .vu-badge-pending         { background: rgba(234,179,8,0.1);   color: #FDE047 !important; border: 1px solid rgba(234,179,8,0.2); }
        .vu-badge-pending         .vu-badge-dot { background: #EAB308; }
        .vu-badge-pending_review  { background: rgba(245,158,11,0.1);  color: #FCD34D !important; border: 1px solid rgba(245,158,11,0.2); }
        .vu-badge-pending_review  .vu-badge-dot { background: #F59E0B; }
        .vu-badge-approved        { background: rgba(34,197,94,0.1);   color: #86EFAC !important; border: 1px solid rgba(34,197,94,0.2); }
        .vu-badge-approved        .vu-badge-dot { background: #22C55E; }
        .vu-badge-rejected        { background: rgba(244,63,94,0.1);   color: #FDA4AF !important; border: 1px solid rgba(244,63,94,0.2); }
        .vu-badge-rejected        .vu-badge-dot { background: #F43F5E; }
        .vu-badge-processing      { background: rgba(59,130,246,0.1);  color: #93C5FD !important; border: 1px solid rgba(59,130,246,0.2); }
        .vu-badge-processing      .vu-badge-dot { background: #3B82F6; }
        .vu-badge-completed       { background: rgba(99,102,241,0.12); color: #C7D2FE !important; border: 1px solid rgba(99,102,241,0.2); }
        .vu-badge-completed       .vu-badge-dot { background: #6366F1; }
        .vu-badge-confirmed       { background: rgba(34,197,94,0.1);   color: #86EFAC !important; border: 1px solid rgba(34,197,94,0.2); }
        .vu-badge-confirmed       .vu-badge-dot { background: #22C55E; }
        .vu-badge-cancelled       { background: rgba(244,63,94,0.1);   color: #FDA4AF !important; border: 1px solid rgba(244,63,94,0.2); }
        .vu-badge-cancelled       .vu-badge-dot { background: #F43F5E; }

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

        /* ── Chips ── */
        .vu-chips { display: flex; flex-direction: column; }
        .vu-chip { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(148,163,184,0.08); }
        .vu-chip:last-child { border-bottom: none; }
        .vu-chip-icon { width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
        .vu-chip-icon i { color: #64748B; font-size: 12px; }
        .vu-chip-body { flex: 1; min-width: 0; }
        .vu-chip-label { font-size: 11px; color: #64748B !important; font-weight: 500; }
        .vu-chip-value { font-size: 13px; color: #CBD5E1 !important; font-weight: 500; margin-top: 1px; }

        /* ── Table ── */
        .vu-table-wrap { overflow-x: auto; }
        .vu-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .vu-table thead tr { border-bottom: 1px solid rgba(148,163,184,0.12); }
        .vu-table th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: #64748B !important; }
        .vu-table td { padding: 10px 10px; color: #CBD5E1 !important; border-bottom: 1px solid rgba(148,163,184,0.06); }
        .vu-table tbody tr:last-child td { border-bottom: none; }
        .vu-table tbody tr:hover td { background: rgba(255,255,255,0.02); }

        /* ── Form inputs ── */
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; color: #94A3B8 !important; }
        textarea, select, input[type="number"] {
            width: 100%; box-sizing: border-box;
            padding: 10px 12px; border-radius: 10px;
            border: 1px solid rgba(148,163,184,0.18);
            background: rgba(255,255,255,0.04);
            color: #CBD5E1; font-size: 13px;
            outline: none; transition: border-color 0.2s, background 0.2s;
        }
        textarea { min-height: 90px; resize: vertical; }
        textarea:focus, select:focus, input[type="number"]:focus {
            border-color: rgba(212,165,116,0.4);
            background: rgba(255,255,255,0.06);
        }
        select option { background: #1E293B; color: #E2E8F0; }

        /* ── Action buttons ── */
        .btn-action {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 11px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            border: 1px solid rgba(148,163,184,0.22);
            background: rgba(255,255,255,0.05); color: #CBD5E1 !important;
            text-decoration: none; transition: background 0.18s, border-color 0.18s, transform 0.15s;
            margin-bottom: 8px;
        }
        .btn-action:last-child { margin-bottom: 0; }
        .btn-action:hover { background: rgba(255,255,255,0.09); transform: translateY(-1px); }
        .btn-action-primary {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0f172a !important; border-color: transparent;
            box-shadow: 0 6px 16px rgba(212,165,116,0.25);
        }
        .btn-action-primary:hover { box-shadow: 0 8px 22px rgba(212,165,116,0.35); }
        .btn-action-danger { border-color: rgba(239,68,68,0.28); color: #fca5a5 !important; }
        .btn-action-danger:hover { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.4); }

        /* ── Issue block ── */
        .issue-block {
            background: rgba(245,158,11,0.06);
            border: 1px solid rgba(245,158,11,0.18);
            border-radius: 10px; padding: 14px 16px; margin-top: 10px;
        }
        .issue-block-label { color: #FCD34D !important; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .issue-block-text  { color: #CBD5E1 !important; font-size: 12px; line-height: 1.6; }

        /* ── Evidence thumbs ── */
        .thumbs { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .thumbs a { display: block; width: 80px; height: 60px; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); transition: transform 0.15s; }
        .thumbs a:hover { transform: scale(1.05); }
        .thumbs img { width:100%; height:100%; object-fit: cover; display:block; }

        /* ── Empty state ── */
        .vu-empty { color: #64748B !important; font-size: 13px; margin: 0; }

        /* ── Light mode ── */
        body.light-mode .vu-hero { background: #fff; border-color: rgba(15,23,42,.08); box-shadow: 0 16px 40px rgba(15,23,42,.1); }
        body.light-mode .vu-hero h1 { color: #0F172A !important; }
        body.light-mode .vu-eyebrow { background: rgba(184,147,95,.12); color: #8B6F47; border-color: rgba(184,147,95,.2); }
        body.light-mode .btn-vu-back { background: rgba(15,23,42,.04) !important; color: #0F172A !important; border-color: rgba(15,23,42,.12) !important; }
        body.light-mode .btn-vu-back:hover { background: rgba(212,165,116,.1) !important; color: #8B6F47 !important; }
        body.light-mode .vu-meta-item { color: #64748B !important; }
        body.light-mode .vu-card { background: #fff; border-color: rgba(15,23,42,.08); }
        body.light-mode .vu-card-title { color: #475569 !important; }
        body.light-mode .vu-chip { border-bottom-color: rgba(15,23,42,.06); }
        body.light-mode .vu-chip-label { color: #64748B !important; }
        body.light-mode .vu-chip-value { color: #0F172A !important; }
        body.light-mode .vu-table th { color: #64748B !important; }
        body.light-mode .vu-table td { color: #0F172A !important; border-bottom-color: rgba(15,23,42,.06); }
        body.light-mode .vu-table tbody tr:hover td { background: rgba(15,23,42,.02); }
        body.light-mode .form-label { color: #475569 !important; }
        body.light-mode textarea, body.light-mode select, body.light-mode input[type="number"] { background: #F8FAFC; color: #0f172a; border-color: rgba(15,23,42,.14); }
        body.light-mode textarea:focus, body.light-mode select:focus, body.light-mode input[type="number"]:focus { border-color: #D4A574; background: #fff; }
        body.light-mode select option { background: #fff; color: #0f172a; }
        body.light-mode .btn-action { background: #F8FAFC !important; color: #0f172a !important; border-color: rgba(15,23,42,.12) !important; }
        body.light-mode .btn-action:hover { background: #F1F5F9 !important; }
        body.light-mode .btn-action-danger { color: #b91c1c !important; border-color: rgba(185,28,28,.25) !important; }
        body.light-mode .issue-block { background: rgba(245,158,11,.04); border-color: rgba(245,158,11,.2); }
        body.light-mode .issue-block-text { color: #334155 !important; }
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
            <div class="theme-toggle" style="margin-bottom: 12px;">
                <span class="theme-toggle-icon" aria-hidden="true"></span>
                <span class="theme-toggle-text">Theme</span>
            </div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">

        <!-- Hero -->
        <div class="vu-hero">
            <div class="vu-hero-left">
                <div>
                    <span class="vu-eyebrow"><i class="fa-solid fa-rotate-left"></i> Refund Request</span>
                    <h1>Refund #<?php echo (int)$r['id']; ?></h1>
                    <div class="vu-meta">
                        <?php
                            $sk = strtolower(str_replace(' ','_',(string)$r['status']));
                            $sl = ucfirst(str_replace('_',' ',$r['status']));
                        ?>
                        <span class="vu-meta-item">
                            <span class="vu-badge vu-badge-<?php echo h($sk); ?>">
                                <span class="vu-badge-dot"></span><?php echo h($sl); ?>
                            </span>
                        </span>
                        <span class="vu-meta-item"><i class="fa-solid fa-tag"></i><?php echo h($r['request_type']); ?></span>
                        <span class="vu-meta-item"><i class="fa-solid fa-calendar"></i>Requested <?php echo date('M j, Y', strtotime($r['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <div class="vu-hero-right">
                <a href="refunds.php" class="btn-vu-back"><i class="fa-solid fa-arrow-left"></i> Back to Refunds</a>
                <a href="../messages.php" class="btn-vu-back"><i class="fa-solid fa-envelope"></i> Messages</a>
            </div>
        </div>

        <!-- Content grid -->
        <div class="vu-grid">

            <!-- Left column: Admin Action + Amounts -->
            <div>
                <div class="vu-card">
                    <div class="vu-card-title"><i class="fa-solid fa-sliders"></i> Admin Action</div>
                    <form method="post" action="refund-action.php">
                        <input type="hidden" name="refund_request_id" value="<?php echo (int)$r['id']; ?>">
                        <div class="form-group">
                            <label class="form-label">Action <span style="color:#f87171;">*</span></label>
                            <select name="action" required>
                                <option value="">Select an action</option>
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                                <option value="processing">Mark as Processing</option>
                                <option value="completed">Mark as Completed</option>
                                <option value="override">Override Percent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Override Percent <span style="color:#64748B; font-weight:400;">(override only)</span></label>
                            <input type="number" name="override_percent" min="0" max="100" step="1" placeholder="e.g. 50">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Admin Note</label>
                            <textarea name="note" maxlength="1000" placeholder="Explain your decision…"></textarea>
                        </div>
                        <button type="submit" class="btn-action btn-action-primary">
                            <i class="fa-solid fa-check"></i> Save Decision
                        </button>
                        <a class="btn-action btn-action-danger" href="../messages.php">
                            <i class="fa-solid fa-envelope"></i> Contact Host / Guest
                        </a>
                    </form>
                </div>

                <div class="vu-card">
                    <div class="vu-card-title"><i class="fa-solid fa-peso-sign"></i> Amounts</div>
                    <div class="vu-chips">
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-receipt"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Booking Total</div>
                                <div class="vu-chip-value">₱<?php echo number_format((float)$r['total_price'], 2); ?></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-percent"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Suggested Refund</div>
                                <div class="vu-chip-value"><?php echo (int)$r['refund_percent']; ?>% — ₱<?php echo number_format((float)$r['refund_amount'], 2); ?></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-gavel"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Host Decision</div>
                                <div class="vu-chip-value"><?php echo !empty($r['host_decision']) ? h($r['host_decision']) . ($r['host_decision_percent'] !== null ? ' (' . (int)$r['host_decision_percent'] . '%)' : '') : '—'; ?></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-file-contract"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Cancellation Policy</div>
                                <div class="vu-chip-value"><?php echo !empty($r['cancellation_policy']) ? h($r['cancellation_policy']) : '—'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column: Details + Log -->
            <div>
                <div class="vu-card">
                    <div class="vu-card-title"><i class="fa-solid fa-circle-info"></i> Refund Details</div>
                    <div class="vu-chips">
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-user"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Guest</div>
                                <div class="vu-chip-value"><?php echo h(trim(($r['guest_first_name'] ?? '') . ' ' . ($r['guest_last_name'] ?? ''))); ?> — <span style="font-size:11px;color:#64748B;"><?php echo h($r['guest_email'] ?? ''); ?></span></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-house-user"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Host</div>
                                <div class="vu-chip-value"><?php echo h(trim(($r['host_first_name'] ?? '') . ' ' . ($r['host_last_name'] ?? ''))); ?> — <span style="font-size:11px;color:#64748B;"><?php echo h($r['host_email'] ?? ''); ?></span></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-building"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Property</div>
                                <div class="vu-chip-value"><?php echo h($r['property_title'] ?? ''); ?> <span style="font-size:11px;color:#64748B;"><?php echo h(($r['city'] ?? '') . ', ' . ($r['country'] ?? '')); ?></span></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Stay Dates</div>
                                <div class="vu-chip-value"><?php echo h((string)$r['check_in']); ?> → <?php echo h((string)$r['check_out']); ?></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-calendar-check"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Booking Date</div>
                                <div class="vu-chip-value"><?php echo h((string)($r['booking_date'] ?? '')); ?></div>
                            </div>
                        </div>
                        <div class="vu-chip">
                            <div class="vu-chip-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                            <div class="vu-chip-body">
                                <div class="vu-chip-label">Booking Status</div>
                                <div class="vu-chip-value">
                                    <span class="vu-badge vu-badge-<?php echo strtolower($r['booking_status'] ?? ''); ?>">
                                        <span class="vu-badge-dot"></span>
                                        <?php echo ucfirst($r['booking_status'] ?? ''); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($r['issue_type'])): ?>
                    <div class="issue-block">
                        <div class="issue-block-label"><i class="fa-solid fa-triangle-exclamation"></i> Issue — <?php echo h($r['issue_type']); ?></div>
                        <div class="issue-block-text"><?php echo nl2br(h($r['description'] ?? '')); ?></div>
                        <?php if (!empty($evidence)): ?>
                        <div class="thumbs">
                            <?php foreach ($evidence as $ep): ?>
                                <a href="../<?php echo h(ltrim((string)$ep, '/')); ?>" target="_blank" rel="noopener">
                                    <img src="../<?php echo h(ltrim((string)$ep, '/')); ?>" alt="Evidence photo">
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="vu-card">
                    <div class="vu-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Activity Log (<?php echo count($logs); ?>)</div>
                    <?php if (empty($logs)): ?>
                        <p class="vu-empty">No activity logged yet.</p>
                    <?php else: ?>
                    <div class="vu-table-wrap">
                        <table class="vu-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Status Change</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td><?php echo h($l['created_at']); ?></td>
                                    <td><?php echo h(ucfirst($l['actor_role'] ?? '') . ' #' . ($l['actor_user_id'] ?? '')); ?></td>
                                    <td><?php echo h($l['action']); ?></td>
                                    <td><?php echo h(($l['from_status'] ?? '—') . ' → ' . ($l['to_status'] ?? '—')); ?></td>
                                    <td><?php echo h($l['note'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div><!-- end right column -->

        </div><!-- end vu-grid -->
    </main>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
<script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
</body>
</html>

