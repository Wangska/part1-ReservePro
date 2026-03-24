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
    <link rel="icon" href="../background%20image/asd.webp" type="image/webp">
    <title>All Bookings - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=10.0">
    <style>
        .bookings-header {
            /* Trendy gray header instead of brown */
            background: linear-gradient(135deg, #111827 0%, #1F2933 45%, #020617 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
            color: white;
        }

        .bookings-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #FFFFFF !important;
        }

        .booking-id {
            font-family: 'Courier New', monospace;
            color: #D4A574;
            font-weight: 600;
        }
    </style>
</head>
<body class="dashboard-page">
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
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
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
                <a href="bookings.php" class="nav-item active">
                    <span class="nav-icon">📅</span>
                    <span>All Bookings</span>
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
                
                <!-- Theme Toggle -->
                <div class="theme-toggle" style="margin-bottom: 12px;">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="bookings-header">
                <h1>📅 All Bookings</h1>
                <p>View and manage all bookings across the platform</p>
            </div>

            <!-- Statistics -->
            <div class="properties-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">📊</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #22C55E, #16A34A);">✅</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['confirmed']; ?></h3>
                        <p>Confirmed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #FBBF24, #F59E0B);">⏳</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #D4A574, #B8935F);">💰</div>
                    <div class="stat-content">
                        <h3>₱<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="properties-table-container">
                <div class="table-header">
                    <h2>Booking History</h2>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterBookings('all')">All</button>
                        <button class="filter-tab" onclick="filterBookings('confirmed')">Confirmed</button>
                        <button class="filter-tab" onclick="filterBookings('pending')">Pending</button>
                        <button class="filter-tab" onclick="filterBookings('cancelled')">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <h3>📭 No Bookings Found</h3>
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

    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        function filterBookings(status) {
            const rows = document.querySelectorAll('.properties-table tbody tr');
            const buttons = document.querySelectorAll('.filter-tab');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.status === status ? '' : 'none';
                }
            });
        }
    </script>
</body>
</html>
