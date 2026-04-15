<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$property_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$property_id) {
    header('Location: properties.php');
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email,
    COALESCE(
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
        (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
    ) as primary_photo
    FROM properties p
    JOIN users u ON p.host_id = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $property_id);
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

function amenityIconSvg(string $name): string {
    $n = strtolower(trim($name));
    $icons = [
        'wifi'              => '<path d="M5 12.5C7.5 10 10.5 8.5 12 8.5s4.5 1.5 7 4"/><path d="M2 9c3.5-3 7.5-4.5 10-4.5s6.5 1.5 10 4.5"/><circle cx="12" cy="17" r="1"/>',
        'air conditioning'  => '<path d="M8 2v6"/><path d="M16 2v6"/><path d="M12 2v6"/><path d="M3 11h18"/><path d="M5 15l-2 4"/><path d="M19 15l2 4"/><path d="M12 15v6"/>',
        'heating'           => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10"/><path d="M12 8v4l3 3"/>',
        'kitchen'           => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 .55.45 1 1 1h3c.55 0 1-.45 1-1Z"/><path d="M21 15v7"/>',
        'tv'                => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'washing machine'   => '<rect x="2" y="2" width="20" height="20" rx="3"/><circle cx="12" cy="13" r="5"/><path d="M7 7h0M11 7h2"/>',
        'free parking'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
        'swimming pool'     => '<path d="M2 20c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M2 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M8 8a4 4 0 1 0 8 0"/><path d="M12 4v4"/>',
        'hot tub'           => '<path d="M9 6 6.5 3.5a1.5 1.5 0 0 1 0-2.1"/><path d="M14 6 11.5 3.5a1.5 1.5 0 0 1 0-2.1"/><path d="M5 14v2a7 7 0 0 0 14 0v-2"/><path d="M5 14H2"/><path d="M22 14h-3"/>',
        'gym'               => '<path d="M6 7v10"/><path d="M18 7v10"/><path d="M8 7H4"/><path d="M20 7h-4"/><path d="M8 17H4"/><path d="M20 17h-4"/><path d="M9 11h6"/>',
        'bbq grill'         => '<path d="M8 22H5a1 1 0 0 1-.978-1.208l1.255-6.278A2 2 0 0 1 7.243 13h9.514a2 2 0 0 1 1.966 1.514L19.978 20.792A1 1 0 0 1 19 22h-3"/><path d="M10 22v-2a2 2 0 1 1 4 0v2"/><path d="M6 13V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8"/><circle cx="12" cy="7" r="1"/>',
        'pet friendly'      => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
        'smoke detector'    => '<circle cx="12" cy="11" r="7"/><path d="M12 4v1M12 18v1M4 11H3M21 11h-1M6.34 5.34l.71.71M16.95 16.95l.71.71M6.34 16.66l.71-.71M16.95 6.05l.71-.71"/><circle cx="12" cy="11" r="3"/>',
        'first aid kit'     => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M12 12v4"/><path d="M10 14h4"/>',
        'fire extinguisher' => '<path d="M15 6.5A3.5 3.5 0 0 1 8 6.5C8 5 9 3 10 2h4c1 1 2 3 1 4.5Z"/><path d="M8 6.5C6 7 5 9 5 11v8a2 2 0 0 0 4 0v-5"/><path d="M14 10h3"/><path d="M17 8v4"/>',
        'cctv'              => '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        'balcony'           => '<path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 10v11"/><path d="M19 10v11"/><path d="M9 10V7"/><path d="M15 10V7"/><rect x="9" y="4" width="6" height="3" rx="1"/>',
        'garden'            => '<path d="M12 22V11"/><path d="M5 11a7 7 0 0 1 14 0"/><path d="M5 11a7 7 0 0 0 3.5 6.06"/><path d="M19 11a7 7 0 0 1-3.5 6.06"/>',
        'workspace'         => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/>',
        'coffee maker'      => '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14"/><path d="M6 2v2"/>',
    ];
    if (isset($icons[$n])) return $icons[$n];
    foreach ($icons as $key => $val) {
        if (str_contains($n, $key) || str_contains($key, $n)) return $val;
    }
    return '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>View Property - <?php echo htmlspecialchars($property['title']); ?> - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=26.0">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }

        /* ── Hero ── */
        .vp-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            background: linear-gradient(135deg, rgba(17,24,39,0.96), rgba(30,41,59,0.88));
            border: 1px solid rgba(212,165,116,0.22);
            border-radius: 24px;
            padding: 28px 30px;
            margin-bottom: 24px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.28);
        }
        .vp-hero-left { flex: 1; min-width: 0; }
        .vp-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #D4A574;
            background: rgba(212,165,116,0.12);
            border: 1px solid rgba(212,165,116,0.22);
            border-radius: 999px;
            padding: 5px 14px;
            margin-bottom: 14px;
        }
        .vp-hero h1 {
            font-size: 26px;
            font-weight: 700;
            color: #F1F5F9 !important;
            margin: 0 0 10px 0;
            line-height: 1.25;
        }
        .vp-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }
        .vp-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #64748B !important;
        }
        .vp-meta-item i { color: #64748B; font-size: 11px; }
        .vp-hero-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
            flex-shrink: 0;
        }
        .btn-vp-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid rgba(148,163,184,0.22);
            background: rgba(255,255,255,0.05);
            color: #CBD5E1 !important;
            transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.2s;
        }
        .btn-vp-back:hover {
            background: rgba(212,165,116,0.12);
            border-color: rgba(212,165,116,0.38);
            color: #D4A574 !important;
            transform: translateY(-1px);
        }

        /* ── Status badges ── */
        .vp-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .vp-status-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .vp-status.status-approved  { background: rgba(34,197,94,0.1)   !important; color: #86EFAC !important; border-color: rgba(34,197,94,0.22); }
        .vp-status.status-approved  .vp-status-dot { background: #22C55E !important; }
        .vp-status.status-pending   { background: rgba(234,179,8,0.1)   !important; color: #FDE047 !important; border-color: rgba(234,179,8,0.22); }
        .vp-status.status-pending   .vp-status-dot { background: #EAB308 !important; }
        .vp-status.status-rejected  { background: rgba(244,63,94,0.1)   !important; color: #FDA4AF !important; border-color: rgba(244,63,94,0.22); }
        .vp-status.status-rejected  .vp-status-dot { background: #F43F5E !important; }
        .vp-status.status-out_of_order { background: rgba(148,163,184,0.1) !important; color: #94A3B8 !important; border-color: rgba(148,163,184,0.22); }
        .vp-status.status-out_of_order .vp-status-dot { background: #94A3B8 !important; }

        /* ── Gallery ── */
        .vp-gallery { margin-bottom: 24px; border-radius: 22px; overflow: hidden; border: 1px solid rgba(148,163,184,0.12); box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .vp-gallery-main { position: relative; background: #0A0F1A; }
        .vp-gallery-main {
            aspect-ratio: 16 / 7;
            overflow: hidden;
        }
        .vp-gallery-main img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            background: #0A0F1A;
        }
        .vp-thumbs {
            display: flex;
            gap: 8px;
            padding: 12px 16px;
            background: rgba(17,24,39,0.96);
            overflow-x: auto;
        }
        .vp-thumbs::-webkit-scrollbar { height: 4px; }
        .vp-thumbs::-webkit-scrollbar-track { background: transparent; }
        .vp-thumbs::-webkit-scrollbar-thumb { background: rgba(212,165,116,0.3); border-radius: 99px; }
        .vp-thumb {
            flex: 0 0 auto;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: border-color 0.2s, transform 0.2s;
        }
        .vp-thumb:hover { transform: translateY(-2px); border-color: rgba(212,165,116,0.5); }
        .vp-thumb.active { border-color: #D4A574; }
        .vp-thumb img { width: 100px; height: 68px; object-fit: cover; display: block; }

        /* ── Content grid ── */
        .vp-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) { .vp-grid { grid-template-columns: 1fr; } }

        /* ── Cards ── */
        .vp-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(148,163,184,0.1);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 14px;
        }
        .vp-card:last-child { margin-bottom: 0; }
        .vp-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #94A3B8 !important;
            margin-bottom: 14px;
        }
        .vp-card-title i { font-size: 12px; }
        .vp-description {
            font-size: 14px;
            line-height: 1.75;
            color: #CBD5E1 !important;
            white-space: pre-wrap;
        }

        /* ── Detail chips ── */
        .vp-chips {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .vp-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: transparent;
            border-bottom: 1px solid rgba(148,163,184,0.08);
        }
        .vp-chip-icon {
            width: 18px; height: 18px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .vp-chip-icon i { color: #64748B; font-size: 12px; }
        .vp-chip-label { font-size: 11px; color: #64748B !important; font-weight: 500; }
        .vp-chip-value { font-size: 13px; color: #CBD5E1 !important; font-weight: 500; margin-top: 1px; }

        /* ── Price highlight ── */
        .vp-price-card { border-color: rgba(148,163,184,0.1); }
        .vp-price-big {
            font-size: 22px;
            font-weight: 700;
            color: #E2E8F0 !important;
            line-height: 1;
        }
        .vp-price-sub { font-size: 12px; color: #64748B !important; margin-top: 4px; }

        /* ── Amenities ── */
        .vp-amenities {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0;
        }
        .vp-amenity {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(148,163,184,0.1);
            font-size: 14px;
            color: #CBD5E1 !important;
            font-weight: 400;
        }
        .vp-amenity:nth-last-child(-n+2) { border-bottom: none; }
        .vp-amenity-icon {
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            color: #D4A574;
            flex-shrink: 0;
        }

        /* ── Address ── */
        .vp-address-line {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: #94A3B8 !important;
            font-size: 13px;
            line-height: 1.6;
        }
        .vp-address-line i { color: #64748B; margin-top: 3px; flex-shrink: 0; font-size: 12px; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page admin-view-property-page">
    <div class="host-layout">
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
                <a href="properties.php" class="nav-item">
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
                <a href="geocode-all-properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                    <span>Geocode Properties</span>
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
                <div class="theme-toggle" style="margin-bottom: 12px;">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">

            <!-- Hero -->
            <div class="vp-hero">
                <div class="vp-hero-left">
                    <div class="vp-eyebrow">
                        <i class="fa-solid fa-house"></i>
                        Property Detail
                    </div>
                    <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                    <span class="vp-status status-<?php echo $property['status']; ?>">
                        <span class="vp-status-dot"></span>
                        <?php echo ucfirst(str_replace('_', ' ', $property['status'])); ?>
                    </span>
                    <div class="vp-meta">
                        <span class="vp-meta-item"><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></span>
                        <span class="vp-meta-item"><i class="fa-solid fa-user-tie"></i><?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></span>
                        <span class="vp-meta-item"><i class="fa-solid fa-envelope"></i><?php echo htmlspecialchars($property['email']); ?></span>
                    </div>
                </div>
                <div class="vp-hero-right">
                    <a href="properties.php" class="btn-vp-back"><i class="fa-solid fa-arrow-left"></i> Back to Properties</a>
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

            <!-- Gallery -->
            <div class="vp-gallery">
                <div class="vp-gallery-main">
                    <img id="admin-main-photo" src="<?php echo htmlspecialchars($main_photo); ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800'">
                </div>
                <?php if (!empty($photos) && count($photos) > 1): ?>
                <div class="vp-thumbs">
                    <?php foreach ($photos as $idx => $p):
                        $thumb = $p['photo_url'];
                        if ($thumb && strpos($thumb, 'http') !== 0) $thumb = '../' . ltrim($thumb, '/');
                    ?>
                    <div class="vp-thumb <?php echo $idx === 0 ? 'active' : ''; ?>"
                         onclick="setMainPhoto(this, '<?php echo htmlspecialchars($thumb, ENT_QUOTES); ?>')">
                        <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Photo <?php echo $idx + 1; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Content Grid -->
            <div class="vp-grid">
                <!-- Left column -->
                <div>
                    <!-- Description -->
                    <div class="vp-card">
                        <div class="vp-card-title"><i class="fa-solid fa-align-left"></i> Description</div>
                        <p class="vp-description"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                    </div>

                    <!-- Address -->
                    <div class="vp-card">
                        <div class="vp-card-title"><i class="fa-solid fa-map-pin"></i> Address</div>
                        <div class="vp-address-line">
                            <i class="fa-solid fa-location-dot"></i>
                            <span><?php echo nl2br(htmlspecialchars($property['address'])); ?><br><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></span>
                        </div>
                    </div>

                    <!-- Amenities -->
                    <?php if (!empty($property['amenities'])): ?>
                    <div class="vp-card">
                        <div class="vp-card-title"><i class="fa-solid fa-star"></i> Amenities</div>
                        <div class="vp-amenities">
                            <?php foreach ($property['amenities'] as $a): ?>
                            <span class="vp-amenity">
                                <span class="vp-amenity-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?php echo amenityIconSvg($a['name']); ?></svg>
                                </span>
                                <?php echo htmlspecialchars($a['name']); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right column -->
                <div>
                    <!-- Price -->
                    <div class="vp-card vp-price-card">
                        <div class="vp-card-title"><i class="fa-solid fa-tag"></i> Pricing</div>
                        <div class="vp-price-big">₱<?php echo number_format($property['price_per_night'], 0); ?></div>
                        <div class="vp-price-sub">per night</div>
                    </div>

                    <!-- Details -->
                    <div class="vp-card">
                        <div class="vp-card-title"><i class="fa-solid fa-circle-info"></i> Details</div>
                        <div class="vp-chips">
                            <div class="vp-chip">
                                <div class="vp-chip-icon"><i class="fa-solid fa-building"></i></div>
                                <div>
                                    <div class="vp-chip-label">Type</div>
                                    <div class="vp-chip-value"><?php echo htmlspecialchars(ucfirst($property['property_type'])); ?></div>
                                </div>
                            </div>
                            <div class="vp-chip">
                                <div class="vp-chip-icon"><i class="fa-solid fa-bed"></i></div>
                                <div>
                                    <div class="vp-chip-label">Bedrooms</div>
                                    <div class="vp-chip-value"><?php echo (int)$property['bedrooms']; ?></div>
                                </div>
                            </div>
                            <div class="vp-chip">
                                <div class="vp-chip-icon"><i class="fa-solid fa-shower"></i></div>
                                <div>
                                    <div class="vp-chip-label">Bathrooms</div>
                                    <div class="vp-chip-value"><?php echo (int)$property['bathrooms']; ?></div>
                                </div>
                            </div>
                            <div class="vp-chip">
                                <div class="vp-chip-icon"><i class="fa-solid fa-users"></i></div>
                                <div>
                                    <div class="vp-chip-label">Max Guests</div>
                                    <div class="vp-chip-value"><?php echo (int)$property['max_guests']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        function setMainPhoto(el, src) {
            document.getElementById('admin-main-photo').src = src;
            document.querySelectorAll('.vp-thumb').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
        }
    </script>
</body>
</html>
