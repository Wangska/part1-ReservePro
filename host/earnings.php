<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/booking_money.php';

requireLogin();
$user = getCurrentUser();

// Hosts must complete verification before accessing earnings
if ($user && $user['role'] === 'host' && empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

// Ensure user is a host
if ($user['role'] !== 'host') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

// Get all bookings for host's properties
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.booking_date,
        b.check_in,
        b.check_out,
        b.total_price,
        b.status,
        p.title as property_name,
        p.price_per_night,
        u.first_name,
        u.last_name,
        u.email,
        DATEDIFF(b.check_out, b.check_in) as nights
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users u ON b.guest_id = u.id
    WHERE p.host_id = ?
    ORDER BY b.booking_date DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate earnings statistics
// Only confirmed/completed bookings should count toward total earnings.
$total_earnings     = 0; // net host earnings (host share - refund deductions)
$pending_earnings   = 0; // host share preview for pending
$completed_earnings = 0; // host share for confirmed/completed (gross)
$refund_deductions  = 0; // negative number (sum of debits)
$total_bookings     = count($bookings);

foreach ($bookings as $booking) {
    $amountTotal = (float) $booking['total_price'];
    $amountHost  = reservepro_host_share_from_total($amountTotal);

    // Pending = awaiting payment, do NOT include in totals
    if ($booking['status'] === 'pending') {
        $pending_earnings += $amountHost;
        continue;
    }

    // Confirmed/completed = paid earnings
    if ($booking['status'] === 'confirmed' || $booking['status'] === 'completed') {
        $completed_earnings += $amountHost;
        $total_earnings     += $amountHost;
    }
}

// Apply refund deductions from host_ledger (only refund debits)
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM host_ledger WHERE host_id = ? AND entry_type = 'refund_debit'");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$refund_deductions = (float)($row['s'] ?? 0); // negative
$total_earnings += $refund_deductions;

// Count host's properties (for "First" vs "Add" button text)
$count_result = $conn->query("SELECT COUNT(*) as n FROM properties WHERE host_id = " . (int)$user['id']);
$host_property_count = $count_result ? (int)$count_result->fetch_assoc()['n'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Earnings - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=11.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .earnings-header {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            gap: 20px;
            padding: 28px 30px;
            margin-bottom: 28px;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.24);
            color: white;
        }

        .earnings-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #FFFFFF !important;
        }

        .earnings-header p {
            opacity: 0.9;
            font-size: 16px;
            color: #E0E0E0 !important;
        }

        .earnings-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        /* Match host-metric-card from host-dashboard.css */
        .earnings-stat-card {
            padding: 22px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .earnings-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 24px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(212, 165, 116, 0.3);
        }

        /* Match host-surface-header from host-dashboard.css */
        .table-header.host-surface-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            padding: 24px 24px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(17, 24, 39, 0.86);
        }
        .table-header.host-surface-header h2 {
            margin: 0 0 6px;
            color: #FFFFFF !important;
        }

        /* Match host-empty-state from host-dashboard.css */
        .empty-earnings {
            padding: 52px 36px;
            text-align: center;
        }
        .empty-earnings h3 {
            margin-bottom: 8px;
        }
        .empty-earnings p {
            margin-bottom: 0;
        }

        .stat-label {
            font-size: 14px;
            color: #B8B8B8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 4px;
        }

        .stat-change {
            font-size: 13px;
            color: #22C55E;
        }

        .earnings-table-container.host-surface {
            background: rgba(17, 24, 39, 0.86);
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 20px 36px rgba(0, 0, 0, 0.18);
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
        }

        .earnings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .earnings-table thead {
            background: rgba(255, 255, 255, 0.04);
        }

        .earnings-table th {
            padding: 14px 18px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .earnings-table td {
            padding: 16px 18px;
            color: #E2E8F0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            vertical-align: middle;
        }

        .earnings-table tbody tr {
            transition: background 0.2s ease;
        }

        .earnings-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .booking-id {
            font-family: 'Courier New', monospace;
            color: #D4A574;
            font-weight: 600;
        }

        .guest-name {
            font-weight: 500;
            color: #FFFFFF;
        }

        .amount {
            font-weight: 700;
            font-size: 16px;
            color: #D4A574;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-completed {
            background: rgba(34, 197, 94, 0.2);
            color: #22C55E;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #FBBf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-confirmed {
            background: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .empty-earnings {
            text-align: center;
            padding: 80px 20px;
            color: #B8B8B8;
        }

        .empty-earnings h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: #FFFFFF !important;
        }

        .empty-earnings p {
            font-size: 16px;
            margin-bottom: 24px;
            color: #B8B8B8 !important;
        }
        /* Notification bell styles */
        .admin-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }
        .adm-notif-wrap {
            position: relative;
        }
        .adm-notif-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.06);
            color: #A3A3A3;
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s;
        }
        .adm-notif-btn:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(212, 165, 116, 0.4);
        }
        .adm-notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: #EF4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            pointer-events: none;
        }
        .adm-notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 340px;
            max-width: calc(100vw - 32px);
            border-radius: 18px;
            background: rgba(17, 24, 39, 0.97);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.42);
            z-index: 9999;
            overflow: hidden;
        }
        .adm-notif-dropdown-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 14px 11px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .adm-notif-dropdown-title {
            font-size: 13px;
            font-weight: 900;
            color: #F1F5F9;
            letter-spacing: -0.01em;
        }
        .adm-notif-markall {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 10px;
            cursor: pointer;
        }
        .adm-notif-markall:hover {
            background: rgba(255, 255, 255, 0.11);
        }
        .adm-notif-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            max-height: 340px;
            overflow-y: auto;
        }
        .adm-notif-item {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 9px 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(255, 255, 255, 0.03);
        }
        .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.32);
            background: rgba(212, 165, 116, 0.07);
        }
        .adm-notif-item-body {
            flex: 1;
            min-width: 0;
        }
        .adm-notif-item strong {
            display: block;
            color: #F1F5F9;
            font-weight: 800;
            font-size: 12px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .adm-notif-item small {
            display: block;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
        }
        .adm-notif-item-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
            flex-shrink: 0;
        }
        .adm-notif-empty {
            padding: 14px 10px;
            color: #94A3B8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        body.light-mode .adm-notif-btn {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.10);
            color: #6B7280;
        }
        body.light-mode .adm-notif-btn:hover {
            background: #F1F5F9;
        }
        body.light-mode .adm-notif-dropdown {
            background: #FFFFFF;
            border-color: rgba(15, 23, 42, 0.10);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
        }
        body.light-mode .adm-notif-dropdown-head {
            border-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .adm-notif-dropdown-title {
            color: #0F172A;
        }
        body.light-mode .adm-notif-markall {
            background: #F8FAFC;
            color: #0F172A;
            border-color: rgba(15, 23, 42, 0.10);
        }
        body.light-mode .adm-notif-item {
            background: #F8FAFC;
            border-color: #E2E8F0;
        }
        body.light-mode .adm-notif-item.unread {
            background: rgba(212, 165, 116, 0.10);
            border-color: rgba(212, 165, 116, 0.40);
        }
        body.light-mode .adm-notif-item strong {
            color: #0F172A;
        }
        body.light-mode .adm-notif-item small {
            color: #475569;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-earnings-page">
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
                
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span>Profile</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>Bookings</span>
                </a>
                <a href="refund-requests.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                    <span>Refund Requests</span>
                </a>
                <a href="earnings.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img
                                src="<?php echo htmlspecialchars('../' . ltrim((string)$user['profile_photo'], '/')); ?>"
                                alt="Profile photo"
                                style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;"
                                onerror="this.style.display='none'"
                            >
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host</div>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="earnings-header host-page-hero">
                <div class="host-page-hero-content">
                    <h1 style="margin-top: 20px;">Earnings</h1>
                </div>
                <div class="admin-hero-actions">
                    <div class="adm-notif-wrap" id="admNotifWrap">
                        <button class="adm-notif-btn" id="admNotifBtn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="admNotifDropdown">
                            <i class="fa-solid fa-bell" aria-hidden="true" style="font-size: 17px;"></i>
                            <span class="adm-notif-badge" id="admNotifBadge" hidden></span>
                        </button>
                        <div class="adm-notif-dropdown" id="admNotifDropdown" hidden>
                            <div class="adm-notif-dropdown-head">
                                <span class="adm-notif-dropdown-title">Notifications</span>
                                <button class="adm-notif-markall" id="admNotifMarkAll" type="button">Mark all read</button>
                            </div>
                            <div class="adm-notif-list" id="admNotifList">
                                <div class="adm-notif-empty">Loading&hellip;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earnings Statistics -->
            <div class="earnings-stats host-metric-grid">
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-emerald"><i class="fa-solid fa-wallet" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Total Earnings</div>
                        <div class="stat-value">₱<?php echo number_format($total_earnings, 2); ?></div>
                    </div>
                </div>
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-sky"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Completed</div>
                        <div class="stat-value">₱<?php echo number_format($completed_earnings, 2); ?></div>
                    </div>
                </div>
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value">₱<?php echo number_format($pending_earnings, 2); ?></div>
                    </div>
                </div>
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-red"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Refund deductions</div>
                        <div class="stat-value">₱<?php echo number_format(abs($refund_deductions), 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Earnings Table -->
            <div class="earnings-table-container host-surface">
                <div class="table-header host-surface-header">
                    <div>
                        <h2>Booking History</h2>
                    </div>
                    <div class="filter-buttons host-filter-row">
                        <button class="filter-btn host-filter-btn active" onclick="filterBookings('all', this)">All</button>
                        <button class="filter-btn host-filter-btn" onclick="filterBookings('confirmed', this)">Completed</button>
                        <button class="filter-btn host-filter-btn" onclick="filterBookings('pending', this)">Pending</button>
                        <button class="filter-btn host-filter-btn" onclick="filterBookings('cancelled', this)">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-earnings host-empty-state host-surface">
                        <span class="host-empty-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                        <h3>No earnings yet</h3>
                    </div>
                <?php else: ?>
                    <div class="host-table-scroll">
                    <table class="earnings-table host-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Property</th>
                                <th>Guest</th>
                                <th>Dates</th>
                                <th>Nights</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr data-status="<?php echo $booking['status']; ?>">
                                    <td class="booking-id">BK-<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($booking['property_name']); ?></td>
                                    <td class="guest-name"><?php echo htmlspecialchars($booking['first_name'] . ' ' . substr($booking['last_name'], 0, 1) . '.'); ?></td>
                                    <td><?php echo date('M j', strtotime($booking['check_in'])) . '–' . date('j', strtotime($booking['check_out'])); ?></td>
                                    <td><?php echo $booking['nights']; ?></td>
                                    <td class="amount">₱<?php echo number_format($booking['total_price'], 0); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Status Labels removed -->
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
    <script>
        function filterBookings(status, el) {
            const rows = document.querySelectorAll('.earnings-table tbody tr');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            if (el) el.classList.add('active');
            
            // Filter rows
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    if (row.dataset.status === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
    </script>
    <script src="../assets/js/add-property-notifications.js"></script>
</body>
</html>
