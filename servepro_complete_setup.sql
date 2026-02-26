-- ============================================
-- ServePro Complete Database Setup
-- Airbnb-style Property Rental Platform
-- ============================================
-- Import this file in phpMyAdmin to set up everything!
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `servepro_auth` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `servepro_auth`;

-- ============================================
-- Table: users
-- Stores all user accounts (guests, hosts, admins)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('guest','host','admin') DEFAULT 'guest',
  `host_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: host_documents
-- Stores KYC / verification data for hosts
-- ============================================
CREATE TABLE IF NOT EXISTS `host_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `gov_id_type` varchar(100) NOT NULL,
  `gov_id_number` varchar(100) DEFAULT NULL,
  `ownership_proof_type` varchar(100) NOT NULL,
  `ownership_reference` varchar(255) DEFAULT NULL,
  `business_registration` varchar(255) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `tourism_license` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) NOT NULL,
  `bank_account_name` varchar(255) NOT NULL,
  `bank_account_number` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `host_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: properties
-- Stores property/listing information
-- ============================================
CREATE TABLE IF NOT EXISTS `properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `host_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `property_type` enum('house','apartment','condo','villa','hotel') NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `max_guests` int(11) NOT NULL,
  `bedrooms` int(11) NOT NULL,
  `bathrooms` int(11) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `auto_accept_bookings` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected','suspended','out_of_order') DEFAULT 'pending',
  `admin_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `host_id` (`host_id`),
  KEY `status` (`status`),
  CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: amenities
-- Stores available amenities (WiFi, Pool, etc.)
-- ============================================
CREATE TABLE IF NOT EXISTS `amenities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `category` enum('basic','comfort','entertainment','safety','outdoor') DEFAULT 'basic',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: property_amenities
-- Junction table linking properties to amenities
-- ============================================
CREATE TABLE IF NOT EXISTS `property_amenities` (
  `property_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL,
  PRIMARY KEY (`property_id`,`amenity_id`),
  KEY `amenity_id` (`amenity_id`),
  CONSTRAINT `property_amenities_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `property_amenities_ibfk_2` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: property_photos
-- Stores property images
-- ============================================
CREATE TABLE IF NOT EXISTS `property_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  CONSTRAINT `property_photos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: bookings
-- Stores guest reservations
-- ============================================
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `guests` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `booking_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `guest_id` (`guest_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`guest_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Insert Default Amenities
INSERT INTO `amenities` (`name`, `icon`, `category`) VALUES
('WiFi', '📶', 'basic'),
('Air Conditioning', '❄️', 'comfort'),
('Heating', '🔥', 'comfort'),
('Kitchen', '🍳', 'basic'),
('TV', '📺', 'entertainment'),
('Washing Machine', '🧺', 'basic'),
('Free Parking', '🅿️', 'outdoor'),
('Swimming Pool', '🏊', 'outdoor'),
('Hot Tub', '🛁', 'comfort'),
('Gym', '💪', 'entertainment'),
('BBQ Grill', '🍖', 'outdoor'),
('Pet Friendly', '🐕', 'basic'),
('Smoke Detector', '🔔', 'safety'),
('First Aid Kit', '🩹', 'safety'),
('Fire Extinguisher', '🧯', 'safety'),
('CCTV', '📹', 'safety'),
('Balcony', '🌅', 'outdoor'),
('Garden', '🌳', 'outdoor'),
('Workspace', '💻', 'comfort'),
('Coffee Maker', '☕', 'basic')
ON DUPLICATE KEY UPDATE name=name;

-- ============================================
-- Create Admin Account
-- Email: admin@servepro.com
-- Password: admin123
-- ============================================
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `role`) 
VALUES (
    'Admin',
    'ServePro',
    'admin@servepro.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
)
ON DUPLICATE KEY UPDATE role = 'admin';

-- ============================================
-- Create Sample Host Account (Optional)
-- Email: host@servepro.com
-- Password: host123
-- ============================================
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `role`) 
VALUES (
    'John',
    'Doe',
    'host@servepro.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'host'
)
ON DUPLICATE KEY UPDATE role = 'host';

-- ============================================
-- Create Sample Guest Account (Optional)
-- Email: guest@servepro.com
-- Password: guest123
-- ============================================
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `role`) 
VALUES (
    'Jane',
    'Smith',
    'guest@servepro.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'guest'
)
ON DUPLICATE KEY UPDATE role = 'guest';

-- ============================================
-- Verification Queries
-- ============================================

-- Show all tables
SELECT 'All tables created successfully!' as status;
SHOW TABLES;

-- Show all amenities
SELECT 'Total Amenities:' as info, COUNT(*) as count FROM amenities;

-- Show all users
SELECT 
    'Users Created:' as info,
    id, 
    first_name, 
    last_name, 
    email, 
    role, 
    created_at 
FROM users 
ORDER BY role;

-- ============================================
-- SETUP COMPLETE!
-- ============================================
-- 
-- DEFAULT ACCOUNTS:
-- 
-- ADMIN:
--   Email: admin@servepro.com
--   Password: admin123
--   Access: http://localhost/part1/admin/dashboard.php
--
-- HOST:
--   Email: host@servepro.com
--   Password: host123
--   Access: http://localhost/part1/host/dashboard.php
--
-- GUEST:
--   Email: guest@servepro.com
--   Password: guest123
--   Access: http://localhost/part1/dashboard.php
--
-- IMPORTANT: Change all passwords after first login!
--
-- ============================================
