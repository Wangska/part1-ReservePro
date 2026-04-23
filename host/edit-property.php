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

// Amenity ID -> label mapping (for readable edit diffs)
$amenityById = [];
foreach ($amenities as $cat => $items) {
    foreach ($items as $a) {
        $aid = (int)($a['id'] ?? 0);
        if ($aid > 0) {
            $icon = trim((string)($a['icon'] ?? ''));
            $name = trim((string)($a['name'] ?? ''));
            $amenityById[$aid] = trim(($icon !== '' ? ($icon . ' ') : '') . $name);
        }
    }
}

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

    // Capture "before" snapshot for edit audit
    $before = $property;
    $beforeAmenityIds = $currentAmenityIds;
    $beforePhotoIds = array_map(function($p) { return (int)($p['id'] ?? 0); }, $photos);
    $beforePrimaryId = 0;
    foreach ($photos as $p) {
        if (!empty($p['is_primary'])) { $beforePrimaryId = (int)$p['id']; break; }
    }

    if (empty($errors)) {
        // Update property main fields.
        // IMPORTANT: Any edit requires admin re-approval, so set status back to 'pending'.
        $stmt = $conn->prepare("UPDATE properties SET title = ?, description = ?, property_type = ?, address = ?, city = ?, country = ?, price_per_night = ?, max_guests = ?, bedrooms = ?, bathrooms = ?, latitude = ?, longitude = ?, status = 'pending' WHERE id = ? AND host_id = ?");
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
        $uploadedCount = 0;
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

                for ($i = 0; $i < $photo_count; $i++) {
                    if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['property_photos']['tmp_name'][$i];
                        $file_name = $_FILES['property_photos']['name'][$i];
                        $file_size = $_FILES['property_photos']['size'][$i];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
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
                                    $uploadedCount++;
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
        // Write edit audit log (what changed + when)
        $changes = [];
        $fields = [
            'title' => 'Title',
            'description' => 'Description',
            'property_type' => 'Property type',
            'address' => 'Address',
            'city' => 'City',
            'country' => 'Country',
            'price_per_night' => 'Price per night',
            'max_guests' => 'Max guests',
            'bedrooms' => 'Bedrooms',
            'bathrooms' => 'Bathrooms',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
        ];
        foreach ($fields as $k => $label) {
            $old = isset($before[$k]) ? (string)$before[$k] : '';
            $new = isset($$k) ? (string)$$k : (string)($before[$k] ?? '');
            // Normalize numbers a bit
            if (in_array($k, ['price_per_night','latitude','longitude'], true)) {
                $old = (string)((float)$old);
                $new = (string)((float)$new);
            }
            if (in_array($k, ['max_guests','bedrooms','bathrooms'], true)) {
                $old = (string)((int)$old);
                $new = (string)((int)$new);
            }
            if ($old !== $new) {
                $changes[] = ['field' => $k, 'label' => $label, 'from' => $old, 'to' => $new];
            }
        }

        // Amenities diff
        $afterAmenityIds = array_map('intval', $selected_amenities ?? []);
        $afterAmenityIds = array_values(array_unique(array_filter($afterAmenityIds)));
        $beforeSet = array_values(array_unique(array_filter(array_map('intval', $beforeAmenityIds))));
        $added = array_values(array_diff($afterAmenityIds, $beforeSet));
        $removed = array_values(array_diff($beforeSet, $afterAmenityIds));
        if (!empty($added) || !empty($removed)) {
            $changes[] = [
                'field' => 'amenities',
                'label' => 'Amenities',
                'added' => array_map(function($id) use ($amenityById) { return $amenityById[$id] ?? ('Amenity #' . $id); }, $added),
                'removed' => array_map(function($id) use ($amenityById) { return $amenityById[$id] ?? ('Amenity #' . $id); }, $removed),
            ];
        }

        // Photos summary
        if (!empty($deletePhotoIds)) {
            $changes[] = ['field' => 'photos_deleted', 'label' => 'Photos deleted', 'count' => count($deletePhotoIds)];
        }
        if (!empty($uploadedCount)) {
            $changes[] = ['field' => 'photos_uploaded', 'label' => 'Photos uploaded', 'count' => (int)$uploadedCount];
        }
        if ($primaryPhotoId > 0 && $beforePrimaryId > 0 && $primaryPhotoId !== $beforePrimaryId) {
            $changes[] = ['field' => 'primary_photo', 'label' => 'Primary photo', 'from' => (string)$beforePrimaryId, 'to' => (string)$primaryPhotoId];
        }

        // Always include status transition info when edits happen
        if (($before['status'] ?? '') !== 'pending') {
            $changes[] = ['field' => 'status', 'label' => 'Status', 'from' => (string)($before['status'] ?? ''), 'to' => 'pending'];
        }

        $changesJson = json_encode([
            'property_id' => (int)$property_id,
            'host_id' => (int)$user['id'],
            'changes' => $changes,
        ], JSON_UNESCAPED_SLASHES);

        if ($changesJson !== false) {
            $log = $conn->prepare("INSERT INTO property_edit_logs (property_id, host_id, changes_json) VALUES (?, ?, ?)");
            if ($log) {
                $log->bind_param('iis', $property_id, $user['id'], $changesJson);
                $log->execute();
                $log->close();
            }
        }

        $success = true;
        $conn->close();
        header('Location: view-property.php?id=' . $property_id . '&updated=1&needs_approval=1');
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
    <link rel="stylesheet" href="../assets/css/style.css?v=14.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/add-property.css?v=17.5">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.6">
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-form-page">
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
                
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img
                                src="<?php echo htmlspecialchars('../' . ltrim((string)$user['profile_photo'], '/')); ?>"
                                alt="Profile photo"
                                style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;"
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
            <div class="host-header host-page-hero">
                <div class="host-page-hero-content">
                    <h1>Edit Property</h1>
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

            <div class="host-form-shell">
            <form method="POST" action="edit-property.php?id=<?php echo (int)$property_id; ?>" class="property-form" enctype="multipart/form-data">
                <input type="hidden" name="property_id" value="<?php echo (int)$property_id; ?>">

                <!-- Basic Information -->
                <div class="form-section">
                    <h2 class="section-title">Basic Information</h2>
                    <div class="form-group">
                        <label for="title">Property Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? $property['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($_POST['description'] ?? $property['description']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type</label>
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
                            <label for="price_per_night">Price per Night</label>
                            <input type="number" id="price_per_night" name="price_per_night" min="0" step="0.01" required
                                value="<?php echo htmlspecialchars($_POST['price_per_night'] ?? $property['price_per_night']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-section">
                    <h2 class="section-title">Location</h2>
                    <div class="form-group">
                        <label for="address">Full Address</label>
                        <input type="text" id="address" name="address" required
                            value="<?php echo htmlspecialchars($_POST['address'] ?? $property['address']); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" required
                                value="<?php echo htmlspecialchars($_POST['city'] ?? $property['city']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" required
                                value="<?php echo htmlspecialchars($_POST['country'] ?? $property['country']); ?>">
                        </div>
                    </div>
                    <div class="host-property-pin-map-wrap">
                        <div class="host-property-pin-map-actions">
                            <button type="button" class="btn-map-geocode" id="hostPropertyPinGeocodeBtn">Place from address</button>
                        </div>
                        <div id="hostPropertyPinMap" role="application" aria-label="Map to position property pin"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="latitude">Latitude</label>
                            <input type="text" id="latitude" name="latitude" inputmode="decimal" autocomplete="off"
                                value="<?php echo htmlspecialchars($_POST['latitude'] ?? ($property['latitude'] ?: '')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="longitude">Longitude</label>
                            <input type="text" id="longitude" name="longitude" inputmode="decimal" autocomplete="off"
                                value="<?php echo htmlspecialchars($_POST['longitude'] ?? ($property['longitude'] ?: '')); ?>">
                        </div>
                    </div>
                </div>

                <!-- Property Details -->
                <div class="form-section">
                    <h2 class="section-title">Property Details</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_guests">Maximum Guests</label>
                            <input type="number" id="max_guests" name="max_guests" min="1" required
                                value="<?php echo htmlspecialchars($_POST['max_guests'] ?? $property['max_guests']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="1" required
                                value="<?php echo htmlspecialchars($_POST['bedrooms'] ?? $property['bedrooms']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="1" required
                                value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? $property['bathrooms']); ?>">
                        </div>
                    </div>
                </div>

                <!-- Amenities -->
                <div class="form-section">
                    <h2 class="section-title">Amenities</h2>
                    <?php
                    $checkedIds = array_map('intval', $_POST['amenities'] ?? $currentAmenityIds);
                    foreach ($amenities as $category => $category_amenities): ?>
                        <div class="ap-amenity-category">
                            <h3 class="ap-category-title"><?php echo ucfirst($category); ?></h3>
                            <div class="ap-amenity-grid">
                                <?php foreach ($category_amenities as $a):
                                    $id = (int)$a['id'];
                                    $isChecked = in_array($id, $checkedIds, true);
                                ?>
                                <label class="ap-amenity-item">
                                    <input type="checkbox" name="amenities[]" value="<?php echo $id; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <span class="ap-amenity-tile">
                                        <span class="ap-amenity-check">
                                            <span class="ap-amenity-check-icon"><i class="fa-solid fa-check"></i></span>
                                        </span>
                                        <span class="amenity-name" style="font-size: 15px; color: #F1F5F9; font-weight: 500; letter-spacing: 0.01em;"><?php echo htmlspecialchars($a['name']); ?></span>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Existing Photos -->
                <div class="form-section">
                    <h2 class="section-title">Existing Photos</h2>
                    <?php if (empty($photos)): ?>
                        <p style="color:#B8B8B8;">No photos yet. Upload some below.</p>
                    <?php else: ?>
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
                    <h2 class="section-title">Add More Photos</h2>
                    <p class="section-description">Upload additional high-quality photos (no limit, JPG/PNG/WebP/AVIF up to 5MB each).</p>

                    <div class="photo-upload-container">
                        <div class="photo-upload-area" id="photoUploadArea">
                            <h3>Click to Upload Photos</h3>
                            <p>Or drag and drop images here</p>
                            <p class="upload-hint">Supported: JPG, PNG, WEBP, AVIF (Max 5MB each)</p>
                        </div>
                        <input type="file" id="propertyPhotos" name="property_photos[]" multiple accept="image/*" style="display:none;">
                        <div style="text-align:center; margin-top:16px;">
                            <label for="propertyPhotos" style="display:inline-block; padding:12px 24px; background:linear-gradient(135deg,#D4A574,#B8935F); color:#0F0F0F; border-radius:8px; cursor:pointer; font-weight:600;">
                                Choose Files
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
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
    <script src="../assets/js/host-property-pin-map.js?v=3"></script>
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

