<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

// Hosts must complete verification before adding properties
if ($user && $user['role'] === 'host' && empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$errors = [];
$success = false;

// Get all amenities
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM amenities ORDER BY category, name");
$amenities = [];
while ($row = $result->fetch_assoc()) {
    $amenities[$row['category']][] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $property_type = $_POST['property_type'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $latitude_input = trim($_POST['latitude'] ?? '');
    $longitude_input = trim($_POST['longitude'] ?? '');
    $price = floatval($_POST['price_per_night'] ?? 0);
    $max_guests = intval($_POST['max_guests'] ?? 0);
    $bedrooms = intval($_POST['bedrooms'] ?? 0);
    $bathrooms = intval($_POST['bathrooms'] ?? 0);
    $selected_amenities = $_POST['amenities'] ?? [];
    
    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if (empty($property_type)) $errors[] = "Property type is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($country)) $errors[] = "Country is required";
    if ($price <= 0) $errors[] = "Valid price is required";
    if ($max_guests <= 0) $errors[] = "Number of guests is required";
    if ($bedrooms <= 0) $errors[] = "Number of bedrooms is required";
    if ($bathrooms <= 0) $errors[] = "Number of bathrooms is required";
    
    // Optional: basic validation for coordinates if provided
    $latitude = $latitude_input === '' ? 0 : floatval($latitude_input);
    $longitude = $longitude_input === '' ? 0 : floatval($longitude_input);
    
    if (empty($errors)) {
        // Insert property (with optional latitude/longitude for precise map pin)
        $stmt = $conn->prepare("INSERT INTO properties (host_id, title, description, property_type, address, city, country, price_per_night, max_guests, bedrooms, bathrooms, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("issssssdiiddd", $user['id'], $title, $description, $property_type, $address, $city, $country, $price, $max_guests, $bedrooms, $bathrooms, $latitude, $longitude);
        
        if ($stmt->execute()) {
            $property_id = $stmt->insert_id;
            
            // Insert amenities
            if (!empty($selected_amenities)) {
                $stmt = $conn->prepare("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)");
                foreach ($selected_amenities as $amenity_id) {
                    $stmt->bind_param("ii", $property_id, $amenity_id);
                    $stmt->execute();
                }
            }
            
            // Handle photo uploads
            $upload_errors = [];
            if (isset($_FILES['property_photos'])) {
                // Store uploads inside this project directory so 'uploads/properties/...' URLs work reliably
                $upload_dir = dirname(__DIR__) . '/uploads/properties/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                    chmod($upload_dir, 0777);
                }
                
                // Debug: Check if directory is writable
                if (!is_writable($upload_dir)) {
                    $errors[] = "Upload directory is not writable: " . $upload_dir;
                }
                
                // Check if files were uploaded
                if (!empty($_FILES['property_photos']['name'][0])) {
                    $photo_count = count($_FILES['property_photos']['name']);
                    $is_primary = 1;
                    
                    for ($i = 0; $i < $photo_count && $i < 5; $i++) {
                        if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_tmp = $_FILES['property_photos']['tmp_name'][$i];
                            $file_name = $_FILES['property_photos']['name'][$i];
                            $file_size = $_FILES['property_photos']['size'][$i];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            
                            // Validate file
                            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            $max_size = 5 * 1024 * 1024; // 5MB
                            
                            if (in_array($file_ext, $allowed_ext) && $file_size <= $max_size) {
                                // Generate unique filename
                                $new_filename = 'property_' . $property_id . '_' . time() . '_' . $i . '.' . $file_ext;
                                $upload_path = $upload_dir . $new_filename;
                                $photo_url = 'uploads/properties/' . $new_filename;
                                
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    // Verify file was actually created
                                    if (file_exists($upload_path)) {
                                        // Insert into database
                                        $photo_stmt = $conn->prepare("INSERT INTO property_photos (property_id, photo_url, is_primary) VALUES (?, ?, ?)");
                                        $photo_stmt->bind_param("isi", $property_id, $photo_url, $is_primary);
                                        $photo_stmt->execute();
                                        $photo_stmt->close();
                                        
                                        // Only first photo is primary
                                        $is_primary = 0;
                                    } else {
                                        $upload_errors[] = "File upload succeeded but file not found: " . $file_name;
                                    }
                                } else {
                                    $upload_errors[] = "Failed to move uploaded file: " . $file_name . " (Temp: " . $file_tmp . ", Target: " . $upload_path . ")";
                                }
                            } else {
                                $upload_errors[] = "Invalid file: " . $file_name;
                            }
                        }
                    }
                }
            }
            
            // Surface any upload-specific errors to the user
            if (!empty($upload_errors)) {
                $errors = array_merge($errors, $upload_errors);
            }
            
            if (empty($errors)) {
                $success = true;
                header('Location: dashboard.php?success=property_added');
                exit();
            }
        } else {
            $errors[] = "Failed to create property. Please try again.";
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Add Property - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/add-property.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=14.0">
</head>
<body class="dashboard-page">
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
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item active">
                    <span class="nav-icon">➕</span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon">📅</span>
                    <span>Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon">💰</span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon">💬</span>
                    <span>Messages</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon">🌐</span>
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
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="host-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="../background%20image/y.webp" alt="Add property icon" style="width:40px; height:40px; border-radius:8px; object-fit:cover;">
                    <div>
                        <h1>Add New Property</h1>
                        <p class="subtitle">List your place and start hosting</p>
                    </div>
                </div>
                <!-- Theme Toggle -->
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <h4>Please fix the following errors:</h4>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Multi-step wizard wrapper -->
            <form method="POST" action="add-property.php" class="property-form" enctype="multipart/form-data" id="addPropertyForm">
                <div class="wizard-steps-indicator">
                    <span class="wizard-step-dot wizard-step-dot-active" data-step="1">1</span>
                    <span class="wizard-step-label">Basics</span>
                    <span class="wizard-step-dot" data-step="2">2</span>
                    <span class="wizard-step-label">Details</span>
                    <span class="wizard-step-dot" data-step="3">3</span>
                    <span class="wizard-step-label">Photos</span>
                </div>

                <!-- Basic Information -->
                <div class="form-section wizard-step step-1">
                    <h2 class="section-title">📝 Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="title">Property Title *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="Beautiful 2BR Apartment in the City" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" rows="5" placeholder="Describe your property..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <select id="property_type" name="property_type" required>
                                <option value="">Select type</option>
                                <option value="house" <?php echo ($_POST['property_type'] ?? '') === 'house' ? 'selected' : ''; ?>>House</option>
                                <option value="apartment" <?php echo ($_POST['property_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                                <option value="condo" <?php echo ($_POST['property_type'] ?? '') === 'condo' ? 'selected' : ''; ?>>Condo</option>
                                <option value="villa" <?php echo ($_POST['property_type'] ?? '') === 'villa' ? 'selected' : ''; ?>>Villa</option>
                                <option value="hotel" <?php echo ($_POST['property_type'] ?? '') === 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="price_per_night">Price per Night (₱) *</label>
                            <input type="number" id="price_per_night" name="price_per_night" value="<?php echo htmlspecialchars($_POST['price_per_night'] ?? ''); ?>" min="0" step="0.01" placeholder="1500.00" required>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-section wizard-step step-1">
                    <h2 class="section-title">📍 Location</h2>
                    
                    <div class="form-group">
                        <label for="address">Full Address *</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" placeholder="123 Main Street" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" placeholder="Manila" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="country">Country *</label>
                            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>" placeholder="Philippines" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="latitude">Latitude (optional)</label>
                            <input type="text" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>" placeholder="14.5995">
                            <small class="helper-text">Paste coordinates from a map for an exact pin (e.g. from Google Maps).</small>
                        </div>
                        <div class="form-group">
                            <label for="longitude">Longitude (optional)</label>
                            <input type="text" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>" placeholder="120.9842">
                        </div>
                    </div>
                </div>

                <!-- Property Details -->
                <div class="form-section wizard-step step-2">
                    <h2 class="section-title">🛏️ Property Details</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_guests">Maximum Guests *</label>
                            <input type="number" id="max_guests" name="max_guests" value="<?php echo htmlspecialchars($_POST['max_guests'] ?? ''); ?>" min="1" placeholder="4" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms *</label>
                            <input type="number" id="bedrooms" name="bedrooms" value="<?php echo htmlspecialchars($_POST['bedrooms'] ?? ''); ?>" min="1" placeholder="2" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms *</label>
                            <input type="number" id="bathrooms" name="bathrooms" value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? ''); ?>" min="1" placeholder="1" required>
                        </div>
                    </div>
                </div>

                <!-- Amenities -->
                <div class="form-section wizard-step step-2">
                    <h2 class="section-title">✨ Amenities</h2>
                    <p class="section-description">Select all amenities available at your property</p>
                    
                    <?php foreach ($amenities as $category => $category_amenities): ?>
                    <div class="amenities-category">
                        <h3 class="category-title"><?php echo ucfirst($category); ?></h3>
                        <div class="amenities-grid">
                            <?php foreach ($category_amenities as $amenity): ?>
                            <label class="amenity-checkbox">
                                <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>" 
                                    <?php echo in_array($amenity['id'], $_POST['amenities'] ?? []) ? 'checked' : ''; ?>>
                                <span class="amenity-label">
                                    <span class="amenity-icon"><?php echo $amenity['icon']; ?></span>
                                    <span class="amenity-name"><?php echo htmlspecialchars($amenity['name']); ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Photos Section -->
                <div class="form-section wizard-step step-3">
                    <h2 class="section-title">📸 Property Photos</h2>
                    <p class="section-description">Upload high-quality photos of your property (Maximum 5 photos)</p>
                    
                    <div class="photo-upload-container">
                        <div class="photo-upload-area" id="photoUploadArea">
                            <div class="upload-icon">📷</div>
                            <h3>Click to Upload Photos</h3>
                            <p>Or drag and drop images here</p>
                            <p class="upload-hint">Supported: JPG, PNG (Max 5MB each)</p>
                        </div>
                        
                        <input type="file" id="propertyPhotos" name="property_photos[]" multiple accept="image/*" style="display: none;">
                        
                        <!-- Backup visible button -->
                        <div style="text-align: center; margin-top: 16px;">
                            <label for="propertyPhotos" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #D4A574, #B8935F); color: #0F0F0F; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                📁 Choose Files
                            </label>
                        </div>
                        
                        <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                    </div>
                    
                    <div class="primary-photo-note">
                        <p>💡 <strong>Tip:</strong> The first photo will be set as the primary photo displayed in listings.</p>
                    </div>
                    
                    <div id="uploadStatus" style="margin-top: 16px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3B82F6; border-radius: 8px; display: none;">
                        <p style="color: #3B82F6 !important; margin: 0; font-size: 14px;">
                            <strong>Ready to upload:</strong> <span id="fileCount">0</span> photo(s) selected
                        </p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="form-actions">
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    <button type="button" class="btn-secondary" id="wizardBackBtn" style="display:none;">Back</button>
                    <button type="button" class="btn-primary" id="wizardNextBtn">Next</button>
                    <button type="submit" class="btn-primary" id="wizardSubmitBtn" style="display:none;">Submit for Review</button>
                </div>
                
                <div class="form-note">
                    <p><strong>Note:</strong> Your property will be reviewed by our admin team before it goes live. You'll receive a notification once it's approved.</p>
                </div>
            </form>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        console.log('🎬 Photo upload script loading...');
        
        // Photo Upload Functionality
        const photoUploadArea = document.getElementById('photoUploadArea');
        const photoInput = document.getElementById('propertyPhotos');
        const photoPreviewGrid = document.getElementById('photoPreviewGrid');
        let selectedFiles = [];

        console.log('Elements:', {
            photoUploadArea: !!photoUploadArea,
            photoInput: !!photoInput,
            photoPreviewGrid: !!photoPreviewGrid
        });

        // Make sure elements exist
        if (photoUploadArea && photoInput && photoPreviewGrid) {
            console.log('✅ All elements found, setting up listeners...');
            
            // Click to upload
            photoUploadArea.addEventListener('click', function(e) {
                console.log('📸 Upload area clicked!');
                e.preventDefault();
                e.stopPropagation();
                photoInput.click();
            });

            // File input change
            photoInput.addEventListener('change', function(e) {
                console.log('📁 Files selected:', e.target.files.length);
                handleFiles(e.target.files);
            });

        // Drag and drop
        photoUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            photoUploadArea.classList.add('dragover');
        });

        photoUploadArea.addEventListener('dragleave', () => {
            photoUploadArea.classList.remove('dragover');
        });

        photoUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            photoUploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        function handleFiles(files) {
            const maxFiles = 5;
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            // Convert FileList to Array and filter
            const newFiles = Array.from(files).filter(file => {
                // Check file type
                if (!file.type.startsWith('image/')) {
                    alert('Please upload only image files.');
                    return false;
                }
                
                // Check file size
                if (file.size > maxSize) {
                    alert(`${file.name} is too large. Maximum size is 5MB.`);
                    return false;
                }
                
                return true;
            });

            // Check total files limit
            if (selectedFiles.length + newFiles.length > maxFiles) {
                alert(`You can only upload a maximum of ${maxFiles} photos.`);
                return;
            }

            // Add new files to selected files
            selectedFiles = [...selectedFiles, ...newFiles];
            updatePhotoPreview();
        }

        function updatePhotoPreview() {
            photoPreviewGrid.innerHTML = '';
            
            // Update status
            const uploadStatus = document.getElementById('uploadStatus');
            const fileCount = document.getElementById('fileCount');
            if (selectedFiles.length > 0) {
                uploadStatus.style.display = 'block';
                fileCount.textContent = selectedFiles.length;
            } else {
                uploadStatus.style.display = 'none';
            }
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = (e) => {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'photo-preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="Property photo ${index + 1}">
                        ${index === 0 ? '<span class="photo-preview-badge">Primary</span>' : ''}
                        <button type="button" class="photo-remove-btn" onclick="removePhoto(${index})">&times;</button>
                    `;
                    photoPreviewGrid.appendChild(previewItem);
                };
                
                reader.readAsDataURL(file);
            });

            // Update file input
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => {
                dataTransfer.items.add(file);
            });
            photoInput.files = dataTransfer.files;
        }

        function removePhoto(index) {
            selectedFiles.splice(index, 1);
            updatePhotoPreview();
        }
        
        } else {
            console.error('❌ Elements not found!', {
                photoUploadArea: !!photoUploadArea,
                photoInput: !!photoInput,
                photoPreviewGrid: !!photoPreviewGrid
            });
        }

        // Simple multi-step wizard logic
        (function() {
            const steps = Array.from(document.querySelectorAll('.wizard-step'));
            if (!steps.length) return;
            let currentStep = 1;
            const maxStep = 3;
            const backBtn = document.getElementById('wizardBackBtn');
            const nextBtn = document.getElementById('wizardNextBtn');
            const submitBtn = document.getElementById('wizardSubmitBtn');
            const dots = Array.from(document.querySelectorAll('.wizard-step-dot'));

            function updateUI() {
                steps.forEach(s => {
                    s.classList.remove('wizard-step-active');
                    if (s.classList.contains('step-' + currentStep)) {
                        s.classList.add('wizard-step-active');
                    }
                });
                dots.forEach(d => {
                    const step = parseInt(d.getAttribute('data-step') || '0', 10);
                    d.classList.toggle('wizard-step-dot-active', step === currentStep);
                });
                if (backBtn) backBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
                if (nextBtn) nextBtn.style.display = currentStep < maxStep ? 'inline-block' : 'none';
                if (submitBtn) submitBtn.style.display = currentStep === maxStep ? 'inline-block' : 'none';
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (currentStep < maxStep) {
                        currentStep++;
                        updateUI();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    if (currentStep > 1) {
                        currentStep--;
                        updateUI();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }

            updateUI();
        })();
    </script>
</body>
</html>
