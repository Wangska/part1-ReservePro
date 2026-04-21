<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Only hosts can view booking details
if (!$user || ($user['role'] ?? 'guest') !== 'host') {
    header('Location: ../home.php');
    exit();
}

// Host must be verified (same rule as other host tools)
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$booking_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($booking_id <= 0) {
    header('Location: bookings.php');
    exit();
}

$conn = getDBConnection();

// Load booking with related property, guest, and latest payment info
$stmt = $conn->prepare("
    SELECT 
        b.*,
        p.title AS property_title,
        p.address,
        p.city,
        p.country,
        p.price_per_night,
        u.first_name,
        u.last_name,
        u.email,
        pay.status  AS payment_status,
        pay.amount  AS payment_amount,
        pay.provider,
        pay.method,
        pay.external_reference
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users u      ON b.guest_id   = u.id
    LEFT JOIN payments pay ON pay.booking_id = b.id
    WHERE b.id = ? AND p.host_id = ?
    ORDER BY pay.id DESC
    LIMIT 1
");

$stmt->bind_param("ii", $booking_id, $user['id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$booking) {
    header('Location: bookings.php?error=notfound');
    exit();
}

// Compute derived values
$checkIn  = new DateTime($booking['check_in']);
$checkOut = new DateTime($booking['check_out']);
$nights   = max(1, (int) $checkIn->diff($checkOut)->days);
$nightly  = (float) $booking['price_per_night'];
$subtotal = $nights * $nightly;
$serviceFee = max(0, (float) $booking['total_price'] - $subtotal);

// Hosts may approve a booking only while it's pending
$canApprove = ($booking['status'] === 'pending');

$justConfirmed = isset($_GET['confirmed']) && $_GET['confirmed'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Booking #<?php echo htmlspecialchars($booking['id']); ?> - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        /* ── Page ── */
        .view-booking-page {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px 36px;
        }

        /* ── Header ── */
        .vb-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
        }
        .vb-hero h1 {
            font-size: 30px;
            font-weight: 800;
            margin: 0 0 6px;
            color: #F1F5F9 !important;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .vb-hero .vb-subtitle {
            font-size: 14px;
            color: #94a3b8;
            margin: 0;
        }
        .vb-hero .vb-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        /* ── Sections ── */
        .view-section {
            background: var(--bg-secondary, #161616);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 26px 28px;
            margin-bottom: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .view-section h2 {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #fff !important;
            margin: 0 0 20px;
        }
        .view-section p,
        .view-section .detail-row {
            color: #C0C0C0 !important;
            margin: 0 0 10px;
            font-size: 15px;
            line-height: 1.7;
        }
        .view-section p strong { color: #F1F5F9 !important; }

        /* ── Two-column layout ── */
        .vb-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 760px) { .vb-grid { grid-template-columns: 1fr; } }

        /* ── Detail grid cards ── */
        .host-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 14px;
        }
        .host-detail-card {
            display: flex; flex-direction: column; gap: 5px;
            padding: 16px 18px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
        }
        .host-detail-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
        .host-detail-value { font-size: 16px; font-weight: 700; color: #F1F5F9; }

        /* ── Pricing rows ── */
        .vb-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 15px;
            color: #C0C0C0;
        }
        .vb-price-row:last-child { border-bottom: none; padding-bottom: 0; }
        .vb-price-row.total { font-weight: 700; font-size: 17px; color: #F1F5F9; }
        .vb-price-row.total span:last-child { color: #D4A574; }

        /* ── Payment info rows ── */
        .vb-info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 14px;
            color: #C0C0C0;
        }
        .vb-info-row:last-child { border-bottom: none; }
        .vb-info-label { min-width: 120px; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .vb-info-value { color: #F1F5F9; font-weight: 500; }

        /* ── Guest card ── */
        .vb-guest-card {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .vb-guest-avatar {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: #0F0F0F;
            flex-shrink: 0;
        }
        .vb-guest-name { font-size: 17px; font-weight: 700; color: #F1F5F9; margin-bottom: 2px; }
        .vb-guest-email { font-size: 13px; color: #94a3b8; }

        /* ── Light mode ── */
        body.light-mode .view-section { background: #fff; border-color: rgba(0,0,0,0.07); box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        body.light-mode .view-section h2 { color: #0f172a !important; }
        body.light-mode .view-section p, body.light-mode .view-section .detail-row { color: #374151 !important; }
        body.light-mode .host-detail-card { background: #f8fafc; border-color: rgba(0,0,0,0.08); }
        body.light-mode .host-detail-value { color: #0f172a; }
        body.light-mode .host-detail-label { color: #64748b; }
        body.light-mode .vb-price-row { color: #374151; border-bottom-color: rgba(0,0,0,0.07); }
        body.light-mode .vb-price-row.total { color: #0f172a; }
        body.light-mode .vb-info-row { color: #374151; border-bottom-color: rgba(0,0,0,0.07); }
        body.light-mode .vb-info-value { color: #0f172a; }
        body.light-mode .vb-guest-name { color: #0f172a; }
        body.light-mode .vb-hero h1 { color: #0f172a !important; }
        body.light-mode .vb-hero .vb-subtitle { color: #64748b; }

        .detail-pill {
            padding: 8px 12px;
            background: #111827;
            border-radius: 8px;
            font-size: 13px;
            color: #f8fafc !important;
        }
        body.dashboard-page.light-mode .view-booking-page .detail-pill {
            color: #f8fafc !important;
            background: #0f172a !important;
            border: 1px solid #334155;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: rgba(234, 179, 8, 0.18);
            color: #fde047;
        }
        .status-confirmed {
            background: rgba(34, 197, 94, 0.18);
            color: #86efac;
        }
        .status-completed {
            background: rgba(59, 130, 246, 0.18);
            color: #93c5fd;
        }
        .status-cancelled {
            background: rgba(239, 68, 68, 0.18);
            color: #fca5a5;
        }
        .badge-payment {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-payment.pending {
            background: rgba(234, 179, 8, 0.18);
            color: #fbbf24;
        }
        .badge-payment.paid {
            background: rgba(34, 197, 94, 0.18);
            color: #4ade80;
        }
        .badge-payment.failed,
        .badge-payment.cancelled {
            background: rgba(239, 68, 68, 0.18);
            color: #fca5a5;
        }
        body.dashboard-page.light-mode .view-booking-page .badge-payment.pending {
            background: rgba(234, 179, 8, 0.25) !important;
            color: #854d0e !important;
        }
        body.dashboard-page.light-mode .view-booking-page .badge-payment.paid {
            background: rgba(34, 197, 94, 0.22) !important;
            color: #166534 !important;
        }
        body.dashboard-page.light-mode .view-booking-page .badge-payment.failed,
        body.dashboard-page.light-mode .view-booking-page .badge-payment.cancelled {
            background: rgba(239, 68, 68, 0.18) !important;
            color: #b91c1c !important;
        }
        body.dashboard-page.light-mode .view-booking-page .view-booking-header .status-badge.status-pending {
            color: #854d0e !important;
            background: rgba(234, 179, 8, 0.25) !important;
        }
        body.dashboard-page.light-mode .view-booking-page .view-booking-header .status-badge.status-confirmed {
            color: #166534 !important;
            background: rgba(34, 197, 94, 0.22) !important;
        }
        body.dashboard-page.light-mode .view-booking-page .view-booking-header .status-badge.status-completed {
            color: #1d4ed8 !important;
            background: rgba(59, 130, 246, 0.2) !important;
        }
        body.dashboard-page.light-mode .view-booking-page .view-booking-header .status-badge.status-cancelled {
            color: #b91c1c !important;
            background: rgba(239, 68, 68, 0.18) !important;
        }

        .rp-toast {
            position: fixed;
            right: 18px;
            top: 18px;
            z-index: 9999;
            display: none;
            max-width: 420px;
            border-radius: 16px;
            padding: 14px 14px;
            border: 1px solid rgba(34, 197, 94, 0.26);
            background: rgba(17, 24, 39, 0.92);
            box-shadow: 0 24px 60px rgba(0,0,0,0.35);
            color: #E2E8F0;
        }
        .rp-toast strong { color: #86efac; }
        .rp-toast .row { display:flex; gap: 10px; align-items:flex-start; }
        .rp-toast .icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(34,197,94,0.14);
            color:#86efac;
            flex: 0 0 auto;
        }
        .rp-toast .msg { font-weight: 800; line-height: 1.4; }
        .rp-toast .sub { margin-top: 4px; font-size: 13px; color:#CBD5E1; font-weight: 700; }
        .rp-toast .x {
            margin-left: auto;
            background: transparent;
            border: 0;
            color: #CBD5E1;
            cursor: pointer;
            font-size: 18px;
            padding: 0 6px;
        }

        body.dashboard-page.light-mode .rp-toast {
            background: #ffffff;
            color: #0f172a;
            border-color: rgba(34, 197, 94, 0.25);
            box-shadow: 0 18px 50px rgba(0,0,0,0.12);
        }
        body.dashboard-page.light-mode .rp-toast .sub { color:#475569; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-detail-page">
    <div class="rp-toast" id="rpToast">
        <div class="row">
            <div class="icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
            <div>
                <div class="msg"><strong>Congrats!</strong> You just approved this booking.</div>
                <div class="sub">The guest will see it as confirmed.</div>
            </div>
            <button class="x" type="button" id="rpToastClose" aria-label="Close">&times;</button>
        </div>
    </div>
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>My Properties</span></a>
                <a href="add-property.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span><span>Add Property</span></a>
                <a href="bookings.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span>Bookings</span></a>
                <a href="refund-requests.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span><span>Refund Requests</span></a>
                <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span><span>Earnings</span></a>
                <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span>Messages</span></a>
                <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span>Home</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host</div>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="view-booking-page">
                <div class="vb-hero">
                    <div>
                        <h1>Booking #<?php echo htmlspecialchars($booking['id']); ?></h1>
                        <p class="vb-subtitle"><?php echo htmlspecialchars($booking['property_title']); ?> &mdash; <?php echo htmlspecialchars($booking['city'] . ', ' . $booking['country']); ?></p>
                    </div>
                    <div class="vb-actions">
                        <a href="bookings.php" class="host-action-btn is-primary">Back to bookings</a>
                    </div>
                </div>

                <!-- Stay Details (full width) -->
                <div class="view-section host-detail-shell">
                    <h2>Stay Details</h2>
                    <div class="host-detail-grid">
                        <div class="host-detail-card"><span class="host-detail-label">Check-In</span><span class="host-detail-value"><?php echo $checkIn->format('M d, Y'); ?></span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Check-Out</span><span class="host-detail-value"><?php echo $checkOut->format('M d, Y'); ?></span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Nights</span><span class="host-detail-value"><?php echo $nights; ?></span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Guests</span><span class="host-detail-value"><?php echo (int) $booking['guests']; ?></span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Booked On</span><span class="host-detail-value"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span></div>
                    </div>
                    <div style="margin-top: 24px; text-align: right;">
                    </div>
                </div>

                <!-- Two-column: Pricing + Guest -->
                <div class="vb-grid">
                    <div class="view-section host-detail-shell">
                        <h2>Pricing</h2>
                        <div class="vb-price-row"><span>Nightly rate</span><span>₱<?php echo number_format($nightly, 2); ?></span></div>
                        <div class="vb-price-row"><span>Subtotal (<?php echo $nights; ?> nights)</span><span>₱<?php echo number_format($subtotal, 2); ?></span></div>
                        <div class="vb-price-row"><span>Service fee</span><span>₱<?php echo number_format($serviceFee, 2); ?></span></div>
                        <div class="vb-price-row total"><span>Total</span><span>₱<?php echo number_format($booking['total_price'], 2); ?></span></div>
                    </div>

                    <div class="view-section host-detail-shell">
                        <h2>Guest</h2>
                        <div class="vb-guest-card">
                            <div class="vb-guest-avatar"><?php echo strtoupper(substr($booking['first_name'], 0, 1) . substr($booking['last_name'], 0, 1)); ?></div>
                            <div>
                                <div class="vb-guest-name"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></div>
                                <div class="vb-guest-email"><?php echo htmlspecialchars($booking['email']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment -->
                <div class="view-section host-detail-shell">
                    <h2>Payment</h2>
                    <?php if (!empty($booking['payment_status'])): ?>
                        <div class="vb-info-row"><span class="vb-info-label">Status</span><span class="vb-info-value"><span class="badge-payment <?php echo htmlspecialchars($booking['payment_status']); ?>"><?php echo ucfirst($booking['payment_status']); ?></span></span></div>
                        <div class="vb-info-row"><span class="vb-info-label">Amount</span><span class="vb-info-value">₱<?php echo number_format((float) $booking['payment_amount'], 2); ?></span></div>
                        <div class="vb-info-row"><span class="vb-info-label">Provider</span><span class="vb-info-value"><?php echo htmlspecialchars(strtoupper($booking['provider'])); ?></span></div>
                        <div class="vb-info-row"><span class="vb-info-label">Method</span><span class="vb-info-value"><?php echo htmlspecialchars(strtoupper($booking['method'])); ?></span></div>
                        <?php if (!empty($booking['external_reference'])): ?>
                            <div class="vb-info-row"><span class="vb-info-label">Reference</span><span class="vb-info-value"><?php echo htmlspecialchars($booking['external_reference']); ?></span></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="detail-row" style="margin:0;">No payment record yet for this booking.</p>
                    <?php endif; ?>
                </div>
                <?php if ($canApprove): ?>
                <div style="text-align: right; margin-bottom: 24px;">
                    <form method="POST" action="update-booking-status.php" style="display:inline;">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                        <input type="hidden" name="new_status" value="confirmed">
                        <button type="submit" class="host-action-btn is-primary">Approve booking</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
    <script>
        (function() {
            const show = <?php echo $justConfirmed ? 'true' : 'false'; ?>;
            if (!show) return;
            const toast = document.getElementById('rpToast');
            const close = document.getElementById('rpToastClose');
            if (!toast) return;
            toast.style.display = 'block';
            function hide() { toast.style.display = 'none'; }
            if (close) close.addEventListener('click', hide);
            setTimeout(hide, 5200);

            // Remove flag from URL so refresh doesn't re-toast
            try {
                const u = new URL(window.location.href);
                u.searchParams.delete('confirmed');
                window.history.replaceState({}, document.title, u.toString());
            } catch (e) {}
        })();
    </script>
</body>
</html>

