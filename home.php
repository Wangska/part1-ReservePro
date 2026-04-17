<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
$user = isLoggedIn() ? getCurrentUser() : null;

// Fetch approved properties from database
$conn = getDBConnection();
$query = "
    SELECT p.*, u.first_name, u.last_name,
    (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_photo
    FROM properties p
    JOIN users u ON p.host_id = u.id
    WHERE p.status = 'approved'
    ORDER BY p.property_type ASC, p.created_at DESC
    LIMIT 50
";
$result = $conn->query($query);
$properties = [];
if ($result) {
    $properties = $result->fetch_all(MYSQLI_ASSOC);
}

// Get property types with counts
$property_types_query = "
    SELECT property_type, COUNT(*) as count 
    FROM properties 
    WHERE status = 'approved' 
    GROUP BY property_type
";
$types_result = $conn->query($property_types_query);
$property_types = [];
if ($types_result) {
    $property_types = $types_result->fetch_all(MYSQLI_ASSOC);
}

// Get min and max prices
$price_query = "SELECT MIN(price_per_night) as min_price, MAX(price_per_night) as max_price FROM properties WHERE status = 'approved'";
$price_result = $conn->query($price_query);
$price_range = $price_result->fetch_assoc();
$min_price = $price_range['min_price'] ?? 0;
$max_price = $price_range['max_price'] ?? 10000;

// Get property amenities count and list of amenity IDs per property (for filtering)
$property_amenities = [];
$property_amenity_ids = [];
foreach ($properties as $property) {
    $stmt = $conn->prepare("
        SELECT amenity_id FROM property_amenities WHERE property_id = ?
    ");
    $stmt->bind_param("i", $property['id']);
    $stmt->execute();
    $ar = $stmt->get_result();
    $ids = [];
    while ($row = $ar->fetch_assoc()) {
        $ids[] = $row['amenity_id'];
    }
    $stmt->close();
    $property_amenity_ids[$property['id']] = $ids;
    $property_amenities[$property['id']] = count($ids);
}

// Get all amenities offered by the site (for filter sidebar)
$amenities_result = $conn->query("
    SELECT a.id, a.name, a.icon,
    (SELECT COUNT(DISTINCT pa.property_id) FROM property_amenities pa JOIN properties p ON p.id = pa.property_id WHERE pa.amenity_id = a.id AND p.status = 'approved') as prop_count
    FROM amenities a
    ORDER BY a.category, a.name
");
$all_amenities = [];
if ($amenities_result) {
    $all_amenities = $amenities_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReservePro - Discover Amazing Services</title>
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/landing.css?v=25.1">
    <link rel="stylesheet" href="assets/css/modal.css?v=26.2">
    <link rel="stylesheet" href="assets/css/role-select.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=25.2">
    <link rel="stylesheet" href="assets/css/animations.css?v=1.0">
    <link rel="stylesheet" href="assets/css/home-modern.css?v=4.5">
    <style>
        .theme-toggle.theme-toggle-home-static {
            width: 42px;
            min-height: 42px;
            padding: 0;
            justify-content: center;
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(148, 163, 184, 0.18);
            box-shadow: none;
        }

        .theme-toggle.theme-toggle-home-static .theme-toggle-icon {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: none;
            color: #f8fafc;
            transition: none;
        }

        .theme-toggle.theme-toggle-home-static:hover .theme-toggle-icon,
        .theme-toggle.theme-toggle-home-static[aria-pressed="true"] .theme-toggle-icon,
        .theme-toggle.theme-toggle-home-static[aria-pressed="true"]:hover .theme-toggle-icon {
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: none;
            color: #f8fafc;
        }

        body.light-mode .theme-toggle.theme-toggle-home-static {
            background: #ffffff;
            border-color: rgba(15, 23, 42, 0.1);
            box-shadow: none;
        }

        body.light-mode .theme-toggle.theme-toggle-home-static .theme-toggle-icon,
        body.light-mode .theme-toggle.theme-toggle-home-static:hover .theme-toggle-icon,
        body.light-mode .theme-toggle.theme-toggle-home-static[aria-pressed="true"] .theme-toggle-icon,
        body.light-mode .theme-toggle.theme-toggle-home-static[aria-pressed="true"]:hover .theme-toggle-icon {
            background: rgba(15, 23, 42, 0.08);
            color: #0f172a;
        }
    </style>
</head>
<body>
    <!-- 3D ReservePro loading overlay -->
    <div id="rp-loader">
        <div class="rp-loader-inner">
            <div class="rp-logo-3d">
                <img src="background%20image/asd.webp" alt="ReservePro logo">
            </div>
            <div class="rp-loader-text">ReservePro</div>
            <div class="rp-loader-subtext">Loading your next stay</div>
        </div>
    </div>
    <!-- No navbar: brand bar lives inside the hero -->

    <!-- Expose current user info to JS (used for things like reviews) -->
    <script>
        window.currentUser = <?php
            if ($user) {
                $safeUser = [
                    'id' => (int) $user['id'],
                    'role' => $user['role'] ?? null,
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                ];
                echo json_encode($safeUser);
            } else {
                echo 'null';
            }
        ?>;
    </script>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <!-- Search row: logo + bar + filter + burger/user-menu -->
            <div class="rp-hero-searchrow">
            <a href="home.php" class="rp-hero-brand">
                <?php require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                <span class="rp-hero-brandname">ReservePro</span>
            </a>
            <a href="index.php" class="rp-hero-landing-btn" title="Go back to landing page">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Landing
            </a>
            <div class="rp-wwwsearch">
                <div class="rp-wwwfield">
                    <span class="rp-wwwlabel">Where</span>
                    <div class="rp-wwwinput-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <input type="text" id="searchInput" class="rp-wwwinput" placeholder="City or destination">
                    </div>
                </div>
                <div class="rp-wwwsep"></div>
                <div class="rp-wwwfield">
                    <span class="rp-wwwlabel">When</span>
                    <div class="rp-wwwinput-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <input type="date" id="searchDate" class="rp-wwwinput" placeholder="Add date">
                    </div>
                </div>
                <div class="rp-wwwsep"></div>
                <div class="rp-wwwfield">
                    <span class="rp-wwwlabel">Who</span>
                    <div class="rp-wwwinput-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <div class="rp-guest-counter">
                            <button type="button" class="rp-guest-btn" id="guestMinus" aria-label="Remove guest">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/></svg>
                            </button>
                            <span class="rp-guest-count" id="guestCount">1</span>
                            <input type="hidden" id="searchGuests" name="guests" value="1">
                            <button type="button" class="rp-guest-btn" id="guestPlus" aria-label="Add guest">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                            </button>
                        </div>
                        <span class="rp-guest-label" id="guestLabel">Guest</span>
                    </div>
                </div>
                <button class="rp-wwwbtn" id="searchBtn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Search
                </button>
            </div><!-- /.rp-wwwsearch -->

            <!-- Hero filter button -->
            <button type="button" class="rp-hero-filter-btn" id="filterToggleHero" aria-label="Open filters">
                <i class="fa-solid fa-sliders"></i>
                Filter
            </button>

            <?php if ($user): ?>
            <div class="guest-menu">
                <button type="button" class="guest-menu-trigger" id="guestMenuTrigger" aria-expanded="false" aria-haspopup="true">
                    <span class="guest-menu-name">Hi, <?php echo htmlspecialchars($user['first_name']); ?></span>
                    <svg class="guest-menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="guest-menu-panel" id="guestMenuPanel" role="menu" aria-hidden="true">
                    <?php if (isset($user['role']) && $user['role'] !== 'admin'): ?>
                    <a href="messages.php" role="menuitem" class="guest-menu-item">Messages</a>
                    <?php endif; ?>
                    <?php if (isset($user['role']) && $user['role'] === 'guest'): ?>
                    <a href="my-bookings.php" role="menuitem" class="guest-menu-item">My bookings</a>
                    <a href="profile.php" role="menuitem" class="guest-menu-item">Profile</a>
                    <?php elseif (isset($user['role']) && $user['role'] === 'host'): ?>
                    <a href="host/dashboard.php" role="menuitem" class="guest-menu-item">Dashboard</a>
                    <?php elseif (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <a href="admin/dashboard.php" role="menuitem" class="guest-menu-item">Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" role="menuitem" class="guest-menu-item guest-menu-item-logout">Logout</a>
                </div>
            </div>
            <?php else: ?>
            <div class="rp-burger-menu">
                <button type="button" class="rp-burger-trigger" id="navBurgerTrigger" aria-expanded="false" aria-haspopup="true" aria-controls="navBurgerPanel" aria-label="Open menu">
                    <span class="rp-burger-lines" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </span>
                </button>
                <div class="rp-burger-panel" id="navBurgerPanel" role="menu" aria-hidden="true">
                    <a href="become-host.php" role="menuitem" class="rp-burger-item">Become a Host</a>
                    <a href="login.php" role="menuitem" class="rp-burger-item">Sign in</a>
                    <a href="contact.php" role="menuitem" class="rp-burger-item">Help</a>
                    <a href="about.php" role="menuitem" class="rp-burger-item">About</a>
                </div>
            </div>
            <?php endif; ?>

            </div><!-- /.rp-hero-searchrow -->
        </div><!-- /.hero-content -->
    </section>

    <!-- Filter and Content Section -->
    <section class="main-content">
        <div class="content-wrapper">
            <!-- Sidebar Filters -->
            <aside class="filters-sidebar is-collapsed" id="filtersSidebar" aria-label="Filters">
                <div class="filter-header">
                    <button type="button" class="rp-sidebar-filter-toggle" id="filterContentToggle" aria-controls="filtersContent" aria-expanded="false">
                        <span>Filters</span>
                        <span class="rp-sidebar-filter-chevron" aria-hidden="true">▾</span>
                    </button>
                    <button class="rp-filter-close" id="filterClose" type="button" aria-label="Close filters">×</button>
                </div>

                <div class="filters-content" id="filtersContent" aria-hidden="true">
                <div class="filter-section">
                    <h4>Property Types</h4>
                    <label class="filter-checkbox">
                        <input type="checkbox" class="category-filter" value="all">
                        <span>All Properties</span>
                        <span class="filter-count">(<?php echo count($properties); ?>)</span>
                    </label>
                    <?php foreach ($property_types as $type): ?>
                    <label class="filter-checkbox">
                        <input type="checkbox" class="category-filter" value="<?php echo htmlspecialchars($type['property_type']); ?>">
                        <span><?php echo ucfirst($type['property_type']); ?></span>
                        <span class="filter-count">(<?php echo $type['count']; ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-section">
                    <h4>Price Range</h4>
                    <div class="price-range">
                        <input type="range" 
                               min="0" 
                               max="250000" 
                               value="0" 
                               class="price-slider" 
                               id="priceSlider">
                        <div class="price-labels">
                            <span>₱0</span>
                            <span id="currentPrice">₱0</span>
                        </div>
                    </div>
                </div>

                <div class="filter-section">
                    <h4>Amenities</h4>
                    <?php foreach ($all_amenities as $amenity): ?>
                    <label class="filter-checkbox">
                        <input type="checkbox" class="amenity-filter" value="<?php echo (int)$amenity['id']; ?>">
                        <span><?php echo htmlspecialchars($amenity['name']); ?></span>
                        <span class="filter-count">(<?php echo (int)$amenity['prop_count']; ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
                </div>

            </aside>
            <div class="rp-filter-overlay" id="filterOverlay" aria-hidden="true"></div>

            <!-- Services Grid -->
            <main class="services-grid">
                <div class="grid-header">
                    <div>
                        <h2>Popular Services</h2>
                        <p id="resultsCount" class="rp-results-count">Showing <?php echo count($properties); ?> properties</p>
                    </div>
                    <div class="sort-options">
                        <button type="button" class="rp-filter-toggle" id="filterToggle" aria-controls="filtersSidebar" aria-expanded="false">
                            Filters <span class="rp-filter-badge" id="filterBadge" aria-hidden="true">0</span>
                        </button>
                    </div>
                </div>

                <div class="rp-applied-filters" id="appliedFilters" aria-live="polite"></div>

                <div class="cards-grid" id="cardsGrid">
                    <?php if (empty($properties)): ?>
                        <!-- No Properties Available -->
                        <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                            <div style="font-size: 64px; margin-bottom: 20px;">🏠</div>
                            <h3 style="font-size: 24px; color: #FFFFFF !important; margin-bottom: 12px;">No Properties Available Yet</h3>
                            <p style="color: #E0E0E0 !important; font-size: 16px;">Check back soon for amazing properties!</p>
                        </div>
                    <?php else: ?>
                        <?php $current_type = null; ?>
                        <?php foreach ($properties as $property): 
                            if (!empty($property['primary_photo'])) {
                                $photo_path = ltrim($property['primary_photo'], '/');
                                $image_url = $photo_path;
                            } else {
                                $image_url = 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800&auto=format&fit=crop&q=80';
                            }

                            // Inject group heading when type changes
                            if ($property['property_type'] !== $current_type):
                                $current_type = $property['property_type'];
                        ?>
                        <div class="rp-type-heading" style="grid-column: 1 / -1; margin: 28px 0 8px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <h3 style="font-size: 18px; font-weight: 700; color: #F1F5F9; letter-spacing: -0.01em; text-transform: capitalize;"><?php echo htmlspecialchars($current_type); ?>s</h3>
                        </div>
                        <?php
                            endif;

                            // Demo-friendly titles/locations (Home page only; does not change DB)
                            $demo_titles = [
                                'Sunrise Studio Apartment',
                                'Harborview Apartment Suite',
                                'Modern Loft Near IT Park',
                                'Cozy Urban Apartment',
                                'Cityscape 1BR Apartment',
                                'Seaside Apartment Retreat',
                                'Minimalist Apartment Haven',
                                'Executive Apartment Residence',
                                'Skyline Apartment Getaway',
                                'Boutique Apartment Stay',
                            ];
                            $demo_locations = [
                                ['Cebu City', 'Philippines'],
                                ['Manila', 'Philippines'],
                            ];
                            $demo_seed = (int)($property['id'] ?? 0);
                            $demo_title = $demo_titles[$demo_seed % count($demo_titles)];
                            $demo_loc = $demo_locations[$demo_seed % count($demo_locations)];
                            $demo_city = $demo_loc[0];
                            $demo_country = $demo_loc[1];

                            $amenity_count = $property_amenities[$property['id']] ?? 0;
                            $avg_rating = isset($property['average_rating']) ? (float) $property['average_rating'] : null;
                            $review_count = isset($property['review_count']) ? (int) $property['review_count'] : 0;
                            $rating_for_data = $avg_rating !== null ? number_format($avg_rating, 2, '.', '') : '0';
                        ?>
                        <div class="service-card" onclick="openPropertyModal(<?php echo $property['id']; ?>)" 
                             data-price="<?php echo $property['price_per_night']; ?>" 
                             data-date="<?php echo $property['created_at']; ?>"
                             data-type="<?php echo htmlspecialchars($property['property_type']); ?>"
                             data-title="<?php echo htmlspecialchars(strtolower($demo_title)); ?>"
                             data-city="<?php echo htmlspecialchars(strtolower($demo_city)); ?>"
                             data-country="<?php echo htmlspecialchars(strtolower($demo_country)); ?>"
                             data-description="<?php echo htmlspecialchars(strtolower($property['description'])); ?>"
                             data-amenity-ids="<?php echo implode(',', array_map('intval', $property_amenity_ids[$property['id']] ?? [])); ?>"
                             data-rating="<?php echo $rating_for_data; ?>">
                            <div class="card-image">
                                <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($demo_title); ?>" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800&auto=format&fit=crop&q=80'">
                                <span class="card-badge"><?php echo ucfirst($property['property_type']); ?></span>
                                <button class="card-favorite">&#9825;</button>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title"><?php echo htmlspecialchars($demo_title); ?></h3>
                                <div class="card-location">
                                    <span class="card-location-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z"></path>
                                            <circle cx="12" cy="11" r="2.5"></circle>
                                        </svg>
                                    </span>
                                    <span><?php echo htmlspecialchars($demo_city . ', ' . $demo_country); ?></span>
                                </div>
                                <?php if ($avg_rating !== null && $review_count > 0): ?>
                                    <div class="card-rating">
                                        <span class="card-rating-star">★</span>
                                        <span class="card-rating-score"><?php echo number_format($avg_rating, 1); ?></span>
                                        <span class="card-rating-count">(<?php echo $review_count; ?>)</span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-details">
                                    <span class="card-meta-item">
                                        <span class="card-meta-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 11V7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5V11"></path>
                                                <path d="M3 13h18"></path>
                                                <path d="M5 19v-6"></path>
                                                <path d="M19 19v-6"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo $property['bedrooms']; ?> bed<?php echo $property['bedrooms'] > 1 ? 's' : ''; ?></span>
                                    </span>
                                    <span class="card-meta-item">
                                        <span class="card-meta-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M7 21h10"></path>
                                                <path d="M9 17h6"></path>
                                                <path d="M8 3h8l1 9a5 5 0 0 1-10 0l1-9Z"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo $property['bathrooms']; ?> bath<?php echo $property['bathrooms'] > 1 ? 's' : ''; ?></span>
                                    </span>
                                    <span class="card-meta-item">
                                        <span class="card-meta-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9.5" cy="7" r="4"></circle>
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo $property['max_guests']; ?> guest<?php echo $property['max_guests'] > 1 ? 's' : ''; ?></span>
                                    </span>
                                </div>
                                <div class="card-footer">
                                    <div class="card-price">
                                        <div class="price-wrapper">
                                            <span class="price-current price-amount">₱<?php echo number_format($property['price_per_night'], 2); ?></span>
                                            <span class="price-label">/night</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn">...</button>
                    <button class="page-btn">11</button>
                    <button class="page-btn">Next</button>
                </div>
            </main>
        </div>
    </section>

    <!-- Footer -->
    <footer class="lp-footer">
        <div class="lp-footer-inner">
            <div class="lp-footer-top">
                <div>
                    <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                        <img style="width:32px;height:32px;border-radius:10px;border:2px solid rgba(212,165,116,0.5);object-fit:contain;"
                             src="/part1-ReservePro/background%20image/asd.webp" alt="ReservePro">
                        <span style="font-size:20px;font-weight:800;background:linear-gradient(135deg,#D4A574,#FAD798);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-0.5px;">ReservePro</span>
                    </a>
                    <p class="lp-footer-brand-desc">Find, compare, and book curated stays across the Philippines. Built for travelers, designed for hosts.</p>
                </div>
                <div class="lp-footer-col">
                    <h5>Explore</h5>
                    <ul>
                        <li><a href="home.php">Browse Stays</a></li>
                        <li><a href="experiences.php">Experiences</a></li>
                        <li><a href="become-host.php">Become a Host</a></li>
                    </ul>
                </div>
                <div class="lp-footer-col">
                    <h5>Company</h5>
                    <ul>
                        <li><a href="about.php">About</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#">Careers</a></li>
                    </ul>
                </div>
                <div class="lp-footer-col">
                    <h5>Support</h5>
                    <ul>
                        <li><a href="contact.php">Help Center</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="lp-footer-bottom">
                <p>&copy; 2026 ReservePro. All rights reserved.</p>
                <div class="lp-footer-bottom-links">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('loginModal')"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
            <div class="modal-header">
                <div style="margin-bottom: 12px;">
                    <img src="background%20image/z.jpg"
                         alt="Secure login"
                         style="width:64px; height:64px; border-radius:18px; object-fit:cover; display:block; margin:0 auto;">
                </div>
                <h2>Welcome Back</h2>
                <p>Log in to your ReservePro account</p>
            </div>
            <form class="modal-form" method="POST" action="login.php">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" placeholder="john@example.com" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="modal-btn">Sign In</button>
            </form>
            <div class="modal-divider">
                <span>or</span>
            </div>
            <button class="modal-btn-social" onclick="window.location.href='google-login.php'; return false;">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </button>
            <div class="modal-footer">
                <p>Don't have an account? <a href="register.php">Sign up as Guest</a></p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('registerModal')"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('registerModal')">&times;</button>
            <div class="modal-header">
                <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
                <h2>Join ReservePro</h2>
                <p>Create your guest account to get started</p>
            </div>
            <form class="modal-form" method="POST" action="register-handler.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="register-firstname">First Name</label>
                        <input type="text" id="register-firstname" name="first_name" placeholder="John" required>
                    </div>
                    <div class="form-group">
                        <label for="register-lastname">Last Name</label>
                        <input type="text" id="register-lastname" name="last_name" placeholder="Doe" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" placeholder="john@example.com" required>
                </div>
                <!-- Role selection removed: modal sign-up always creates a Guest account -->
                <div class="form-group">
                    <label for="register-password">Password</label>
                    <input type="password" id="register-password" name="password" placeholder="At least 8 characters" required>
                </div>
                <div class="form-group">
                    <label for="register-confirm">Confirm Password</label>
                    <input type="password" id="register-confirm" name="confirm_password" placeholder="Re-enter password" required>
                </div>
                <button type="submit" class="modal-btn">Create Account</button>
            </form>
            <div class="modal-footer">
                <p>Already have an account? <a href="#" onclick="switchModal('registerModal', 'loginModal')">Sign in</a></p>
            </div>
        </div>
    </div>

    <!-- Property Details Modal -->
    <div id="propertyModal" class="modal" style="display: none;">
        <div class="modal-overlay" onclick="closePropertyModal()"></div>
        <div class="modal-content" style="max-width: 1000px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" onclick="closePropertyModal()">&times;</button>
            
            <div id="propertyModalContent">
                <div style="text-align: center; padding: 40px; color: #B8B8B8;">
                    <div style="font-size: 48px; margin-bottom: 16px;">⏳</div>
                    <p>Loading property details...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/landing.js?v=1.1"></script>
    <script src="assets/js/modal.js"></script>
    <script src="assets/js/property-modal.js?v=7.2"></script>
    <script>
        // Fade out 3D loader when page finishes loading
        window.addEventListener('load', function () {
            var loader = document.getElementById('rp-loader');
            if (!loader) return;
            setTimeout(function () {
                loader.classList.add('rp-loader-hide');
                setTimeout(function () {
                    if (loader && loader.parentNode) {
                        loader.parentNode.removeChild(loader);
                    }
                }, 600);
            }, 300); // small delay so logo is visible briefly
        });
        // Guest sliding menu
        (function () {
            var trigger = document.getElementById('guestMenuTrigger');
            var panel = document.getElementById('guestMenuPanel');
            var menu = trigger && trigger.closest('.guest-menu');
            if (!trigger || !panel) return;
            function toggle() {
                var open = panel.classList.toggle('guest-menu-panel-open');
                trigger.setAttribute('aria-expanded', open);
                panel.setAttribute('aria-hidden', !open);
                if (menu) menu.classList.toggle('guest-menu-open', open);
            }
            function close() {
                panel.classList.remove('guest-menu-panel-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');
                if (menu) menu.classList.remove('guest-menu-open');
            }
            trigger.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
            document.addEventListener('click', function () { close(); });
            panel.addEventListener('click', function (e) { e.stopPropagation(); });
        })();
    </script>

    <script>
        // Guest counter for Where/When/Who search bar
        (function () {
            var minusBtn  = document.getElementById('guestMinus');
            var plusBtn   = document.getElementById('guestPlus');
            var countEl   = document.getElementById('guestCount');
            var labelEl   = document.getElementById('guestLabel');
            var hiddenInput = document.getElementById('searchGuests');
            var count = 1;
            var MAX = 20;

            function update() {
                countEl.textContent = count;
                hiddenInput.value   = count;
                labelEl.textContent = count === 1 ? 'Guest' : 'Guests';
                minusBtn.disabled   = count <= 1;
                plusBtn.disabled    = count >= MAX;
            }

            minusBtn.addEventListener('click', function () { if (count > 1)   { count--; update(); } });
            plusBtn.addEventListener('click',  function () { if (count < MAX) { count++; update(); } });

            update();
        })();
    </script>

</body>
</html>


