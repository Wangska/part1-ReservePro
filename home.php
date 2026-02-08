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
    ORDER BY p.created_at DESC
    LIMIT 12
";
$result = $conn->query($query);
$properties = [];
if ($result) {
    $properties = $result->fetch_all(MYSQLI_ASSOC);
}

// Get property amenities count
$property_amenities = [];
foreach ($properties as $property) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as amenity_count 
        FROM property_amenities 
        WHERE property_id = ?
    ");
    $stmt->bind_param("i", $property['id']);
    $stmt->execute();
    $amenity_result = $stmt->get_result();
    $property_amenities[$property['id']] = $amenity_result->fetch_assoc()['amenity_count'];
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServePro - Discover Amazing Services</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="stylesheet" href="assets/css/modal.css">
    <link rel="stylesheet" href="assets/css/role-select.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="home.php" class="brand">
                    <svg class="brand-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1c2 0 3.46 1.63 3.46 3.41 0 1.78-1.46 3.41-3.46 3.41s-3.46-1.63-3.46-3.41C12.54 2.63 14 1 16 1zm0 6.82c2.52 0 4.61-1.84 4.61-4.41C20.61 1.84 18.52 0 16 0s-4.61 1.84-4.61 4.41c0 2.57 2.09 4.41 4.61 4.41zM13.96 28.85l6.72-11.87c-1.41-.83-3.07-1.33-4.86-1.33-1.79 0-3.45.5-4.86 1.33l6.72 11.87h.28zm-1.27-1.89l-5.12-9.04C8.47 16.02 9.99 15 11.71 15h8.58c1.72 0 3.24 1.02 4.14 2.92l-5.12 9.04h-7.62z"/>
                    </svg>
                    <span class="brand-name">ServePro</span>
                </a>
                <div class="nav-links">
                    <a href="#services">Services</a>
                    <a href="#experiences">Experiences</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                </div>
            </div>
            <div class="nav-right">
                <?php if ($user): ?>
                    <div class="user-nav">
                        <span class="user-greeting">Hi, <?php echo htmlspecialchars($user['first_name']); ?></span>
                        <a href="dashboard.php" class="nav-btn">Dashboard</a>
                        <a href="logout.php" class="nav-btn-outline">Logout</a>
                    </div>
                <?php else: ?>
                    <button onclick="openModal('loginModal')" class="nav-btn-outline">Sign in</button>
                    <button onclick="openModal('registerModal')" class="nav-btn">Sign up</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title">Discover Your Next Adventure</h1>
            <p class="hero-subtitle">Premium services and unforgettable experiences</p>
            
            <!-- Search Bar -->
            <div class="search-container">
                <div class="search-box">
                    <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" placeholder="Where do you want to go?" class="search-input">
                    <button class="search-btn">Search</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter and Content Section -->
    <section class="main-content">
        <div class="content-wrapper">
            <!-- Sidebar Filters -->
            <aside class="filters-sidebar">
                <div class="filter-header">
                    <h3>Filters</h3>
                    <button class="filter-reset">Clear all</button>
                </div>

                <div class="filter-section">
                    <h4>Categories</h4>
                    <label class="filter-checkbox">
                        <input type="checkbox" checked>
                        <span>All Services</span>
                        <span class="filter-count">(242)</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>Tours</span>
                        <span class="filter-count">(75)</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>Experiences</span>
                        <span class="filter-count">(67)</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>Transportation</span>
                        <span class="filter-count">(44)</span>
                    </label>
                </div>

                <div class="filter-section">
                    <h4>Price Range</h4>
                    <div class="price-range">
                        <input type="range" min="0" max="1000" value="500" class="price-slider">
                        <div class="price-labels">
                            <span>₱0</span>
                            <span>₱1000+</span>
                        </div>
                    </div>
                </div>

                <div class="filter-section">
                    <h4>Rating</h4>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>⭐⭐⭐⭐⭐</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>⭐⭐⭐⭐</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>⭐⭐⭐</span>
                    </label>
                </div>

                <div class="filter-section">
                    <h4>Features</h4>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>Free Cancellation</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox">
                        <span>Instant Confirmation</span>
                    </label>
                </div>
            </aside>

            <!-- Services Grid -->
            <main class="services-grid">
                <div class="grid-header">
                    <h2>Popular Services</h2>
                    <div class="sort-options">
                        <label>Sort by:</label>
                        <select class="sort-select">
                            <option>Popular</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                            <option>Highest Rated</option>
                        </select>
                    </div>
                </div>

                <div class="cards-grid">
                    <?php if (empty($properties)): ?>
                        <!-- No Properties Available -->
                        <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                            <div style="font-size: 64px; margin-bottom: 20px;">🏠</div>
                            <h3 style="font-size: 24px; color: #1F2937; margin-bottom: 12px;">No Properties Available Yet</h3>
                            <p style="color: #6B7280; font-size: 16px;">Check back soon for amazing properties!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($properties as $property): 
                            // Use placeholder image if no photo
                            $image_url = $property['primary_photo'] ?? 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400';
                            $amenity_count = $property_amenities[$property['id']] ?? 0;
                        ?>
                        <div class="service-card">
                            <div class="card-image">
                                <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($property['title']); ?>">
                                <span class="card-badge"><?php echo ucfirst($property['property_type']); ?></span>
                                <button class="card-favorite">♡</button>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title"><?php echo htmlspecialchars($property['title']); ?></h3>
                                <div class="card-location">
                                    📍 <?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?>
                                </div>
                                <div class="card-details">
                                    <span>🛏️ <?php echo $property['bedrooms']; ?> bed<?php echo $property['bedrooms'] > 1 ? 's' : ''; ?></span>
                                    <span>🚿 <?php echo $property['bathrooms']; ?> bath<?php echo $property['bathrooms'] > 1 ? 's' : ''; ?></span>
                                    <span>👥 <?php echo $property['max_guests']; ?> guest<?php echo $property['max_guests'] > 1 ? 's' : ''; ?></span>
                                </div>
                                <?php if ($amenity_count > 0): ?>
                                <div class="card-features">
                                    <span class="feature-tag">✨ <?php echo $amenity_count; ?> amenities</span>
                                </div>
                                <?php endif; ?>
                                <div class="card-footer">
                                    <div class="card-price">
                                        <div class="price-wrapper">
                                            <span class="price-current">₱<?php echo number_format($property['price_per_night'], 2); ?></span>
                                            <span class="price-label">/night</span>
                                        </div>
                                    </div>
                                    <div class="host-info">
                                        <small>by <?php echo htmlspecialchars($property['first_name']); ?></small>
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
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>About ServePro</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Support</h4>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Services</h4>
                <ul>
                    <li><a href="#">Tours</a></li>
                    <li><a href="#">Experiences</a></li>
                    <li><a href="#">Transportation</a></li>
                    <li><a href="#">Attractions</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Connect</h4>
                <ul>
                    <li><a href="#">Facebook</a></li>
                    <li><a href="#">Instagram</a></li>
                    <li><a href="#">Twitter</a></li>
                    <li><a href="#">LinkedIn</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 ServePro. All rights reserved.</p>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('loginModal')"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
            <div class="modal-header">
                <div style="font-size: 48px; margin-bottom: 16px;">🔐</div>
                <h2>Welcome Back</h2>
                <p>Log in to your ServePro account</p>
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
            <button class="modal-btn-social" onclick="alert('Social login coming soon!')">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </button>
            <div class="modal-footer">
                <p>Don't have an account? <a href="#" onclick="switchModal('loginModal', 'registerModal')">Sign up</a></p>
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
                <h2>Join ServePro</h2>
                <p>Create your account to get started</p>
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
                <div class="form-group">
                    <label for="register-role">I want to</label>
                    <select id="register-role" name="role" required onchange="showRoleInfo(this.value)">
                        <option value="guest">🏖️ Browse & Book Properties (Guest)</option>
                        <option value="host">🏠 List My Properties (Host)</option>
                    </select>
                    <div id="role-description" class="role-info guest">
                        <strong>🏖️ Guest Account</strong>
                        Browse properties, make bookings, and enjoy amazing experiences. After signup, you'll go to the <strong>Guest Dashboard</strong>.
                    </div>
                </div>
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

    <script src="assets/js/landing.js"></script>
    <script src="assets/js/modal.js"></script>
</body>
</html>
