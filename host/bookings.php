<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Hosts must complete verification before managing bookings
if ($user && $user['role'] === 'host' && empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

// Get all bookings for host properties
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT b.*, p.title as property_title, u.first_name, u.last_name, u.email
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

$booking_stats = [
    'total' => count($bookings),
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

foreach ($bookings as $booking_item) {
    $status_key = $booking_item['status'] ?? '';
    if (isset($booking_stats[$status_key])) {
        $booking_stats[$status_key]++;
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
    <title>Bookings - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=11.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
</head>
<body class="dashboard-page host-clean-page host-bookings-page">
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
                <a href="bookings.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>Bookings</span>
                </a>
                <a href="refund-requests.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                    <span>Refund Requests</span>
                </a>
                <a href="earnings.php" class="nav-item">
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
            <div class="host-header host-page-hero">
                <div class="host-page-hero-content">
                    <span class="host-page-eyebrow">Reservation Activity</span>
                    <h1>Bookings</h1>
                    <p class="subtitle">Review every reservation across your properties and jump into the details when guest or booking status changes.</p>
                </div>
                <div style="display:flex; align-items:flex-start; gap:14px; margin-left:auto;">
                    <div class="host-page-summary">
                        <span class="host-page-summary-label">Confirmed Stays</span>
                        <strong><?php echo $booking_stats['confirmed']; ?></strong>
                        <span class="host-page-summary-text">reservations currently approved</span>
                    </div>
                </div>
            </div>

            <div class="host-metric-grid">
                <div class="host-metric-card">
                    <div class="host-metric-icon is-sky"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Total Bookings</p>
                        <h3><?php echo $booking_stats['total']; ?></h3>
                        <span class="host-metric-note">Every reservation received across your listings.</span>
                    </div>
                </div>
                <div class="host-metric-card">
                    <div class="host-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Pending</p>
                        <h3><?php echo $booking_stats['pending']; ?></h3>
                        <span class="host-metric-note">Reservations still waiting on progress or payment.</span>
                    </div>
                </div>
                <div class="host-metric-card">
                    <div class="host-metric-icon is-emerald"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Completed</p>
                        <h3><?php echo $booking_stats['completed']; ?></h3>
                        <span class="host-metric-note">Trips that have already finished successfully.</span>
                    </div>
                </div>
                <div class="host-metric-card">
                    <div class="host-metric-icon is-red"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Cancelled</p>
                        <h3><?php echo $booking_stats['cancelled']; ?></h3>
                        <span class="host-metric-note">Reservations that will not move forward.</span>
                    </div>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="empty-state host-empty-state host-surface">
                    <span class="empty-icon host-empty-icon"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i></span>
                    <h3>No bookings yet</h3>
                    <p>Your bookings will appear here once guests make reservations</p>
                </div>
            <?php else: ?>
                <div class="bookings-table host-surface">
                    <div class="host-surface-header">
                        <div>
                            <h2>Booking History</h2>
                            <p>Open a booking to review stay details, payment status, and guest information.</p>
                        </div>
                        <span class="host-badge-neutral"><?php echo $booking_stats['total']; ?> total</span>
                    </div>
                    <div class="host-table-scroll">
                    <table class="host-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Guest</th>
                                <th>Property</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Guests</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                <td>
                                    <div class="guest-info">
                                        <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($booking['property_title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_in'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_out'])); ?></td>
                                <td><?php echo $booking['guests']; ?></td>
                                <td><strong>₱<?php echo number_format($booking['total_price'], 2); ?></strong></td>
                                <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                <td>
                                    <a href="view-booking.php?id=<?php echo (int)$booking['id']; ?>" class="host-action-btn is-info">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
</body>
</html>
