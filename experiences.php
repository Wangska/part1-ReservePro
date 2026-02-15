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
    <title>Experiences - ServePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=23.0">
    <link rel="stylesheet" href="assets/css/landing.css?v=23.0">
    <link rel="stylesheet" href="assets/css/modal.css?v=23.0">
    <link rel="stylesheet" href="assets/css/role-select.css?v=23.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=23.0">
    <style>
        .experiences-hero {
            background: linear-gradient(135deg, #2C1810 0%, #3E2723 50%, #0F0F0F 100%);
            padding: 80px 20px 60px;
            text-align: center;
            color: white;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }
        
        .experiences-hero h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #FFFFFF !important;
        }
        
        .experiences-hero p {
            font-size: 20px;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
            color: #E0E0E0 !important;
        }
        
        .experience-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .experiences-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 80px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 48px;
        }
        
        .section-header h2 {
            font-size: 36px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin-bottom: 12px;
        }
        
        .section-header p {
            font-size: 18px;
            color: #E0E0E0 !important;
        }
        
        .experiences-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 32px;
            margin-top: 40px;
        }
        
        .experience-card {
            background: #1F1F1F;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #3A3A3A;
            display: block;
            text-decoration: none;
            color: inherit;
        }
        
        a.experience-card {
            text-decoration: none;
            color: inherit;
        }
        
        .experience-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(212, 165, 116, 0.3);
            border-color: #D4A574;
        }
        
        .experience-image {
            width: 100%;
            height: 240px;
            background: linear-gradient(135deg, #E0E7FF, #C7D2FE);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            position: relative;
            overflow: hidden;
        }
        
        .experience-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .experience-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .experience-info {
            padding: 20px;
        }
        
        .experience-title {
            font-size: 20px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .experience-location {
            color: #D0D0D0 !important;
            font-size: 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .experience-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #3A3A3A;
        }
        
        .experience-price {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .experience-price-label {
            font-size: 12px;
            color: #D0D0D0 !important;
            font-weight: 400;
        }
        
        .experience-host {
            font-size: 13px;
            color: #D0D0D0 !important;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .amenities-count {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #2C2C2C;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            color: #D4A574;
            margin-top: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            grid-column: 1 / -1;
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 28px;
            color: #FFFFFF !important;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 16px;
            color: #E0E0E0 !important;
            margin-bottom: 24px;
        }
        
        .btn-home {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .btn-home:hover {
            transform: scale(1.05);
        }
    </style>
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
                    <a href="home.php">Home</a>
                    <a href="experiences.php" class="active">Experiences</a>
                    <a href="about.php">About</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
            <div class="nav-right">
                <?php if ($user): ?>
                    <div class="user-nav">
                        <span class="user-greeting">Hi, <?php echo htmlspecialchars($user['first_name']); ?></span>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="nav-btn">Admin Panel</a>
                        <?php elseif ($user['role'] === 'host'): ?>
                            <a href="host/dashboard.php" class="nav-btn">Dashboard</a>
                        <?php else: ?>
                            <a href="home.php" class="nav-btn">Browse</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-btn-outline">Logout</a>
                    </div>
                <?php else: ?>
                    <button onclick="openModal('loginModal')" class="nav-btn-outline">Sign in</button>
                    <button onclick="openModal('registerModal')" class="nav-btn">Sign up</button>
                <?php endif; ?>
                
                <!-- Theme Toggle -->
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="experiences-hero">
        <div class="experience-icon">✨</div>
        <h1>Discover Amazing Experiences</h1>
        <p>Explore unique properties and unforgettable stays curated just for you</p>
    </section>

    <!-- Experiences Content -->
    <div class="experiences-content">
        <div class="section-header">
            <h2>Featured Experiences</h2>
            <p>Browse our collection of verified properties and amazing places to stay</p>
            <div class="sort-options" style="margin-top: 24px; display: flex; justify-content: center; align-items: center; gap: 12px;">
                <label style="color: #E0E0E0; font-size: 16px;">Sort by:</label>
                <select class="sort-select experience-sort" style="padding: 10px 16px; background: #2C2C2C; color: #FFFFFF; border: 2px solid #3A3A3A; border-radius: 8px; font-size: 14px; cursor: pointer;">
                    <option value="popular">Popular</option>
                    <option value="price-low">Price: Low to High</option>
                    <option value="price-high">Price: High to Low</option>
                    <option value="newest">Newest First</option>
                </select>
            </div>
        </div>

        <div class="experiences-grid">
            <?php if (empty($properties)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏝️</div>
                    <h3>No Experiences Available Yet</h3>
                    <p>Check back soon for amazing properties and experiences!</p>
                    <a href="home.php" class="btn-home">Back to Home</a>
                </div>
            <?php else: ?>
                <?php foreach ($properties as $property): ?>
                    <div class="experience-card" onclick="openPropertyModal(<?php echo $property['id']; ?>)" data-price="<?php echo $property['price_per_night']; ?>" data-date="<?php echo $property['created_at']; ?>">
                        <div class="experience-image">
                            <?php if (!empty($property['primary_photo'])): 
                                $photo_path = ltrim($property['primary_photo'], '/');
                            ?>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                                     alt="<?php echo htmlspecialchars($property['title']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='🏠';">
                            <?php else: ?>
                                <div style="font-size: 80px; text-align: center; padding: 40px;">🏠</div>
                            <?php endif; ?>
                            <div class="experience-badge">Verified</div>
                        </div>
                        <div class="experience-info">
                            <h3 class="experience-title"><?php echo htmlspecialchars($property['title']); ?></h3>
                            <div class="experience-location">
                                📍 <?php echo htmlspecialchars($property['city'] . ', ' . $property['country']); ?>
                            </div>
                            
                            <?php if (isset($property_amenities[$property['id']]) && $property_amenities[$property['id']] > 0): ?>
                                <div class="amenities-count">
                                    ⭐ <?php echo $property_amenities[$property['id']]; ?> amenities
                                </div>
                            <?php endif; ?>
                            
                            <div class="experience-details">
                                <div>
                                    <div class="experience-price price-amount">
                                        ₱<?php echo number_format($property['price_per_night'], 0); ?>
                                    </div>
                                    <div class="experience-price-label">per night</div>
                                </div>
                                <span class="experience-host">Hosted by <?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('loginModal')"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
            
            <div class="modal-header">
                <div style="font-size: 48px; margin-bottom: 10px;">🔐</div>
                <h2>Welcome back</h2>
                <p>Log in to your account</p>
            </div>

            <form class="modal-form" method="POST" action="login.php">
                <div class="form-group">
                    <label for="modal_login_email">Email</label>
                    <input type="email" id="modal_login_email" name="email" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="modal_login_password">Password</label>
                    <input type="password" id="modal_login_password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-primary">Log in</button>
            </form>

            <div class="modal-divider">
                <span>or</span>
            </div>

            <div class="social-buttons">
                <button class="modal-btn-social" onclick="alert('Social login coming soon!')">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>
            </div>

            <div class="modal-footer">
                <p>Don't have an account? <a href="#" onclick="switchModal('loginModal', 'registerModal'); return false;">Sign up</a></p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('registerModal')"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('registerModal')">&times;</button>
            
            <div class="modal-header">
                <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
                <h2>Create an account</h2>
                <p>Join ServePro today</p>
            </div>

            <form class="modal-form" method="POST" action="register-handler.php">
                <div class="form-group">
                    <label for="modal_first_name">First Name</label>
                    <input type="text" id="modal_first_name" name="first_name" placeholder="John" required>
                </div>

                <div class="form-group">
                    <label for="modal_last_name">Last Name</label>
                    <input type="text" id="modal_last_name" name="last_name" placeholder="Doe" required>
                </div>

                <div class="form-group">
                    <label for="modal_email">Email</label>
                    <input type="email" id="modal_email" name="email" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="modal_role">I want to</label>
                    <select id="modal_role" name="role" onchange="showRoleInfo(this.value)" required>
                        <option value="guest">Book properties (Guest)</option>
                        <option value="host">List my properties (Host)</option>
                    </select>
                </div>

                <div id="modal_role_info" class="role-info guest" style="display: block;">
                    <strong>As a Guest:</strong> Browse and book amazing properties worldwide. Save favorites and manage your bookings easily.
                </div>

                <div class="form-group">
                    <label for="modal_password">Password</label>
                    <input type="password" id="modal_password" name="password" placeholder="Create a strong password" required>
                </div>

                <div class="form-group">
                    <label for="modal_confirm_password">Confirm Password</label>
                    <input type="password" id="modal_confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="btn-primary">Create Account</button>
            </form>

            <div class="modal-divider">
                <span>or</span>
            </div>

            <div class="social-buttons">
                <button class="modal-btn-social" onclick="alert('Social signup coming soon!')">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>
            </div>

            <div class="modal-footer">
                <p>Already have an account? <a href="#" onclick="switchModal('registerModal', 'loginModal'); return false;">Sign in</a></p>
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
    <script src="assets/js/landing.js"></script>
    <script src="assets/js/modal.js"></script>
    <script src="assets/js/property-modal.js?v=3.0"></script>
    <script>
        // Sort functionality for experiences page
        const experienceSort = document.querySelector('.experience-sort');
        const experiencesGrid = document.querySelector('.experiences-grid');
        
        if (experienceSort && experiencesGrid) {
            experienceSort.addEventListener('change', function() {
                const sortValue = this.value;
                const cards = Array.from(experiencesGrid.querySelectorAll('.experience-card'));
                
                // Sort the cards based on selected option
                cards.sort((a, b) => {
                    switch(sortValue) {
                        case 'price-low':
                            const priceA = parseFloat(a.getAttribute('data-price'));
                            const priceB = parseFloat(b.getAttribute('data-price'));
                            return priceA - priceB;
                            
                        case 'price-high':
                            const priceA2 = parseFloat(a.getAttribute('data-price'));
                            const priceB2 = parseFloat(b.getAttribute('data-price'));
                            return priceB2 - priceA2;
                            
                        case 'newest':
                            const dateA = new Date(a.getAttribute('data-date'));
                            const dateB = new Date(b.getAttribute('data-date'));
                            return dateB - dateA;
                            
                        case 'popular':
                        default:
                            return 0;
                    }
                });
                
                // Re-append cards in new order with animation
                cards.forEach(card => {
                    card.style.animation = 'fadeIn 0.3s ease-in-out';
                    experiencesGrid.appendChild(card);
                });
            });
        }
    </script>
</body>
</html>
