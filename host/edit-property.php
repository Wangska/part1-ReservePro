<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

// Only hosts may edit properties
if (!$user || !isset($user['role']) || $user['role'] !== 'host') {
    header('Location: ' . (isset($user['role']) && $user['role'] === 'admin' ? '../admin/dashboard.php' : '../dashboard.php'));
    exit();
}

// Hosts must complete verification before managing properties
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$conn = getDBConnection();

$property_id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['property_id'] ?? 0);
if ($property_id <= 0) {
    $conn->close();
    header('Location: properties.php?error=notfound');
    exit();
}

// Load property and ensure it belongs to this host
$stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND host_id = ?");
$stmt->bind_param("ii", $property_id, $user['id']);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    $conn->close();
    header('Location: properties.php?error=notfound');
    exit();
}

// Load amenities list (for checkboxes)
$amenities = [];
$result = $conn->query("SELECT * FROM amenities ORDER BY category, name");
while ($row = $result->fetch_assoc()) {
    $amenities[$row['category']][] = $row;
}

// Current amenity IDs for this property
$currentAmenityIds = [];
$stmt = $conn->prepare("SELECT amenity_id FROM property_amenities WHERE property_id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $currentAmenityIds[] = (int)$row['amenity_id'];
}
$stmt->close();

// Current photos
$photos = [];
$stmt = $conn->prepare("SELECT id, photo_url, is_primary FROM property_photos WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$errors = [];
$upload_errors = [];
$success = false;

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

    // Validation (same rules as add-property)
    if ($title === '') $errors[] = "Title is required";
    if ($description === '') $errors[] = "Description is required";
    if ($property_type === '') $errors[] = "Property type is required";
    if ($address === '') $errors[] = "Address is required";
    if ($city === '') $errors[] = "City is required";
    if ($country === '') $errors[] = "Country is required";
    if ($price <= 0) $errors[] = "Valid price is required";
    if ($max_guests <= 0) $errors[] = "Number of guests is required";
    if ($bedrooms <= 0) $errors[] = "Number of bedrooms is required";
    if ($bathrooms <= 0) $errors[] = "Number of bathrooms is required";

    $latitude = $latitude_input === '' ? 0 : floatval($latitude_input);
    $longitude = $longitude_input === '' ? 0 : floatval($longitude_input);

    // Handle delete photo checkboxes
    $deletePhotoIds = array_map('intval', $_POST['delete_photos'] ?? []);
    $primaryPhotoId = isset($_POST['primary_photo_id']) ? (int) $_POST['primary_photo_id'] : 0;

    if (empty($errors)) {
        // Update property main fields (keep status as-is)
        $stmt = $conn->prepare("UPDATE properties SET title = ?, description = ?, property_type = ?, address = ?, city = ?, country = ?, price_per_night = ?, max_guests = ?, bedrooms = ?, bathrooms = ?, latitude = ?, longitude = ? WHERE id = ? AND host_id = ?");
        $stmt->bind_param(
            "ssssssdiidddii",
            $title,
            $description,
            $property_type,
            $address,
            $city,
            $country,
            $price,
            $max_guests,
            $bedrooms,
            $bathrooms,
            $latitude,
            $longitude,
            $property_id,
            $user['id']
        );
        if (!$stmt->execute()) {
            $errors[] = "Failed to update property details.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        // Replace amenities
        $stmt = $conn->prepare("DELETE FROM property_amenities WHERE property_id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $stmt->close();

        if (!empty($selected_amenities)) {
            $stmt = $conn->prepare("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)");
            foreach ($selected_amenities as $amenity_id) {
                $aid = (int)$amenity_id;
                $stmt->bind_param("ii", $property_id, $aid);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    // Refresh current photos from DB before applying deletes / uploads
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, photo_url, is_primary FROM property_photos WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Delete selected photos
        if (!empty($deletePhotoIds)) {
            foreach ($photos as $p) {
                if (in_array((int)$p['id'], $deletePhotoIds, true)) {
                    $filePath = dirname(__DIR__) . '/' . ltrim($p['photo_url'], '/');
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            if (!empty($deletePhotoIds)) {
                // Delete selected photos one by one to keep logic simple
                foreach ($deletePhotoIds as $pid) {
                    $one = (int)$pid;
                    $stmt = $conn->prepare("DELETE FROM property_photos WHERE property_id = ? AND id = ?");
                    $stmt->bind_param("ii", $property_id, $one);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        // Reload photos after deletes
        $stmt = $conn->prepare("SELECT id, photo_url, is_primary FROM property_photos WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Handle new uploads (same rules as add-property)
        if (isset($_FILES['property_photos']) && !empty($_FILES['property_photos']['name'][0])) {
            $upload_dir = dirname(__DIR__) . '/uploads/properties/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
                chmod($upload_dir, 0777);
            }
            if (!is_writable($upload_dir)) {
                $upload_errors[] = "Upload directory is not writable: " . $upload_dir;
            } else {
                $photo_count = count($_FILES['property_photos']['name']);
                $hasPrimary = false;
                foreach ($photos as $p) {
                    if (!empty($p['is_primary'])) {
                        $hasPrimary = true;
                        break;
                    }
                }
                $is_primary_flag = $hasPrimary ? 0 : 1;

                for ($i = 0; $i < $photo_count && $i < 5; $i++) {
                    if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['property_photos']['tmp_name'][$i];
                        $file_name = $_FILES['property_photos']['name'][$i];
                        $file_size = $_FILES['property_photos']['size'][$i];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        $max_size = 5 * 1024 * 1024;

                        if (in_array($file_ext, $allowed_ext, true) && $file_size <= $max_size) {
                            $new_filename = 'property_' . $property_id . '_' . time() . '_' . $i . '.' . $file_ext;
                            $upload_path = $upload_dir . $new_filename;
                            $photo_url = 'uploads/properties/' . $new_filename;

                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                if (file_exists($upload_path)) {
                                    $photo_stmt = $conn->prepare("INSERT INTO property_photos (property_id, photo_url, is_primary) VALUES (?, ?, ?)");
                                    $photo_stmt->bind_param("isi", $property_id, $photo_url, $is_primary_flag);
                                    $photo_stmt->execute();
                                    $photo_stmt->close();
                                    $is_primary_flag = 0;
                                } else {
                                    $upload_errors[] = "File upload succeeded but file not found: " . $file_name;
                                }
                            } else {
                                $upload_errors[] = "Failed to move uploaded file: " . $file_name;
                            }
                        } else {
                            $upload_errors[] = "Invalid file: " . $file_name;
                        }
                    }
                }
            }
        }

        // Reload photos again after uploads
        $stmt = $conn->prepare("SELECT id, photo_url, is_primary FROM property_photos WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Update primary photo if admin chose one
        if ($primaryPhotoId > 0) {
            $stmt = $conn->prepare("UPDATE property_photos SET is_primary = 0 WHERE property_id = ?");
            $stmt->bind_param("i", $property_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE property_photos SET is_primary = 1 WHERE property_id = ? AND id = ?");
            $stmt->bind_param("ii", $property_id, $primaryPhotoId);
            $stmt->execute();
            $stmt->close();
        } else {
            // Ensure at least one primary exists if there are photos
            $hasPrimary = false;
            foreach ($photos as $p) {
                if (!empty($p['is_primary'])) {
                    $hasPrimary = true;
                    break;
                }
            }
            if (!$hasPrimary && !empty($photos)) {
                $firstId = (int)$photos[0]['id'];
                $stmt = $conn->prepare("UPDATE property_photos SET is_primary = 1 WHERE property_id = ? AND id = ?");
                $stmt->bind_param("ii", $property_id, $firstId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if (!empty($upload_errors)) {
        $errors = array_merge($errors, $upload_errors);
    }

    if (empty($errors)) {
        $success = true;
        $conn->close();
        header('Location: view-property.php?id=' . $property_id . '&updated=1');
        exit();
    }
}

// If we reach here (GET or validation errors), keep latest values for form
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($errors)) {
    // Re-fetch photos for display (in case of GET or after failed POST)
    $stmt = $conn->prepare("SELECT id, photo_url, is_primary FROM property_photos WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Edit Property - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.1">
    <link rel="stylesheet" href="../assets/css/add-property.css?v=15.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=14.0">
</head>
<body class="dashboard-page">
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a href="properties.php" class="nav-item active"><span class="nav-icon">🏠</span><span>My Properties</span></a>
                <a href="add-property.php" class="nav-item"><span class="nav-icon">➕</span><span>Add Property</span></a>
                <a href="bookings.php" class="nav-item"><span class="nav-icon">📅</span><span>Bookings</span></a>
                <a href="earnings.php" class="nav-item"><span class="nav-icon">💰</span><span>Earnings</span></a>
                <a href="messages.php" class="nav-item"><span class="nav-icon">💬</span><span>Messages</span></a>
                <a href="../home.php" class="nav-item"><span class="nav-icon">🌐</span><span>View Site</span></a>
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

        <main class="host-main">
            <div class="host-header" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1>Edit Property 🛠️</h1>
                    <p class="subtitle">Update your listing details and photos</p>
                </div>
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

            <form method="POST" action="edit-property.php?id=<?php echo (int)$property_id; ?>" class="property-form" enctype="multipart/form-data">
                <input type="hidden" name="property_id" value="<?php echo (int)$property_id; ?>">

                <!-- Basic Information -->
                <div class="form-section">
                    <h2 class="section-title">📝 Basic Information</h2>
                    <div class="form-group">
                        <label for="title">Property Title *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? $property['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($_POST['description'] ?? $property['description']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <?php $typeVal = $_POST['property_type'] ?? $property['property_type']; ?>
                            <select id="property_type" name="property_type" required>
                                <option value="">Select type</option>
                                <option value="house" <?php echo $typeVal === 'house' ? 'selected' : ''; ?>>House</option>
                                <option value="apartment" <?php echo $typeVal === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                                <option value="condo" <?php echo $typeVal === 'condo' ? 'selected' : ''; ?>>Condo</option>
                                <option value="villa" <?php echo $typeVal === 'villa' ? 'selected' : ''; ?>>Villa</option>
                                <option value="hotel" <?php echo $typeVal === 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="price_per_night">Price per Night (₱) *</label>
                            <input type="number" id="price_per_night" name="price_per_night" min="0" step="0.01" required
                                value="<?php echo htmlspecialchars($_POST['price_per_night'] ?? $property['price_per_night']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-section">
                    <h2 class="section-title">📍 Location</h2>
                    <div class="form-group">
                        <label for="address">Full Address *</label>
                        <input type="text" id="address" name="address" required
                            value="<?php echo htmlspecialchars($_POST['address'] ?? $property['address']); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" required
                                value="<?php echo htmlspecialchars($_POST['city'] ?? $property['city']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country *</label>
                            <input type="text" id="country" name="country" required
                                value="<?php echo htmlspecialchars($_POST['country'] ?? $property['country']); ?>">
                        </div>
                    </div>
                    <div class="host-property-pin-map-wrap">
                        <p class="section-description" style="margin-bottom:12px;">Drag the pin so it matches your entrance exactly. You can also tap the map or use your address as a starting point.</p>
                        <div class="host-property-pin-map-actions">
                            <button type="button" class="btn-map-geocode" id="hostPropertyPinGeocodeBtn">Place from address</button>
                            <p class="host-property-pin-map-hint">Fine-tune after lookup by dragging the marker.</p>
                        </div>
                        <div id="hostPropertyPinMap" role="application" aria-label="Map to position property pin"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="latitude">Latitude (optional)</label>
                            <input type="text" id="latitude" name="latitude" inputmode="decimal" autocomplete="off"
                                value="<?php echo htmlspecialchars($_POST['latitude'] ?? ($property['latitude'] ?: '')); ?>">
                            <small class="helper-text">Updates when you move the pin.</small>
                        </div>
                        <div class="form-group">
                            <label for="longitude">Longitude (optional)</label>
                            <input type="text" id="longitude" name="longitude" inputmode="decimal" autocomplete="off"
                                value="<?php echo htmlspecialchars($_POST['longitude'] ?? ($property['longitude'] ?: '')); ?>">
                        </div>
                    </div>
                </div>

                <!-- Property Details -->
                <div class="form-section">
                    <h2 class="section-title">🛏️ Property Details</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_guests">Maximum Guests *</label>
                            <input type="number" id="max_guests" name="max_guests" min="1" required
                                value="<?php echo htmlspecialchars($_POST['max_guests'] ?? $property['max_guests']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms *</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="1" required
                                value="<?php echo htmlspecialchars($_POST['bedrooms'] ?? $property['bedrooms']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms *</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="1" required
                                value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? $property['bathrooms']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Amenities -->
                <div class="form-section">
                    <h2 class="section-title">✨ Amenities</h2>
                    <p class="section-description">Update which amenities are available at your property</p>
                    <?php
                    $checkedIds = array_map('intval', $_POST['amenities'] ?? $currentAmenityIds);
                    foreach ($amenities as $category => $category_amenities): ?>
                        <div class="amenities-category">
                            <h3 class="category-title"><?php echo ucfirst($category); ?></h3>
                            <div class="amenities-grid">
                                <?php foreach ($category_amenities as $a):
                                    $id = (int)$a['id'];
                                    $isChecked = in_array($id, $checkedIds, true);
                                ?>
                                <label class="amenity-checkbox">
                                    <input type="checkbox" name="amenities[]" value="<?php echo $id; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <span class="amenity-label">
                                        <span class="amenity-icon"><?php echo $a['icon']; ?></span>
                                        <span class="amenity-name"><?php echo htmlspecialchars($a['name']); ?></span>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Existing Photos -->
                <div class="form-section">
                    <h2 class="section-title">📸 Existing Photos</h2>
                    <?php if (empty($photos)): ?>
                        <p style="color:#B8B8B8;">No photos yet. Upload some below.</p>
                    <?php else: ?>
                        <p class="section-description">Choose a primary photo and optionally remove photos.</p>
                        <div style="display:flex; flex-wrap:wrap; gap:12px;">
                            <?php foreach ($photos as $p): 
                                $thumb = $p['photo_url'];
                                if ($thumb && strpos($thumb, 'http') !== 0) {
                                    $thumb = '../' . ltrim($thumb, '/');
                                }
                            ?>
                            <div style="width:150px; border-radius:10px; overflow:hidden; background:#1F1F1F; border:1px solid #3A3A3A; padding-bottom:8px;">
                                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Photo" style="width:100%; height:100px; object-fit:cover;">
                                <div style="padding:6px 8px; font-size:12px; color:#E5E7EB;">
                                    <label style="display:block; margin-bottom:4px;">
                                        <input type="radio" name="primary_photo_id" value="<?php echo (int)$p['id']; ?>" <?php echo !empty($p['is_primary']) ? 'checked' : ''; ?>>
                                        Primary
                                    </label>
                                    <label style="display:block; color:#FCA5A5;">
                                        <input type="checkbox" name="delete_photos[]" value="<?php echo (int)$p['id']; ?>">
                                        Remove
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- New Photos -->
                <div class="form-section">
                    <h2 class="section-title">➕ Add More Photos</h2>
                    <p class="section-description">Upload additional high-quality photos (maximum 5 per update, JPG/PNG/WebP up to 5MB each).</p>

                    <div class="photo-upload-container">
                        <div class="photo-upload-area" id="photoUploadArea">
                            <div class="upload-icon">📷</div>
                            <h3>Click to Upload Photos</h3>
                            <p>Or drag and drop images here</p>
                            <p class="upload-hint">Supported: JPG, PNG, WEBP (Max 5MB each)</p>
                        </div>
                        <input type="file" id="propertyPhotos" name="property_photos[]" multiple accept="image/*" style="display:none;">
                        <div style="text-align:center; margin-top:16px;">
                            <label for="propertyPhotos" style="display:inline-block; padding:12px 24px; background:linear-gradient(135deg,#D4A574,#B8935F); color:#0F0F0F; border-radius:8px; cursor:pointer; font-weight:600;">
                                📁 Choose Files
                            </label>
                        </div>
                        <div class="photo-preview-grid" id="photoPreviewGrid"></div>
                    </div>
                    <div id="uploadStatus" style="margin-top:16px; padding:12px; background:rgba(59,130,246,0.1); border-left:4px solid #3B82F6; border-radius:8px; display:none;">
                        <p style="color:#3B82F6 !important; margin:0; font-size:14px;">
                            <strong>Ready to upload:</strong> <span id="fileCount">0</span> photo(s) selected
                        </p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="form-actions">
                    <a href="properties.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
    <script src="../assets/js/host-property-pin-map.js?v=1"></script>
    <script>
        if (typeof window.initHostPropertyPinMap === 'function') {
            window.initHostPropertyPinMap({});
        }
        const photoUploadArea = document.getElementById('photoUploadArea');
        const photoInput = document.getElementById('propertyPhotos');
        const photoPreviewGrid = document.getElementById('photoPreviewGrid');
        let selectedFiles = [];

        if (photoUploadArea && photoInput && photoPreviewGrid) {
            photoUploadArea.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                photoInput.click();
            });

            photoInput.addEventListener('change', function(e) {
                handleFiles(e.target.files);
            });

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
        }

        function handleFiles(files) {
            const maxFiles = 5;
            const maxSize = 5 * 1024 * 1024;
            const newFiles = Array.from(files).filter(file => {
                if (!file.type.startsWith('image/')) {
                    alert('Please upload only image files.');
                    return false;
                }
                if (file.size > maxSize) {
                    alert(file.name + ' is too large. Maximum size is 5MB.');
                    return false;
                }
                return true;
            });
            if (selectedFiles.length + newFiles.length > maxFiles) {
                alert('You can only upload a maximum of ' + maxFiles + ' photos at a time.');
                return;
            }
            selectedFiles = [...selectedFiles, ...newFiles];
            updatePhotoPreview();
        }

        function updatePhotoPreview() {
            photoPreviewGrid.innerHTML = '';
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
                        <img src="${e.target.result}" alt="New photo ${index + 1}">
                        <button type="button" class="photo-remove-btn" onclick="removePhoto(${index})">&times;</button>
                    `;
                    photoPreviewGrid.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            photoInput.files = dataTransfer.files;
        }

        function removePhoto(index) {
            selectedFiles.splice(index, 1);
            updatePhotoPreview();
        }
    </script>
</body>
</html>

