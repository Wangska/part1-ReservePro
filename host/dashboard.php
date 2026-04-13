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

$stmt = $conn->prepare("SELECT COUNT(*) as total_bookings FROM bookings b JOIN properties p ON b.property_id = p.id WHERE p.host_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$stats['total_bookings'] = $stmt->get_result()->fetch_assoc()['total_bookings'];
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
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Host Dashboard - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .host-dashboard-page .host-main {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.18) 0%, rgba(15, 15, 15, 0) 260px);
        }

        .host-dashboard-page .dashboard-hero {
            align-items: stretch;
            gap: 20px;
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 24px;
            padding: 28px 30px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.28);
            margin-bottom: 28px;
        }

        .host-dashboard-page .dashboard-eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(212, 165, 116, 0.14);
            color: #f3d9b4;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .host-dashboard-page .dashboard-hero h1 {
            margin-bottom: 10px;
        }

        .host-dashboard-page .dashboard-hero .subtitle {
            max-width: 680px;
            line-height: 1.6;
            color: #cbd5e1;
        }

        .host-dashboard-page .dashboard-summary-card {
            min-width: 240px;
            margin-left: auto;
            padding: 22px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
        }

        .host-dashboard-page .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            font-weight: 700;
        }

        .host-dashboard-page .dashboard-summary-card strong {
            font-size: 36px;
            line-height: 1;
            color: #ffffff;
        }

        .host-dashboard-page .summary-text {
            font-size: 14px;
            color: #cbd5e1;
        }

        .host-dashboard-page .stats-grid {
            gap: 18px;
            margin-bottom: 30px;
        }

        .host-dashboard-page .stat-card {
            padding: 22px;
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .host-dashboard-page .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 24px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(212, 165, 116, 0.3);
        }

        .host-dashboard-page .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #e2e8f0;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.14);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .host-dashboard-page .stat-icon i {
            font-size: 20px;
        }

        .host-dashboard-page .stat-icon-indigo { color: #c7d2fe; }
        .host-dashboard-page .stat-icon-emerald { color: #a7f3d0; }
        .host-dashboard-page .stat-icon-amber { color: #fde68a; }
        .host-dashboard-page .stat-icon-sky { color: #bae6fd; }

        .host-dashboard-page .stat-content p {
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #94a3b8;
        }

        .host-dashboard-page .stat-content h3 {
            margin-bottom: 6px;
            font-size: 30px;
        }

        .host-dashboard-page .stat-meta {
            display: block;
            font-size: 13px;
            line-height: 1.5;
            color: #cbd5e1;
        }

        .host-dashboard-page .dashboard-section-header {
            margin-top: 6px;
            margin-bottom: 18px;
            align-items: flex-end;
        }

        .host-dashboard-page .dashboard-section-header h2 {
            margin-bottom: 6px;
            color: #ffffff;
        }

        .host-dashboard-page .dashboard-section-header p {
            margin: 0;
            color: #94a3b8;
            font-size: 14px;
        }

        .host-dashboard-page .badge-neutral {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.16);
            color: #cbd5e1 !important;
        }

        .host-dashboard-page .quick-actions {
            margin-bottom: 34px;
        }

        .host-dashboard-page .quick-actions h2 {
            margin-bottom: 6px;
        }

        .host-dashboard-page .quick-actions-copy {
            margin: 0 0 18px;
            color: #94a3b8;
            font-size: 14px;
        }

        .host-dashboard-page .action-card {
            padding: 24px;
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .host-dashboard-page .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 24px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(212, 165, 116, 0.3);
        }

        .host-dashboard-page .action-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 20px;
            color: #e2e8f0;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.14);
        }

        .host-dashboard-page .action-card h3 {
            margin-bottom: 8px;
        }

        .host-dashboard-page .action-card p {
            color: #cbd5e1;
            line-height: 1.6;
        }

        .host-dashboard-page .empty-state {
            padding: 52px 36px;
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.16);
        }

        .host-dashboard-page .empty-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #7dd3fc;
            background: rgba(125, 211, 252, 0.12);
        }

        .host-dashboard-page .properties-grid {
            gap: 18px;
        }

        .host-dashboard-page .property-card {
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .host-dashboard-page .property-image {
            height: 210px;
        }

        .host-dashboard-page .property-info {
            padding: 18px;
        }

        .host-dashboard-page .property-info h3 {
            margin-bottom: 8px;
        }

        .host-dashboard-page .property-location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #cbd5e1;
        }

        .host-dashboard-page .property-location i {
            color: #fbbf24;
        }

        .host-dashboard-page .property-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }

        .host-dashboard-page .property-detail-item {
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .host-dashboard-page .property-detail-label {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .host-dashboard-page .property-detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
        }

        .host-dashboard-page .price {
            color: #ffffff;
        }

        .host-dashboard-page .price small {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
        }

        .host-dashboard-page .btn-edit {
            border-radius: 12px;
            padding: 10px 18px;
        }

        .host-dashboard-page .bookings-table {
            border-radius: 20px;
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            overflow: hidden;
        }

        .host-dashboard-page .bookings-table thead {
            background: rgba(255, 255, 255, 0.04);
        }

        .host-dashboard-page .bookings-table th {
            color: #94a3b8;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .host-dashboard-page .bookings-table td {
            color: #ffffff;
            border-top-color: rgba(148, 163, 184, 0.12);
        }

        body.light-mode.host-dashboard-page .host-main {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.9) 0%, rgba(248, 250, 252, 0) 260px);
        }

        body.light-mode.host-dashboard-page .dashboard-hero,
        body.light-mode.host-dashboard-page .stat-card,
        body.light-mode.host-dashboard-page .action-card,
        body.light-mode.host-dashboard-page .empty-state,
        body.light-mode.host-dashboard-page .property-card,
        body.light-mode.host-dashboard-page .bookings-table {
            background: #ffffff;
            border-color: rgba(15, 23, 42, 0.08);
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
        }

        body.light-mode.host-dashboard-page .dashboard-eyebrow {
            background: rgba(184, 147, 95, 0.12);
            color: #8b6f47;
        }

        body.light-mode.host-dashboard-page .dashboard-hero .subtitle,
        body.light-mode.host-dashboard-page .summary-text,
        body.light-mode.host-dashboard-page .stat-meta,
        body.light-mode.host-dashboard-page .dashboard-section-header p,
        body.light-mode.host-dashboard-page .quick-actions-copy,
        body.light-mode.host-dashboard-page .action-card p,
        body.light-mode.host-dashboard-page .property-location,
        body.light-mode.host-dashboard-page .guest-info small {
            color: #475569 !important;
        }

        body.light-mode.host-dashboard-page .summary-label,
        body.light-mode.host-dashboard-page .stat-content p,
        body.light-mode.host-dashboard-page .property-detail-label,
        body.light-mode.host-dashboard-page .bookings-table th {
            color: #64748b !important;
        }

        body.light-mode.host-dashboard-page .dashboard-summary-card {
            background: #f8fafc;
            border-color: rgba(15, 23, 42, 0.08);
        }

        body.light-mode.host-dashboard-page .stat-icon,
        body.light-mode.host-dashboard-page .action-icon,
        body.light-mode.host-dashboard-page .property-detail-item {
            background: #f8fafc;
            border-color: rgba(15, 23, 42, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        body.light-mode.host-dashboard-page .stat-icon-indigo { color: #4f46e5; }
        body.light-mode.host-dashboard-page .stat-icon-emerald { color: #047857; }
        body.light-mode.host-dashboard-page .stat-icon-amber { color: #b45309; }
        body.light-mode.host-dashboard-page .stat-icon-sky { color: #0369a1; }

        body.light-mode.host-dashboard-page .dashboard-summary-card strong,
        body.light-mode.host-dashboard-page .dashboard-section-header h2,
        body.light-mode.host-dashboard-page .property-detail-value,
        body.light-mode.host-dashboard-page .guest-info strong,
        body.light-mode.host-dashboard-page .price,
        body.light-mode.host-dashboard-page .bookings-table td,
        body.light-mode.host-dashboard-page .property-info h3,
        body.light-mode.host-dashboard-page .action-card h3 {
            color: #0f172a;
        }

        @media (max-width: 1024px) {
            .host-dashboard-page .dashboard-hero {
                flex-direction: column;
            }

            .host-dashboard-page .dashboard-summary-card {
                margin-left: 0;
                min-width: 0;
            }
        }

        @media (max-width: 768px) {
            .host-dashboard-page .host-main {
                padding: 22px 18px 36px;
            }

            .host-dashboard-page .dashboard-hero,
            .host-dashboard-page .stat-card,
            .host-dashboard-page .action-card,
            .host-dashboard-page .empty-state,
            .host-dashboard-page .property-card {
                border-radius: 18px;
            }

            .host-dashboard-page .property-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-page host-dashboard-page">
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
                    <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                    <span>Dashboard</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
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

                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="host-header dashboard-hero">
                <div>
                    <span class="dashboard-eyebrow">Host Overview</span>
                    <h1>Host Dashboard</h1>
                    <p class="subtitle">Track your listings, see what needs attention, and keep bookings moving from one place.</p>
                </div>
                <div class="dashboard-summary-card">
                    <span class="summary-label">Needs Attention</span>
                    <strong><?php echo $stats['pending']; ?></strong>
                    <span class="summary-text">listings currently waiting for review</span>
                </div>
            </div>

            <div class="stats-grid">
                <a href="properties.php" class="stat-card stat-card-link" title="View all listings">
                    <div class="stat-icon stat-icon-indigo"><i class="fa-solid fa-house" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Total Listings</p>
                        <h3><?php echo $stats['total_listings']; ?></h3>
                        <span class="stat-meta">All active and in-review properties attached to your account.</span>
                    </div>
                </a>
                
                <a href="properties.php" class="stat-card stat-card-link" title="View approved properties">
                    <div class="stat-icon stat-icon-emerald"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Approved</p>
                        <h3><?php echo $stats['approved']; ?></h3>
                        <span class="stat-meta">Listings already cleared and ready for guests to discover.</span>
                    </div>
                </a>
                
                <a href="properties.php" class="stat-card stat-card-link" title="View pending properties">
                    <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Pending Review</p>
                        <h3><?php echo $stats['pending']; ?></h3>
                        <span class="stat-meta">Listings waiting for approval before they can accept bookings.</span>
                    </div>
                </a>
                
                <a href="bookings.php" class="stat-card stat-card-link" title="View bookings">
                    <div class="stat-icon stat-icon-sky"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></div>
                    <div class="stat-content">
                        <p>Total Bookings</p>
                        <h3><?php echo $stats['total_bookings']; ?></h3>
                        <span class="stat-meta">Reservation activity across all of your hosted properties.</span>
                    </div>
                </a>
            </div>

            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <p class="quick-actions-copy">Jump into the most common host tasks without opening extra pages first.</p>
                <div class="actions-grid">
                    <a href="add-property.php" class="action-card">
                        <span class="action-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                        <h3>Add New Property</h3>
                        <p>Create a new listing and prepare it for review.</p>
                    </a>
                    <a href="properties.php" class="action-card">
                        <span class="action-icon"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></span>
                        <h3>Manage Listings</h3>
                        <p>Review details, pricing, and status for your properties.</p>
                    </a>
                    <a href="bookings.php" class="action-card">
                        <span class="action-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                        <h3>View Bookings</h3>
                        <p>Check guest reservations and upcoming stays.</p>
                    </a>
                </div>
            </div>

            <div class="properties-section">
                <div class="section-header dashboard-section-header">
                    <div>
                        <h2>Your Properties</h2>
                        <p>A quick view of the latest listings you have published or submitted for review.</p>
                    </div>
                    <a href="properties.php" class="view-all">View All</a>
                </div>
                
                <?php if (empty($properties)): ?>
                    <div class="empty-state">
                        <span class="empty-icon"><i class="fa-solid fa-house-circle-xmark" aria-hidden="true"></i></span>
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
                                    <p class="property-location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                                    <div class="property-details">
                                        <div class="property-detail-item">
                                            <span class="property-detail-label">Bedrooms</span>
                                            <span class="property-detail-value"><?php echo $property['bedrooms']; ?> beds</span>
                                        </div>
                                        <div class="property-detail-item">
                                            <span class="property-detail-label">Bathrooms</span>
                                            <span class="property-detail-value"><?php echo $property['bathrooms']; ?> baths</span>
                                        </div>
                                        <div class="property-detail-item">
                                            <span class="property-detail-label">Guest Capacity</span>
                                            <span class="property-detail-value"><?php echo $property['max_guests']; ?> guests</span>
                                        </div>
                                    </div>
                                    <div class="property-footer">
                                        <span class="price">₱<?php echo number_format($property['price_per_night'], 2); ?> <small>/night</small></span>
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
                <div class="section-header dashboard-section-header">
                    <div>
                        <h2>Recent Bookings</h2>
                        <p>The latest reservation activity from guests staying at your listings.</p>
                    </div>
                    <a href="bookings.php" class="view-all">View All</a>
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
    
    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
</body>
</html>
