<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();

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
    
    if (empty($errors)) {
        // Insert property
        $stmt = $conn->prepare("INSERT INTO properties (host_id, title, description, property_type, address, city, country, price_per_night, max_guests, bedrooms, bathrooms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("issssssdiid", $user['id'], $title, $description, $property_type, $address, $city, $country, $price, $max_guests, $bedrooms, $bathrooms);
        
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
            
            $success = true;
            header('Location: dashboard.php?success=property_added');
            exit();
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
    <title>Add Property - ServePro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css">
    <link rel="stylesheet" href="../assets/css/add-property.css">
</head>
<body>
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <svg class="brand-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1c2 0 3.46 1.63 3.46 3.41 0 1.78-1.46 3.41-3.46 3.41s-3.46-1.63-3.46-3.41C12.54 2.63 14 1 16 1zm0 6.82c2.52 0 4.61-1.84 4.61-4.41C20.61 1.84 18.52 0 16 0s-4.61 1.84-4.61 4.41c0 2.57 2.09 4.41 4.61 4.41zM13.96 28.85l6.72-11.87c-1.41-.83-3.07-1.33-4.86-1.33-1.79 0-3.45.5-4.86 1.33l6.72 11.87h.28zm-1.27-1.89l-5.12-9.04C8.47 16.02 9.99 15 11.71 15h8.58c1.72 0 3.24 1.02 4.14 2.92l-5.12 9.04h-7.62z"/>
                    </svg>
                    <span>ServePro</span>
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
            <div class="host-header">
                <h1>Add New Property 🏠</h1>
                <p class="subtitle">List your place and start hosting</p>
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

            <form method="POST" action="add-property.php" class="property-form">
                <!-- Basic Information -->
                <div class="form-section">
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
                <div class="form-section">
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
                </div>

                <!-- Property Details -->
                <div class="form-section">
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
                <div class="form-section">
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

                <!-- Submit -->
                <div class="form-actions">
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Submit for Review</button>
                </div>
                
                <div class="form-note">
                    <p><strong>Note:</strong> Your property will be reviewed by our admin team before it goes live. You'll receive a notification once it's approved.</p>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
