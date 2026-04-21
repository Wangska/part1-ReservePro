<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

// Ensure user is an admin
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

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
        (SELECT COUNT(*) FROM bookings WHERE property_id = p.id) as total_bookings,
        (SELECT created_at FROM property_edit_logs WHERE property_id = p.id ORDER BY id DESC LIMIT 1) as last_edited_at
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
    'rejected' => 0,
    'out_of_order' => 0,
    'archived' => 0
];

foreach ($properties as $property) {
    $status = $property['status'];
    if (isset($stats[$status])) {
        $stats[$status]++;
    } else {
        $stats[$status] = 1;
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
    <title>All Properties - Admin - ReservePro</title>
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
        .admin-properties-page .properties-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .admin-properties-page .properties-table-container {
            width: 100%;
            box-sizing: border-box;
        }
        .admin-properties-page .properties-table th:nth-child(1),
        .admin-properties-page .properties-table td:nth-child(1) { width: 240px; }
        .admin-properties-page .properties-table th:nth-child(2),
        .admin-properties-page .properties-table td:nth-child(2) { width: 130px; text-align: center; }
        .admin-properties-page .properties-table th:nth-child(3),
        .admin-properties-page .properties-table td:nth-child(3) { width: 150px; text-align: center; }
        .admin-properties-page .properties-table th:nth-child(4),
        .admin-properties-page .properties-table td:nth-child(4) { width: 100px; text-align: center; }
        .admin-properties-page .properties-table th:nth-child(5),
        .admin-properties-page .properties-table td:nth-child(5) { width: 80px; text-align: center; }
        .admin-properties-page .properties-table th:nth-child(6),
        .admin-properties-page .properties-table td:nth-child(6) { width: 100px; text-align: center; }
        .admin-properties-page .properties-table th:nth-child(7),
        .admin-properties-page .properties-table td:nth-child(7) { width: 140px; text-align: center; }
        .admin-properties-page .properties-table th:nth-child(8),
        .admin-properties-page .properties-table td:nth-child(8) { width: 120px; text-align: center; }
        .property-cell {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        /* Use a unique class name so it won't clash with host-dashboard.css (.property-image) */
        .property-thumb {
            flex-shrink: 0;
            width: 80px;
            height: 60px;
            min-width: 80px;
            max-width: 80px;
            border-radius: 8px;
            object-fit: cover;
            object-position: center;
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

        .admin-properties-page .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .admin-properties-page .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .admin-properties-page .status-approved {
            background: rgba(34,197,94,0.1);
            color: #86EFAC !important;
            border-color: rgba(34,197,94,0.22);
        }
        .admin-properties-page .status-approved::before { background: #22C55E; }
        .admin-properties-page .status-pending {
            background: rgba(234,179,8,0.1);
            color: #FDE047 !important;
            border-color: rgba(234,179,8,0.22);
        }
        .admin-properties-page .status-pending::before { background: #EAB308; }
        .admin-properties-page .status-rejected {
            background: rgba(244,63,94,0.1);
            color: #FDA4AF !important;
            border-color: rgba(244,63,94,0.22);
        }
        .admin-properties-page .status-rejected::before { background: #F43F5E; }
        .admin-properties-page .status-out_of_order {
            background: rgba(148,163,184,0.1);
            color: #94A3B8 !important;
            border-color: rgba(148,163,184,0.22);
        }
        .admin-properties-page .status-out_of_order::before { background: #94A3B8; }
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            background: transparent;
            color: #D4A574;
            border: 1px solid rgba(212,165,116,0.32);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border-color: transparent;
            box-shadow: 0 8px 20px rgba(212,165,116,0.22);
        }

        .btn-delete {
            background: transparent;
            color: #FDA4AF;
            border: 1px solid rgba(244,63,94,0.28);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #F43F5E, #E11D48);
            color: #FFFFFF;
            border-color: transparent;
            box-shadow: 0 8px 20px rgba(244,63,94,0.22);
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

                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <?php require __DIR__ . '/../includes/notifications-widget.php'; ?>
            <div class="properties-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <h1>All Properties</h1>
                    <p></p>
                </div>
                <!-- admin-page-summary removed -->
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
                <div class="admin-metric-card">
                    <div class="admin-metric-icon is-sky"><i class="fa-solid fa-building" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <p>Total Properties</p>
                        <h3><?php echo $stats['total']; ?></h3>
                        <span class="admin-metric-note"></span>
                    </div>
                </div>
                <div class="admin-metric-card">
                    <div class="admin-metric-icon is-emerald"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <p>Approved</p>
                        <h3><?php echo $stats['approved']; ?></h3>
                        <span class="admin-metric-note"></span>
                    </div>
                </div>
                <div class="admin-metric-card">
                    <div class="admin-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <p>Pending Review</p>
                        <h3><?php echo $stats['pending']; ?></h3>
                        <span class="admin-metric-note"></span>
                    </div>
                </div>
                <div class="admin-metric-card">
                    <div class="admin-metric-icon is-red"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <p>Rejected</p>
                        <h3><?php echo $stats['rejected']; ?></h3>
                        <span class="admin-metric-note"></span>
                    </div>
                </div>
            </div>

            <!-- Properties Table -->
            <div class="properties-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>Property Listings</h2>
                        <p></p>
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
                    <div style="overflow-x: auto; width: 100%;">
                    <table class="properties-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Host</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Edited</th>
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
                                                 alt="Property" class="property-thumb" loading="lazy" decoding="async" onerror="this.src='https://via.placeholder.com/80x60?text=No+Image'">
                                            <div class="property-info">
                                                <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></td>
                                    <td class="amount">₱<?php echo number_format($property['price_per_night'], 0); ?></td>
                                    <td><?php echo $property['total_bookings']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $property['status']; ?>">
                                            <?php echo ucfirst($property['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($property['last_edited_at'])): ?>
                                            <span style="display:flex;flex-direction:column;align-items:center;gap:3px;">
                                                <span title="Last edited: <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($property['last_edited_at']))); ?>" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:8px;background:rgba(245,158,11,0.13);border:1px solid rgba(245,158,11,0.22);color:#F59E0B;font-weight:800;font-size:12px;line-height:1.1;">
                                                    <span>Edited</span>
                                                </span>
                                                <span style="color:#FBBF24;font-weight:700;font-size:12px;letter-spacing:0.01em;" title="<?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($property['last_edited_at']))); ?>">
                                                    <?php echo date('M j, Y', strtotime($property['last_edited_at'])); ?>
                                                </span>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#64748B; font-weight:800; font-size:13px;opacity:0.7;">—</span>
                                        <?php endif; ?>
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
                    </div>
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
