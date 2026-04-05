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
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.1">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.0">
    <style>
        .earnings-header {
            background: linear-gradient(135deg, #111827 0%, #1F2933 45%, #020617 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .earnings-stat-card {
            background: #1F1F1F;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
            transition: all 0.3s ease;
        }
        .earnings-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(212, 165, 116, 0.2);
            border-color: #D4A574;
        }
        .earnings-stat-card .stat-label {
            font-size: 14px;
            color: #B8B8B8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .earnings-stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 4px;
        }
        .earnings-stat-card .stat-change {
            font-size: 13px;
            color: #22C55E;
        }
        .earnings-table-container {
            background: #1F1F1F;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #3A3A3A;
        }
        .earnings-table-container .table-header {
            padding: 24px;
            border-bottom: 1px solid #3A3A3A;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        .earnings-table-container .table-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin: 0;
        }
        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            width: 100%;
        }
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #3A3A3A;
            background: transparent;
            color: #B8B8B8;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .filter-btn:hover {
            background: #2C2C2C;
            color: #D4A574;
            border-color: #D4A574;
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border-color: transparent;
        }
        .earnings-table {
            width: 100%;
            border-collapse: collapse;
        }
        .earnings-table thead {
            background: #2C2C2C;
        }
        .earnings-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #B8B8B8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #3A3A3A;
        }
        .earnings-table td {
            padding: 16px;
            color: #E0E0E0;
            border-bottom: 1px solid #2C2C2C;
            font-size: 14px;
        }
        .earnings-table tbody tr:hover {
            background: #2C2C2C;
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
            color: #FBBF24;
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
            padding: 64px 20px;
            color: #B8B8B8;
        }
        .empty-earnings h3 {
            color: #FFFFFF !important;
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
                <a href="earnings.php" class="nav-item active">
                    <span class="nav-icon">💰</span>
                    <span>Earnings</span>
                </a>
                <a href="commission.php" class="nav-item">
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
            <div class="earnings-header">
                <h1>💰 Platform Earnings</h1>
                <p>Gross guest totals (subtotal + 10% service fee) across all hosts and properties. For the platform’s 10% commission slice, see <a href="commission.php" style="color: #FDE68A; text-decoration: underline;">Commission</a>.</p>
            </div>

            <div class="earnings-stats">
                <div class="earnings-stat-card">
                    <div class="stat-label">Earned (confirmed + completed)</div>
                    <div class="stat-value">₱<?php echo number_format($total_earnings, 2); ?></div>
                    <div class="stat-change">Platform booking totals</div>
                </div>
                <div class="earnings-stat-card">
                    <div class="stat-label">Pending pipeline</div>
                    <div class="stat-value">₱<?php echo number_format($pending_earnings, 2); ?></div>
                    <div class="stat-change">Awaiting host / payment</div>
                </div>
                <div class="earnings-stat-card">
                    <div class="stat-label">Cancelled (historical)</div>
                    <div class="stat-value">₱<?php echo number_format($cancelled_value, 2); ?></div>
                    <div class="stat-change">Not counted as revenue</div>
                </div>
                <div class="earnings-stat-card">
                    <div class="stat-label">Total bookings</div>
                    <div class="stat-value"><?php echo (int) $total_bookings; ?></div>
                    <div class="stat-change">All statuses</div>
                </div>
            </div>

            <div class="earnings-table-container">
                <div class="table-header">
                    <h2>All booking transactions</h2>
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active" data-filter="all">All</button>
                        <button type="button" class="filter-btn" data-filter="earned">Earned</button>
                        <button type="button" class="filter-btn" data-filter="pending">Pending</button>
                        <button type="button" class="filter-btn" data-filter="cancelled">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-earnings">
                        <h3>No bookings yet</h3>
                        <p>When guests reserve properties, they will appear here with amounts and status.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
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
