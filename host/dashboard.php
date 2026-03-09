<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

// Only hosts may see the host dashboard; guests and others are redirected
if (!$user || !isset($user['role']) || $user['role'] !== 'host') {
    header('Location: ' . (isset($user['role']) && $user['role'] === 'admin' ? '../admin/dashboard.php' : '../dashboard.php'));
    exit();
}

// Hosts must complete verification before accessing dashboard
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

// Get host properties
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT p.*,
    COALESCE(
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
        (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
    ) as primary_photo
    FROM properties p
    WHERE p.host_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM properties WHERE host_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats['total_listings'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as approved FROM properties WHERE host_id = ? AND status = 'approved'");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats['approved'] = $stmt->get_result()->fetch_assoc()['approved'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as pending FROM properties WHERE host_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats['pending'] = $stmt->get_result()->fetch_assoc()['pending'];
$stmt->close();

// Get recent bookings
$stmt = $conn->prepare("
    SELECT b.*, p.title as property_title, u.first_name, u.last_name, u.email
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users u ON b.guest_id = u.id
    WHERE p.host_id = ?
    ORDER BY b.booking_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host Dashboard - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=25.0">
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
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item">
                    <span class="nav-icon">➕</span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon">📅</span>
                    <span>Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon">💰</span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon">💬</span>
                    <span>Messages</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon">🌐</span>
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
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="host-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h1>
                    <p class="subtitle">Manage your properties and bookings</p>
                </div>
                <!-- Theme Toggle -->
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>

            <!-- Stats Cards (clickable, redirect to relevant page) -->
            <div class="stats-grid">
                <a href="properties.php" class="stat-card stat-card-link" title="View all listings">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #6366F1, #4F46E5);">
                        <img src="../assets/images/home-icon.png" alt="Listings" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_listings']; ?></h3>
                        <p>Total Listings</p>
                    </div>
                </a>
                
                <a href="properties.php" class="stat-card stat-card-link" title="View approved properties">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #10B981, #059669);">
                        <img src="../background%20image/p.webp" alt="Approved" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['approved']; ?></h3>
                        <p>Approved</p>
                    </div>
                </a>
                
                <a href="properties.php" class="stat-card stat-card-link" title="View pending properties">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <img src="../background%20image/o.webp" alt="Pending Review" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </a>
                
                <a href="bookings.php" class="stat-card stat-card-link" title="View bookings">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                        <img src="../background%20image/u.webp" alt="Bookings" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($bookings); ?></h3>
                        <p>Recent Bookings</p>
                    </div>
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <a href="add-property.php" class="action-card">
                        <span class="action-icon">
                            <img src="../background%20image/y.webp" alt="Add property" style="width:32px; height:32px; border-radius:6px; object-fit:cover;">
                        </span>
                        <h3>Add New Property</h3>
                        <p>List a new place for guests</p>
                    </a>
                    <a href="properties.php" class="action-card">
                        <span class="action-icon">
                            <img src="../background%20image/i.webp" alt="Manage listings" style="width:32px; height:32px; border-radius:6px; object-fit:cover;">
                        </span>
                        <h3>Manage Listings</h3>
                        <p>Edit your properties</p>
                    </a>
                    <a href="bookings.php" class="action-card">
                        <span class="action-icon">
                            <img src="../background%20image/u.webp" alt="View bookings" style="width:32px; height:32px; border-radius:6px; object-fit:cover;">
                        </span>
                        <h3>View Bookings</h3>
                        <p>Check reservations</p>
                    </a>
                </div>
            </div>

            <!-- Properties List -->
            <div class="properties-section">
                <div class="section-header">
                    <h2>Your Properties</h2>
                    <a href="properties.php" class="view-all">View All →</a>
                </div>
                
                <?php if (empty($properties)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🏠</span>
                        <h3>No properties yet</h3>
                        <p>Start hosting by adding your first property</p>
                        <a href="add-property.php" class="btn-primary">Add Property</a>
                    </div>
                <?php else: ?>
                    <div class="properties-grid">
                        <?php foreach (array_slice($properties, 0, 3) as $property): 
                            $raw_photo = $property['primary_photo'] ?? '';
                            if (!empty($raw_photo) && strpos($raw_photo, 'http') !== 0) {
                                $photo_url = htmlspecialchars('../' . ltrim($raw_photo, '/'));
                            } else {
                                $photo_url = !empty($raw_photo) ? htmlspecialchars($raw_photo) : 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400';
                            }
                        ?>
                            <div class="property-card">
                                <div class="property-image">
                                    <img src="<?php echo $photo_url; ?>" alt="Property" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400'">
                                    <span class="status-badge status-<?php echo $property['status']; ?>">
                                        <?php echo ucfirst($property['status']); ?>
                                    </span>
                                </div>
                                <div class="property-info">
                                    <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                    <p class="property-location">📍 <?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                                    <div class="property-details">
                                        <span>🛏️ <?php echo $property['bedrooms']; ?> beds</span>
                                        <span>🚿 <?php echo $property['bathrooms']; ?> baths</span>
                                        <span>👥 <?php echo $property['max_guests']; ?> guests</span>
                                    </div>
                                    <div class="property-footer">
                                        <span class="price">₱<?php echo number_format($property['price_per_night'], 2); ?>/night</span>
                                        <a href="edit-property.php?id=<?php echo $property['id']; ?>" class="btn-edit">Edit</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Bookings -->
            <?php if (!empty($bookings)): ?>
            <div class="bookings-section">
                <div class="section-header">
                    <h2>Recent Bookings</h2>
                    <a href="bookings.php" class="view-all">View All →</a>
                </div>
                
                <div class="bookings-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Property</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <div class="guest-info">
                                        <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($booking['property_title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_in'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['check_out'])); ?></td>
                                <td><strong>₱<?php echo number_format($booking['total_price'], 2); ?></strong></td>
                                <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>
