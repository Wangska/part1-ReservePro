<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();

$result = $conn->query("
    SELECT 
        b.id,
        b.booking_date,
        b.check_in,
        b.check_out,
        b.total_price,
        b.status,
        b.guests,
        p.title AS property_name,
        p.price_per_night,
        h.id AS host_id,
        h.first_name AS host_first_name,
        h.last_name AS host_last_name,
        g.first_name AS guest_first_name,
        g.last_name AS guest_last_name,
        g.email AS guest_email,
        DATEDIFF(b.check_out, b.check_in) AS nights
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users h ON p.host_id = h.id
    JOIN users g ON b.guest_id = g.id
    ORDER BY b.booking_date DESC
");
$bookings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$total_earnings   = 0;
$pending_earnings = 0;
$cancelled_value  = 0;
$total_bookings     = count($bookings);

foreach ($bookings as $booking) {
    $amount = (float) $booking['total_price'];
    if ($booking['status'] === 'pending') {
        $pending_earnings += $amount;
        continue;
    }
    if ($booking['status'] === 'confirmed' || $booking['status'] === 'completed') {
        $total_earnings += $amount;
        continue;
    }
    if ($booking['status'] === 'cancelled') {
        $cancelled_value += $amount;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Platform Earnings - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            width: 100%;
        }
        .booking-id {
            font-family: 'Courier New', monospace;
            color: #D4A574;
            font-weight: 600;
        }
        .amount {
            font-weight: 700;
            color: #D4A574;
        }

        .admin-earnings-page .earnings-table th,
        .admin-earnings-page .earnings-table td {
            text-align: center;
            vertical-align: middle;
            border-left: none !important;
            border-right: none !important;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page admin-earnings-page">
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
                <a href="refunds.php" class="nav-item">
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
                <a href="earnings.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="commission.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
                    <span>Commission</span>
                </a>
                <a href="geocode-all-properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                    <span>Geocode Properties</span>
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
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>

                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="earnings-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <span class="admin-page-eyebrow">Revenue Overview</span>
                    <h1>Platform Earnings</h1>
                    <p></p>
                </div>
                <div class="admin-page-summary">
                    <span class="admin-page-summary-label">Earned Revenue</span>
                    <strong>₱<?php echo number_format($total_earnings, 0); ?></strong>
                    <span class="admin-page-summary-text"></span>
                </div>
            </div>

            <div class="earnings-stats admin-metric-grid">
                <div class="earnings-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-emerald"><i class="fa-solid fa-wallet" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Earned Revenue</div>
                        <div class="stat-value">₱<?php echo number_format($total_earnings, 2); ?></div>
                    </div>
                </div>
                <div class="earnings-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Pending Pipeline</div>
                        <div class="stat-value">₱<?php echo number_format($pending_earnings, 2); ?></div>
                    </div>
                </div>
                <div class="earnings-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-red"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Cancelled Value</div>
                        <div class="stat-value">₱<?php echo number_format($cancelled_value, 2); ?></div>
                    </div>
                </div>
                <div class="earnings-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-sky"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-value"><?php echo (int) $total_bookings; ?></div>
                    </div>
                </div>
            </div>

            <div class="earnings-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>All Booking Transactions</h2>
                        <p></p>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active" data-filter="all">All</button>
                        <button type="button" class="filter-btn" data-filter="earned">Earned</button>
                        <button type="button" class="filter-btn" data-filter="pending">Pending</button>
                        <button type="button" class="filter-btn" data-filter="cancelled">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-earnings admin-empty-state">
                        <span class="admin-empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                        <h3>No bookings yet</h3>
                        <p>When guests reserve properties, they will appear here with amounts and status.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-scroll-x">
                        <table class="earnings-table">
                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Property</th>
                                    <th>Host</th>
                                    <th>Guest</th>
                                    <th>Dates</th>
                                    <th>Nights</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr data-status="<?php echo htmlspecialchars($b['status']); ?>">
                                        <td class="booking-id">BK-<?php echo str_pad((string) $b['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($b['property_name']); ?></td>
                                        <td><?php echo htmlspecialchars($b['host_first_name'] . ' ' . $b['host_last_name']); ?></td>
                                        <td>
                                            <div style="display:flex;flex-direction:column;gap:2px;">
                                                <span><?php echo htmlspecialchars($b['guest_first_name'] . ' ' . $b['guest_last_name']); ?></span>
                                                <small style="font-size:11px;color:#9CA3AF;"><?php echo htmlspecialchars($b['guest_email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j', strtotime($b['check_in'])) . '–' . date('j, Y', strtotime($b['check_out'])); ?></td>
                                        <td><?php echo (int) $b['nights']; ?></td>
                                        <td class="amount">₱<?php echo number_format((float) $b['total_price'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($b['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($b['status'])); ?>
                                            </span>
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

    <script src="../assets/js/theme-toggle.js?v=27.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        (function () {
            var buttons = document.querySelectorAll('.earnings-table-container .filter-btn');
            var rows = document.querySelectorAll('.earnings-table tbody tr');
            if (!buttons.length || !rows.length) return;

            function applyFilter(mode) {
                rows.forEach(function (row) {
                    var s = row.getAttribute('data-status') || '';
                    if (mode === 'all') {
                        row.style.display = '';
                        return;
                    }
                    if (mode === 'earned') {
                        row.style.display = (s === 'confirmed' || s === 'completed') ? '' : 'none';
                        return;
                    }
                    if (mode === 'pending') {
                        row.style.display = s === 'pending' ? '' : 'none';
                        return;
                    }
                    if (mode === 'cancelled') {
                        row.style.display = s === 'cancelled' ? '' : 'none';
                    }
                });
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    buttons.forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    applyFilter(btn.getAttribute('data-filter') || 'all');
                });
            });
        })();
    </script>
</body>
</html>
