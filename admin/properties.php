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
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>All Properties - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.2">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.5">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
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
<body class="dashboard-page admin-page admin-clean-page admin-properties-page">
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
                <a href="properties.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item">
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
            <div class="properties-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <span class="admin-page-eyebrow">Listing Management</span>
                    <h1>All Properties</h1>
                    <p>Review inventory, track approval status, and manage listing quality across the platform from one place.</p>
                </div>
                <div class="admin-page-summary">
                    <span class="admin-page-summary-label">Live Inventory</span>
                    <strong><?php echo $stats['approved']; ?></strong>
                    <span class="admin-page-summary-text">approved listings currently available</span>
                </div>
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
            <div class="properties-stats admin-metric-grid">
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-sky"><i class="fa-solid fa-building" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Total Properties</p>
                        <h3><?php echo $stats['total']; ?></h3>
                        <span class="admin-metric-note">Every listing in the catalog, regardless of status.</span>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-emerald"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Approved</p>
                        <h3><?php echo $stats['approved']; ?></h3>
                        <span class="admin-metric-note">Listings that are visible and bookable.</span>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Pending Review</p>
                        <h3><?php echo $stats['pending']; ?></h3>
                        <span class="admin-metric-note">Submissions still waiting for moderation.</span>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-red"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Rejected</p>
                        <h3><?php echo $stats['rejected']; ?></h3>
                        <span class="admin-metric-note">Listings that need changes before resubmission.</span>
                    </div>
                </div>
            </div>

            <!-- Properties Table -->
            <div class="properties-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>Property Listings</h2>
                        <p>Filter by approval status to focus on the listings that need action.</p>
                    </div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" onclick="filterProperties('all', this)">All</button>
                        <button type="button" class="filter-tab" onclick="filterProperties('approved', this)">Approved</button>
                        <button type="button" class="filter-tab" onclick="filterProperties('pending', this)">Pending</button>
                        <button type="button" class="filter-tab" onclick="filterProperties('rejected', this)">Rejected</button>
                    </div>
                </div>

                <?php if (empty($properties)): ?>
                    <div class="empty-state admin-empty-state">
                        <span class="admin-empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                        <h3>No Properties Found</h3>
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

    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        function filterProperties(status, el) {
            const rows = document.querySelectorAll('.properties-table tbody tr');
            const buttons = document.querySelectorAll('.properties-table-container .filter-tab');
            buttons.forEach(btn => btn.classList.remove('active'));
            if (el) el.classList.add('active');
            
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
