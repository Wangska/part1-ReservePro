<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';

requireLogin();
$user = getCurrentUser();

// Guests only (hosts/admins have their own dashboards)
if (!$user || ($user['role'] ?? '') !== 'guest') {
    if ($user && ($user['role'] ?? '') === 'host') {
        header('Location: host/dashboard.php');
        exit();
    }
    if ($user && ($user['role'] ?? '') === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    }
    header('Location: home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("
    SELECT
        b.id,
        b.property_id,
        b.guest_id,
        b.check_in,
        b.check_out,
        b.total_price,
        b.status,
        b.booking_date,
        p.title AS property_title,
        p.address,
        p.city,
        p.country,
        p.cancellation_policy,
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS primary_photo,
        rr.status AS refund_status,
        rr.refund_amount AS refund_amount
    FROM bookings b
    JOIN properties p ON p.id = b.property_id
    LEFT JOIN (
        SELECT r1.*
        FROM refund_requests r1
        JOIN (
            SELECT booking_id, MAX(id) AS max_id
            FROM refund_requests
            GROUP BY booking_id
        ) last ON last.booking_id = r1.booking_id AND last.max_id = r1.id
    ) rr ON rr.booking_id = b.id
    WHERE b.guest_id = ?
    ORDER BY b.booking_date DESC
");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function policy_label($p) {
    $p = strtolower((string)$p);
    if ($p === 'flexible') return 'Flexible';
    if ($p === 'strict') return 'Strict';
    return 'Moderate';
}
function policy_badge_class($p) {
    $p = strtolower((string)$p);
    if ($p === 'flexible') return 'policy-flexible';
    if ($p === 'strict') return 'policy-strict';
    return 'policy-moderate';
}
function booking_status_label(array $b) {
    $refundStatus = (string)($b['refund_status'] ?? '');
    if ($refundStatus === 'completed') return 'Refunded';
    $s = (string)($b['status'] ?? '');
    return ucfirst($s ?: 'unknown');
}
function booking_status_class(array $b) {
    $refundStatus = (string)($b['refund_status'] ?? '');
    if ($refundStatus === 'completed') return 'status-refunded';
    $s = (string)($b['status'] ?? '');
    if ($s === 'confirmed') return 'status-confirmed';
    if ($s === 'cancelled') return 'status-cancelled';
    if ($s === 'completed') return 'status-completed';
    return 'status-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>My Bookings - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body.mb-page-body { background: #06090F !important; }
        body.mb-page-body::before, body.mb-page-body::after { display: none !important; }
        .gb-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 14px; }
        .gb-card {
            background: rgba(17, 24, 39, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 18px;
            overflow:hidden;
            box-shadow: 0 18px 40px rgba(0,0,0,0.18);
        }
        .gb-img { position: relative; height: 170px; background: #0b1220; }
        .gb-img img { width:100%; height:100%; object-fit: cover; display:block; }
        .gb-badges { position:absolute; top: 12px; left: 12px; display:flex; gap:8px; flex-wrap:wrap; }
        .badge {
            display:inline-flex; align-items:center; gap:6px;
            padding: 6px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 800;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(15, 23, 42, 0.55);
            color: #E2E8F0;
        }
        .status-confirmed { border-color: rgba(34,197,94,0.35); color:#86efac; }
        .status-cancelled { border-color: rgba(239,68,68,0.35); color:#fca5a5; }
        .status-pending { border-color: rgba(234,179,8,0.35); color:#fde68a; }
        .status-completed { border-color: rgba(59,130,246,0.35); color:#93c5fd; }
        .status-refunded { border-color: rgba(99,102,241,0.35); color:#c7d2fe; }
        .policy-flexible { border-color: rgba(56,189,248,0.35); color:#bae6fd; }
        .policy-moderate { border-color: rgba(212,165,116,0.45); color:#FDE68A; }
        .policy-strict { border-color: rgba(244,63,94,0.35); color:#fecdd3; }
        .gb-body { padding: 14px 14px 16px; }
        .gb-title { margin:0 0 6px; color:#fff !important; font-size: 16px; font-weight: 900; letter-spacing:-0.01em; }

        .gb-meta { display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .gb-pill { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10); border-radius: 14px; padding: 10px 12px; }
        .gb-pill small { display:block; color:#94A3B8 !important; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; font-size: 10px; margin-bottom: 6px; }
        .gb-pill strong { color:#F1F5F9 !important; font-size: 13px; }
        .gb-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top: 12px; }
        .gb-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding: 10px 12px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0; text-decoration:none; font-weight: 900; font-size: 13px;
            cursor:pointer;
        }
        .gb-btn:hover { background: rgba(255,255,255,0.09); }
        .gb-btn-danger { border-color: rgba(239,68,68,0.28); color:#fecaca; }
        .gb-btn-disabled { opacity: 0.55; cursor: not-allowed; }

        /* Modal */
        .gb-modal-backdrop { position: fixed; inset: 0; display:none; align-items: center; justify-content: center; background: rgba(0,0,0,0.55); z-index: 9999; padding: 18px; }
        .gb-modal { width: 100%; max-width: 560px; border-radius: 18px; overflow:hidden; background: rgba(17,24,39,0.96); border: 1px solid rgba(148,163,184,0.18); box-shadow: 0 30px 80px rgba(0,0,0,0.4); }
        .gb-modal-head { padding: 16px 18px; border-bottom: 1px solid rgba(148,163,184,0.14); display:flex; justify-content: space-between; gap: 12px; align-items:flex-start; }
        .gb-modal-head h3 { margin:0; color:#fff !important; font-size: 16px; }
        .gb-modal-head p { margin:4px 0 0; color:#CBD5E1 !important; font-size: 13px; line-height:1.55; }
        .gb-x { background: transparent; border: 0; color:#CBD5E1; font-size: 18px; cursor:pointer; padding: 6px; }
        .gb-modal-body { padding: 16px 18px; }
        .gb-warning { background: rgba(234,179,8,0.10); border: 1px solid rgba(234,179,8,0.28); color:#FDE68A; border-radius: 14px; padding: 12px 12px; font-weight: 800; font-size: 13px; line-height: 1.55; }
        .gb-preview-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .gb-modal-foot { padding: 14px 18px; border-top: 1px solid rgba(148,163,184,0.14); display:flex; justify-content: flex-end; gap: 10px; flex-wrap:wrap; }
        .gb-btn-primary { background: linear-gradient(135deg, #D4A574, #B8935F); color:#0f172a; border-color: transparent; }

        body.light-mode .gb-card { background: #fff !important; border-color:#E2E8F0 !important; }
        body.light-mode .gb-title { color:#0f172a !important; }

        body.light-mode .gb-pill { background:#F8FAFC !important; border-color:#E2E8F0 !important; }
        body.light-mode .gb-pill small { color:#475569 !important; }
        body.light-mode .gb-pill strong { color:#0f172a !important; }
        body.light-mode .gb-btn { background:#fff !important; border-color:#E2E8F0 !important; color:#0f172a !important; }
        body.light-mode .gb-btn-danger { color:#b91c1c !important; border-color: rgba(185,28,28,0.25) !important; }
        body.light-mode .gb-modal { background: #fff !important; }
        body.light-mode .gb-modal-head h3 { color:#0f172a !important; }
        body.light-mode .gb-modal-head p { color:#475569 !important; }
        body.light-mode .gb-warning { background: rgba(234,179,8,0.14); border-color: rgba(234,179,8,0.35); color:#854d0e; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page mb-page-body">
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
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span>Profile</span>
                </a>
                <a href="my-bookings.php" class="nav-item active">
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
                        <div class="user-role">Guest</div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="admin-page-hero">
                <div class="admin-page-hero-content">

                    <h1>My Bookings</h1>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="gb-card" style="padding: 28px 24px; text-align:center;">
                    <div style="width:56px;height:56px;border-radius:16px;background:rgba(212,165,116,0.12);border:1px solid rgba(212,165,116,0.2);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fa-solid fa-calendar-xmark" style="font-size:22px;color:#D4A574;"></i>
                    </div>
                    <h2 class="gb-title" style="font-size:18px;margin-bottom:8px;">No bookings yet</h2>


                </div>
            <?php else: ?>
                <div class="gb-grid">
                <?php foreach ($bookings as $b):
                    $raw = (string)($b['primary_photo'] ?? '');
                    if ($raw !== '' && strpos($raw, 'http') !== 0) {
                        $img = h(ltrim($raw, '/'));
                    } else {
                        $img = $raw !== '' ? h($raw) : 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800&auto=format&fit=crop&q=80';
                    }
                    $policy = (string)($b['cancellation_policy'] ?? 'moderate');
                    $isRefunded = ((string)($b['refund_status'] ?? '') === 'completed');
                    $canCancel = ((string)$b['status'] === 'confirmed') && !$isRefunded;
                ?>
                <div class="gb-card">
                    <div class="gb-img">
                        <img src="<?php echo $img; ?>" alt="Property image" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800&auto=format&fit=crop&q=80'">
                        <div class="gb-badges">
                            <span class="badge <?php echo booking_status_class($b); ?>"><i class="fa-solid fa-circle" style="font-size:8px;"></i><?php echo h(booking_status_label($b)); ?></span>
                            <span class="badge <?php echo policy_badge_class($policy); ?>"><i class="fa-solid fa-shield-heart"></i><?php echo h(policy_label($policy)); ?></span>
                        </div>
                    </div>
                    <div class="gb-body">
                        <h3 class="gb-title"><?php echo h($b['property_title']); ?></h3>


                        <div class="gb-meta">
                            <div class="gb-pill">
                                <small>Check-in</small>
                                <strong><?php echo h(date('M j, Y', strtotime((string)$b['check_in']))); ?></strong>
                            </div>
                            <div class="gb-pill">
                                <small>Check-out</small>
                                <strong><?php echo h(date('M j, Y', strtotime((string)$b['check_out']))); ?></strong>
                            </div>
                            <div class="gb-pill">
                                <small>Total</small>
                                <strong>₱<?php echo number_format((float)$b['total_price'], 2); ?></strong>
                            </div>
                            <div class="gb-pill">
                                <small>Booked on</small>
                                <strong><?php echo h(date('M j, Y', strtotime((string)$b['booking_date']))); ?></strong>
                            </div>
                        </div>

                        <div class="gb-actions">
                            <button
                                type="button"
                                class="gb-btn gb-btn-danger <?php echo $canCancel ? '' : 'gb-btn-disabled'; ?>"
                                data-action="cancel"
                                data-booking-id="<?php echo (int)$b['id']; ?>"
                                <?php echo $canCancel ? '' : 'disabled'; ?>
                            >
                                <i class="fa-solid fa-ban"></i>Cancel booking
                            </button>
                            <a
                                class="gb-btn"
                                href="request-refund-issue.php?booking_id=<?php echo (int)$b['id']; ?>"
                                title="Report an issue and request a refund"
                            >
                                <i class="fa-solid fa-triangle-exclamation"></i>Report an issue
                            </a>
                            <?php if (!empty($b['refund_status'])): ?>
                                <span class="gb-btn gb-btn-disabled" style="cursor:default;">
                                    <i class="fa-solid fa-arrows-rotate"></i>Refund: <?php echo h($b['refund_status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div class="gb-modal-backdrop" id="cancelModalBackdrop" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="gb-modal">
            <div class="gb-modal-head">
                <div>
                    <h3>Cancel booking</h3>
                    <p id="cancelSub">We’ll show your refund preview before you confirm.</p>
                </div>
                <button class="gb-x" type="button" id="cancelModalClose" aria-label="Close">&times;</button>
            </div>
            <div class="gb-modal-body">
                <div class="gb-warning" id="cancelWarning">Loading refund preview…</div>
                <div class="gb-preview-grid">
                    <div class="gb-pill">
                        <small>Refund percent</small>
                        <strong id="refundPct">—</strong>
                    </div>
                    <div class="gb-pill">
                        <small>Refund amount</small>
                        <strong id="refundAmt">—</strong>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label style="display:block; color:#94A3B8; font-weight:800; letter-spacing:0.04em; text-transform:uppercase; font-size:11px; margin-bottom:6px;">Reason (optional)</label>
                    <input id="cancelReason" type="text" style="width:100%; padding:12px 12px; border-radius:12px; border:1px solid rgba(148,163,184,0.18); background: rgba(255,255,255,0.06); color:#E2E8F0;">
                </div>
                <div style="margin-top:12px; display:flex; gap:10px; align-items:flex-start;">
                    <input type="checkbox" id="refundAck" style="margin-top: 4px;">
                    <label for="refundAck" style="margin:0; color:#CBD5E1; font-weight:800; font-size:13px; line-height:1.5; text-transform:none; letter-spacing:0;">
                        I understand refunds may take up to <strong>24 hours</strong> to process.
                    </label>
                </div>
            </div>
            <div class="gb-modal-foot">
                <button class="gb-btn" type="button" id="cancelModalBack">Back</button>
                <form method="post" action="cancel-booking.php" id="cancelForm" style="margin:0;">
                    <input type="hidden" name="booking_id" id="cancelBookingId" value="">
                    <input type="hidden" name="reason" id="cancelReasonHidden" value="">
                    <input type="hidden" name="refund_ack" id="refundAckHidden" value="0">

                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js?v=26.0"></script>
    <script>
        const backdrop = document.getElementById('cancelModalBackdrop');
        const closeBtn = document.getElementById('cancelModalClose');
        const backBtn = document.getElementById('cancelModalBack');
        const warningEl = document.getElementById('cancelWarning');
        const pctEl = document.getElementById('refundPct');
        const amtEl = document.getElementById('refundAmt');
        const bookingIdInput = document.getElementById('cancelBookingId');
        const reasonInput = document.getElementById('cancelReason');
        const reasonHidden = document.getElementById('cancelReasonHidden');
        const ackBox = document.getElementById('refundAck');
        const ackHidden = document.getElementById('refundAckHidden');
        const confirmBtn = document.getElementById('cancelConfirmBtn');

        let previewOk = false;

        function syncConfirmState() {
            const ackOk = !!(ackBox && ackBox.checked);
            confirmBtn.disabled = !(previewOk && ackOk);
        }

        function openModal() {
            backdrop.style.display = 'flex';
            backdrop.setAttribute('aria-hidden', 'false');
        }
        function closeModal() {
            backdrop.style.display = 'none';
            backdrop.setAttribute('aria-hidden', 'true');
        }

        function pesos(n) {
            const x = Number(n || 0);
            return '₱' + x.toFixed(2);
        }

        async function loadPreview(bookingId) {
            warningEl.textContent = 'Loading refund preview…';
            pctEl.textContent = '—';
            amtEl.textContent = '—';
            previewOk = false;
            syncConfirmState();

            const url = 'api/refund-preview.php?type=cancellation&booking_id=' + encodeURIComponent(bookingId);
            const res = await fetch(url, { credentials: 'same-origin' });
            const data = await res.json().catch(() => null);
            if (!data || !data.ok) {
                warningEl.textContent = (data && data.error) ? data.error : 'Failed to load refund preview.';
                previewOk = false;
                syncConfirmState();
                return;
            }

            if (data.active_request) {
                warningEl.textContent = 'A refund/cancellation request already exists for this booking. Status: ' + data.active_request.status;
                previewOk = false;
                syncConfirmState();
                return;
            }

            const p = data.preview || {};
            pctEl.textContent = (p.refund_percent != null ? String(p.refund_percent) + '%' : '0%');
            amtEl.textContent = pesos(p.refund_amount || 0);
            warningEl.textContent = p.warning || 'Refund preview ready.';
            previewOk = true;
            syncConfirmState();
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-action="cancel"]');
            if (!btn) return;
            if (btn.disabled) return;

            const bookingId = btn.getAttribute('data-booking-id');
            if (!bookingId) return;

            bookingIdInput.value = bookingId;
            reasonInput.value = '';
            if (ackBox) ackBox.checked = false;
            previewOk = false;
            openModal();
            loadPreview(bookingId);
        });

        closeBtn.addEventListener('click', closeModal);
        backBtn.addEventListener('click', closeModal);
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && backdrop.style.display === 'flex') closeModal();
        });

        document.getElementById('cancelForm').addEventListener('submit', function() {
            reasonHidden.value = reasonInput.value || '';
            ackHidden.value = (ackBox && ackBox.checked) ? '1' : '0';
        });

        if (ackBox) {
            ackBox.addEventListener('change', syncConfirmState);
        }
    </script>
</body>
</html>

