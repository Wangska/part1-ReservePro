<?php
require_once __DIR__ . '/config/session.php';
$user = isLoggedIn() ? getCurrentUser() : null;

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($errors)) {
        // In a real application, you would send an email or save to database here
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=8.0">
    <link rel="stylesheet" href="assets/css/landing.css?v=8.0">
    <link rel="stylesheet" href="assets/css/modal.css?v=8.0">
    <link rel="stylesheet" href="assets/css/role-select.css?v=8.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
    <link rel="stylesheet" href="assets/css/animations.css?v=1.0">
    <style>
        .contact-hero {
            background: linear-gradient(135deg, #2C1810 0%, #3E2723 50%, #0F0F0F 100%);
            padding: 100px 20px 80px;
            text-align: center;
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .contact-hero h1 {
            font-size: 56px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #FFFFFF !important;
        }
        
        .contact-hero p {
            font-size: 22px;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
            color: #E0E0E0 !important;
        }
        
        .contact-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .contact-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 20px;
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }
        
        @media (max-width: 968px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .contact-info h2 {
            font-size: 36px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin-bottom: 20px;
        }
        
        .contact-info p {
            font-size: 18px;
            color: #E0E0E0 !important;
            line-height: 1.8;
            margin-bottom: 40px;
        }
        
        .info-cards {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .info-card {
            background: #1F1F1F;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s ease;
            border: 1px solid #3A3A3A;
        }
        
        .info-card:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.15);
        }
        
        .info-icon {
            font-size: 40px;
            flex-shrink: 0;
        }
        
        .info-content h3 {
            font-size: 20px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 8px;
        }
        
        .info-content p {
            font-size: 16px;
            color: #D0D0D0 !important;
            margin: 0;
        }
        
        .contact-form-wrapper {
            background: #1F1F1F;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.4);
            border: 1px solid #3A3A3A;
        }
        
        .contact-form-wrapper h2 {
            font-size: 32px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin-bottom: 24px;
        }
        
        .success-message {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-weight: 500;
        }
        
        .contact-form .form-group {
            margin-bottom: 24px;
        }
        
        .contact-form label {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 8px;
        }
        
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #3A3A3A;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s;
            font-family: inherit;
            background: #2C2C2C;
            color: #FFFFFF;
        }
        
        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: #D4A574;
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }
        
        .contact-form input::placeholder,
        .contact-form textarea::placeholder {
            color: #6B6B6B;
        }
        
        .contact-form textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .contact-form .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .contact-form .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
        }
        
        .faq-section {
            margin-top: 80px;
        }
        
        .faq-section h2 {
            font-size: 40px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 32px;
        }
        
        .faq-item {
            background: #1F1F1F;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.4);
            border: 1px solid #3A3A3A;
        }
        
        .faq-question {
            font-size: 20px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .faq-answer {
            font-size: 16px;
            color: #D0D0D0 !important;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="home.php" class="brand">
                    <?php require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                    <span class="brand-name">ReservePro</span>
                </a>
                <div class="nav-links">
                    <a href="home.php">Home</a>
                    <a href="experiences.php">Experiences</a>
                    <a href="about.php">About</a>
                    <a href="contact.php" class="active">Contact</a>
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
    <section class="contact-hero">
        <div class="contact-icon">💬</div>
        <h1>Get in Touch</h1>
        <p>Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
    </section>

    <!-- Contact Content -->
    <div class="contact-content">
        <div class="contact-grid">
            <!-- Contact Information -->
            <div class="contact-info">
                <h2>Let's Connect</h2>
                <p>Whether you're a host looking to list your property or a guest seeking the perfect stay, we're here to help.</p>
                
                <div class="info-cards">
                    <div class="info-card">
                        <div class="info-icon">📧</div>
                        <div class="info-content">
                            <h3>Email Us</h3>
                            <p>support@servepro.com<br>We'll respond within 24 hours</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon">📱</div>
                        <div class="info-content">
                            <h3>Call Us</h3>
                            <p>+1 (555) 123-4567<br>Mon-Fri, 9AM-6PM EST</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon">📍</div>
                        <div class="info-content">
                            <h3>Visit Us</h3>
                            <p>Poblacion, Ward II, Minglanilla<br>Cebu 6046, Philippines</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon">💬</div>
                        <div class="info-content">
                            <h3>Live Chat</h3>
                            <p>Available 24/7<br>Instant support when you need it</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form-wrapper">
                <h2>Send us a Message</h2>
                
                <?php if ($success): ?>
                    <div class="success-message">
                        ✓ Thank you! Your message has been sent successfully. We'll get back to you soon.
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form class="contact-form" method="POST">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" placeholder="How can we help?" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Tell us more about your inquiry..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question">
                        ❓ How do I list my property?
                    </div>
                    <div class="faq-answer">
                        Simply sign up as a host, complete your profile, and use our easy-to-follow listing wizard to add your property details, photos, and pricing.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        ❓ What are the fees?
                    </div>
                    <div class="faq-answer">
                        We charge a small service fee on each booking. Hosts keep the majority of their earnings. No hidden fees - everything is transparent.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        ❓ Is my payment secure?
                    </div>
                    <div class="faq-answer">
                        Yes! We use industry-standard encryption and secure payment processing. Your financial information is always protected.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        ❓ Can I cancel a booking?
                    </div>
                    <div class="faq-answer">
                        Cancellation policies vary by property. Check the specific listing's policy before booking. Most hosts offer flexible cancellation options.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        ❓ How do I contact a host?
                    </div>
                    <div class="faq-answer">
                        Once you create an account, you can message hosts directly through our platform to ask questions before booking.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        ❓ What if I have an issue?
                    </div>
                    <div class="faq-answer">
                        Our 24/7 support team is here to help. Contact us anytime via email, phone, or live chat for immediate assistance.
                    </div>
                </div>
            </div>
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
                <p>Join ReservePro today</p>
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
