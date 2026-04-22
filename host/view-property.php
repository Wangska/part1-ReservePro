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
        /* ── Page layout ── */
        .view-property-page { max-width: 1280px; margin: 0 auto; padding: 28px 32px; }

        /* ── Hero header ── */
        .vp-hero {
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
        }
        .vp-hero h1 {
            font-size: 26px; font-weight: 800; margin: 0 0 6px;
            color: #F1F5F9 !important; letter-spacing: -0.02em; line-height: 1.2;
        }
        .vp-hero .vp-location {
            font-size: 13px; color: #94a3b8; display: flex; align-items: center; gap: 5px;
        }
        .vp-hero .vp-actions {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto;
        }

        /* ── Status badge ── */
        .status-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        .status-pending  { background: rgba(234,179,8,0.15);  color: #fde047; border: 1px solid rgba(234,179,8,0.3); }
        .status-rejected { background: rgba(239,68,68,0.15);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .status-out_of_order { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }

        /* ── Back button ── */
        .vp-btn-back {
            padding: 9px 16px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border: none; border-radius: 10px;
            font-weight: 700; font-size: 13px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .vp-btn-back:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(212,165,116,0.25); }

        /* ── Gallery ── */
        .view-gallery {
            border-radius: 18px; overflow: hidden; margin-bottom: 22px;
            background: #1a1a1a;
            box-shadow: 0 8px 32px rgba(0,0,0,0.35);
            position: relative;
        }
        .view-gallery img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
            object-fit: unset;
        }
        .vp-gallery-nav {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .vp-gallery-arrow {
            pointer-events: auto;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 46px;
            height: 46px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(15,23,42,0.35);
            color: #E2E8F0;
            font-weight: 900;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            display: inline-grid;
            place-items: center;
            backdrop-filter: blur(4px);
        }
        .vp-gallery-arrow:hover { background: rgba(15,23,42,0.55); }
        .vp-gallery-arrow:disabled { opacity: .25; cursor: not-allowed; }
        .vp-gallery-prev { left: 14px; }
        .vp-gallery-next { right: 14px; }

        /* ── Sections ── */
        .view-section {
            background: var(--bg-secondary, #161616);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
        }
        .view-section h2 {
            font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            margin: 0 0 16px; color: #D4A574 !important;
            display: flex; align-items: center; gap: 8px;
        }
        .view-section h2::before {
            display: none !important;
        }
        .view-section p, .view-section .detail-row { color: #C0C0C0 !important; margin: 0 0 8px; line-height: 1.7; }

        /* ── Detail grid cards ── */
        .host-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .host-detail-card {
            display: flex; flex-direction: column; gap: 4px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
        }
        .host-detail-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
        .host-detail-value { font-size: 15px; font-weight: 700; color: #F1F5F9; }

        /* ── Amenity pills (matches modal.css design) ── */
        .vp-amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0;
            column-gap: 24px;
        }
        .amenity-pill {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            background: transparent;
        }
        .amenity-pill:last-child { border-bottom: none; }
        .amenity-pill-icon {
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            color: #D4A574; flex-shrink: 0; font-size: 15px;
        }
        .amenity-pill-label { font-size: 14px; color: #C0C0C0; font-weight: 400; }

        /* ── Light mode overrides ── */
        body.light-mode .view-section { background: #fff; border-color: rgba(0,0,0,0.07); box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        body.light-mode .view-section p, body.light-mode .view-section .detail-row { color: #374151 !important; }
        body.light-mode .vp-hero h1 { color: #0f172a !important; }
        body.light-mode .vp-hero .vp-location { color: #64748b; }
        body.light-mode .host-detail-card { background: #f8fafc; border-color: rgba(0,0,0,0.08); }
        body.light-mode .host-detail-value { color: #0f172a; }
        body.light-mode .amenity-pill { border-bottom-color: rgba(0,0,0,0.08); }
        body.light-mode .amenity-pill-label { color: #374151; }
        body.light-mode .amenity-pill-icon { color: #B8935F; }
        body.light-mode .status-pending  { background: rgba(234,179,8,0.1);  color: #a16207; border-color: rgba(234,179,8,0.25); }
        body.light-mode .status-rejected, body.light-mode .status-out_of_order { background: rgba(239,68,68,0.1); color: #dc2626; border-color: rgba(239,68,68,0.25); }

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

        /* ── Map ── */
        .vp-map {
            width: 100%;
            height: 420px;
            border-radius: 14px;
            overflow: hidden;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
        }
        body.light-mode .vp-map {
            background: #f8fafc;
            border-color: rgba(0,0,0,0.08);
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
                
                <a href="profile.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Profile</span></a>
                <a href="properties.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>My Properties</span></a>
                <a href="add-property.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span><span>Add Property</span></a>
                <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span>Bookings</span></a>
                <a href="refund-requests.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span><span>Refund Requests</span></a>
                <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span><span>Earnings</span></a>
                <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span>Messages</span></a>
                <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span><span>Home</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="overflow:hidden;">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img
                                src="<?php echo htmlspecialchars('../' . ltrim((string)$user['profile_photo'], '/')); ?>"
                                alt="Profile photo"
                                style="width:100%;height:100%;object-fit:cover;display:block;"
                                onerror="this.style.display='none'"
                            >
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host</div>
                    </div>
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

                <div class="vp-hero">
                    <div>
                        <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                        <p class="vp-location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                    </div>
                    <div class="vp-actions">
                        <a href="properties.php" class="vp-btn-back">Back to list</a>
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
                    <img id="host-main-photo" data-lightbox="property" data-lightbox-title="<?php echo htmlspecialchars($property['title'], ENT_QUOTES); ?>" src="<?php echo htmlspecialchars($main_photo); ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800'">
                    <?php if (!empty($photos) && count($photos) > 1): ?>
                        <div class="vp-gallery-nav" aria-hidden="true">
                            <button type="button" class="vp-gallery-arrow vp-gallery-prev" id="vpGalleryPrev" aria-label="Previous photo">‹</button>
                            <button type="button" class="vp-gallery-arrow vp-gallery-next" id="vpGalleryNext" aria-label="Next photo">›</button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($photos) && count($photos) > 1): ?>
                    <div class="view-section" style="margin-top: 12px;">
                        <h2 style="color: #fff !important;">Photo gallery</h2>
                        <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;">
                            <?php foreach ($photos as $idx => $p): 
                                $thumb = $p['photo_url'];
                                if ($thumb && strpos($thumb, 'http') !== 0) {
                                    $thumb = '../' . ltrim($thumb, '/');
                                }
                            ?>
                                <div class="vp-thumb" data-vp-thumb data-index="<?php echo (int)$idx; ?>" data-src="<?php echo htmlspecialchars($thumb, ENT_QUOTES); ?>" style="flex: 0 0 auto; border-radius: 8px; overflow: hidden; border: 2px solid <?php echo $idx === 0 ? '#D4A574' : 'transparent'; ?>; cursor: pointer;">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" data-lightbox="property" data-lightbox-title="<?php echo htmlspecialchars($property['title'], ENT_QUOTES); ?>" alt="Photo <?php echo $idx + 1; ?>" style="width: 120px; height: 80px; object-fit: cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="view-section host-detail-shell">
                    <h2 style="color: #fff !important;">Description</h2>
                    <p style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                </div>

                <div class="view-section host-detail-shell">
                    <h2 style="color: #fff !important;">Details</h2>
                    <div class="host-detail-grid">
                        <div class="host-detail-card"><span class="host-detail-label">Property Type</span><span class="host-detail-value"><?php echo htmlspecialchars(ucfirst($property['property_type'])); ?></span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Bedrooms</span><span class="host-detail-value"><?php echo (int)$property['bedrooms']; ?> beds</span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Bathrooms</span><span class="host-detail-value"><?php echo (int)$property['bathrooms']; ?> baths</span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Guests</span><span class="host-detail-value"><?php echo (int)$property['max_guests']; ?> guests</span></div>
                        <div class="host-detail-card"><span class="host-detail-label">Nightly Rate</span><span class="host-detail-value">₱<?php echo number_format($property['price_per_night'], 0); ?></span></div>
                    </div>
                </div>

                <div class="view-section host-detail-shell">
                    <h2 style="color: #fff !important;">Address</h2>
                    <p><?php echo nl2br(htmlspecialchars($property['address'])); ?></p>
                    <p><?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?></p>
                    <div id="vpMap" class="vp-map" style="margin-top:14px;"></div>
                </div>

                <?php if (!empty($property['amenities'])): ?>
                <div class="view-section host-detail-shell">
                    <h2 style="color: #fff !important;">Amenities</h2>
                    <div class="vp-amenities-grid">
                        <?php foreach ($property['amenities'] as $a): ?>
                        <div class="amenity-pill">
                            <div class="amenity-pill-label"><?php echo htmlspecialchars($a['name']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
    <script src="../assets/js/image-lightbox.js?v=1.0"></script>
    <script>
        (function () {
            var mapEl = document.getElementById('vpMap');
            if (!mapEl) return;

            var data = <?php
                $payload = [
                    'title' => $property['title'] ?? '',
                    'address' => $property['address'] ?? '',
                    'city' => $property['city'] ?? '',
                    'country' => $property['country'] ?? '',
                    'latitude' => $property['latitude'] ?? null,
                    'longitude' => $property['longitude'] ?? null,
                ];
                echo json_encode($payload);
            ?>;

            function loadLeaflet(cb) {
                if (window.L) return cb();
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(link);

                var script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.onload = cb;
                script.onerror = function () {
                    mapEl.innerHTML = '<div style="padding:14px;color:#94a3b8;font-size:13px;">Map failed to load.</div>';
                };
                document.head.appendChild(script);
            }

            function initMap(lat, lng) {
                if (!window.L) return;
                var L = window.L;
                mapEl.innerHTML = '';
                var map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
                var marker = L.marker([lat, lng]).addTo(map);
                var addressLine = [data.address, data.city, data.country].filter(Boolean).join(', ');
                marker.bindPopup('<strong>' + String(data.title || 'Property') + '</strong><br>' + addressLine).openPopup();
                setTimeout(function () { map.invalidateSize(); }, 50);
            }

            function geocodeAndInit() {
                var query = [data.address, data.city, data.country || 'Philippines'].filter(Boolean).join(', ');
                if (!query) query = (data.city || 'Philippines');
                var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=1';
                fetch(url, { headers: { 'Accept': 'application/json', 'User-Agent': 'ReserveProHostMap/1.0' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res[0] && res[0].lat && res[0].lon) {
                            initMap(parseFloat(res[0].lat), parseFloat(res[0].lon));
                        } else {
                            mapEl.innerHTML = '<div style="padding:14px;color:#94a3b8;font-size:13px;">Map unavailable for this address.</div>';
                        }
                    })
                    .catch(function () {
                        mapEl.innerHTML = '<div style="padding:14px;color:#94a3b8;font-size:13px;">Map unavailable.</div>';
                    });
            }

            loadLeaflet(function () {
                var lat = data.latitude !== null ? parseFloat(data.latitude) : NaN;
                var lng = data.longitude !== null ? parseFloat(data.longitude) : NaN;
                if (isFinite(lat) && isFinite(lng)) initMap(lat, lng);
                else geocodeAndInit();
            });
        })();
    </script>
    <?php if (!empty($photos) && count($photos) > 1): ?>
    <script>
        (function () {
            var mainImg = document.getElementById('host-main-photo');
            if (!mainImg) return;

            var prevBtn = document.getElementById('vpGalleryPrev');
            var nextBtn = document.getElementById('vpGalleryNext');
            var thumbs = Array.prototype.slice.call(document.querySelectorAll('[data-vp-thumb]'));
            if (!thumbs.length) return;

            var urls = thumbs.map(function (t) { return t.getAttribute('data-src') || ''; }).filter(Boolean);
            var index = 0;

            function setActive(i) {
                index = ((i % urls.length) + urls.length) % urls.length;
                mainImg.src = urls[index];
                thumbs.forEach(function (t, ti) {
                    t.style.borderColor = (ti === index) ? '#D4A574' : 'transparent';
                });
            }

            thumbs.forEach(function (t) {
                t.addEventListener('click', function () {
                    var i = parseInt(t.getAttribute('data-index') || '0', 10);
                    if (isNaN(i)) i = 0;
                    setActive(i);
                });
            });

            if (prevBtn) prevBtn.addEventListener('click', function (e) { e.preventDefault(); setActive(index - 1); });
            if (nextBtn) nextBtn.addEventListener('click', function (e) { e.preventDefault(); setActive(index + 1); });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') setActive(index - 1);
                if (e.key === 'ArrowRight') setActive(index + 1);
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
