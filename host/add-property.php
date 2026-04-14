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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/add-property.css?v=17.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
</head>
<body class="dashboard-page host-clean-page host-form-page">
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
                    <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                    <span>Dashboard</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item active">
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
                    <span class="theme-toggle-icon"><i class="fa-solid fa-sun"></i></span>
                    <span class="theme-toggle-text">Light</span>
                </div>
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="host-header host-page-hero">
                <div class="host-page-hero-content">
                    <span class="host-page-eyebrow">Listing Setup</span>
                    <h1>Add New Property</h1>
                    <p class="subtitle">Create a complete listing with pricing, details, map pin, and photos so it is ready for review the first time.</p>
                </div>
                <div style="display:flex; align-items:flex-start; gap:14px; margin-left:auto;">
                    <div class="host-page-summary">
                        <span class="host-page-summary-label">Submission Flow</span>
                        <strong>3</strong>
                        <span class="host-page-summary-text">steps to complete before sending for review</span>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="ap-alert-error">
                <h4>Please fix the following errors:</h4>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="host-form-shell">
            <form method="POST" action="add-property.php" class="property-form" enctype="multipart/form-data" id="addPropertyForm" novalidate>

                <!-- Stepper -->
                <div class="ap-stepper">
                    <div class="ap-stepper-step is-active" data-step="1">
                        <div class="ap-step-circle">
                            <span class="ap-step-num">1</span>
                            <svg class="ap-step-check" width="14" height="14" viewBox="0 0 14 14" fill="none"><polyline points="2 7 5.5 10.5 12 3.5" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="ap-step-label">Basics</span>
                    </div>
                    <div class="ap-stepper-line" id="apLine1"></div>
                    <div class="ap-stepper-step" data-step="2">
                        <div class="ap-step-circle">
                            <span class="ap-step-num">2</span>
                            <svg class="ap-step-check" width="14" height="14" viewBox="0 0 14 14" fill="none"><polyline points="2 7 5.5 10.5 12 3.5" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="ap-step-label">Details</span>
                    </div>
                    <div class="ap-stepper-line" id="apLine2"></div>
                    <div class="ap-stepper-step" data-step="3">
                        <div class="ap-step-circle">
                            <span class="ap-step-num">3</span>
                            <svg class="ap-step-check" width="14" height="14" viewBox="0 0 14 14" fill="none"><polyline points="2 7 5.5 10.5 12 3.5" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="ap-step-label">Photos</span>
                    </div>
                </div>

                <!-- Slide viewport -->
                <div class="ap-wizard-viewport">
                    <div class="ap-wizard-track" id="apWizardTrack">

                        <!-- PANEL 1 : BASICS -->
                        <div class="ap-wizard-panel">

                            <div class="ap-section">
                                <h2 class="ap-section-title"><i class="fa-solid fa-pen-to-square"></i> Basic Information</h2>
                                <p class="ap-section-desc">Give your listing a title that stands out and describe what makes it special.</p>

                                <div class="ap-field">
                                    <label for="title">Property Title <span style="color:#EF4444">*</span></label>
                                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="Cozy 2BR Beachfront Villa in Palawan" required>
                                </div>

                                <div class="ap-field">
                                    <label for="description">Description <span style="color:#EF4444">*</span></label>
                                    <textarea id="description" name="description" rows="5" placeholder="Describe the space, neighbourhood, and what guests will love..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="ap-row">
                                    <div class="ap-field">
                                        <label for="property_type">Property Type <span style="color:#EF4444">*</span></label>
                                        <select id="property_type" name="property_type" required>
                                            <option value="">Select type</option>
                                            <option value="house" <?php echo ($_POST['property_type'] ?? '') === 'house' ? 'selected' : ''; ?>>House</option>
                                            <option value="apartment" <?php echo ($_POST['property_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                                            <option value="condo" <?php echo ($_POST['property_type'] ?? '') === 'condo' ? 'selected' : ''; ?>>Condo</option>
                                            <option value="villa" <?php echo ($_POST['property_type'] ?? '') === 'villa' ? 'selected' : ''; ?>>Villa</option>
                                            <option value="hotel" <?php echo ($_POST['property_type'] ?? '') === 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                        </select>
                                    </div>
                                    <div class="ap-field">
                                        <label for="price_per_night">Price per Night (&#8369;) <span style="color:#EF4444">*</span></label>
                                        <input type="number" id="price_per_night" name="price_per_night" value="<?php echo htmlspecialchars($_POST['price_per_night'] ?? ''); ?>" min="0" step="0.01" placeholder="1500.00" required>
                                    </div>
                                </div>
                            </div>

                            <div class="ap-section">
                                <h2 class="ap-section-title"><i class="fa-solid fa-location-dot"></i> Location</h2>
                                <p class="ap-section-desc">Enter your address then fine-tune the map pin so guests can find you easily.</p>

                                <div class="ap-field">
                                    <label for="address">Full Address <span style="color:#EF4444">*</span></label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" placeholder="123 Rizal Street, Poblacion" required>
                                </div>

                                <div class="ap-row">
                                    <div class="ap-field">
                                        <label for="city">City <span style="color:#EF4444">*</span></label>
                                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" placeholder="Manila" required>
                                    </div>
                                    <div class="ap-field">
                                        <label for="country">Country <span style="color:#EF4444">*</span></label>
                                        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>" placeholder="Philippines" required>
                                    </div>
                                </div>

                                <div style="margin-top:16px;">
                                    <div class="ap-row">
                                        <div class="ap-field">
                                            <label for="latitude">Latitude</label>
                                            <input type="number" id="latitude" name="latitude" step="any" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>" placeholder="14.5995">
                                        </div>
                                        <div class="ap-field">
                                            <label for="longitude">Longitude</label>
                                            <input type="number" id="longitude" name="longitude" step="any" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>" placeholder="120.9842">
                                        </div>
                                    </div>
                                </div>

                                <div class="host-property-pin-map-wrap">
                                    <div class="host-property-pin-map-actions">
                                        <button type="button" class="btn-map-geocode" id="hostPropertyPinGeocodeBtn">
                                            <i class="fa-solid fa-map-pin" style="margin-right:6px;font-size:12px;"></i>Place from address
                                        </button>
                                        <p class="host-property-pin-map-hint">Uses your address fields above � drag the pin to fine-tune.</p>
                                    </div>
                                    <p id="hostPropertyPinReverseStatus" class="host-property-pin-map-hint" style="margin: -2px 0 10px 0; opacity: 0; transition: opacity 0.2s ease;"></p>
                                    <div id="hostPropertyPinMap" role="application" aria-label="Map to position property pin"></div>
                                </div>


                            </div>

                        </div><!-- /panel 1 -->

                        <!-- PANEL 2 : DETAILS -->
                        <div class="ap-wizard-panel">

                            <div class="ap-section">
                                <h2 class="ap-section-title"><i class="fa-solid fa-bed"></i> Property Details</h2>
                                <p class="ap-section-desc">Set the guest capacity and room counts for your listing.</p>

                                <div class="ap-row">
                                    <div class="ap-field">
                                        <label for="max_guests">Max Guests <span style="color:#EF4444">*</span></label>
                                        <input type="number" id="max_guests" name="max_guests" value="<?php echo htmlspecialchars($_POST['max_guests'] ?? ''); ?>" min="1" placeholder="4" required>
                                    </div>
                                    <div class="ap-field">
                                        <label for="bedrooms">Bedrooms <span style="color:#EF4444">*</span></label>
                                        <input type="number" id="bedrooms" name="bedrooms" value="<?php echo htmlspecialchars($_POST['bedrooms'] ?? ''); ?>" min="1" placeholder="2" required>
                                    </div>
                                    <div class="ap-field">
                                        <label for="bathrooms">Bathrooms <span style="color:#EF4444">*</span></label>
                                        <input type="number" id="bathrooms" name="bathrooms" value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? ''); ?>" min="1" placeholder="1" required>
                                    </div>
                                </div>
                            </div>

                            <div class="ap-section">
                                <h2 class="ap-section-title"><i class="fa-solid fa-sliders"></i> Amenities</h2>
                                <p class="ap-section-desc">Select everything available at your property.</p>

                                <?php foreach ($amenities as $category => $category_amenities): ?>
                                <div class="ap-amenity-category">
                                    <h3 class="ap-category-title"><?php echo ucfirst($category); ?></h3>
                                    <div class="ap-amenity-grid">
                                        <?php foreach ($category_amenities as $amenity): ?>
                                        <div class="ap-amenity-item">
                                            <input type="checkbox" name="amenities[]"
                                                   id="amenity_<?php echo $amenity['id']; ?>"
                                                   value="<?php echo $amenity['id']; ?>"
                                                   <?php echo in_array($amenity['id'], $_POST['amenities'] ?? []) ? 'checked' : ''; ?>>
                                            <label class="ap-amenity-tile" for="amenity_<?php echo $amenity['id']; ?>">
                                                <span class="ap-amenity-check">
                                                    <svg class="ap-amenity-check-icon" width="10" height="10" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2.5" stroke="#0F0F0F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </span>
                                                <span class="ap-amenity-name"><?php echo htmlspecialchars($amenity['name']); ?></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                        </div><!-- /panel 2 -->

                        <!-- PANEL 3 : PHOTOS -->
                        <div class="ap-wizard-panel">

                            <div class="ap-section">
                                <h2 class="ap-section-title"><i class="fa-solid fa-images"></i> Property Photos</h2>
                                <p class="ap-section-desc">Upload up to 5 high-quality photos. The first photo becomes your primary listing image.</p>

                                <div class="ap-photo-drop" id="photoUploadArea">
                                    <div class="ap-photo-drop-icon">
                                        <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                                            <path d="M32 32L24 24L16 32" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M24 24V42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M40.7 36.7A10 10 0 0 0 34 18h-2.5A16 16 0 1 0 8 32.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <h3>Drag &amp; drop photos here</h3>
                                    <p>or click to browse from your computer</p>
                                    <p class="ap-photo-hint">JPG, PNG, WEBP &mdash; max 5 MB each</p>
                                    <label for="propertyPhotos" class="ap-upload-btn-label">
                                        <i class="fa-solid fa-folder-open"></i> Choose Files
                                    </label>
                                </div>

                                <input type="file" id="propertyPhotos" name="property_photos[]" multiple accept="image/*" style="display:none;">

                                <div class="ap-photo-count-badge" id="uploadStatus">
                                    <i class="fa-solid fa-circle-check" style="color:#22c55e"></i>
                                    <span id="fileCount">0</span> photo(s) selected
                                </div>

                                <div class="ap-photo-grid" id="photoPreviewGrid"></div>

                                <p class="ap-photo-tip"><strong>Tip:</strong> The first photo will be your primary listing image shown in search results.</p>
                            </div>

                        </div><!-- /panel 3 -->

                    </div><!-- /ap-wizard-track -->
                </div><!-- /ap-wizard-viewport -->

                <!-- Action buttons -->
                <div class="ap-form-actions">
                    <a href="dashboard.php" class="ap-btn-cancel">Cancel</a>
                    <button type="button" class="ap-btn-back" id="wizardBackBtn" style="display:none;">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="ap-btn-next" id="wizardNextBtn">
                        Next <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="ap-btn-submit" id="wizardSubmitBtn" style="display:none;">
                        <i class="fa-solid fa-paper-plane"></i> Submit for Review
                    </button>
                </div>

                <p class="ap-form-note"><strong>Note:</strong> Your property will be reviewed by our admin team before it goes live. You will be notified once it is approved.</p>

            </form>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
    <script src="../assets/js/host-property-pin-map.js?v=3"></script>
    <script>
        // Photo upload
        const photoUploadArea = document.getElementById('photoUploadArea');
        const photoInput      = document.getElementById('propertyPhotos');
        const photoGrid       = document.getElementById('photoPreviewGrid');
        let selectedFiles     = [];

        if (photoUploadArea && photoInput && photoGrid) {
            photoUploadArea.addEventListener('click', function(e) {
                if (e.target.closest('label[for="propertyPhotos"]')) return;
                e.preventDefault();
                photoInput.click();
            });
            photoInput.addEventListener('change', function(e) { handleFiles(e.target.files); });
            photoUploadArea.addEventListener('dragover',  (e) => { e.preventDefault(); photoUploadArea.classList.add('dragover'); });
            photoUploadArea.addEventListener('dragleave', ()  => { photoUploadArea.classList.remove('dragover'); });
            photoUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                photoUploadArea.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });
        }

        function handleFiles(files) {
            const maxFiles = 5, maxSize = 5 * 1024 * 1024;
            const newFiles = Array.from(files).filter(f => {
                if (!f.type.startsWith('image/')) { alert('Please upload image files only.'); return false; }
                if (f.size > maxSize)              { alert(f.name + ' exceeds 5 MB.');        return false; }
                return true;
            });
            if (selectedFiles.length + newFiles.length > maxFiles) { alert('Maximum 5 photos allowed.'); return; }
            selectedFiles = [...selectedFiles, ...newFiles];
            renderPreviews();
        }

        function renderPreviews() {
            photoGrid.innerHTML = '';
            const badge   = document.getElementById('uploadStatus');
            const countEl = document.getElementById('fileCount');
            if (badge)   badge.classList.toggle('visible', selectedFiles.length > 0);
            if (countEl) countEl.textContent = selectedFiles.length;
            selectedFiles.forEach((file, i) => {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const thumb = document.createElement('div');
                    thumb.className = 'ap-photo-thumb';
                    thumb.innerHTML = `<img src="${ev.target.result}" alt="Photo ${i + 1}">`
                        + (i === 0 ? '<span class="ap-photo-primary-badge">Primary</span>' : '')
                        + `<button type="button" class="ap-photo-remove" onclick="removePhoto(${i})">&times;</button>`;
                    photoGrid.appendChild(thumb);
                };
                reader.readAsDataURL(file);
            });
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            photoInput.files = dt.files;
        }

        function removePhoto(i) { selectedFiles.splice(i, 1); renderPreviews(); }

        // Multi-step wizard � smooth slide animation
        (function () {
            const track     = document.getElementById('apWizardTrack');
            const formEl    = document.getElementById('addPropertyForm');
            const backBtn   = document.getElementById('wizardBackBtn');
            const nextBtn   = document.getElementById('wizardNextBtn');
            const submitBtn = document.getElementById('wizardSubmitBtn');
            const stepEls   = Array.from(document.querySelectorAll('.ap-stepper-step'));
            const lineEls   = [document.getElementById('apLine1'), document.getElementById('apLine2')];
            const total     = 3;
            let current     = 1;

            function getPanels() {
                return track ? Array.from(track.querySelectorAll('.ap-wizard-panel')) : [];
            }

            function getStepForElement(el) {
                const panels = getPanels();
                if (!el || !panels.length) return 1;
                const panel = el.closest('.ap-wizard-panel');
                const idx = panel ? panels.indexOf(panel) : -1;
                return idx >= 0 ? (idx + 1) : 1;
            }

            function goTo(n) {
                current = n;
                if (track) track.style.transform = `translateX(-${(current - 1) * 100}%)`;
                // Resize viewport to exactly the active panel's height
                const viewport = track ? track.parentElement : null;
                const panels   = getPanels();
                if (viewport && panels[current - 1]) {
                    requestAnimationFrame(() => {
                        const panel = panels[current - 1];
                        const sections = panel.querySelectorAll('.ap-section');
                        let totalHeight = 0;
                        sections.forEach(section => {
                            totalHeight += section.offsetHeight;
                            const style = window.getComputedStyle(section);
                            totalHeight += parseFloat(style.marginTop) + parseFloat(style.marginBottom);
                        });
                        // Add a small buffer
                        totalHeight += 10;
                        console.log('Setting viewport height to', totalHeight, 'for step', current);
                        viewport.style.height = totalHeight + 'px';
                    });
                }
                stepEls.forEach((el, idx) => {
                    el.classList.remove('is-active', 'is-done');
                    if (idx + 1 === current)    el.classList.add('is-active');
                    else if (idx + 1 < current) el.classList.add('is-done');
                });
                lineEls.forEach((line, idx) => {
                    if (line) line.classList.toggle('is-done', current > idx + 1);
                });
                if (backBtn)   backBtn.style.display   = current > 1     ? 'inline-flex' : 'none';
                if (nextBtn)   nextBtn.style.display   = current < total ? 'inline-flex' : 'none';
                if (submitBtn) submitBtn.style.display = current === total ? 'inline-flex' : 'none';
                if (current === 1 && typeof window.hostPropertyPinMapRefresh === 'function') {
                    window.hostPropertyPinMapRefresh();
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            if (nextBtn)  nextBtn.addEventListener('click',  () => { if (current < total) goTo(current + 1); });
            if (backBtn)  backBtn.addEventListener('click',  () => { if (current > 1)     goTo(current - 1); });

            // Validation: ensure all required fields are filled even across steps.
            // We use novalidate so our popup runs (native constraint validation can block submit before JS runs).
            let allowProgrammaticSubmit = false;
            function validateAllFields() {
                const requiredFields = Array.from(formEl.querySelectorAll('input[required], select[required], textarea[required]'));
                const missing = [];
                let firstMissingEl = null;

                requiredFields.forEach(function (el) {
                    if (el.disabled) return;
                    const tag = (el.tagName || '').toLowerCase();
                    const type = (el.getAttribute('type') || '').toLowerCase();
                    let ok = true;
                    if (tag === 'select') {
                        ok = !!(el.value && String(el.value).trim() !== '');
                    } else if (type === 'checkbox' || type === 'radio') {
                        ok = el.checked;
                    } else {
                        ok = !!(el.value && String(el.value).trim() !== '');
                    }
                    if (!ok) {
                        const label = formEl.querySelector('label[for="' + el.id + '"]');
                        const name = label ? label.textContent.replace('*', '').trim() : (el.name || el.id || 'a field');
                        missing.push(name);
                        if (!firstMissingEl) firstMissingEl = el;
                    }
                });

                const photos = document.getElementById('propertyPhotos');
                if (photos && (!photos.files || photos.files.length === 0)) {
                    missing.push('Property Photos (at least 1)');
                    if (!firstMissingEl) firstMissingEl = photos;
                }

                return { missing, firstMissingEl };
            }

            function showMissingPopup(missing, firstMissingEl) {
                const step = getStepForElement(firstMissingEl);
                goTo(step);
                window.setTimeout(function () {
                    if (firstMissingEl && typeof firstMissingEl.focus === 'function') {
                        try { firstMissingEl.focus({ preventScroll: true }); } catch (_) { firstMissingEl.focus(); }
                    }
                }, 120);
                alert('Please fill up all required fields before submitting:\\n\\n- ' + missing.slice(0, 10).join('\\n- ') + (missing.length > 10 ? '\\n- ...' : ''));
            }

            if (formEl) {
                formEl.addEventListener('submit', function (e) {
                    if (allowProgrammaticSubmit) return;
                    e.preventDefault();
                    const res = validateAllFields();
                    if (res.missing.length) {
                        showMissingPopup(res.missing, res.firstMissingEl);
                        return;
                    }
                    allowProgrammaticSubmit = true;
                    formEl.submit();
                });
            }

            if (submitBtn && formEl) {
                submitBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const res = validateAllFields();
                    if (res.missing.length) {
                        showMissingPopup(res.missing, res.firstMissingEl);
                        return;
                    }
                    allowProgrammaticSubmit = true;
                    formEl.submit();
                });
            }

            goTo(1);
            if (typeof window.initHostPropertyPinMap === 'function') {
                window.initHostPropertyPinMap({});
            }
        })();
    </script>
</body>
</html>
