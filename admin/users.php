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
        u.*,
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
    <title>Users - Admin - ServePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=10.0">
    <style>
        .users-header {
            background: linear-gradient(135deg, #2C1810 0%, #3E2723 50%, #0F0F0F 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
            color: white;
        }

        .users-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #FFFFFF !important;
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
    </style>
</head>
<body>
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <svg class="brand-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1c2 0 3.46 1.63 3.46 3.41 0 1.78-1.46 3.41-3.46 3.41s-3.46-1.63-3.46-3.41C12.54 2.63 14 1 16 1zm0 6.82c2.52 0 4.61-1.84 4.61-4.41C20.61 1.84 18.52 0 16 0s-4.61 1.84-4.61 4.41c0 2.57 2.09 4.41 4.61 4.41zM13.96 28.85l6.72-11.87c-1.41-.83-3.07-1.33-4.86-1.33-1.79 0-3.45.5-4.86 1.33l6.72 11.87h.28zm-1.27-1.89l-5.12-9.04C8.47 16.02 9.99 15 11.71 15h8.58c1.72 0 3.24 1.02 4.14 2.92l-5.12 9.04h-7.62z"/>
                    </svg>
                    <span>ServePro</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item active">
                    <span class="nav-icon">👥</span>
                    <span>Users</span>
                </a>
                <a href="bookings.php" class="nav-item">
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
            <div class="users-header">
                <h1>👥 Users</h1>
                <p>Manage all users on the platform</p>
            </div>

            <!-- Statistics -->
            <div class="properties-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">👤</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #22C55E, #16A34A);">🧑</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['guests']; ?></h3>
                        <p>Guests</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #D4A574, #B8935F);">🏠</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['hosts']; ?></h3>
                        <p>Hosts</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">👑</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['admins']; ?></h3>
                        <p>Administrators</p>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="properties-table-container">
                <div class="table-header">
                    <h2>All Users</h2>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterUsers('all')">All</button>
                        <button class="filter-tab" onclick="filterUsers('guest')">Guests</button>
                        <button class="filter-tab" onclick="filterUsers('host')">Hosts</button>
                        <button class="filter-tab" onclick="filterUsers('admin')">Admins</button>
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
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
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

    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        function filterUsers(role) {
            const rows = document.querySelectorAll('.properties-table tbody tr');
            const buttons = document.querySelectorAll('.filter-tab');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            rows.forEach(row => {
                if (role === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.role === role ? '' : 'none';
                }
            });
        }

        function viewUser(id) {
            alert('User details view will be implemented. User ID: ' + id);
        }
    </script>
</body>
</html>
