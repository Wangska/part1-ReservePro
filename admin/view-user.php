<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$currentUser = getCurrentUser();

// Only admins may view user details
if (!$currentUser || ($currentUser['role'] ?? null) !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: users.php');
    exit();
}

$conn = getDBConnection();

// Load main user record with some aggregate stats
$stmt = $conn->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM properties WHERE host_id = u.id)               AS total_properties,
        (SELECT COUNT(*) FROM bookings  WHERE guest_id = u.id)              AS total_bookings_as_guest,
        (SELECT COUNT(*) 
           FROM bookings b 
           JOIN properties p ON b.property_id = p.id 
          WHERE p.host_id = u.id)                                           AS total_bookings_as_host
    FROM users u
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$userResult = $stmt->get_result();
$viewUser = $userResult->fetch_assoc();
$stmt->close();

if (!$viewUser) {
    $conn->close();
    header('Location: users.php');
    exit();
}

// Properties owned (if host)
$properties = [];
if ($viewUser['role'] === 'host') {
    $stmt = $conn->prepare("
        SELECT id, title, city, country, status, price_per_night, created_at
        FROM properties
        WHERE host_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Recent bookings as guest
$bookings_guest = [];
if ($viewUser['role'] === 'guest') {
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            p.title  AS property_title,
            p.city   AS property_city,
            p.country AS property_country
        FROM bookings b
        JOIN properties p ON b.property_id = p.id
        WHERE b.guest_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $bookings_guest = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Recent bookings for this host's properties
$bookings_host = [];
if ($viewUser['role'] === 'host') {
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            p.title  AS property_title,
            g.first_name AS guest_first_name,
            g.last_name  AS guest_last_name
        FROM bookings b
        JOIN properties p ON b.property_id = p.id
        JOIN users g      ON b.guest_id    = g.id
        WHERE p.host_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 20
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $bookings_host = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

function bool_label($value) {
    return $value ? 'Yes' : 'No';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>User Details - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.2">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .user-details-layout {
            display: grid;
            grid-template-columns: minmax(0, 420px) minmax(0, 1fr);
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .user-details-layout {
                grid-template-columns: 1fr;
            }
        }
        .user-summary-card {
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.03)),
                linear-gradient(135deg, #111827, #020617);
            border-radius: 18px;
            padding: 24px;
            border: 1px solid rgba(212, 165, 116, 0.9);
            box-shadow:
                0 22px 70px rgba(0, 0, 0, 0.9),
                0 0 0 2px rgba(212, 165, 116, 0.7);
        }
        .user-summary-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        .user-summary-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            color: #0F0F0F;
        }
        .user-summary-name {
            font-size: 22px;
            font-weight: 700;
            color: #FFFFFF;
        }
        .user-summary-meta {
            font-size: 13px;
            color: #9CA3AF;
        }
        .user-meta-grid {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            font-size: 13px;
        }
        .user-meta-item-label {
            color: #E5E7EB !important;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .user-meta-item-value {
            color: #FFFFFF !important;
            font-weight: 600;
            font-size: 14px;
        }
        .detail-section {
            background: rgba(15, 15, 15, 0.9);
            border-radius: 18px;
            padding: 20px 20px 12px;
            border: 1px solid rgba(55, 65, 81, 0.8);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.75);
            margin-bottom: 18px;
        }
        .detail-section h2 {
            font-size: 18px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin-bottom: 10px;
        }
        .detail-section small {
            color: #E5E7EB !important;
            font-size: 14px;
            line-height: 1.5;
        }
        .mini-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        .mini-table th,
        .mini-table td {
            padding: 8px 6px;
            border-bottom: 1px solid rgba(55, 65, 81, 0.9);
        }
        .mini-table th {
            text-align: left;
            color: #CBD5E1 !important;
            font-weight: 600;
        }
        .mini-table td {
            color: #F1F5F9 !important;
        }
        .mini-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .mini-badge-role-guest {
            background: rgba(59, 130, 246, 0.2);
            color: #60A5FA;
        }
        .mini-badge-role-host {
            background: rgba(212, 165, 116, 0.2);
            color: #FBBF77;
        }
        .mini-badge-role-admin {
            background: rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
        }
    </style>
</head>
<body class="dashboard-page admin-page">
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
                <a href="host-verifications.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <span>Host Verifications</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span>Users</span>
                </a>
                <a href="bookings.php" class="nav-item">
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
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <div class="theme-toggle" style="margin-bottom: 12px;">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="host-header">
                <div>
                    <h1>User Details</h1>
                    <p class="subtitle">View activity and information for this user</p>
                </div>
                <a href="users.php" class="nav-btn-outline">← Back to Users</a>
            </div>

            <div class="user-details-layout">
                <!-- Left: summary -->
                <section class="user-summary-card">
                    <div class="user-summary-header">
                        <div class="user-summary-avatar">
                            <?php echo strtoupper(substr($viewUser['first_name'], 0, 1) . substr($viewUser['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="user-summary-name">
                                <?php echo htmlspecialchars($viewUser['first_name'] . ' ' . $viewUser['last_name']); ?>
                            </div>
                            <div class="user-summary-meta">
                                ID #<?php echo $viewUser['id']; ?> · 
                                Role: <?php echo ucfirst($viewUser['role']); ?> ·
                                Joined <?php echo date('M j, Y', strtotime($viewUser['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="user-meta-grid">
                        <div>
                            <div class="user-meta-item-label">Email</div>
                            <div class="user-meta-item-value"><?php echo htmlspecialchars($viewUser['email']); ?></div>
                        </div>
                        <div>
                            <div class="user-meta-item-label">Email Verified</div>
                            <div class="user-meta-item-value">
                                <?php echo bool_label((bool)($viewUser['email_verified'] ?? 0)); ?>
                            </div>
                        </div>
                        <div>
                            <div class="user-meta-item-label">Bookings as Guest</div>
                            <div class="user-meta-item-value">
                                <?php echo (int)($viewUser['total_bookings_as_guest'] ?? 0); ?>
                            </div>
                        </div>
                        <div>
                            <div class="user-meta-item-label">Properties (Host)</div>
                            <div class="user-meta-item-value">
                                <?php echo (int)($viewUser['total_properties'] ?? 0); ?>
                            </div>
                        </div>
                        <div>
                            <div class="user-meta-item-label">Bookings on Host Properties</div>
                            <div class="user-meta-item-value">
                                <?php echo (int)($viewUser['total_bookings_as_host'] ?? 0); ?>
                            </div>
                        </div>
                        <?php if ($viewUser['role'] === 'host'): ?>
                        <div>
                            <div class="user-meta-item-label">Host Verified</div>
                            <div class="user-meta-item-value">
                                <?php echo bool_label((bool)($viewUser['host_verified'] ?? 0)); ?>
                            </div>
                        </div>
                        <div>
                            <div class="user-meta-item-label">Host Status</div>
                            <div class="user-meta-item-value">
                                <?php echo htmlspecialchars($viewUser['host_verification_status'] ?? 'pending'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Right: activity lists -->
                <section>
                    <?php if ($viewUser['role'] === 'host'): ?>
                        <div class="detail-section">
                            <h2>Host Properties</h2>
                            <?php if (empty($properties)): ?>
                                <small>No properties listed yet.</small>
                            <?php else: ?>
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Price / night</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($properties as $p): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($p['title']); ?></td>
                                                <td><?php echo htmlspecialchars($p['city'] . ', ' . $p['country']); ?></td>
                                                <td><?php echo ucfirst($p['status']); ?></td>
                                                <td>₱<?php echo number_format($p['price_per_night'], 0); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="detail-section">
                            <h2>Bookings on Host Properties</h2>
                            <?php if (empty($bookings_host)): ?>
                                <small>No bookings yet.</small>
                            <?php else: ?>
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Guest</th>
                                            <th>Dates</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings_host as $b): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($b['property_title']); ?></td>
                                                <td><?php echo htmlspecialchars($b['guest_first_name'] . ' ' . $b['guest_last_name']); ?></td>
                                                <td>
                                                    <?php echo date('M j', strtotime($b['check_in'])); ?>
                                                    –
                                                    <?php echo date('M j, Y', strtotime($b['check_out'])); ?>
                                                </td>
                                                <td><?php echo ucfirst($b['status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($viewUser['role'] === 'guest'): ?>
                        <div class="detail-section">
                            <h2>Guest Bookings</h2>
                            <?php if (empty($bookings_guest)): ?>
                                <small>No bookings yet.</small>
                            <?php else: ?>
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Location</th>
                                            <th>Dates</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings_guest as $b): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($b['property_title']); ?></td>
                                                <td><?php echo htmlspecialchars($b['property_city'] . ', ' . $b['property_country']); ?></td>
                                                <td>
                                                    <?php echo date('M j', strtotime($b['check_in'])); ?>
                                                    –
                                                    <?php echo date('M j, Y', strtotime($b['check_out'])); ?>
                                                </td>
                                                <td><?php echo ucfirst($b['status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="detail-section">
                            <h2>Administrator</h2>
                            <small>This account is an administrator. Activity is not listed here.</small>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
</body>
</html>

