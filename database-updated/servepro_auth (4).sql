-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 05, 2026 at 06:18 PM
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

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `property_id`, `guest_id`, `check_in`, `check_out`, `guests`, `total_price`, `status`, `booking_date`) VALUES
(1, 13, 1, '2026-03-10', '2026-03-15', 1, 24750.00, 'pending', '2026-03-08 12:36:11'),
(2, 12, 1, '2026-03-19', '2026-03-20', 1, 1958.00, 'confirmed', '2026-03-08 12:44:06'),
(3, 12, 18, '2026-04-06', '2026-04-16', 1, 19580.00, 'pending', '2026-04-05 13:29:53'),
(4, 11, 18, '2026-04-17', '2026-04-21', 1, 8140.00, 'pending', '2026-04-05 13:38:47'),
(5, 13, 15, '2026-04-06', '2026-04-07', 1, 4950.00, 'pending', '2026-04-05 13:49:54'),
(6, 13, 15, '2026-04-09', '2026-04-10', 1, 4950.00, 'pending', '2026-04-05 13:51:46'),
(7, 13, 15, '2026-04-14', '2026-04-15', 1, 4950.00, 'pending', '2026-04-05 13:54:10'),
(8, 13, 15, '2026-04-16', '2026-04-17', 1, 4950.00, 'confirmed', '2026-04-05 13:56:15'),
(9, 12, 15, '2026-04-16', '2026-04-17', 1, 1958.00, 'confirmed', '2026-04-05 13:57:34');

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
  `verification_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `host_documents`
--

INSERT INTO `host_documents` (`id`, `user_id`, `gov_id_type`, `gov_id_number`, `ownership_proof_type`, `ownership_reference`, `business_registration`, `tax_id`, `tourism_license`, `bank_name`, `bank_account_name`, `bank_account_number`, `verification_status`, `created_at`) VALUES
(1, 7, 'Passport', 'PH-PASS-2026-0001', 'Land title / Ownership certificate', 'Transfer Certificate of Title No. TCT-458792 under the name of Juan Dela Cruz. Property located at 123 Main Street, Quezon City.', 'DTI-2025-9876543', '123-456-789-000', 'QC-Tourism-2025-1122', 'BDO Unibank', 'Juan Dela Cruz', '012345678901', 'approved', '2026-02-26 02:32:17'),
(2, 6, 'Passport', '123', 'Land title / Ownership certificate', '123', '', '1231241', '', 'jameron', 'jaime', '123', 'pending', '2026-03-01 11:01:03'),
(3, 5, 'Passport', '12345', 'Land title / Ownership certificate', '12345', '12345', '12345', '-2024-3344', 'Global Trust Bank', 'John Michael Doe', '123456789012', 'pending', '2026-03-01 11:18:05');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `property_id`, `sender_id`, `receiver_id`, `message`, `created_at`, `read_at`) VALUES
(1, 10, 9, 7, 'hi can i ask more info about ur unit', '2026-03-01 10:19:04', NULL),
(2, 10, 7, 9, 'okay what info would like to know specifically?', '2026-03-01 10:30:35', NULL),
(3, 6, 1, 5, 'why is your pic is not property?', '2026-03-01 11:53:24', NULL),
(4, 6, 1, 5, 'lala', '2026-03-01 11:53:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `method` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `status` enum('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `external_reference` varchar(191) DEFAULT NULL,
  `raw_payload` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `provider`, `method`, `amount`, `currency`, `status`, `external_reference`, `raw_payload`, `created_at`, `updated_at`) VALUES
(1, 2, 'gcash', 'gcash', 1958.00, 'PHP', 'pending', NULL, NULL, '2026-03-08 12:44:06', '2026-03-08 12:44:06'),
(2, 3, 'paymongo', 'checkout_session', 19580.00, 'PHP', 'pending', NULL, NULL, '2026-04-05 13:29:53', '2026-04-05 13:29:53'),
(3, 4, 'manual', 'pay_later', 8140.00, 'PHP', 'pending', NULL, NULL, '2026-04-05 13:38:47', '2026-04-05 13:38:47'),
(4, 5, 'paymongo', 'checkout_session', 4950.00, 'PHP', 'pending', 'cs_d1973ce86d86a306729b1af1', NULL, '2026-04-05 13:49:54', '2026-04-05 13:49:55'),
(5, 6, 'paymongo', 'checkout_session', 4950.00, 'PHP', 'pending', 'cs_d8c36c4f789176b1784e8932', NULL, '2026-04-05 13:51:46', '2026-04-05 13:51:54'),
(6, 7, 'paymongo', 'checkout_session', 4950.00, 'PHP', 'pending', 'cs_93dca425445394ae31972a14', NULL, '2026-04-05 13:54:10', '2026-04-05 13:54:11'),
(7, 8, 'paymongo', 'checkout_session', 4950.00, 'PHP', 'pending', 'cs_1cac90cc93e3155662293710', NULL, '2026-04-05 13:56:15', '2026-04-05 13:56:16'),
(8, 9, 'paymongo', 'checkout_session', 1958.00, 'PHP', 'pending', 'cs_ef0ff28727d7ac1569e9fd2f', NULL, '2026-04-05 13:57:34', '2026-04-05 13:57:35');

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
  `average_rating` decimal(3,2) DEFAULT NULL,
  `review_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected','suspended','out_of_order') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `host_id`, `title`, `description`, `property_type`, `address`, `city`, `country`, `price_per_night`, `max_guests`, `bedrooms`, `bathrooms`, `latitude`, `longitude`, `auto_accept_bookings`, `average_rating`, `review_count`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'ECOLOGY', 'lodge', 'house', 'Little Valley Colon City of Naga Cebu', 'City of Naga', 'Philippines', 5000.00, 2, 1, 1, 10.32856060, 123.90088490, 0, NULL, 0, 'approved', '', '2026-02-06 07:42:05', '2026-03-01 12:15:18'),
(2, 6, 'nyark house', 'pit house', 'hotel', 'pangdan naga cebu', 'City of Naga', 'Philippines', 500.00, 3, 1, 2, 10.32856060, 123.90088490, 0, NULL, 0, 'approved', '', '2026-02-06 09:14:58', '2026-03-01 12:15:20'),
(3, 5, 'saudi village', 'haunted house', 'house', 'lubak streek', 'minglanilla', 'Saudi', 10000.00, 2, 2, 2, 14.59950000, 120.98420000, 0, NULL, 0, 'approved', '', '2026-02-08 14:53:17', '2026-03-01 12:08:45'),
(4, 5, '23 Sampaguita Street, Brgy. Lahug, Cebu City', 'A tropical-style house perfect for vacationers. Surrounded by resorts and island activities, this property offers open living spaces, natural lighting, and easy access to water sports.', 'apartment', '56 Garden Bloom Street, Brgy. Banilad, Cebu City', 'banilad', 'philippines', 150000.00, 5, 2, 2, 10.32856060, 123.90088490, 0, NULL, 0, 'approved', '', '2026-02-10 09:59:27', '2026-03-01 12:15:23'),
(6, 5, '9 Pine Tree Lane, Brgy. Busay, Cebu City', 'A quiet hillside home with a stunning city view. Ideal for guests seeking relaxation, this house features a balcony, cool mountain air, and a calm environment away from city noise.', 'condo', '9 Pine Tree Lane, Brgy. Busay, Cebu City', 'busay', 'Philippines', 250000.00, 8, 8, 10, 10.32856060, 123.90088490, 0, NULL, 0, 'approved', '', '2026-02-10 10:35:40', '2026-03-01 12:15:26'),
(7, 7, '72 Coral Reef Road, Brgy. Maribago, Lapu-Lapu City', 'A tropical-style house perfect for vacationers. Surrounded by resorts and island activities, this property offers open living spaces, natural lighting, and easy access to water sports.', 'hotel', '72 Coral Reef Road, Brgy. Maribago, Lapu-Lapu City', 'lapu-lapu', 'Philippines', 3500.00, 3, 4, 4, 10.31190000, 123.94940000, 0, NULL, 0, 'approved', '', '2026-02-10 10:56:23', '2026-03-01 12:08:58'),
(8, 7, '18 Sunrise Avenue, Brgy. Basak, Lapu-Lapu City', 'A bright and relaxing home just minutes away from the beach and Mactan Airport. Guests can enjoy air-conditioned rooms, a small garden, and a peaceful coastal vibe.', 'apartment', '18 Sunrise Avenue, Brgy. Basak, Lapu-Lapu City', 'basak', 'Philippines', 4500.00, 8, 4, 4, 14.59950000, 120.98420000, 0, NULL, 0, 'approved', '', '2026-02-10 10:59:18', '2026-03-01 12:09:01'),
(9, 7, '45 Mahogany Drive, Brgy. Talamban, Cebu City', 'A modern single-storey house with a spacious living area and private parking. Ideal for long-term stays, this home is close to universities, grocery stores, and local restaurants.', 'apartment', '45 Mahogany Drive, Brgy. Talamban, Cebu City', 'talamban', 'Philippines', 5500.00, 6, 4, 4, 10.36935750, 123.91693150, 0, NULL, 0, 'approved', '', '2026-02-10 11:06:46', '2026-03-01 12:15:30'),
(10, 7, 'Modern 2BR Apartment Near City Center', 'This beautiful and fully furnished 2-bedroom apartment is perfect for families, couples, or business travelers. Located in the heart of the city, it offers easy access to malls, restaurants, and public transportation. The unit features a spacious living area, high-speed WiFi, air-conditioning, a fully equipped kitchen, and a private balcony with a relaxing city view. Guests can also enjoy 24/7 security and free parking.', 'apartment', '123 Main Street, Barangay Central', 'quezon', 'Philippines', 1500.00, 2, 2, 2, 14.59950000, 120.98420000, 0, NULL, 0, 'approved', '', '2026-02-26 02:13:41', '2026-03-01 12:09:07'),
(11, 7, 'Modern 2BR Condo with City View', 'A cozy and modern 2-bedroom condo located in the heart of the city. The unit features a fully equipped kitchen, high-speed WiFi, air conditioning, and a balcony with a great skyline view. Perfect for families, couples, or business travelers looking for comfort and convenience.', 'condo', '145 Natalio B. Bacalso South National Highway, Barangay Tina-an', 'minglanilla cebu', 'Philippines', 1850.00, 1, 1, 2, 10.24673200, 123.79959900, 0, NULL, 0, 'approved', '', '2026-03-08 10:09:02', '2026-03-08 10:23:46'),
(12, 7, 'Cozy Studio Near MRT', 'Minimalist studio unit located near MRT station and malls. Ideal for solo travelers and short stays.', 'villa', '78 Pioneer Street, Mandaluyong', 'City of Naga', 'Philippines', 1780.00, 3, 3, 3, 10.25050000, 123.71742200, 0, NULL, 0, 'approved', '', '2026-03-08 10:32:54', '2026-03-08 10:33:28'),
(13, 7, 'Luxury 3BR Penthouse with Pool Access', 'Spacious penthouse with panoramic views, private balcony, smart TV, and access to pool and gym.', 'villa', '220 Makati Avenue, Bel-Air Village', 'City: Makati City', 'Philippines', 4500.00, 5, 5, 5, 10.25050000, 123.71742200, 0, NULL, 0, 'approved', '', '2026-03-08 11:22:31', '2026-03-08 11:25:39'),
(14, 7, 'apartment near gaisano', 'relaxing', 'apartment', 'Cansojong barangay Hall', 'Talisay City', 'Philippines', 3580.00, 6, 3, 3, 10.24916300, 123.85429000, 0, NULL, 0, 'approved', '', '2026-04-05 15:52:51', '2026-04-05 15:54:16');

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
(10, 20),
(11, 1),
(11, 4),
(11, 6),
(11, 7),
(11, 9),
(11, 11),
(11, 12),
(11, 14),
(11, 15),
(11, 16),
(11, 19),
(12, 4),
(12, 9),
(12, 11),
(12, 12),
(12, 14),
(12, 15),
(12, 17),
(12, 20),
(13, 1),
(13, 2),
(13, 3),
(13, 5),
(13, 6),
(13, 8),
(13, 9),
(13, 11),
(13, 12),
(13, 13),
(13, 14),
(13, 16),
(14, 4),
(14, 5),
(14, 6),
(14, 9),
(14, 12),
(14, 14),
(14, 15),
(14, 16),
(14, 17),
(14, 19);

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
(2, 6, 'uploads/properties/property_6_1770719740_0.jpg', 1, '2026-02-10 10:35:40'),
(3, 7, 'uploads/properties/property_7_1770720983_0.webp', 1, '2026-02-10 10:56:23'),
(4, 8, 'uploads/properties/property_8_1770721158_0.webp', 1, '2026-02-10 10:59:18'),
(5, 9, 'uploads/properties/property_9_1770721606_0.webp', 1, '2026-02-10 11:06:46'),
(6, 10, 'uploads/properties/property_10_1772072021_0.webp', 1, '2026-02-26 02:13:41'),
(7, 11, 'uploads/properties/property_11_1772964542_0.webp', 1, '2026-03-08 10:09:02'),
(8, 11, 'uploads/properties/property_11_1772964542_1.jpg', 0, '2026-03-08 10:09:02'),
(9, 11, 'uploads/properties/property_11_1772964542_2.webp', 0, '2026-03-08 10:09:02'),
(10, 12, 'uploads/properties/property_12_1772965974_0.webp', 1, '2026-03-08 10:32:54'),
(11, 12, 'uploads/properties/property_12_1772965974_1.webp', 0, '2026-03-08 10:32:54'),
(12, 12, 'uploads/properties/property_12_1772965974_2.jpg', 0, '2026-03-08 10:32:54'),
(13, 13, 'uploads/properties/property_13_1772968951_0.jpg', 0, '2026-03-08 11:22:31'),
(14, 13, 'uploads/properties/property_13_1772968951_1.jpg', 1, '2026-03-08 11:22:31'),
(15, 13, 'uploads/properties/property_13_1772968951_2.jpg', 0, '2026-03-08 11:22:31'),
(16, 14, 'uploads/properties/property_14_1775404371_0.webp', 1, '2026-04-05 15:52:51'),
(17, 14, 'uploads/properties/property_14_1775404371_1.webp', 0, '2026-04-05 15:52:51'),
(18, 14, 'uploads/properties/property_14_1775404371_2.webp', 0, '2026-04-05 15:52:51');

-- --------------------------------------------------------

--
-- Table structure for table `property_reviews`
--

CREATE TABLE `property_reviews` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('guest','host','admin') DEFAULT 'guest',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `host_verified` tinyint(1) NOT NULL DEFAULT 0,
  `host_verification_status` enum('none','under review','approved','rejected') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `date_of_birth`, `email`, `password`, `created_at`, `role`, `email_verified`, `verification_token`, `host_verified`, `host_verification_status`) VALUES
(1, 'angel', 'lou', NULL, 'angel@gmail.com', '$2y$10$IhgBdkVY/9C7PEq7f7KWSepGCehUQYsEJWeMPyoQ6g7.pk4NXkxzS', '2026-02-06 07:00:02', 'guest', 0, NULL, 0, 'none'),
(2, 'Admin', 'ServePro', NULL, 'admin@servepro.com', '$2y$10$IWAaKuos/UEVZ0boNWZoTOinH2d1n/3Zbi6t41DOI3PXwoASZTm/i', '2026-02-06 08:03:38', 'admin', 1, NULL, 0, 'none'),
(3, 'angela', 'lopez', NULL, 'angela@gmail.com', '$2y$10$4f07bVfO1WD/owvmjYnUw.Uol7U.Pb/gtf6XaKT/KL1fqw6hjnBNW', '2026-02-06 08:29:04', 'guest', 0, NULL, 0, 'none'),
(4, 'john', 'cena', NULL, 'johncena@gmail.com', '$2y$10$z3W6HvtJSkSkWojp.GGdxuK/bSEgcb7izG2SeELquNDymc0l6oXuK', '2026-02-06 08:37:01', 'guest', 0, NULL, 0, 'none'),
(5, 'nyar', 'qu', NULL, 'nyarqu@gmail.com', '$2y$10$1MrD5dQ2JmLLL4dt7ILfPOusbPplvqtSEvziESOsUWWroFDvnrZRW', '2026-02-06 08:44:35', 'host', 0, NULL, 0, 'under review'),
(6, 'nyarkow', 'que', NULL, 'nyark@gmail.com', '$2y$10$ST6d2HBzzTP/JKri025a8OhE00FBabhP9YmExAQ.4YX7Onls8y9sa', '2026-02-06 09:13:03', 'host', 0, NULL, 1, 'approved'),
(7, 'joy', 'joy', NULL, 'joy@gmail.com', '$2y$10$aCNkmlSADkV10fs0oKQH1.1QzvSNfosFE9qs2WfEOZwFMlYigmriq', '2026-02-10 10:46:02', 'host', 1, NULL, 1, 'approved'),
(8, 'test', 'test', NULL, 'test@gmail.com', '$2y$10$QJstYacKdmmTWsCy4zgnC.MJaB3rBJjTOWsGo8FZJgpQv69orTVWi', '2026-02-15 06:55:30', 'guest', 0, NULL, 0, 'none'),
(9, 'juan', 'mananavas2', NULL, 'juan@gmail.com', '$2y$10$H2v7kHDLCvSwPjVBsmeNyurdz7Hqd/OrMWpLgq9KKVC.VcrmBVEhS', '2026-03-01 10:18:25', 'guest', 0, NULL, 0, 'none'),
(11, 'joshua', 'padilla', NULL, 'joshua@gmail.com', '$2y$10$K0WMqqrVZ5pW5XmPmZwg2e.DQcKfUQm4QpEjsUoK4DWY00v5QF30W', '2026-03-02 08:25:26', 'host', 0, 'ab39126683f9f288f5c62f2e772a0dfef6de4b01f6be4961a92745eccf802f2e', 0, 'none'),
(12, 'duterte', 'dgong', NULL, 'cxduterte30@gmail.com', '$2y$10$lijw4xA9fqaWxEKD6ADYOu.spWlZ01SCt6FVQSaT9rtDeT4Y5ObrO', '2026-03-08 15:28:25', 'guest', 1, NULL, 0, 'none'),
(13, 'dasd', 'dasdsa', NULL, 'saturonjogo2@gmail.com', '$2y$10$FyYsUlrEtrJaKzCVNH33Oe5e9kEmT1L6NnV8y058/WeYMNS0vCcsq', '2026-03-08 15:36:35', 'guest', 0, '15e09ac98c79700a10eb6089c3266f3b07b15f3f826bcb6339c9f54881852558', 0, 'none'),
(14, 'sdasd', 'dfgfdgdf', NULL, 'saturonjogo3@gmail.com', '$2y$10$YYBa6L1MCIR81oG9vlP69emsLYo5aHQ8EcSoNaLddkQaprdrN7VHe', '2026-03-08 15:39:09', 'guest', 1, NULL, 0, 'none'),
(15, 'lucky', 'me', NULL, 'saturonjogo4@gmail.com', '$2y$10$FQDy71fryQaZXJMWt367buNoFejKCxVoLg3gvcL86dz5now2ayMrS', '2026-03-09 12:46:19', 'guest', 1, NULL, 0, 'none'),
(16, 'parknhas', 'park', NULL, 'saturonjogo6@gmail.com', '$2y$10$ZpMK8Zu3UvdG1Ce6bhMv6OXzGCQ0Zvs6RtF9FWWhc/x6HjReIGxSW', '2026-03-09 13:59:54', 'guest', 1, NULL, 0, 'none'),
(17, 'pancit', 'canton', NULL, 'saturonjogo5@gmail.com', '$2y$10$QKup4gZMCfbJg2ZNxspYxORkc.gciCeaVp0DwGHJFgz67Gonj7QL.', '2026-03-09 14:22:19', 'guest', 1, NULL, 0, 'none'),
(18, 'mobile', 'legends', NULL, 'legends@gmail.com', '$2y$10$0cXkwjVs2pTm6BQgfg71puPQ0jKdPPYBHGg1y.W5ZUP6mBYizZA4u', '2026-03-09 14:53:23', 'guest', 1, NULL, 0, 'none'),
(19, 'vienz', 'libradilla', NULL, 'vienz@gmail.com', '$2y$10$kv.KvxI5wO0kcH6T4wb/lOHEHk4p8Fm3mZYPAHBw1.jWbMnZP01Ji', '2026-03-24 02:43:52', 'admin', 1, 'b20d7ad579161b1a1006b883289da75b19fbe68a07a54ec559298befa746549b', 0, 'none');

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
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

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
-- Indexes for table `property_reviews`
--
ALTER TABLE `property_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_property_guest` (`property_id`,`guest_id`),
  ADD KEY `guest_id` (`guest_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20501;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `host_documents`
--
ALTER TABLE `host_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `property_photos`
--
ALTER TABLE `property_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `property_reviews`
--
ALTER TABLE `property_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

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

--
-- Constraints for table `property_reviews`
--
ALTER TABLE `property_reviews`
  ADD CONSTRAINT `property_reviews_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_reviews_ibfk_2` FOREIGN KEY (`guest_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
