-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2026 at 04:16 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `servepro_auth`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `category` enum('basic','comfort','entertainment','safety','outdoor') DEFAULT 'basic'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `icon`, `category`) VALUES
(1, 'WiFi', '📶', 'basic'),
(2, 'Air Conditioning', '❄️', 'comfort'),
(3, 'Heating', '🔥', 'comfort'),
(4, 'Kitchen', '🍳', 'basic'),
(5, 'TV', '📺', 'entertainment'),
(6, 'Washing Machine', '🧺', 'basic'),
(7, 'Free Parking', '🅿️', 'outdoor'),
(8, 'Swimming Pool', '🏊', 'outdoor'),
(9, 'Hot Tub', '🛁', 'comfort'),
(10, 'Gym', '💪', 'entertainment'),
(11, 'BBQ Grill', '🍖', 'outdoor'),
(12, 'Pet Friendly', '🐕', 'basic'),
(13, 'Smoke Detector', '🔔', 'safety'),
(14, 'First Aid Kit', '🩹', 'safety'),
(15, 'Fire Extinguisher', '🧯', 'safety'),
(16, 'CCTV', '📹', 'safety'),
(17, 'Balcony', '🌅', 'outdoor'),
(18, 'Garden', '🌳', 'outdoor'),
(19, 'Workspace', '💻', 'comfort'),
(20, 'Coffee Maker', '☕', 'basic');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `guests` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `host_documents`
--

CREATE TABLE `host_documents` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `host_documents`
--

INSERT INTO `host_documents` (`id`, `user_id`, `gov_id_type`, `gov_id_number`, `ownership_proof_type`, `ownership_reference`, `business_registration`, `tax_id`, `tourism_license`, `bank_name`, `bank_account_name`, `bank_account_number`, `created_at`) VALUES
(1, 7, 'Passport', 'PH-PASS-2026-0001', 'Land title / Ownership certificate', 'Transfer Certificate of Title No. TCT-458792 under the name of Juan Dela Cruz. Property located at 123 Main Street, Quezon City.', 'DTI-2025-9876543', '123-456-789-000', 'QC-Tourism-2025-1122', 'BDO Unibank', 'Juan Dela Cruz', '012345678901', '2026-02-26 02:32:17');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
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
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `host_id`, `title`, `description`, `property_type`, `address`, `city`, `country`, `price_per_night`, `max_guests`, `bedrooms`, `bathrooms`, `latitude`, `longitude`, `auto_accept_bookings`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'ECOLOGY', 'lodge', 'house', 'Little Valley Colon City of Naga Cebu', 'City of Naga', 'Philippines', 5000.00, 2, 1, 1, NULL, NULL, 0, 'approved', '', '2026-02-06 07:42:05', '2026-02-06 08:10:27'),
(2, 6, 'nyark house', 'pit house', 'hotel', 'pangdan naga cebu', 'City of Naga', 'Philippines', 500.00, 3, 1, 2, NULL, NULL, 0, 'approved', '', '2026-02-06 09:14:58', '2026-02-06 09:16:02'),
(3, 5, 'saudi village', 'haunted house', 'house', 'lubak streek', 'minglanilla', 'Saudi', 10000.00, 2, 2, 2, NULL, NULL, 0, 'approved', '', '2026-02-08 14:53:17', '2026-02-08 14:58:10'),
(4, 5, '23 Sampaguita Street, Brgy. Lahug, Cebu City', 'A tropical-style house perfect for vacationers. Surrounded by resorts and island activities, this property offers open living spaces, natural lighting, and easy access to water sports.', 'apartment', '56 Garden Bloom Street, Brgy. Banilad, Cebu City', 'banilad', 'philippines', 150000.00, 5, 2, 2, NULL, NULL, 0, 'approved', '', '2026-02-10 09:59:27', '2026-02-10 10:39:23'),
(5, 5, '101 Riverside Street, Brgy. Poblacion, Danao City', 'A simple and comfortable home near local markets and transport terminals. Best for budget travelers looking for convenience and an authentic local experience.', 'villa', '101 Riverside Street, Brgy. Poblacion, Danao City', 'danao', 'Philippines', 5500.00, 4, 6, 6, NULL, NULL, 0, 'approved', '', '2026-02-10 10:26:36', '2026-02-10 10:39:22'),
(6, 5, '9 Pine Tree Lane, Brgy. Busay, Cebu City', 'A quiet hillside home with a stunning city view. Ideal for guests seeking relaxation, this house features a balcony, cool mountain air, and a calm environment away from city noise.', 'condo', '9 Pine Tree Lane, Brgy. Busay, Cebu City', 'busay', 'Philippines', 250000.00, 8, 8, 10, NULL, NULL, 0, 'approved', '', '2026-02-10 10:35:40', '2026-02-10 10:38:41'),
(7, 7, '72 Coral Reef Road, Brgy. Maribago, Lapu-Lapu City', 'A tropical-style house perfect for vacationers. Surrounded by resorts and island activities, this property offers open living spaces, natural lighting, and easy access to water sports.', 'hotel', '72 Coral Reef Road, Brgy. Maribago, Lapu-Lapu City', 'lapu-lapu', 'Philippines', 3500.00, 3, 4, 4, NULL, NULL, 0, 'approved', '', '2026-02-10 10:56:23', '2026-02-10 10:56:50'),
(8, 7, '18 Sunrise Avenue, Brgy. Basak, Lapu-Lapu City', 'A bright and relaxing home just minutes away from the beach and Mactan Airport. Guests can enjoy air-conditioned rooms, a small garden, and a peaceful coastal vibe.', 'apartment', '18 Sunrise Avenue, Brgy. Basak, Lapu-Lapu City', 'basak', 'Philippines', 4500.00, 8, 4, 4, NULL, NULL, 0, 'approved', '', '2026-02-10 10:59:18', '2026-02-10 11:13:07'),
(9, 7, '45 Mahogany Drive, Brgy. Talamban, Cebu City', 'A modern single-storey house with a spacious living area and private parking. Ideal for long-term stays, this home is close to universities, grocery stores, and local restaurants.', 'apartment', '45 Mahogany Drive, Brgy. Talamban, Cebu City', 'talamban', 'Philippines', 5500.00, 6, 4, 4, NULL, NULL, 0, 'approved', '', '2026-02-10 11:06:46', '2026-02-10 11:13:06'),
(10, 7, 'Modern 2BR Apartment Near City Center', 'This beautiful and fully furnished 2-bedroom apartment is perfect for families, couples, or business travelers. Located in the heart of the city, it offers easy access to malls, restaurants, and public transportation. The unit features a spacious living area, high-speed WiFi, air-conditioning, a fully equipped kitchen, and a private balcony with a relaxing city view. Guests can also enjoy 24/7 security and free parking.', 'apartment', '123 Main Street, Barangay Central', 'quezon', 'Philippines', 1500.00, 2, 2, 2, 0.00000000, 0.00000000, 0, 'approved', '', '2026-02-26 02:13:41', '2026-02-26 02:14:38');

-- --------------------------------------------------------

--
-- Table structure for table `property_amenities`
--

CREATE TABLE `property_amenities` (
  `property_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_amenities`
--

INSERT INTO `property_amenities` (`property_id`, `amenity_id`) VALUES
(1, 1),
(1, 2),
(1, 5),
(1, 7),
(1, 16),
(1, 17),
(2, 1),
(2, 2),
(2, 4),
(2, 5),
(2, 7),
(2, 12),
(2, 16),
(2, 18),
(2, 19),
(3, 3),
(3, 4),
(3, 7),
(3, 8),
(3, 10),
(3, 11),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 17),
(3, 18),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(4, 11),
(4, 12),
(4, 14),
(4, 16),
(4, 17),
(4, 20),
(5, 2),
(5, 3),
(5, 4),
(5, 5),
(5, 8),
(5, 9),
(5, 11),
(5, 12),
(5, 14),
(5, 15),
(5, 16),
(5, 18),
(5, 20),
(6, 2),
(6, 3),
(6, 4),
(6, 5),
(6, 7),
(6, 8),
(6, 9),
(6, 10),
(6, 11),
(6, 12),
(6, 14),
(6, 15),
(6, 16),
(6, 17),
(6, 18),
(6, 20),
(7, 1),
(7, 3),
(7, 5),
(7, 11),
(7, 12),
(7, 13),
(7, 14),
(8, 1),
(8, 2),
(8, 3),
(8, 4),
(8, 5),
(8, 7),
(8, 9),
(8, 11),
(8, 12),
(8, 13),
(8, 14),
(8, 15),
(9, 1),
(9, 3),
(9, 4),
(9, 5),
(9, 7),
(9, 9),
(9, 11),
(9, 14),
(9, 15),
(9, 16),
(9, 17),
(9, 20),
(10, 3),
(10, 4),
(10, 5),
(10, 8),
(10, 9),
(10, 11),
(10, 14),
(10, 16),
(10, 20);

-- --------------------------------------------------------

--
-- Table structure for table `property_photos`
--

CREATE TABLE `property_photos` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_photos`
--

INSERT INTO `property_photos` (`id`, `property_id`, `photo_url`, `is_primary`, `uploaded_at`) VALUES
(1, 5, 'uploads/properties/property_5_1770719196_0.jpg', 1, '2026-02-10 10:26:36'),
(2, 6, 'uploads/properties/property_6_1770719740_0.jpg', 1, '2026-02-10 10:35:40'),
(3, 7, 'uploads/properties/property_7_1770720983_0.webp', 1, '2026-02-10 10:56:23'),
(4, 8, 'uploads/properties/property_8_1770721158_0.webp', 1, '2026-02-10 10:59:18'),
(5, 9, 'uploads/properties/property_9_1770721606_0.webp', 1, '2026-02-10 11:06:46'),
(6, 10, 'uploads/properties/property_10_1772072021_0.webp', 1, '2026-02-26 02:13:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('guest','host','admin') DEFAULT 'guest',
  `host_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `created_at`, `role`, `host_verified`) VALUES
(1, 'angel', 'lou', 'angel@gmail.com', '$2y$10$IhgBdkVY/9C7PEq7f7KWSepGCehUQYsEJWeMPyoQ6g7.pk4NXkxzS', '2026-02-06 07:00:02', 'guest', 0),
(2, 'Admin', 'ServePro', 'admin@servepro.com', '$2y$10$IWAaKuos/UEVZ0boNWZoTOinH2d1n/3Zbi6t41DOI3PXwoASZTm/i', '2026-02-06 08:03:38', 'admin', 0),
(3, 'angela', 'lopez', 'angela@gmail.com', '$2y$10$4f07bVfO1WD/owvmjYnUw.Uol7U.Pb/gtf6XaKT/KL1fqw6hjnBNW', '2026-02-06 08:29:04', 'guest', 0),
(4, 'john', 'cena', 'johncena@gmail.com', '$2y$10$z3W6HvtJSkSkWojp.GGdxuK/bSEgcb7izG2SeELquNDymc0l6oXuK', '2026-02-06 08:37:01', 'guest', 0),
(5, 'nyar', 'qu', 'nyarqu@gmail.com', '$2y$10$1MrD5dQ2JmLLL4dt7ILfPOusbPplvqtSEvziESOsUWWroFDvnrZRW', '2026-02-06 08:44:35', 'host', 0),
(6, 'nyarkow', 'que', 'nyark@gmail.com', '$2y$10$ST6d2HBzzTP/JKri025a8OhE00FBabhP9YmExAQ.4YX7Onls8y9sa', '2026-02-06 09:13:03', 'host', 0),
(7, 'joy', 'joy', 'joy@gmail.com', '$2y$10$aCNkmlSADkV10fs0oKQH1.1QzvSNfosFE9qs2WfEOZwFMlYigmriq', '2026-02-10 10:46:02', 'host', 1),
(8, 'test', 'test', 'test@gmail.com', '$2y$10$QJstYacKdmmTWsCy4zgnC.MJaB3rBJjTOWsGo8FZJgpQv69orTVWi', '2026-02-15 06:55:30', 'guest', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `guest_id` (`guest_id`);

--
-- Indexes for table `host_documents`
--
ALTER TABLE `host_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `host_id` (`host_id`);

--
-- Indexes for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD PRIMARY KEY (`property_id`,`amenity_id`),
  ADD KEY `amenity_id` (`amenity_id`);

--
-- Indexes for table `property_photos`
--
ALTER TABLE `property_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2801;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `host_documents`
--
ALTER TABLE `host_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `property_photos`
--
ALTER TABLE `property_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`guest_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `host_documents`
--
ALTER TABLE `host_documents`
  ADD CONSTRAINT `host_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD CONSTRAINT `property_amenities_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_amenities_ibfk_2` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_photos`
--
ALTER TABLE `property_photos`
  ADD CONSTRAINT `property_photos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
