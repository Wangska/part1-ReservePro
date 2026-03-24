<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/geocode-property.php';

requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$message = null;
$error = null;
$updated = 0;
$skipped = 0;
$run = isset($_POST['run_geocode']) && $_POST['run_geocode'] === '1';

if ($run) {
    set_time_limit(300);
    $conn = getDBConnection();
    $res = $conn->query("SELECT id, address, city, country, latitude, longitude FROM properties ORDER BY id");
    $properties = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt = $conn->prepare("UPDATE properties SET latitude = ?, longitude = ? WHERE id = ?");

    foreach ($properties as $p) {
        if (!property_needs_geocode($p)) {
            $skipped++;
            continue;
        }
        $coords = geocode_property($p);
        if ($coords) {
            $stmt->bind_param("ddi", $coords['lat'], $coords['lng'], $p['id']);
            if ($stmt->execute()) {
                $updated++;
            }
        }
        // Nominatim usage policy: max 1 request per second
        sleep(1);
    }
    $stmt->close();
    $conn->close();
    $message = "Map coordinates updated for {$updated} propert" . ($updated === 1 ? 'y' : 'ies') . ". {$skipped} already had correct coordinates.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/asd.webp" type="image/webp">
    <title>Update map coordinates - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=13.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=13.0">
    <link rel="stylesheet" href="../assets/css/admin.css?v=13.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=13.0">
</head>
<body class="dashboard-page">
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">👑</span><span>Admin Panel</span></a>
                <a href="host-verifications.php" class="nav-item"><span class="nav-icon">✅</span><span>Host Verifications</span></a>
                <a href="properties.php" class="nav-item"><span class="nav-icon">🏠</span><span>All Properties</span></a>
                <a href="users.php" class="nav-item"><span class="nav-icon">👥</span><span>Users</span></a>
                <a href="bookings.php" class="nav-item"><span class="nav-icon">📅</span><span>All Bookings</span></a>
                <a href="../home.php" class="nav-item"><span class="nav-icon">🌐</span><span>View Site</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <div class="theme-toggle" style="margin-bottom: 12px;"><span class="theme-toggle-icon">☀️</span><span class="theme-toggle-text">Light</span></div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>
        <main class="host-main">
            <div class="host-header">
                <h1>📍 Update map coordinates</h1>
                <p class="subtitle">Set correct pin locations for all listed properties (Lapu-Lapu/Cebu vs Manila, etc.)</p>
            </div>
            <?php if ($message): ?>
                <div class="admin-message" style="padding: 16px; background: #d1fae5; color: #065f46; border-radius: 8px; margin-bottom: 24px;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="admin-error" style="padding: 16px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 24px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card" style="max-width: 560px;">
                <p style="margin-bottom: 16px;">This will geocode every property that has missing or wrong coordinates (e.g. Lapu-Lapu City showing in Manila) and save the correct latitude/longitude. Properties that already have correct coordinates are skipped. The same region-aware logic used on the property modal is applied here.</p>
                <p style="margin-bottom: 20px; color: #6b7280; font-size: 14px;">Nominatim is called once per property that needs an update (about 1 request per second). This may take a minute if you have many properties.</p>
                <form method="post">
                    <input type="hidden" name="run_geocode" value="1">
                    <button type="submit" class="nav-btn" style="padding: 12px 24px;">Update map coordinates for all listed properties</button>
                </form>
            </div>
        </main>
    </div>
    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>
