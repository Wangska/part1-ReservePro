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
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        auto_accept_bookings TINYINT(1) NOT NULL DEFAULT 0,
        cancellation_policy ENUM('flexible','moderate','strict') NOT NULL DEFAULT 'moderate',
        status ENUM('pending', 'approved', 'rejected', 'suspended', 'out_of_order') DEFAULT 'pending',
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
    
    // Payments table (track external payment for bookings, e.g. GCash)
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        booking_id INT(11) NOT NULL,
        provider VARCHAR(50) NOT NULL,
        method VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
        status ENUM('pending', 'paid', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
        external_reference VARCHAR(191) DEFAULT NULL,
        raw_payload TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
    
    // Add 'out_of_order' to properties.status only if not already present (avoids repeated ALTER = no table lock)
    $col = $conn->query("SHOW COLUMNS FROM properties WHERE Field = 'status'");
    if ($col && $col->num_rows > 0) {
        $row = $col->fetch_assoc();
        if ($row && strpos($row['Type'], 'out_of_order') === false) {
            $conn->query("ALTER TABLE properties MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'suspended', 'out_of_order') DEFAULT 'pending'");
        }
    }

    // Add latitude/longitude columns if missing (for precise map pins)
    $result = $conn->query("SHOW COLUMNS FROM properties LIKE 'latitude'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER bathrooms");
    }
    $result = $conn->query("SHOW COLUMNS FROM properties LIKE 'longitude'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude");
    }
    $result = $conn->query("SHOW COLUMNS FROM properties LIKE 'auto_accept_bookings'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN auto_accept_bookings TINYINT(1) NOT NULL DEFAULT 0 AFTER longitude");
    }

    // Cancellation policy for refunds (flexible/moderate/strict)
    $result = $conn->query("SHOW COLUMNS FROM properties LIKE 'cancellation_policy'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN cancellation_policy ENUM('flexible','moderate','strict') NOT NULL DEFAULT 'moderate' AFTER auto_accept_bookings");
    }
    
    // Property reviews table (guests reviewing properties)
    $sql = "CREATE TABLE IF NOT EXISTS property_reviews (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        property_id INT(11) NOT NULL,
        guest_id INT(11) NOT NULL,
        rating TINYINT(1) NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (guest_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_property_guest (property_id, guest_id)
    )";
    $conn->query($sql);

    // Add rating summary columns to properties (for fast listing display)
    $result = $conn->query("SHOW COLUMNS FROM properties LIKE 'average_rating'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN average_rating DECIMAL(3,2) NULL DEFAULT NULL AFTER auto_accept_bookings");
    }
    $result = $conn->query("SHOW COLUMNS FROM properties LIKE 'review_count'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN review_count INT NOT NULL DEFAULT 0 AFTER average_rating");
    }
    
    // User roles and profile fields (extend users table)
    // Check if role column exists before adding
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($result && $result->num_rows == 0) {
        $sql = "ALTER TABLE users ADD COLUMN role ENUM('guest', 'host', 'admin') DEFAULT 'guest' AFTER password";
        $conn->query($sql);
    }

    // Date of birth for users (optional, used for host onboarding)
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'date_of_birth'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER last_name");
    }

    // Email verification fields
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    }
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_token'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN verification_token VARCHAR(100) DEFAULT NULL AFTER email_verified");
    }

    // Host verification flag
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'host_verified'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN host_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    }

    // Host verification status for DB display: none, under review, approved, rejected
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'host_verification_status'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN host_verification_status ENUM('none','under review','approved','rejected') NOT NULL DEFAULT 'none' AFTER host_verified");
        $conn->query("UPDATE users SET host_verification_status = 'approved' WHERE host_verified = 1");
    } else {
        $row = $result->fetch_assoc();
        if ($row && strpos($row['Type'], 'pending') !== false) {
            $conn->query("ALTER TABLE users MODIFY COLUMN host_verification_status ENUM('none','pending','under review','approved','rejected') NOT NULL DEFAULT 'none'");
            $conn->query("UPDATE users SET host_verification_status = 'under review' WHERE host_verification_status = 'pending'");
            $conn->query("ALTER TABLE users MODIFY COLUMN host_verification_status ENUM('none','under review','approved','rejected') NOT NULL DEFAULT 'none'");
        }
        // Sync: any host with pending verification in host_documents should show 'under review' in users
        $conn->query("UPDATE users u JOIN host_documents h ON h.user_id = u.id SET u.host_verification_status = 'under review' WHERE h.verification_status = 'pending' AND u.host_verification_status = 'none'");
    }

    // Host documents table (stores KYC/verification data for hosts)
    $sql = "CREATE TABLE IF NOT EXISTS host_documents (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        id_full_name VARCHAR(255) DEFAULT NULL,
        gov_id_type VARCHAR(100) NOT NULL,
        gov_id_number VARCHAR(100) DEFAULT NULL,
        gov_id_photo_path VARCHAR(255) DEFAULT NULL,
        ownership_proof_type VARCHAR(100) NOT NULL,
        ownership_reference VARCHAR(255) DEFAULT NULL,
        ownership_doc_photo_path VARCHAR(255) DEFAULT NULL,
        business_registration VARCHAR(255) DEFAULT NULL,
        tax_id VARCHAR(100) DEFAULT NULL,
        tourism_license VARCHAR(255) DEFAULT NULL,
        bank_name VARCHAR(255) NOT NULL,
        bank_account_name VARCHAR(255) NOT NULL,
        bank_account_number VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Add missing host_documents columns for ID/photos (safe migrations)
    $cols = [
        ['id_full_name', "ALTER TABLE host_documents ADD COLUMN id_full_name VARCHAR(255) DEFAULT NULL AFTER user_id"],
        ['gov_id_photo_path', "ALTER TABLE host_documents ADD COLUMN gov_id_photo_path VARCHAR(255) DEFAULT NULL AFTER gov_id_number"],
        ['ownership_doc_photo_path', "ALTER TABLE host_documents ADD COLUMN ownership_doc_photo_path VARCHAR(255) DEFAULT NULL AFTER ownership_reference"],
    ];
    foreach ($cols as $c) {
        $result = $conn->query("SHOW COLUMNS FROM host_documents LIKE '" . $conn->real_escape_string($c[0]) . "'");
        if ($result && $result->num_rows == 0) {
            $conn->query($c[1]);
        }
    }

    // Host verification status: pending (awaiting admin), approved, rejected
    $result = $conn->query("SHOW COLUMNS FROM host_documents LIKE 'verification_status'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE host_documents ADD COLUMN verification_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER bank_account_number");
    }
    
    // Messages: guest -> host (about a property)
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        property_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        receiver_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Refund requests (cancellation + issue-based)
    $sql = "CREATE TABLE IF NOT EXISTS refund_requests (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        booking_id INT(11) NOT NULL,
        requester_user_id INT(11) NOT NULL,
        property_id INT(11) NOT NULL,
        request_type ENUM('cancellation','issue') NOT NULL,
        issue_type VARCHAR(60) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        evidence_json TEXT DEFAULT NULL,
        policy ENUM('flexible','moderate','strict') DEFAULT NULL,
        refund_percent INT NOT NULL DEFAULT 0,
        refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
        status ENUM('pending_review','pending','approved','rejected','processing','completed') NOT NULL DEFAULT 'pending',
        host_decision ENUM('none','approve_full','approve_partial','reject') NOT NULL DEFAULT 'none',
        host_decision_percent INT DEFAULT NULL,
        host_decision_note TEXT DEFAULT NULL,
        admin_override_percent INT DEFAULT NULL,
        admin_override_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Booking cancellations (audit trail)
    $sql = "CREATE TABLE IF NOT EXISTS booking_cancellations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        booking_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        policy ENUM('flexible','moderate','strict') NOT NULL,
        refund_percent_preview INT NOT NULL DEFAULT 0,
        refund_amount_preview DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        reason VARCHAR(255) DEFAULT NULL,
        cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Refund logs (every action is logged)
    $sql = "CREATE TABLE IF NOT EXISTS refund_logs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        refund_request_id INT(11) NOT NULL,
        actor_user_id INT(11) NOT NULL,
        actor_role VARCHAR(20) NOT NULL,
        action VARCHAR(60) NOT NULL,
        from_status VARCHAR(30) DEFAULT NULL,
        to_status VARCHAR(30) DEFAULT NULL,
        note TEXT DEFAULT NULL,
        meta_json TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (refund_request_id) REFERENCES refund_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Property edit audit log (for admin "what changed" + when)
    $sql = "CREATE TABLE IF NOT EXISTS property_edit_logs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        property_id INT(11) NOT NULL,
        host_id INT(11) NOT NULL,
        changes_json TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Host earnings ledger (credits/debits). Used to deduct host money on refunds.
    $sql = "CREATE TABLE IF NOT EXISTS host_ledger (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        host_id INT(11) NOT NULL,
        booking_id INT(11) DEFAULT NULL,
        refund_request_id INT(11) DEFAULT NULL,
        entry_type ENUM('booking_credit','refund_debit') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
        FOREIGN KEY (refund_request_id) REFERENCES refund_requests(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_refund_debit (refund_request_id, entry_type)
    )";
    $conn->query($sql);
    
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
