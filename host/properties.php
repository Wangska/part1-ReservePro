<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Get all host properties with photos
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT p.*,
    (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_photo
    FROM properties p
    WHERE p.host_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Properties - ServePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=13.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=13.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=13.0">
</head>
<body>
    <div class="host-layout">
        <!-- Sidebar (same as dashboard) -->
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
                <a href="properties.php" class="nav-item active">
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
                    <h1>My Properties 🏠</h1>
                    <p class="subtitle">Manage all your listings</p>
                </div>
                <!-- Theme Toggle -->
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
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
                    <?php foreach ($properties as $property): 
                        $photo_url = !empty($property['primary_photo']) ? htmlspecialchars($property['primary_photo']) : 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400';
                    ?>
                        <div class="property-card">
                            <div class="property-image">
                                <img src="<?php echo $photo_url; ?>" alt="Property" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400'">
                                <span class="status-badge status-<?php echo ucfirst($property['status']); ?>">
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
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>
