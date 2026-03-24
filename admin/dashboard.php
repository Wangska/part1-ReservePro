<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Check if user is admin (for now, you can manually set this in database)
// UPDATE users SET role='admin' WHERE id=1;

// Get pending properties
$conn = getDBConnection();
$result = $conn->query("
    SELECT p.*, u.first_name, u.last_name, u.email,
    COALESCE(
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
        (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
    ) as primary_photo
    FROM properties p
    JOIN users u ON p.host_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");
$pending_properties = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [];
$stats['total_properties'] = $conn->query("SELECT COUNT(*) as count FROM properties")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM properties WHERE status='pending'")->fetch_assoc()['count'];
$stats['approved'] = $conn->query("SELECT COUNT(*) as count FROM properties WHERE status='approved'")->fetch_assoc()['count'];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['total_bookings'] = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/asd.webp" type="image/webp">
    <title>Admin Dashboard - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
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
                    <span class="nav-icon">👑</span>
                    <span>Admin Panel</span>
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
            <div class="host-header">
                <h1>Admin Dashboard 👑</h1>
                <p class="subtitle">Manage ReservePro platform</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #6366F1, #4F46E5);">🏠</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_properties']; ?></h3>
                        <p>Total Properties</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-img-wrap" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <img src="../background%20image/o.webp" alt="Pending Review" class="stat-icon-img">
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">👥</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10B981, #059669);">📅</div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                </div>
            </div>

            <!-- Pending Reviews -->
            <div class="section-header">
                <h2>Pending Property Reviews</h2>
                <span class="badge badge-pending"><?php echo count($pending_properties); ?> pending</span>
            </div>

            <?php if (empty($pending_properties)): ?>
                <div class="empty-state">
                    <span class="empty-icon">✅</span>
                    <h3>All caught up!</h3>
                    <p>No properties pending review</p>
                </div>
            <?php else: ?>
                <div class="review-list">
                    <?php foreach ($pending_properties as $property): 
                        $raw_photo = $property['primary_photo'] ?? '';
                        if (!empty($raw_photo) && strpos($raw_photo, 'http') !== 0) {
                            $photo_url = htmlspecialchars('../' . ltrim($raw_photo, '/'));
                        } elseif (!empty($raw_photo)) {
                            $photo_url = htmlspecialchars($raw_photo);
                        } else {
                            // Clear "no photo" placeholder so it's obvious the host hasn't uploaded images yet
                            $photo_url = 'https://via.placeholder.com/400x260?text=No+Photo';
                        }
                    ?>
                        <div class="review-card">
                            <div class="review-image">
                                <img src="<?php echo $photo_url; ?>" alt="Property" onerror="this.src='https://via.placeholder.com/400x260?text=No+Photo'">
                            </div>
                            <div class="review-content">
                                <div class="review-header">
                                    <div>
                                        <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                        <p class="review-host">Host: <?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></p>
                                        <p class="review-location">📍 <?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                                    </div>
                                    <div class="review-price">
                                        <strong>₱<?php echo number_format($property['price_per_night'], 2); ?></strong>
                                        <span>/night</span>
                                    </div>
                                </div>
                                
                                <p class="review-description"><?php echo htmlspecialchars(substr($property['description'], 0, 200)) . '...'; ?></p>
                                
                                <div class="review-details">
                                    <span>🏠 <?php echo ucfirst($property['property_type']); ?></span>
                                    <span>🛏️ <?php echo $property['bedrooms']; ?> beds</span>
                                    <span>🚿 <?php echo $property['bathrooms']; ?> baths</span>
                                    <span>👥 <?php echo $property['max_guests']; ?> guests</span>
                                </div>
                                
                                <div class="review-actions">
                                    <form method="POST" action="review-property.php" style="display: inline;">
                                        <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">✓ Approve</button>
                                    </form>
                                    <form method="POST" action="review-property.php" style="display: inline;">
                                        <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject">✗ Reject</button>
                                    </form>
                                    <a href="view-property.php?id=<?php echo $property['id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>
