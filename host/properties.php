<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Hosts must complete verification before managing properties
if ($user && $user['role'] === 'host' && empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$conn = getDBConnection();

// Handle host actions: availability, auto-accept, delete
$action_message = null;
$action_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = intval($_POST['property_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($property_id > 0 && $action && $user) {
        // Ensure property belongs to current host
        $stmt = $conn->prepare("SELECT id, status FROM properties WHERE id = ? AND host_id = ?");
        $stmt->bind_param("ii", $property_id, $user['id']);
        $stmt->execute();
        $propResult = $stmt->get_result();
        $propertyRow = $propResult->fetch_assoc();
        $stmt->close();

        if (!$propertyRow) {
            $action_error = "You are not allowed to modify this property.";
        } else {
            if ($action === 'update_availability') {
                $new_status = $_POST['new_status'] ?? '';
                if (in_array($new_status, ['approved', 'out_of_order'], true)) {
                    // Only allow toggling between approved and out_of_order
                    $stmt = $conn->prepare("UPDATE properties SET status = ? WHERE id = ? AND host_id = ?");
                    $stmt->bind_param("sii", $new_status, $property_id, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $action_message = $new_status === 'out_of_order'
                        ? "Property marked as Out of Order. Guests will no longer see it."
                        : "Property marked as Available. Guests can see and book it again.";
                }
            } elseif ($action === 'toggle_auto_accept') {
                $new_value = intval($_POST['new_value'] ?? 0) ? 1 : 0;
                $stmt = $conn->prepare("UPDATE properties SET auto_accept_bookings = ? WHERE id = ? AND host_id = ?");
                $stmt->bind_param("iii", $new_value, $property_id, $user['id']);
                $stmt->execute();
                $stmt->close();
                $action_message = $new_value
                    ? "Auto-accept enabled. New bookings for this property will be auto-confirmed."
                    : "Auto-accept disabled. You will manually review bookings.";
            } elseif ($action === 'delete_property') {
                // Deletion rules:
                // - Only owner can delete (already enforced above)
                // - Cannot delete if there are any bookings for this property
                $stmt = $conn->prepare("SELECT COUNT(*) AS booking_count FROM bookings WHERE property_id = ?");
                $stmt->bind_param("i", $property_id);
                $stmt->execute();
                $countResult = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($countResult && $countResult['booking_count'] > 0) {
                    $action_error = "This property has bookings and cannot be deleted. Settle/cancel bookings instead.";
                } else {
                    // Safe to delete property (related rows cascade via FK)
                    $stmt = $conn->prepare("DELETE FROM properties WHERE id = ? AND host_id = ?");
                    $stmt->bind_param("ii", $property_id, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $action_message = "Property deleted successfully.";
                }
            }
        }
    }
}

// Get all host properties with photos (after any updates)
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
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>My Properties - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=13.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.1">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=13.0">
</head>
<body class="dashboard-page">
    <div class="host-layout">
        <!-- Sidebar (same as dashboard) -->
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

            <?php if ($action_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($action_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($action_error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($action_error); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'notfound'): ?>
                <div class="alert alert-error">Property not found or you do not have permission to view it.</div>
            <?php endif; ?>

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
                                <div class="property-footer" style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                        <span class="price">₱<?php echo number_format($property['price_per_night'], 2); ?>/night</span>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="view-property.php?id=<?php echo (int)$property['id']; ?>" class="btn-view-property" style="display: inline-block; padding: 8px 16px; background: #3B82F6; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px;">View</a>
                                            <form method="POST" action="properties.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this property? This cannot be undone.');">
                                                <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                                <input type="hidden" name="action" value="delete_property">
                                                <button type="submit" class="btn-delete-property" style="padding: 8px 16px; background: #EF4444; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <a href="edit-property.php?id=<?php echo (int)$property['id']; ?>" class="btn-edit">Edit</a>
                                    </div>

                                    <?php if (in_array($property['status'], ['approved', 'out_of_order'])): ?>
                                    <div class="property-meta-row">
                                        <form method="POST" action="properties.php" style="display: inline-flex; align-items: center; gap: 8px;">
                                            <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                            <input type="hidden" name="action" value="update_availability">
                                            <?php if ($property['status'] === 'out_of_order'): ?>
                                                <input type="hidden" name="new_status" value="approved">
                                                <span class="badge badge-warning">Out of Order (hidden from guests)</span>
                                                <button type="submit" class="btn-small">Mark Available</button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="out_of_order">
                                                <span class="badge badge-success">Available</span>
                                                <button type="submit" class="btn-small btn-outline">Mark Out of Order</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <div class="property-meta-row">
                                            <span class="badge badge-neutral">Status: <?php echo ucfirst($property['status']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="property-meta-row" style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                        <form method="POST" action="properties.php" style="display: inline-flex; align-items: center; gap: 8px;">
                                            <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_auto_accept">
                                            <input type="hidden" name="new_value" value="<?php echo $property['auto_accept_bookings'] ? 0 : 1; ?>">
                                            <span class="badge <?php echo $property['auto_accept_bookings'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                Auto-accept: <?php echo $property['auto_accept_bookings'] ? 'On' : 'Off'; ?>
                                            </span>
                                            <button type="submit" class="btn-small btn-outline">
                                                <?php echo $property['auto_accept_bookings'] ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </div>
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
