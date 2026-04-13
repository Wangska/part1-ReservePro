<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

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
$total_earnings     = 0;
$pending_earnings   = 0;
$completed_earnings = 0;
$total_bookings     = count($bookings);

foreach ($bookings as $booking) {
    $amount = (float) $booking['total_price'];

    // Pending = awaiting payment, do NOT include in totals
    if ($booking['status'] === 'pending') {
        $pending_earnings += $amount;
        continue;
    }

    // Confirmed/completed = paid earnings
    if ($booking['status'] === 'confirmed' || $booking['status'] === 'completed') {
        $completed_earnings += $amount;
        $total_earnings     += $amount;
    }
}

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
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .earnings-header {
            /* Trendy gray header instead of brown */
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .earnings-table-container {
            background: #1F1F1F;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #3A3A3A;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid #3A3A3A;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #FFFFFF !important;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
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
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #B8B8B8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #3A3A3A;
        }

        .earnings-table td {
            padding: 20px;
            color: #E0E0E0;
            border-bottom: 1px solid #2C2C2C;
        }

        .earnings-table tbody tr {
            transition: background 0.2s ease;
        }

        .earnings-table tbody tr:hover {
            background: #2C2C2C;
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

        .status-labels {
            margin-top: 32px;
            padding: 24px;
            background: #1F1F1F;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
        }

        .status-labels h3 {
            font-size: 16px;
            margin-bottom: 16px;
            color: #FFFFFF !important;
        }

        .status-labels ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
        }

        .status-labels li {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #B8B8B8;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .dot-completed {
            background: #22C55E;
        }

        .dot-pending {
            background: #FBBF24;
        }

        .dot-cancelled {
            background: #EF4444;
        }

        .dot-confirmed {
            background: #3B82F6;
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
    </style>
</head>
<body class="dashboard-page host-clean-page host-earnings-page">
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
                <a href="earnings.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>View Site</span>
                </a>
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

                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="earnings-header host-page-hero">
                <div class="host-page-hero-content">
                    <span class="host-page-eyebrow">Revenue Overview</span>
                    <h1>Earnings</h1>
                    <p>See what you have already earned, what is still pending, and how each booking contributes to your host revenue.</p>
                </div>
                <div style="display:flex; align-items:flex-start; gap:14px; margin-left:auto;">
                    <div class="host-page-summary">
                        <span class="host-page-summary-label">Total Earnings</span>
                        <strong>₱<?php echo number_format($total_earnings, 0); ?></strong>
                        <span class="host-page-summary-text">confirmed and completed booking revenue</span>
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
                        <div class="stat-change">All time confirmed revenue.</div>
                    </div>
                </div>
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-sky"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Completed</div>
                        <div class="stat-value">₱<?php echo number_format($completed_earnings, 2); ?></div>
                        <div class="stat-change">Paid and completed bookings.</div>
                    </div>
                </div>
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value">₱<?php echo number_format($pending_earnings, 2); ?></div>
                        <div class="stat-change">Awaiting payment or final completion.</div>
                    </div>
                </div>
                <div class="earnings-stat-card host-metric-card">
                    <div class="host-metric-icon is-gold"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-value"><?php echo $total_bookings; ?></div>
                        <div class="stat-change">Every reservation linked to your properties.</div>
                    </div>
                </div>
            </div>

            <!-- Earnings Table -->
            <div class="earnings-table-container host-surface">
                <div class="table-header host-surface-header">
                    <div>
                        <h2>Booking History</h2>
                        <p>Filter the table to focus on paid, pending, or cancelled reservations.</p>
                    </div>
                    <div class="filter-buttons host-filter-row">
                        <button class="filter-btn host-filter-btn active" onclick="filterBookings('all', this)">All</button>
                        <button class="filter-btn host-filter-btn" onclick="filterBookings('confirmed', this)">Completed</button>
                        <button class="filter-btn host-filter-btn" onclick="filterBookings('pending', this)">Pending</button>
                        <button class="filter-btn host-filter-btn" onclick="filterBookings('cancelled', this)">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-earnings host-empty-state">
                        <span class="host-empty-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                        <h3>No earnings yet</h3>
                        <p>You haven't received any bookings yet. Start by adding properties!</p>
                        <a href="add-property.php" class="btn-primary"><?php echo $host_property_count === 0 ? 'Add Your First Property' : 'Add Your Property'; ?></a>
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

            <!-- Status Labels -->
            <?php if (!empty($bookings)): ?>
            <div class="status-labels">
                <h3>Status Labels</h3>
                <ul>
                    <li>
                        <span class="status-dot dot-completed"></span>
                        Confirmed (paid)
                    </li>
                    <li>
                        <span class="status-dot dot-pending"></span>
                        Pending
                    </li>
                    <li>
                        <span class="status-dot dot-cancelled"></span>
                        Cancelled (₱0)
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
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
</body>
</html>
