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

// Map status to badge class
$statusClass = 'status-pending';
switch ($booking['status']) {
    case 'confirmed':
        $statusClass = 'status-confirmed';
        break;
    case 'completed':
        $statusClass = 'status-completed';
        break;
    case 'cancelled':
        $statusClass = 'status-cancelled';
        break;
}

// Hosts may approve a booking only while it's pending
$canApprove = ($booking['status'] === 'pending');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Booking #<?php echo htmlspecialchars($booking['id']); ?> - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.1">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=14.0">
    <style>
        .view-booking-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
        }
        .view-booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .view-booking-header h1 {
            font-size: 24px;
            margin: 0 0 8px 0;
            color: #fff !important;
        }
        body.dashboard-page.light-mode .view-booking-page .view-booking-header h1 {
            color: #0f172a !important;
        }
        .view-booking-header .subtitle {
            margin: 0;
            font-size: 14px;
            color: #9CA3AF;
        }
        body.dashboard-page.light-mode .view-booking-page .view-booking-header .subtitle {
            color: #475569 !important;
        }
        .view-booking-header .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn-view-back {
            padding: 10px 18px;
            background: #3B82F6;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-view-back:hover {
            background: #2563EB;
        }
        .view-section {
            background: var(--bg-secondary, #1A1A1A);
            border: 1px solid var(--border-color, #3A3A3A);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .view-section h2 {
            font-size: 16px;
            margin: 0 0 12px 0;
            color: #D4A574 !important;
        }
        body.dashboard-page.light-mode .view-booking-page .view-section h2 {
            color: #b45309 !important;
        }
        body.dashboard-page:not(.light-mode) .view-section p,
        body.dashboard-page:not(.light-mode) .view-section .detail-row {
            color: #E0E0E0 !important;
            margin: 0 0 8px 0;
            font-size: 14px;
        }
        body.dashboard-page.light-mode .view-booking-page .view-section p,
        body.dashboard-page.light-mode .view-booking-page .view-section .detail-row {
            color: #0f172a !important;
            margin: 0 0 8px 0;
            font-size: 14px;
        }
        body.dashboard-page.light-mode .view-booking-page .view-section p strong,
        body.dashboard-page.light-mode .view-booking-page .view-section .detail-row strong {
            color: #020617 !important;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }
        .detail-pill {
            padding: 8px 12px;
            background: #111827;
            border-radius: 8px;
            font-size: 13px;
            color: #f8fafc !important;
        }
        /* Light mode: theme-toggle sets body.light-mode div { color: ... !important } — reassert pill text on dark chips */
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
    </style>
</head>
<body class="dashboard-page">
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a href="properties.php" class="nav-item"><span class="nav-icon">🏠</span><span>My Properties</span></a>
                <a href="add-property.php" class="nav-item"><span class="nav-icon">➕</span><span>Add Property</span></a>
                <a href="bookings.php" class="nav-item active"><span class="nav-icon">📅</span><span>Bookings</span></a>
                <a href="earnings.php" class="nav-item"><span class="nav-icon">💰</span><span>Earnings</span></a>
                <a href="messages.php" class="nav-item"><span class="nav-icon">💬</span><span>Messages</span></a>
                <a href="../home.php" class="nav-item"><span class="nav-icon">🌐</span><span>View Site</span></a>
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
                <div class="view-booking-header">
                    <div>
                        <h1>Booking #<?php echo htmlspecialchars($booking['id']); ?></h1>
                        <p class="subtitle">
                            <?php echo htmlspecialchars($booking['property_title']); ?> ·
                            <?php echo htmlspecialchars($booking['city'] . ', ' . $booking['country']); ?>
                        </p>
                        <p class="subtitle">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="actions">
                        <a href="bookings.php" class="btn-view-back">← Back to bookings</a>
                        <?php if ($canApprove): ?>
                            <form method="POST" action="update-booking-status.php" style="display:inline;">
                                <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                <input type="hidden" name="new_status" value="confirmed">
                                <button type="submit" class="btn-view-back" style="background:#22C55E; margin-left:8px;">
                                    Approve booking
                                </button>
                            </form>
                        <?php endif; ?>
                        <div class="theme-toggle">
                            <span class="theme-toggle-icon">☀️</span>
                            <span class="theme-toggle-text">Light</span>
                        </div>
                    </div>
                </div>

                <div class="view-section">
                    <h2>Stay details</h2>
                    <div class="detail-grid">
                        <div class="detail-pill">Check-in: <?php echo $checkIn->format('M d, Y'); ?></div>
                        <div class="detail-pill">Check-out: <?php echo $checkOut->format('M d, Y'); ?></div>
                        <div class="detail-pill">Nights: <?php echo $nights; ?></div>
                        <div class="detail-pill">Guests: <?php echo (int) $booking['guests']; ?></div>
                        <div class="detail-pill">Booked on: <?php echo date('M d, Y H:i', strtotime($booking['booking_date'])); ?></div>
                    </div>
                </div>

                <div class="view-section">
                    <h2>Pricing</h2>
                    <p class="detail-row">Nightly rate: ₱<?php echo number_format($nightly, 2); ?></p>
                    <p class="detail-row">Subtotal (<?php echo $nights; ?> nights): ₱<?php echo number_format($subtotal, 2); ?></p>
                    <p class="detail-row">Service fee (approx.): ₱<?php echo number_format($serviceFee, 2); ?></p>
                    <p class="detail-row" style="font-weight: 700;">Total: ₱<?php echo number_format($booking['total_price'], 2); ?></p>
                </div>

                <div class="view-section">
                    <h2>Guest</h2>
                    <p class="detail-row">
                        <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong><br>
                        <?php echo htmlspecialchars($booking['email']); ?>
                    </p>
                </div>

                <div class="view-section">
                    <h2>Payment</h2>
                    <?php if (!empty($booking['payment_status'])): ?>
                        <p class="detail-row">
                            Status:
                            <span class="badge-payment <?php echo htmlspecialchars($booking['payment_status']); ?>">
                                <?php echo ucfirst($booking['payment_status']); ?>
                            </span>
                        </p>
                        <p class="detail-row">Amount: ₱<?php echo number_format((float) $booking['payment_amount'], 2); ?></p>
                        <p class="detail-row">Provider: <?php echo htmlspecialchars(strtoupper($booking['provider'])); ?></p>
                        <p class="detail-row">Method: <?php echo htmlspecialchars(strtoupper($booking['method'])); ?></p>
                        <?php if (!empty($booking['external_reference'])): ?>
                            <p class="detail-row">Reference: <?php echo htmlspecialchars($booking['external_reference']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="detail-row">No payment record yet for this booking.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>

