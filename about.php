<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
$user = isLoggedIn() ? getCurrentUser() : null;

// Fetch real statistics from database
$conn = getDBConnection();

// Total properties
$result = $conn->query("SELECT COUNT(*) as total FROM properties");
$total_properties = $result->fetch_assoc()['total'];

// Total users (guests and hosts)
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_users = $result->fetch_assoc()['total'];

// Total bookings
$result = $conn->query("SELECT COUNT(*) as total FROM bookings");
$total_bookings = $result->fetch_assoc()['total'];

// Count distinct locations (cities)
$result = $conn->query("SELECT COUNT(DISTINCT city) as total FROM properties");
$total_locations = $result->fetch_assoc()['total'];

// Calculate average rating (if you have ratings, otherwise default to 4.9)
// For now, we'll use a default since we don't have a ratings table yet
$average_rating = 4.9;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - ServePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=8.0">
    <link rel="stylesheet" href="assets/css/landing.css?v=8.0">
    <link rel="stylesheet" href="assets/css/modal.css?v=8.0">
    <link rel="stylesheet" href="assets/css/role-select.css?v=8.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
    <style>
        .about-hero {
            background: linear-gradient(135deg, #2C1810 0%, #3E2723 50%, #0F0F0F 100%);
            padding: 100px 20px 80px;
            text-align: center;
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .about-hero h1 {
            font-size: 56px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #FFFFFF !important;
        }
        
        .about-hero p {
            font-size: 22px;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
            color: #E0E0E0 !important;
        }
        
        .about-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .about-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 20px;
        }
        
        .about-section {
            margin-bottom: 80px;
        }
        
        .about-section h2 {
            font-size: 40px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .about-section p {
            font-size: 18px;
            color: #E0E0E0 !important;
            line-height: 1.8;
            text-align: center;
            max-width: 800px;
            margin: 0 auto 40px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 32px;
            margin-top: 60px;
        }
        
        .feature-card {
            background: #1F1F1F;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.4);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #3A3A3A;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(212, 165, 116, 0.3);
            border-color: #D4A574;
        }
        
        .feature-icon {
            font-size: 56px;
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            font-size: 24px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 12px;
        }
        
        .feature-card p {
            font-size: 16px;
            color: #D0D0D0 !important;
            line-height: 1.6;
        }
        
        .stats-section {
            background: linear-gradient(135deg, #F3F4F6, #E5E7EB);
            padding: 60px 20px;
            border-radius: 24px;
            margin: 80px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 48px;
            font-weight: 700;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 16px;
            color: #D0D0D0 !important;
            font-weight: 500;
        }
        
        .team-section {
            text-align: center;
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }
        
        .team-member {
            text-align: center;
        }
        
        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.2);
        }
        
        .team-member h3 {
            font-size: 22px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 8px;
        }
        
        .team-role {
            font-size: 16px;
            color: #D4A574;
            font-weight: 500;
            margin-bottom: 12px;
        }
        
        .team-bio {
            font-size: 15px;
            color: #D0D0D0 !important;
            line-height: 1.6;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #2C1810, #3E2723);
            padding: 80px 20px;
            border-radius: 24px;
            text-align: center;
            color: white;
        }
        
        .cta-section h2 {
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-section p {
            font-size: 18px;
            opacity: 0.95;
            margin-bottom: 32px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            padding: 16px 40px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
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
                    <a href="experiences.php">Experiences</a>
                    <a href="about.php" class="active">About</a>
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
    <section class="about-hero">
        <div class="about-icon">🌟</div>
        <h1>About ServePro</h1>
        <p>Connecting hosts and guests worldwide through exceptional property experiences</p>
    </section>

    <!-- About Content -->
    <div class="about-content">
        <!-- Mission Section -->
        <div class="about-section">
            <h2>Our Mission</h2>
            <p>At ServePro, we believe everyone deserves access to amazing places and unique experiences. We're building a platform that connects property owners with travelers, creating memorable stays and sustainable income opportunities for hosts around the world.</p>
        </div>

        <!-- Features -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🏠</div>
                <h3>Verified Properties</h3>
                <p>Every listing is carefully reviewed and verified to ensure quality and authenticity.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Bookings</h3>
                <p>Your transactions are protected with enterprise-grade security and encryption.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💬</div>
                <h3>24/7 Support</h3>
                <p>Our dedicated team is always ready to assist you with any questions or concerns.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">⭐</div>
                <h3>Best Experiences</h3>
                <p>Curated properties and hosts to ensure you have the best possible stay.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Fair Pricing</h3>
                <p>Transparent pricing with no hidden fees. What you see is what you pay.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🌍</div>
                <h3>Global Reach</h3>
                <p>Discover properties in amazing destinations around the world.</p>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($total_properties); ?></div>
                    <div class="stat-label">Properties Listed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Happy Users</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($total_bookings); ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($average_rating, 1); ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>
        </div>

        <!-- Team Section -->
        <div class="team-section">
            <h2>Meet Our Team</h2>
            <p>Passionate individuals dedicated to revolutionizing the property rental experience</p>
            
            <div class="team-grid">
                <div class="team-member">
                    <div class="team-avatar">👨‍💼</div>
                    <h3>John Martinez</h3>
                    <div class="team-role">CEO & Founder</div>
                    <p class="team-bio">Visionary leader with 15+ years in hospitality and tech innovation.</p>
                </div>
                
                <div class="team-member">
                    <div class="team-avatar">👩‍💻</div>
                    <h3>Sarah Chen</h3>
                    <div class="team-role">CTO</div>
                    <p class="team-bio">Technology expert building secure, scalable platforms for millions of users.</p>
                </div>
                
                <div class="team-member">
                    <div class="team-avatar">👨‍🎨</div>
                    <h3>Mike Johnson</h3>
                    <div class="team-role">Head of Design</div>
                    <p class="team-bio">Creating beautiful, intuitive experiences that users love.</p>
                </div>
                
                <div class="team-member">
                    <div class="team-avatar">👩‍💼</div>
                    <h3>Lisa Wang</h3>
                    <div class="team-role">Customer Success</div>
                    <p class="team-bio">Ensuring every host and guest has an exceptional experience.</p>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="cta-section">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of hosts and guests who trust ServePro for their property rental needs</p>
            <a href="home.php" class="cta-button">Explore Properties</a>
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

    <script src="assets/js/theme-toggle.js"></script>
    <script src="assets/js/landing.js"></script>
    <script src="assets/js/modal.js"></script>
</body>
</html>
