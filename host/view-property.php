<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'host') {
    header('Location: ../home.php');
    exit();
}
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$property_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$property_id) {
    header('Location: properties.php');
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT p.*,
    COALESCE(
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
        (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
    ) as primary_photo
    FROM properties p
    WHERE p.id = ? AND p.host_id = ?
");
$stmt->bind_param("ii", $property_id, $user['id']);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    $conn->close();
    header('Location: properties.php?error=notfound');
    exit();
}

$stmt = $conn->prepare("SELECT photo_url, is_primary FROM property_photos WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property['photos'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT a.name, a.icon FROM amenities a
    JOIN property_amenities pa ON a.id = pa.amenity_id
    WHERE pa.property_id = ?
    ORDER BY a.name
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property['amenities'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$justUpdated = isset($_GET['updated']) && $_GET['updated'] === '1';
$needsApproval = isset($_GET['needs_approval']) && $_GET['needs_approval'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>View Property - <?php echo htmlspecialchars($property['title']); ?> - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .view-property-page { max-width: 900px; margin: 0 auto; padding: 24px; }
        .view-property-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        .view-property-header h1 { font-size: 24px; margin: 0 0 8px 0; color: #fff !important; }
        .view-property-header .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-view-back { padding: 10px 18px; background: #3B82F6; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .btn-view-back:hover { background: #2563EB; }
        .btn-edit-link { padding: 10px 18px; background: #6366F1; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .btn-edit-link:hover { background: #4F46E5; }
        .view-gallery { border-radius: 12px; overflow: hidden; margin-bottom: 24px; background: #1F1F1F; }
        .view-gallery img { width: 100%; max-height: 400px; object-fit: cover; display: block; }
        .view-section { background: var(--bg-secondary, #1A1A1A); border: 1px solid var(--border-color, #3A3A3A); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .view-section h2 { font-size: 16px; margin: 0 0 12px 0; color: #D4A574 !important; }
        .view-section p, .view-section .detail-row { color: #E0E0E0 !important; margin: 0 0 8px 0; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
        .detail-grid span { padding: 8px 12px; background: #2C2C2C; border-radius: 8px; font-size: 14px; color: #E0E0E0; }
        .amenities-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .amenities-list span { padding: 6px 12px; background: #2C2C2C; border-radius: 6px; font-size: 13px; color: #E0E0E0; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .status-approved { background: rgba(34, 197, 94, 0.2); color: #86efac; }
        .status-pending { background: rgba(234, 179, 8, 0.2); color: #fde047; }
        .status-rejected { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .status-out_of_order { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }

        .rp-approval-banner {
            border-radius: 16px;
            padding: 16px 16px;
            border: 1px solid rgba(245, 158, 11, 0.45);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.20), rgba(17, 24, 39, 0.75));
            box-shadow: 0 18px 45px rgba(0,0,0,0.22);
        }
        .rp-approval-banner .rp-approval-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .rp-approval-banner .rp-approval-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(245, 158, 11, 0.18);
            border: 1px solid rgba(245, 158, 11, 0.30);
            color: #FDE68A;
            flex: 0 0 auto;
        }
        .rp-approval-banner h2 {
            margin: 0 0 6px 0;
            font-size: 18px;
            color: #FDE68A !important;
            letter-spacing: -0.01em;
        }
        .rp-approval-banner p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #F1F5F9 !important;
            font-weight: 700;
        }
        .rp-approval-banner strong {
            color: #FFFFFF !important;
            font-weight: 900;
        }

        body.dashboard-page.light-mode .rp-approval-banner {
            border-color: rgba(180, 83, 9, 0.25);
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.22), rgba(255, 255, 255, 0.95));
            box-shadow: 0 14px 35px rgba(0,0,0,0.10);
        }
        body.dashboard-page.light-mode .rp-approval-banner h2 {
            color: #92400e !important;
        }
        body.dashboard-page.light-mode .rp-approval-banner p {
            color: #0f172a !important;
        }
        body.dashboard-page.light-mode .rp-approval-banner strong {
            color: #0f172a !important;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-detail-page">
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><span>Dashboard</span></a>
                <a href="properties.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>My Properties</span></a>
                <a href="add-property.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span><span>Add Property</span></a>
                <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span>Bookings</span></a>
                <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span><span>Earnings</span></a>
                <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span>Messages</span></a>
                <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span>View Site</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host</div>
                    </div>
                </div>
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="view-property-page">
                <?php if ($justUpdated && $needsApproval): ?>
                    <div class="rp-approval-banner">
                        <div class="rp-approval-row">
                            <div class="rp-approval-icon" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></div>
                            <div>
                                <h2>Submitted for admin approval</h2>
                                <p>Your changes were saved and this listing is now <strong>pending</strong> until an admin approves the updates.</p>
                            </div>
                        </div>
                    </div>
                <?php elseif ($justUpdated): ?>
                    <div class="view-section host-detail-shell" style="border-color: rgba(34,197,94,0.35); background: rgba(34,197,94,0.10);">
                        <h2 style="color:#86efac !important;">Updated</h2>
                        <p style="margin:0;">Your listing was updated successfully.</p>
                    </div>
                <?php endif; ?>

                <div class="view-property-header host-page-hero">
                    <div class="host-page-hero-content">
                        <span class="host-page-eyebrow">Property Overview</span>
                        <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                        <p class="subtitle"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                    </div>
                    <div class="actions" style="display: flex; align-items: center; gap: 12px; flex-wrap:wrap; margin-left:auto;">
                        <span class="status-badge status-<?php echo $property['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $property['status'])); ?></span>
                        <a href="properties.php" class="host-action-btn is-info">Back to list</a>
                        <a href="edit-property.php?id=<?php echo (int)$property['id']; ?>" class="host-action-btn is-primary">Edit</a>
                    </div>
                </div>

                <?php 
                $photos = $property['photos'];
                $main_photo = !empty($photos) ? $photos[0]['photo_url'] : ($property['primary_photo'] ?? null);
                if ($main_photo && strpos($main_photo, 'http') !== 0) {
                    $main_photo = '../' . ltrim($main_photo, '/');
                }
                if (!$main_photo) {
                    $main_photo = 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800';
                }
                ?>
                <div class="view-gallery">
                    <img id="host-main-photo" src="<?php echo htmlspecialchars($main_photo); ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800'">
                </div>
                <?php if (!empty($photos) && count($photos) > 1): ?>
                    <div class="view-section" style="margin-top: 12px;">
                        <h2>Photo gallery</h2>
                        <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;">
                            <?php foreach ($photos as $idx => $p): 
                                $thumb = $p['photo_url'];
                                if ($thumb && strpos($thumb, 'http') !== 0) {
                                    $thumb = '../' . ltrim($thumb, '/');
                                }
                            ?>
                                <div style="flex: 0 0 auto; border-radius: 8px; overflow: hidden; border: 2px solid <?php echo $idx === 0 ? '#D4A574' : 'transparent'; ?>; cursor: pointer;"
                                     onclick="document.getElementById('host-main-photo').src='<?php echo htmlspecialchars($thumb); ?>';">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Photo <?php echo $idx + 1; ?>" style="width: 120px; height: 80px; object-fit: cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="view-section host-detail-shell">
                    <h2>Description</h2>
                    <p style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                </div>

                <div class="view-section host-detail-shell">
                    <h2>Details</h2>
                    <div class="host-detail-grid">
                        <div class="host-detail-card"><span class="host-detail-label">Property Type</span><span class="host-detail-value"><?php echo htmlspecialchars(ucfirst($property['property_type'])); ?></span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Bedrooms</span><span class="host-detail-value"><?php echo (int)$property['bedrooms']; ?> beds</span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Bathrooms</span><span class="host-detail-value"><?php echo (int)$property['bathrooms']; ?> baths</span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Guests</span><span class="host-detail-value"><?php echo (int)$property['max_guests']; ?> guests</span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Nightly Rate</span><span class="host-detail-value">₱<?php echo number_format($property['price_per_night'], 0); ?>/night</span></div>
                    </div>
                </div>

                <div class="view-section host-detail-shell">
                    <h2>Address</h2>
                    <p><?php echo nl2br(htmlspecialchars($property['address'])); ?></p>
                    <p><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                </div>

                <?php if (!empty($property['amenities'])): ?>
                <div class="view-section host-detail-shell">
                    <h2>Amenities</h2>
                    <div class="amenities-list">
                        <?php foreach ($property['amenities'] as $a): ?>
                        <span><?php echo $a['icon'] ? $a['icon'] . ' ' : ''; ?><?php echo htmlspecialchars($a['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
</body>
</html>
