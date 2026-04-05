<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/booking_money.php';

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

$commission_earned   = 0.0;
$commission_pending  = 0.0;
$commission_cancelled = 0.0;
$gross_earned        = 0.0;
$gross_pending       = 0.0;

foreach ($bookings as $b) {
    $total = (float) $b['total_price'];
    $fee   = reservepro_platform_commission_from_total($total);
    $st    = $b['status'];

    if ($st === 'pending') {
        $commission_pending += $fee;
        $gross_pending += $total;
        continue;
    }
    if ($st === 'confirmed' || $st === 'completed') {
        $commission_earned += $fee;
        $gross_earned += $total;
        continue;
    }
    if ($st === 'cancelled') {
        $commission_cancelled += $fee;
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
    <title>Platform Commission - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.1">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.0">
    <style>
        .commission-header {
            background: linear-gradient(135deg, #0c4a6e 0%, #111827 50%, #020617 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
            color: #FFFFFF;
        }
        .host-main .commission-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #FFFFFF !important;
        }
        .host-main .commission-header p {
            opacity: 0.95;
            font-size: 15px;
            color: #E8EEFF !important;
            max-width: 720px;
            line-height: 1.55;
            margin: 0;
        }
        .host-main .commission-header strong {
            color: #FEF9C3 !important;
            font-weight: 600;
        }
        .host-main .commission-header a {
            color: #7dd3fc !important;
            text-decoration: underline;
        }
        .host-main .commission-header a:hover {
            color: #BAE6FD !important;
        }
        .commission-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .commission-stat-card {
            background: #1F1F1F;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
        }
        .commission-stat-card .stat-label {
            font-size: 12px;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .commission-stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #38bdf8, #D4A574);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .commission-stat-card .stat-note { font-size: 12px; color: #6B7280; margin-top: 6px; }
        .earnings-table-container {
            background: #1F1F1F;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #3A3A3A;
        }
        .earnings-table-container .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #3A3A3A;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        .earnings-table-container .table-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin: 0;
        }
        .filter-buttons { display: flex; gap: 8px; flex-wrap: wrap; width: 100%; }
        .filter-btn {
            padding: 8px 14px;
            border: 1px solid #3A3A3A;
            background: transparent;
            color: #B8B8B8;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
        }
        .filter-btn:hover { background: #2C2C2C; color: #D4A574; border-color: #D4A574; }
        .filter-btn.active {
            background: linear-gradient(135deg, #0ea5e9, #0369a1);
            color: #fff;
            border-color: transparent;
        }
        .earnings-table { width: 100%; border-collapse: collapse; }
        .earnings-table thead { background: #2C2C2C; }
        .earnings-table th {
            padding: 12px 14px;
            text-align: left;
            font-size: 11px;
            color: #B8B8B8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .earnings-table td {
            padding: 14px;
            color: #E5E7EB;
            border-bottom: 1px solid #2C2C2C;
            font-size: 13px;
        }
        .earnings-table tbody tr:hover { background: #2C2C2C; }
        .booking-id { font-family: monospace; color: #D4A574; font-weight: 600; }
        .col-commission { color: #38bdf8; font-weight: 700; }
        .col-host { color: #A7F3D0; }
        .empty-earnings { text-align: center; padding: 56px 20px; color: #9CA3AF; }
        .empty-earnings h3 { color: #fff !important; }
        /* Self-contained light-mode hero (wins over cached old theme-toggle.css) */
        body.light-mode .host-main .commission-header h1,
        body.dashboard-page.light-mode .host-main .commission-header h1 {
            color: #FFFFFF !important;
        }
        body.light-mode .host-main .commission-header p,
        body.dashboard-page.light-mode .host-main .commission-header p {
            color: #E8EEFF !important;
        }
        body.light-mode .host-main .commission-header strong,
        body.dashboard-page.light-mode .host-main .commission-header strong {
            color: #FEF9C3 !important;
        }
        body.light-mode .host-main .commission-header a,
        body.dashboard-page.light-mode .host-main .commission-header a {
            color: #7dd3fc !important;
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
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span>Admin Panel</span>
                </a>
                <a href="host-verifications.php" class="nav-item">
                    <span class="nav-icon">✅</span>
                    <span>Host Verifications</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span>Users</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon">📅</span>
                    <span>All Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon">💰</span>
                    <span>Earnings</span>
                </a>
                <a href="commission.php" class="nav-item active">
                    <span class="nav-icon">💎</span>
                    <span>Commission</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon">🌐</span>
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
            <div class="commission-header">
                <h1>💎 Platform commission</h1>
                <p>
                    Guests pay <strong>nightly subtotal + 10% service fee</strong>. The fee is the app’s commission:
                    <strong>commission = guest total ÷ 11</strong>, host share = total − commission.
                    <a href="earnings.php">View gross guest payments (Earnings)</a>
                </p>
            </div>

            <div class="commission-stats">
                <div class="commission-stat-card">
                    <div class="stat-label">Commission (earned)</div>
                    <div class="stat-value">₱<?php echo number_format($commission_earned, 2); ?></div>
                    <div class="stat-note">Confirmed + completed bookings</div>
                </div>
                <div class="commission-stat-card">
                    <div class="stat-label">Commission (pending)</div>
                    <div class="stat-value">₱<?php echo number_format($commission_pending, 2); ?></div>
                    <div class="stat-note">If bookings complete</div>
                </div>
                <div class="commission-stat-card">
                    <div class="stat-label">Gross paid (earned)</div>
                    <div class="stat-value" style="font-size:22px;">₱<?php echo number_format($gross_earned, 2); ?></div>
                    <div class="stat-note">Guest totals for reference</div>
                </div>
                <div class="commission-stat-card">
                    <div class="stat-label">Commission (cancelled)</div>
                    <div class="stat-value" style="font-size:22px;">₱<?php echo number_format($commission_cancelled, 2); ?></div>
                    <div class="stat-note">Historical only</div>
                </div>
            </div>

            <div class="earnings-table-container">
                <div class="table-header">
                    <h2>Per-booking breakdown</h2>
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active" data-filter="all">All</button>
                        <button type="button" class="filter-btn" data-filter="earned">Earned</button>
                        <button type="button" class="filter-btn" data-filter="pending">Pending</button>
                        <button type="button" class="filter-btn" data-filter="cancelled">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-earnings">
                        <h3>No bookings</h3>
                        <p>Commission will appear when there are reservations.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="earnings-table">
                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Property</th>
                                    <th>Host</th>
                                    <th>Guest paid</th>
                                    <th>Host share</th>
                                    <th>Commission (10%)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b):
                                    $total = (float) $b['total_price'];
                                    $comm  = reservepro_platform_commission_from_total($total);
                                    $host  = reservepro_host_share_from_total($total);
                                    ?>
                                    <tr data-status="<?php echo htmlspecialchars($b['status']); ?>">
                                        <td class="booking-id">BK-<?php echo str_pad((string) $b['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($b['property_name']); ?></td>
                                        <td><?php echo htmlspecialchars($b['host_first_name'] . ' ' . $b['host_last_name']); ?></td>
                                        <td>₱<?php echo number_format($total, 2); ?></td>
                                        <td class="col-host">₱<?php echo number_format($host, 2); ?></td>
                                        <td class="col-commission">₱<?php echo number_format($comm, 2); ?></td>
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
    <script>
        (function () {
            var buttons = document.querySelectorAll('.earnings-table-container .filter-btn');
            var rows = document.querySelectorAll('.earnings-table tbody tr');
            if (!buttons.length || !rows.length) return;
            function applyFilter(mode) {
                rows.forEach(function (row) {
                    var s = row.getAttribute('data-status') || '';
                    if (mode === 'all') { row.style.display = ''; return; }
                    if (mode === 'earned') {
                        row.style.display = (s === 'confirmed' || s === 'completed') ? '' : 'none';
                        return;
                    }
                    if (mode === 'pending') { row.style.display = s === 'pending' ? '' : 'none'; return; }
                    if (mode === 'cancelled') { row.style.display = s === 'cancelled' ? '' : 'none'; }
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
