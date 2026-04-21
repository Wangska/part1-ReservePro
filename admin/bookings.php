<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Ensure user is an admin
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();

// Get all bookings
$query = "
    SELECT 
        b.*,
        p.title as property_name,
        p.price_per_night,
        h.first_name as host_first_name,
        h.last_name as host_last_name,
        g.first_name as guest_first_name,
        g.last_name as guest_last_name,
        g.email as guest_email,
        DATEDIFF(b.check_out, b.check_in) as nights
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users h ON p.host_id = h.id
    JOIN users g ON b.guest_id = g.id
    ORDER BY b.booking_date DESC
";
$result = $conn->query($query);
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$stats = [
    'total' => count($bookings),
    'confirmed' => 0,
    'pending' => 0,
    'cancelled' => 0,
    'total_revenue' => 0
];

foreach ($bookings as $booking) {
    $stats[$booking['status']]++;
    if ($booking['status'] === 'confirmed') {
        $stats['total_revenue'] += $booking['total_price'];
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
    <title>All Bookings - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.5">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        .booking-id {
            font-family: 'Courier New', monospace;
            color: #D4A574;
            font-weight: 600;
        }

        .properties-table-container .filter-tabs {
            flex-wrap: wrap;
            width: 100%;
        }

        .admin-bookings-page .properties-table th,
        .admin-bookings-page .properties-table td {
            border-left: none !important;
            border-right: none !important;
            border-inline: none !important;
            outline: none !important;
            box-shadow: none !important;
            text-align: center;
            vertical-align: middle;
        }

        .admin-bookings-page .properties-table th {
            text-align: center;
        }

        .admin-bookings-page .properties-table {
            border-collapse: collapse;
            border-spacing: 0;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page admin-bookings-page">
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
                <a href="bookings.php" class="nav-item active">
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
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>

                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <?php require __DIR__ . '/../includes/notifications-widget.php'; ?>
            <div class="bookings-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <h1>All Bookings</h1>
                    <p></p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="properties-stats admin-metric-grid">
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-sky"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Total Bookings</p>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-emerald"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Confirmed</p>
                        <h3><?php echo $stats['confirmed']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Pending</p>
                        <h3><?php echo $stats['pending']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-gold"><i class="fa-solid fa-wallet" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Total Revenue</p>
                        <h3>₱<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="properties-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>Booking History</h2>
                        <p></p>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%;">
                        <div style="width: 320px;">
                            <div style="position:relative;">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94A3B8;"></i>
                                <input
                                    id="bookingSearch"
                                    type="text"
                                    placeholder="Search booking, guest, host, dates…"
                                    style="width:100%; padding: 10px 12px 10px 36px; border-radius: 999px; border: 1px solid rgba(148,163,184,0.18); background: rgba(255,255,255,0.06); color:#E2E8F0; font-weight:800; font-size:13px;"
                                    autocomplete="off"
                                >
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-left:auto;">
                            <button type="button" class="filter-tab active" onclick="filterBookings('all', this)">All</button>
                            <button type="button" class="filter-tab" onclick="filterBookings('confirmed', this)">Confirmed</button>
                            <button type="button" class="filter-tab" onclick="filterBookings('pending', this)">Pending</button>
                            <button type="button" class="filter-tab" onclick="filterBookings('cancelled', this)">Cancelled</button>
                        </div>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-state admin-empty-state">
                        <span class="admin-empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                        <h3>No Bookings Found</h3>
                        <p>There are no bookings in the system yet.</p>
                    </div>
                <?php else: ?>
                    <table class="properties-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Property</th>
                                <th>Guest</th>
                                <th>Host</th>
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
                                    <td><?php echo htmlspecialchars($booking['guest_first_name'] . ' ' . $booking['guest_last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['host_first_name'] . ' ' . $booking['host_last_name']); ?></td>
                                    <td><?php echo date('M j', strtotime($booking['check_in'])) . '–' . date('j, Y', strtotime($booking['check_out'])); ?></td>
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
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        let currentBookingStatusFilter = 'all';

        function applyBookingFilters() {
            const rows = document.querySelectorAll('.properties-table tbody tr');
            const qEl = document.getElementById('bookingSearch');
            const q = (qEl ? qEl.value : '').trim().toLowerCase();

            rows.forEach(row => {
                const statusOk = (currentBookingStatusFilter === 'all') || (row.dataset.status === currentBookingStatusFilter);
                const textOk = (q === '') || ((row.textContent || '').toLowerCase().includes(q));
                const show = statusOk && textOk;
                row.style.display = show ? '' : 'none';
            });
        }

        function filterBookings(status, el) {
            const buttons = document.querySelectorAll('.properties-table-container .filter-tab');
            buttons.forEach(btn => btn.classList.remove('active'));
            if (el) el.classList.add('active');

            currentBookingStatusFilter = status;
            applyBookingFilters();
        }

        (function initBookingSearch() {
            const input = document.getElementById('bookingSearch');
            if (!input) return;
            let t = null;
            input.addEventListener('input', function() {
                if (t) clearTimeout(t);
                t = setTimeout(applyBookingFilters, 90);
            });
        })();
    </script>
</body>
</html>
