<?php
// Extended database schema for Host and Property Management

require_once __DIR__ . '/database.php';

function initializeHostTables() {
    $conn = getDBConnection();
    
    // Properties/Listings table
    $sql = "CREATE TABLE IF NOT EXISTS properties (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        host_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        property_type ENUM('house', 'apartment', 'condo', 'villa', 'hotel') NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        country VARCHAR(100) NOT NULL,
        price_per_night DECIMAL(10, 2) NOT NULL,
        max_guests INT(11) NOT NULL,
        bedrooms INT(11) NOT NULL,
        bathrooms INT(11) NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // Property amenities table
    $sql = "CREATE TABLE IF NOT EXISTS amenities (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        icon VARCHAR(50),
        category ENUM('basic', 'comfort', 'entertainment', 'safety', 'outdoor') DEFAULT 'basic'
    )";
    $conn->query($sql);
    
    // Property-Amenities relationship
    $sql = "CREATE TABLE IF NOT EXISTS property_amenities (
        property_id INT(11) NOT NULL,
        amenity_id INT(11) NOT NULL,
        PRIMARY KEY (property_id, amenity_id),
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // Property photos table
    $sql = "CREATE TABLE IF NOT EXISTS property_photos (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        property_id INT(11) NOT NULL,
        photo_url VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // Bookings table
    $sql = "CREATE TABLE IF NOT EXISTS bookings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        property_id INT(11) NOT NULL,
        guest_id INT(11) NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        guests INT(11) NOT NULL,
        total_price DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
        booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // User roles (extend users table)
    // Check if role column exists before adding
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE users ADD COLUMN role ENUM('guest', 'host', 'admin') DEFAULT 'guest' AFTER password";
        $conn->query($sql);
    }
    
    // Insert default amenities
    $amenitiesList = [
        ['WiFi', '📶', 'basic'],
        ['Air Conditioning', '❄️', 'comfort'],
        ['Heating', '🔥', 'comfort'],
        ['Kitchen', '🍳', 'basic'],
        ['TV', '📺', 'entertainment'],
        ['Washing Machine', '🧺', 'basic'],
        ['Free Parking', '🅿️', 'outdoor'],
        ['Swimming Pool', '🏊', 'outdoor'],
        ['Hot Tub', '🛁', 'comfort'],
        ['Gym', '💪', 'entertainment'],
        ['BBQ Grill', '🍖', 'outdoor'],
        ['Pet Friendly', '🐕', 'basic'],
        ['Smoke Detector', '🔔', 'safety'],
        ['First Aid Kit', '🩹', 'safety'],
        ['Fire Extinguisher', '🧯', 'safety'],
        ['CCTV', '📹', 'safety'],
        ['Balcony', '🌅', 'outdoor'],
        ['Garden', '🌳', 'outdoor'],
        ['Workspace', '💻', 'comfort'],
        ['Coffee Maker', '☕', 'basic']
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO amenities (name, icon, category) VALUES (?, ?, ?)");
    foreach ($amenitiesList as $amenity) {
        $stmt->bind_param("sss", $amenity[0], $amenity[1], $amenity[2]);
        $stmt->execute();
    }
    $stmt->close();
    
    $conn->close();
}

// Initialize tables
initializeHostTables();
?>
