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

// Get all users
$query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role,
        u.email_verified,
        u.host_verified,
        u.host_verification_status,
        u.created_at,
        (SELECT COUNT(*) FROM properties WHERE host_id = u.id) as total_properties,
        (SELECT COUNT(*) FROM bookings WHERE guest_id = u.id) as total_bookings
    FROM users u
    ORDER BY u.created_at DESC
";
$result = $conn->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [
    'total' => count($users),
    'guests' => 0,
    'hosts' => 0,
    'admins' => 0
];

foreach ($users as $u) {
    $stats[$u['role'] . 's']++;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Users - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        .user-avatar-large {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0F0F0F;
            font-size: 18px;
        }

        .user-cell {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .user-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .user-details p {
            font-size: 13px;
            color: #B8B8B8;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .role-guest {
            background: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .role-host {
            background: rgba(212, 165, 116, 0.2);
            color: #D4A574;
            border: 1px solid rgba(212, 165, 116, 0.3);
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .admin-users-page .btn-action.btn-view {
            background: transparent !important;
            color: #D4A574 !important;
            border: 1px solid rgba(212,165,116,0.32) !important;
            border-radius: 10px !important;
            min-height: unset !important;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.18s, border-color 0.18s, color 0.18s, box-shadow 0.18s;
        }
        .admin-users-page .btn-action.btn-view:hover {
            background: linear-gradient(135deg, #D4A574, #B8935F) !important;
            color: #0F0F0F !important;
            border-color: transparent !important;
            box-shadow: 0 8px 20px rgba(212,165,116,0.22) !important;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page admin-users-page">
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
                
                <!-- Theme Toggle -->
                <div class="theme-toggle" style="margin-bottom: 12px;">
                    <span class="theme-toggle-icon" aria-hidden="true"></span>
                    <span class="theme-toggle-text">Theme</span>
                </div>
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="users-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <span class="admin-page-eyebrow">Account Directory</span>
                    <h1>Users</h1>
                    <p></p>
                </div>
                <div class="admin-page-summary">
                    <span class="admin-page-summary-label">Active Hosts</span>
                    <strong><?php echo $stats['hosts']; ?></strong>
                    <span class="admin-page-summary-text"></span>
                </div>
            </div>

            <!-- Statistics -->
            <div class="properties-stats admin-metric-grid">
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-sky"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Total Users</p>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-emerald"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Guests</p>
                        <h3><?php echo $stats['guests']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-gold"><i class="fa-solid fa-house-user" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Hosts</p>
                        <h3><?php echo $stats['hosts']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-red"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Administrators</p>
                        <h3><?php echo $stats['admins']; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="properties-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>All Users</h2>
                        <p></p>
                    </div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" onclick="filterUsers('all', this)">All</button>
                        <button type="button" class="filter-tab" onclick="filterUsers('guest', this)">Guests</button>
                        <button type="button" class="filter-tab" onclick="filterUsers('host', this)">Hosts</button>
                        <button type="button" class="filter-tab" onclick="filterUsers('admin', this)">Admins</button>
                    </div>
                </div>

                <table class="properties-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Properties</th>
                            <th>Bookings</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr data-role="<?php echo $u['role']; ?>">
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-large">
                                            <?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h3><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></h3>
                                            <p>ID: <?php echo $u['id']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="user-table-email"><?php echo htmlspecialchars($u['email']); ?></span></td>
                                <td>
                                    <span class="role-badge role-<?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $u['total_properties']; ?></td>
                                <td><?php echo $u['total_bookings']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-view" onclick="viewUser(<?php echo $u['id']; ?>)">View</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        function filterUsers(role, el) {
            const rows = document.querySelectorAll('.properties-table tbody tr');
            const buttons = document.querySelectorAll('.properties-table-container .filter-tab');
            buttons.forEach(btn => btn.classList.remove('active'));
            if (el) el.classList.add('active');
            
            rows.forEach(row => {
                if (role === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.role === role ? '' : 'none';
                }
            });
        }

        function viewUser(id) {
            if (!id) return;
            window.location.href = 'view-user.php?id=' + encodeURIComponent(id);
        }
    </script>
</body>
</html>
