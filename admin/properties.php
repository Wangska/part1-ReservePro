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

// Handle admin delete property (POST)
$delete_message = null;
$delete_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_property') {
    $property_id = (int) ($_POST['property_id'] ?? 0);
    if ($property_id > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE property_id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && $row['cnt'] > 0) {
            $delete_error = 'Cannot delete: this property has bookings. Cancel or complete them first.';
        } else {
            $stmt = $conn->prepare("DELETE FROM properties WHERE id = ?");
            $stmt->bind_param("i", $property_id);
            if ($stmt->execute()) {
                $delete_message = 'Property deleted successfully.';
            } else {
                $delete_error = 'Failed to delete property.';
            }
            $stmt->close();
        }
    }
    if ($delete_message || $delete_error) {
        $conn->close();
        header('Location: properties.php?' . ($delete_message ? 'deleted=1' : 'delete_error=1'));
        exit();
    }
}

// Get all properties with host information (limit to avoid huge pages and timeouts)
$query = "
    SELECT 
        p.*,
        u.first_name,
        u.last_name,
        u.email,
        COALESCE(
            (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
            (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
        ) as primary_photo,
        (SELECT COUNT(*) FROM bookings WHERE property_id = p.id) as total_bookings
    FROM properties p
    JOIN users u ON p.host_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 500
";
$result = $conn->query($query);
$properties = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [
    'total' => count($properties),
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0
];

foreach ($properties as $property) {
    $stats[$property['status']]++;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Properties - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=10.0">
    <style>
        .properties-header {
            /* Trendy gray header instead of brown */
            background: linear-gradient(135deg, #111827 0%, #1F2933 45%, #020617 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
            color: white;
        }

        .properties-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #FFFFFF !important;
        }

        .properties-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .properties-table-container {
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

        .filter-tabs {
            display: flex;
            gap: 8px;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #3A3A3A;
            background: transparent;
            color: #B8B8B8;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .filter-tab:hover {
            background: #2C2C2C;
            color: #D4A574;
            border-color: #D4A574;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border-color: transparent;
        }

        .properties-table {
            width: 100%;
            border-collapse: collapse;
        }

        .properties-table thead {
            background: #2C2C2C;
        }

        .properties-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #B8B8B8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #3A3A3A;
        }

        .properties-table td {
            padding: 20px;
            color: #E0E0E0;
            border-bottom: 1px solid #2C2C2C;
        }

        .properties-table tbody tr {
            transition: background 0.2s ease;
        }

        .properties-table tbody tr:hover {
            background: #2C2C2C;
        }

        .property-cell {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .property-image {
            width: 80px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #2C2C2C;
        }

        .property-info h3 {
            font-size: 16px;
            font-weight: 600;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .property-info p {
            font-size: 13px;
            color: #B8B8B8;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-view {
            background: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-view:hover {
            background: #3B82F6;
            color: #FFFFFF;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-delete:hover {
            background: #EF4444;
            color: #FFFFFF;
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
                <a href="properties.php" class="nav-item active">
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
            <div class="properties-header">
                <h1>🏠 All Properties</h1>
                <p>Manage all property listings across the platform</p>
            </div>

            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">Property deleted successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['delete_error']) && $_GET['delete_error'] == '1'): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">Cannot delete: this property has bookings. Cancel or complete them first.</div>
            <?php endif; ?>

            <!-- Hidden form for delete (submitted via JS) -->
            <form id="deletePropertyForm" method="POST" action="properties.php" style="display: none;">
                <input type="hidden" name="action" value="delete_property">
                <input type="hidden" name="property_id" id="deletePropertyId" value="">
            </form>

            <!-- Statistics -->
            <div class="properties-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">📊</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Properties</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #22C55E, #16A34A);">
                        <img src="../background%20image/p.webp" alt="Approved" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['approved']; ?></h3>
                        <p>Approved</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #FBBF24, #F59E0B);">
                        <img src="../background%20image/o.webp" alt="Pending Review" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">❌</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['rejected']; ?></h3>
                        <p>Rejected</p>
                    </div>
                </div>
            </div>

            <!-- Properties Table -->
            <div class="properties-table-container">
                <div class="table-header">
                    <h2>Property Listings</h2>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterProperties('all')">All</button>
                        <button class="filter-tab" onclick="filterProperties('approved')">Approved</button>
                        <button class="filter-tab" onclick="filterProperties('pending')">Pending</button>
                        <button class="filter-tab" onclick="filterProperties('rejected')">Rejected</button>
                    </div>
                </div>

                <?php if (empty($properties)): ?>
                    <div class="empty-state">
                        <h3>📭 No Properties Found</h3>
                        <p>There are no properties in the system yet.</p>
                    </div>
                <?php else: ?>
                    <table class="properties-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Host</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): ?>
                                <tr data-status="<?php echo $property['status']; ?>">
                                    <td>
                                        <div class="property-cell">
                                            <?php 
                                        $raw = $property['primary_photo'] ?? '';
                                        $img_src = '';
                                        if (!empty($raw) && strpos($raw, 'http') === 0) {
                                            $img_src = htmlspecialchars($raw);
                                        } elseif (!empty($raw)) {
                                            $img_src = htmlspecialchars('../' . ltrim($raw, '/'));
                                        } else {
                                            $img_src = 'https://via.placeholder.com/80x60?text=No+Image';
                                        }
                                    ?>
                                            <img src="<?php echo $img_src; ?>" 
                                                 alt="Property" class="property-image" loading="lazy" decoding="async" onerror="this.src='https://via.placeholder.com/80x60?text=No+Image'">
                                            <div class="property-info">
                                                <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                                <p><?php echo htmlspecialchars(substr($property['description'], 0, 50)) . '...'; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></td>
                                    <td class="amount">₱<?php echo number_format($property['price_per_night'], 0); ?>/night</td>
                                    <td><?php echo $property['total_bookings']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $property['status']; ?>">
                                            <?php echo ucfirst($property['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" onclick="viewProperty(<?php echo $property['id']; ?>)">View</button>
                                            <button class="btn-action btn-delete" onclick="deleteProperty(<?php echo $property['id']; ?>)">Delete</button>
                                        </div>
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
        function filterProperties(status) {
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

        function viewProperty(id) {
            window.location.href = 'view-property.php?id=' + id;
        }

        function deleteProperty(id) {
            if (confirm('Are you sure you want to delete this property? This action cannot be undone.')) {
                document.getElementById('deletePropertyId').value = id;
                document.getElementById('deletePropertyForm').submit();
            }
        }
    </script>
</body>
</html>
