-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 17, 2026 at 02:47 PM
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
  `status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `host_id`, `title`, `description`, `property_type`, `address`, `city`, `country`, `price_per_night`, `max_guests`, `bedrooms`, `bathrooms`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 5, 'purok bombil', 'haitkaayo', 'house', 'Little Valley Colon City of Naga Cebu', 'City of Naga', 'Philippines', 1000.00, 1, 1, 8, 'approved', '', '2026-02-17 13:44:11', '2026-02-17 13:45:21');

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
(1, 3),
(1, 4),
(1, 5),
(1, 7),
(1, 9),
(1, 16),
(1, 20);

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
(1, 1, 'uploads/properties/property_1_1771335851_0.jpg', 1, '2026-02-17 13:44:11'),
(2, 1, 'uploads/properties/property_1_1771335851_1.png', 0, '2026-02-17 13:44:11');

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
  `role` enum('guest','host','admin') DEFAULT 'guest',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Admin', 'ServePro', 'admin@servepro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-02-17 13:28:06'),
(2, 'John', 'Doe', 'host@servepro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'host', '2026-02-17 13:28:06'),
(3, 'Jane', 'Smith', 'guest@servepro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guest', '2026-02-17 13:28:06'),
(4, 'jowa', 'ko', 'jowa123@gmail.com', '$2y$10$ObElbp0hQcK8LiZUTmbYvukoSLis9FFFE.dV4/0WiRMTwO6ZO2jqS', 'guest', '2026-02-17 13:37:15'),
(5, 'host', 'ko', 'host@gmail.com', '$2y$10$j2evgOmQbXFik0xZxo0Ice8yT2cp6oqk6S474ptMeoyA6w5NQygxK', 'host', '2026-02-17 13:40:15');

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
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `host_id` (`host_id`),
  ADD KEY `status` (`status`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property_photos`
--
ALTER TABLE `property_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
