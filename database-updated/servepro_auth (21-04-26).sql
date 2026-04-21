-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 05:38 PM
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
(14, 17, 3, '2026-04-20', '2026-04-22', 1, 35200.00, 'confirmed', '2026-04-18 07:36:02'),
(15, 1, 3, '2026-04-20', '2026-04-30', 1, 439989.00, 'pending', '2026-04-18 07:41:25'),
(16, 1, 3, '2026-04-30', '2026-05-30', 1, 1319967.00, 'pending', '2026-04-18 07:42:34'),
(17, 15, 3, '2026-04-19', '2026-04-22', 1, 14850.00, 'cancelled', '2026-04-18 07:48:32'),
(18, 19, 3, '2026-04-22', '2026-04-24', 1, 33000.00, 'cancelled', '2026-04-21 13:47:16'),
(19, 17, 3, '2026-04-22', '2026-04-26', 1, 70400.00, 'confirmed', '2026-04-21 14:32:24');

-- --------------------------------------------------------

--
-- Table structure for table `booking_cancellations`
--

CREATE TABLE `booking_cancellations` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `policy` enum('flexible','moderate','strict') NOT NULL,
  `refund_percent_preview` int(11) NOT NULL DEFAULT 0,
  `refund_amount_preview` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `cancelled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_cancellations`
--

INSERT INTO `booking_cancellations` (`id`, `booking_id`, `user_id`, `policy`, `refund_percent_preview`, `refund_amount_preview`, `reason`, `cancelled_at`) VALUES
(2, 17, 3, 'moderate', 70, 10395.00, '', '2026-04-19 13:47:04'),
(3, 18, 3, 'moderate', 99, 32670.00, 'change my mind', '2026-04-21 14:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `host_documents`
--

CREATE TABLE `host_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_full_name` varchar(255) DEFAULT NULL,
  `gov_id_type` varchar(100) NOT NULL,
  `gov_id_number` varchar(100) DEFAULT NULL,
  `gov_id_photo_path` varchar(255) DEFAULT NULL,
  `ownership_proof_type` varchar(100) NOT NULL,
  `ownership_reference` varchar(255) DEFAULT NULL,
  `ownership_doc_photo_path` varchar(255) DEFAULT NULL,
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

INSERT INTO `host_documents` (`id`, `user_id`, `id_full_name`, `gov_id_type`, `gov_id_number`, `gov_id_photo_path`, `ownership_proof_type`, `ownership_reference`, `ownership_doc_photo_path`, `business_registration`, `tax_id`, `tourism_license`, `bank_name`, `bank_account_name`, `bank_account_number`, `verification_status`, `created_at`) VALUES
(4, 1, 'angel ko', 'Driver\'s License', '12345', 'uploads/host-documents/1/gov_id_1_1776488787.png', 'Land title / Ownership certificate', '12345', 'uploads/host-documents/1/supporting_doc_1_1776488787.png', '123456', '1231241', '-2024-3344', 'Global Trust Bank', 'John Michael Doe', '123456789012', 'approved', '2026-04-18 05:06:27');

-- --------------------------------------------------------

--
-- Table structure for table `host_ledger`
--

CREATE TABLE `host_ledger` (
  `id` int(11) NOT NULL,
  `host_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `refund_request_id` int(11) DEFAULT NULL,
  `entry_type` enum('booking_credit','refund_debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(5, 17, 3, 1, 'hi can i ask a questions?', '2026-04-21 14:31:35', NULL),
(6, 17, 1, 3, 'okay what is it?', '2026-04-21 14:46:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(40) NOT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link`, `is_read`, `created_at`) VALUES
(1, 1, 'new_message', 'New message', 'angela lopez messaged you about Aloguinsan Adventure Base | Near Falls & River Tours.', '../host/messages.php', 0, '2026-04-21 14:31:35'),
(2, 1, 'booking_created', 'New booking (pending)', 'angela lopez booked Aloguinsan Adventure Base | Near Falls & River Tours · Booking #19', '../host/bookings.php', 0, '2026-04-21 14:32:25'),
(3, 2, 'booking_created', 'New booking (pending)', 'angela lopez booked Aloguinsan Adventure Base | Near Falls & River Tours · Booking #19', '../admin/bookings.php', 0, '2026-04-21 14:32:26'),
(4, 3, 'new_message', 'New message', 'Host replied about Aloguinsan Adventure Base | Near Falls & River Tours.', 'messages.php', 0, '2026-04-21 14:46:06');

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
(13, 14, 'paymongo', 'checkout_session', 35200.00, 'PHP', 'pending', 'cs_aedcb30258ac382d68da747d', NULL, '2026-04-18 07:36:02', '2026-04-18 07:36:03'),
(14, 15, 'paymongo', 'checkout_session', 439989.00, 'PHP', 'pending', 'cs_20c6d6aa7d58d7b819bb7f43', NULL, '2026-04-18 07:41:25', '2026-04-18 07:41:34'),
(15, 16, 'paymongo', 'checkout_session', 1319967.00, 'PHP', 'pending', 'cs_67e47ed20048cf3e9bb7a81c', NULL, '2026-04-18 07:42:34', '2026-04-18 07:42:36'),
(16, 17, 'paymongo', 'checkout_session', 14850.00, 'PHP', 'cancelled', 'cs_f48eec46852bf3a0068380cb', NULL, '2026-04-18 07:48:32', '2026-04-19 13:47:04'),
(17, 18, 'paymongo', 'checkout_session', 33000.00, 'PHP', 'cancelled', 'cs_608e83912a96081ab3296de7', NULL, '2026-04-21 13:47:16', '2026-04-21 14:11:06'),
(18, 19, 'paymongo', 'checkout_session', 70400.00, 'PHP', 'pending', 'cs_871190f3baed7b9a64f5bac0', NULL, '2026-04-21 14:32:24', '2026-04-21 14:32:25');

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
  `cancellation_policy` enum('flexible','moderate','strict') NOT NULL DEFAULT 'moderate',
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

INSERT INTO `properties` (`id`, `host_id`, `title`, `description`, `property_type`, `address`, `city`, `country`, `price_per_night`, `max_guests`, `bedrooms`, `bathrooms`, `latitude`, `longitude`, `auto_accept_bookings`, `cancellation_policy`, `average_rating`, `review_count`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'ECOLOGY', 'lodge', 'house', 'Poblacion', 'Lapu-Lapu', 'Philippines', 39999.00, 2, 1, 1, 10.31313000, 123.94818800, 0, 'moderate', NULL, 0, 'approved', '', '2026-02-06 07:42:05', '2026-04-18 05:32:35'),
(15, 1, 'Lapu-Lapu Coastal Escape | Near Beaches + Pool', 'Unwind in this peaceful Lapu-Lapu retreat just minutes from the beach. Perfect for couples or small groups, the space offers a cozy bed, air conditioning, and fast Wi-Fi. Spend your days exploring nearby islands or relaxing by the pool, then come home to a quiet, comfortable space. Conveniently located near restaurants, cafes, and the airport, it’s the ideal base for your island adventure.', 'condo', 'Bartolome Mangubat Dimataga Street, Poblacion', 'Lapu-Lapu', 'Philippines', 4500.00, 3, 1, 1, 10.31220200, 123.94716600, 0, 'moderate', NULL, 0, 'approved', '', '2026-04-18 06:07:46', '2026-04-18 07:05:05'),
(16, 1, 'Stylish Aloguinsan Stay w/ Balcony & Fast Wi-Fi', 'Enjoy a modern and comfortable stay in Aloguinsan with a touch of style. This space features clean interiors, cozy furnishings, and a relaxing balcony for your morning coffee. Ideal for travelers looking for a peaceful escape while still having essential comforts like Wi-Fi and air conditioning.', 'apartment', 'Aloguinsan, Cebu', 'Aloguinsan', 'Philippines', 2500.00, 2, 1, 1, 10.22821200, 123.55034800, 0, 'moderate', NULL, 0, 'approved', '', '2026-04-18 07:04:22', '2026-04-18 07:06:54'),
(17, 1, 'Aloguinsan Adventure Base | Near Falls & River Tours', 'Perfect for explorers, this Aloguinsan stay puts you close to waterfalls, river cruises, and scenic trails. After a day of adventure, come home to a comfortable and quiet space with all the essentials. Ideal for nature lovers looking for both excitement and relaxation.', 'house', 'Looc', 'Danao', 'Philippines', 16000.00, 8, 3, 3, 10.51161600, 124.02301800, 0, 'moderate', NULL, 0, 'approved', '', '2026-04-18 07:31:46', '2026-04-18 07:33:47'),
(18, 1, 'Romantic Aloguinsan Hideaway | Peaceful & Private', 'Escape the noise and enjoy a quiet, intimate stay in Aloguinsan. This cozy space is perfect for couples seeking privacy and relaxation. Surrounded by nature, it’s an ideal spot to unwind, reconnect, and enjoy slow, peaceful moments together.', 'apartment', 'Diosdado Macapagal Highway, Poblacion', 'Aloguinsan', 'Philippines', 6800.00, 8, 4, 4, 10.22200100, 123.54878300, 0, 'moderate', NULL, 0, 'approved', '', '2026-04-18 08:06:42', '2026-04-18 08:09:25'),
(19, 1, 'Cozy 2-Bedroom Condo in Cebu City', 'A comfortable 2-bedroom condo located in the heart of Cebu City. Offers easy access to malls, restaurants, and business districts, making it ideal for professionals and small families.', 'villa', 'P. Burgos Street, San Roque', 'Cebu City', 'Philippines', 15000.00, 12, 12, 12, 10.29342100, 123.90226100, 0, 'moderate', NULL, 0, 'approved', '', '2026-04-21 13:10:12', '2026-04-21 13:34:17');

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
(15, 1),
(15, 2),
(15, 4),
(15, 5),
(15, 6),
(15, 7),
(15, 8),
(15, 10),
(15, 16),
(15, 17),
(16, 1),
(16, 2),
(16, 6),
(16, 7),
(17, 2),
(17, 3),
(17, 4),
(17, 5),
(17, 7),
(17, 8),
(17, 10),
(17, 11),
(17, 13),
(17, 14),
(17, 15),
(17, 16),
(17, 17),
(17, 18),
(17, 20),
(18, 1),
(18, 2),
(18, 3),
(18, 4),
(18, 5),
(18, 6),
(18, 7),
(18, 8),
(18, 9),
(18, 10),
(18, 11),
(18, 12),
(18, 13),
(18, 14),
(18, 15),
(18, 16),
(18, 17),
(18, 18),
(18, 19),
(18, 20),
(19, 1),
(19, 2),
(19, 3),
(19, 4),
(19, 5),
(19, 6),
(19, 7),
(19, 8),
(19, 9),
(19, 10),
(19, 11),
(19, 12),
(19, 13),
(19, 14),
(19, 15),
(19, 16),
(19, 17),
(19, 18),
(19, 19),
(19, 20);

-- --------------------------------------------------------

--
-- Table structure for table `property_edit_logs`
--

CREATE TABLE `property_edit_logs` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `host_id` int(11) NOT NULL,
  `changes_json` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_edit_logs`
--

INSERT INTO `property_edit_logs` (`id`, `property_id`, `host_id`, `changes_json`, `created_at`) VALUES
(3, 1, 1, '{\"property_id\":1,\"host_id\":1,\"changes\":[{\"field\":\"photos_uploaded\",\"label\":\"Photos uploaded\",\"count\":5}]}', '2026-04-18 05:27:40'),
(4, 1, 1, '{\"property_id\":1,\"host_id\":1,\"changes\":[{\"field\":\"photos_uploaded\",\"label\":\"Photos uploaded\",\"count\":4}]}', '2026-04-18 05:28:06'),
(5, 1, 1, '{\"property_id\":1,\"host_id\":1,\"changes\":[{\"field\":\"address\",\"label\":\"Address\",\"from\":\"Little Valley Colon City of Naga Cebu\",\"to\":\"Poblacion\"},{\"field\":\"city\",\"label\":\"City\",\"from\":\"City of Naga\",\"to\":\"Lapu-Lapu\"},{\"field\":\"latitude\",\"label\":\"Latitude\",\"from\":\"10.3285606\",\"to\":\"10.31313\"},{\"field\":\"longitude\",\"label\":\"Longitude\",\"from\":\"123.9008849\",\"to\":\"123.948188\"}]}', '2026-04-18 05:31:15'),
(6, 16, 1, '{\"property_id\":16,\"host_id\":1,\"changes\":[{\"field\":\"property_type\",\"label\":\"Property type\",\"from\":\"condo\",\"to\":\"apartment\"},{\"field\":\"status\",\"label\":\"Status\",\"from\":\"approved\",\"to\":\"pending\"}]}', '2026-04-18 07:05:38'),
(7, 17, 1, '{\"property_id\":17,\"host_id\":1,\"changes\":[{\"field\":\"photos_deleted\",\"label\":\"Photos deleted\",\"count\":1}]}', '2026-04-18 07:32:40'),
(8, 17, 1, '{\"property_id\":17,\"host_id\":1,\"changes\":[{\"field\":\"property_type\",\"label\":\"Property type\",\"from\":\"hotel\",\"to\":\"house\"}]}', '2026-04-18 07:33:00');

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
(22, 1, 'uploads/properties/property_1_1776490060_0.png', 1, '2026-04-18 05:27:40'),
(23, 1, 'uploads/properties/property_1_1776490060_1.png', 0, '2026-04-18 05:27:40'),
(24, 1, 'uploads/properties/property_1_1776490060_2.png', 0, '2026-04-18 05:27:40'),
(25, 1, 'uploads/properties/property_1_1776490060_3.png', 0, '2026-04-18 05:27:40'),
(26, 1, 'uploads/properties/property_1_1776490060_4.png', 0, '2026-04-18 05:27:40'),
(27, 1, 'uploads/properties/property_1_1776490086_0.png', 0, '2026-04-18 05:28:06'),
(28, 1, 'uploads/properties/property_1_1776490086_1.png', 0, '2026-04-18 05:28:06'),
(29, 1, 'uploads/properties/property_1_1776490086_2.png', 0, '2026-04-18 05:28:06'),
(30, 1, 'uploads/properties/property_1_1776490086_3.png', 0, '2026-04-18 05:28:06'),
(31, 15, 'uploads/properties/property_15_1776492466_0.avif', 1, '2026-04-18 06:07:46'),
(32, 15, 'uploads/properties/property_15_1776492466_1.avif', 0, '2026-04-18 06:07:46'),
(33, 15, 'uploads/properties/property_15_1776492466_2.avif', 0, '2026-04-18 06:07:46'),
(34, 15, 'uploads/properties/property_15_1776492466_3.avif', 0, '2026-04-18 06:07:46'),
(35, 15, 'uploads/properties/property_15_1776492466_4.avif', 0, '2026-04-18 06:07:46'),
(36, 16, 'uploads/properties/property_16_1776495862_0.avif', 1, '2026-04-18 07:04:22'),
(37, 16, 'uploads/properties/property_16_1776495862_1.avif', 0, '2026-04-18 07:04:22'),
(38, 16, 'uploads/properties/property_16_1776495862_2.avif', 0, '2026-04-18 07:04:22'),
(39, 16, 'uploads/properties/property_16_1776495862_3.avif', 0, '2026-04-18 07:04:22'),
(40, 16, 'uploads/properties/property_16_1776495862_4.avif', 0, '2026-04-18 07:04:22'),
(41, 17, 'uploads/properties/property_17_1776497506_0.jpeg', 1, '2026-04-18 07:31:46'),
(42, 17, 'uploads/properties/property_17_1776497506_1.jpeg', 0, '2026-04-18 07:31:46'),
(43, 17, 'uploads/properties/property_17_1776497506_2.jpeg', 0, '2026-04-18 07:31:46'),
(44, 17, 'uploads/properties/property_17_1776497506_3.jpeg', 0, '2026-04-18 07:31:46'),
(46, 18, 'uploads/properties/property_18_1776499602_0.webp', 1, '2026-04-18 08:06:42'),
(47, 18, 'uploads/properties/property_18_1776499602_1.avif', 0, '2026-04-18 08:06:42'),
(48, 18, 'uploads/properties/property_18_1776499602_2.avif', 0, '2026-04-18 08:06:42'),
(49, 18, 'uploads/properties/property_18_1776499602_3.jpeg', 0, '2026-04-18 08:06:42'),
(50, 18, 'uploads/properties/property_18_1776499602_4.avif', 0, '2026-04-18 08:06:42'),
(51, 18, 'uploads/properties/property_18_1776499602_5.avif', 0, '2026-04-18 08:06:42'),
(52, 18, 'uploads/properties/property_18_1776499602_6.avif', 0, '2026-04-18 08:06:42'),
(53, 18, 'uploads/properties/property_18_1776499602_7.avif', 0, '2026-04-18 08:06:42'),
(54, 18, 'uploads/properties/property_18_1776499602_8.webp', 0, '2026-04-18 08:06:42'),
(55, 18, 'uploads/properties/property_18_1776499602_9.avif', 0, '2026-04-18 08:06:42'),
(56, 18, 'uploads/properties/property_18_1776499602_10.avif', 0, '2026-04-18 08:06:42'),
(57, 18, 'uploads/properties/property_18_1776499602_11.avif', 0, '2026-04-18 08:06:42'),
(58, 18, 'uploads/properties/property_18_1776499602_12.avif', 0, '2026-04-18 08:06:42'),
(59, 18, 'uploads/properties/property_18_1776499602_13.avif', 0, '2026-04-18 08:06:42'),
(60, 18, 'uploads/properties/property_18_1776499602_14.avif', 0, '2026-04-18 08:06:42'),
(61, 18, 'uploads/properties/property_18_1776499602_15.avif', 0, '2026-04-18 08:06:42'),
(62, 18, 'uploads/properties/property_18_1776499602_16.avif', 0, '2026-04-18 08:06:42'),
(63, 18, 'uploads/properties/property_18_1776499602_17.avif', 0, '2026-04-18 08:06:42'),
(64, 19, 'uploads/properties/property_19_1776777012_0.avif', 1, '2026-04-21 13:10:12'),
(65, 19, 'uploads/properties/property_19_1776777012_1.avif', 0, '2026-04-21 13:10:12'),
(66, 19, 'uploads/properties/property_19_1776777012_2.avif', 0, '2026-04-21 13:10:12'),
(67, 19, 'uploads/properties/property_19_1776777012_3.avif', 0, '2026-04-21 13:10:12'),
(68, 19, 'uploads/properties/property_19_1776777012_4.avif', 0, '2026-04-21 13:10:12'),
(69, 19, 'uploads/properties/property_19_1776777012_5.avif', 0, '2026-04-21 13:10:12'),
(70, 19, 'uploads/properties/property_19_1776777012_6.avif', 0, '2026-04-21 13:10:12'),
(71, 19, 'uploads/properties/property_19_1776777012_7.avif', 0, '2026-04-21 13:10:12'),
(72, 19, 'uploads/properties/property_19_1776777012_8.avif', 0, '2026-04-21 13:10:12'),
(73, 19, 'uploads/properties/property_19_1776777012_9.avif', 0, '2026-04-21 13:10:12'),
(74, 19, 'uploads/properties/property_19_1776777012_10.avif', 0, '2026-04-21 13:10:12'),
(75, 19, 'uploads/properties/property_19_1776777012_11.avif', 0, '2026-04-21 13:10:12'),
(76, 19, 'uploads/properties/property_19_1776777012_12.avif', 0, '2026-04-21 13:10:12'),
(77, 19, 'uploads/properties/property_19_1776777012_13.avif', 0, '2026-04-21 13:10:12'),
(78, 19, 'uploads/properties/property_19_1776777012_14.avif', 0, '2026-04-21 13:10:12');

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
-- Table structure for table `refund_logs`
--

CREATE TABLE `refund_logs` (
  `id` int(11) NOT NULL,
  `refund_request_id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_role` varchar(20) NOT NULL,
  `action` varchar(60) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `meta_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `refund_logs`
--

INSERT INTO `refund_logs` (`id`, `refund_request_id`, `actor_user_id`, `actor_role`, `action`, `from_status`, `to_status`, `note`, `meta_json`, `created_at`) VALUES
(2, 2, 3, 'guest', 'create_cancellation_refund_request', NULL, 'pending', NULL, '{\"policy\":\"moderate\",\"preview_rule\":\"cancel_12h_to_24h_70\",\"warning\":\"If you cancel now, you will receive a 70% refund.\",\"refund_percent\":70,\"refund_amount\":10395}', '2026-04-19 13:47:04'),
(3, 3, 3, 'guest', 'create_cancellation_refund_request', NULL, 'approved', 'Cancellation reason: change my mind', '{\"policy\":\"moderate\",\"preview_rule\":\"cancel_within_6h_99\",\"warning\":\"If you cancel now, you will receive a 99% refund.\",\"refund_percent\":99,\"refund_amount\":32670}', '2026-04-21 14:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `refund_requests`
--

CREATE TABLE `refund_requests` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `requester_user_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `request_type` enum('cancellation','issue') NOT NULL,
  `issue_type` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `evidence_json` text DEFAULT NULL,
  `policy` enum('flexible','moderate','strict') DEFAULT NULL,
  `refund_percent` int(11) NOT NULL DEFAULT 0,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `status` enum('pending_review','pending','approved','rejected','processing','completed') NOT NULL DEFAULT 'pending',
  `host_decision` enum('none','approve_full','approve_partial','reject') NOT NULL DEFAULT 'none',
  `host_decision_percent` int(11) DEFAULT NULL,
  `host_decision_note` text DEFAULT NULL,
  `admin_override_percent` int(11) DEFAULT NULL,
  `admin_override_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `refund_requests`
--

INSERT INTO `refund_requests` (`id`, `booking_id`, `requester_user_id`, `property_id`, `request_type`, `issue_type`, `description`, `evidence_json`, `policy`, `refund_percent`, `refund_amount`, `currency`, `status`, `host_decision`, `host_decision_percent`, `host_decision_note`, `admin_override_percent`, `admin_override_note`, `created_at`, `updated_at`) VALUES
(2, 17, 3, 15, 'cancellation', NULL, NULL, NULL, 'moderate', 70, 10395.00, 'PHP', 'pending', 'none', NULL, NULL, NULL, NULL, '2026-04-19 13:47:04', '2026-04-19 13:47:04'),
(3, 18, 3, 19, 'cancellation', NULL, NULL, NULL, 'moderate', 99, 32670.00, 'PHP', 'approved', 'none', NULL, NULL, NULL, NULL, '2026-04-21 14:11:06', '2026-04-21 14:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
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

INSERT INTO `users` (`id`, `first_name`, `last_name`, `date_of_birth`, `profile_photo`, `email`, `password`, `created_at`, `role`, `email_verified`, `verification_token`, `host_verified`, `host_verification_status`) VALUES
(1, 'angel', 'lou', '2000-01-01', 'uploads/profile-photos/1/avatar_1_1776784854.webp', 'angel@gmail.com', '$2y$10$IhgBdkVY/9C7PEq7f7KWSepGCehUQYsEJWeMPyoQ6g7.pk4NXkxzS', '2026-02-06 07:00:02', 'host', 1, NULL, 1, 'approved'),
(2, 'Admin', 'ServePro', NULL, NULL, 'admin@servepro.com', '$2y$10$IWAaKuos/UEVZ0boNWZoTOinH2d1n/3Zbi6t41DOI3PXwoASZTm/i', '2026-02-06 08:03:38', 'admin', 1, NULL, 0, 'none'),
(3, 'angela', 'lopez', '2003-01-20', 'uploads/profile-photos/3/avatar_3_1776784401.jpg', 'angela@gmail.com', '$2y$10$4f07bVfO1WD/owvmjYnUw.Uol7U.Pb/gtf6XaKT/KL1fqw6hjnBNW', '2026-02-06 08:29:04', 'guest', 1, NULL, 0, 'none'),
(4, 'john', 'cena', NULL, NULL, 'johncena@gmail.com', '$2y$10$z3W6HvtJSkSkWojp.GGdxuK/bSEgcb7izG2SeELquNDymc0l6oXuK', '2026-02-06 08:37:01', 'guest', 0, NULL, 0, 'none');

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
-- Indexes for table `booking_cancellations`
--
ALTER TABLE `booking_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `host_documents`
--
ALTER TABLE `host_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `host_ledger`
--
ALTER TABLE `host_ledger`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_refund_debit` (`refund_request_id`,`entry_type`),
  ADD KEY `host_id` (`host_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read_time` (`user_id`,`is_read`,`created_at`);

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
-- Indexes for table `property_edit_logs`
--
ALTER TABLE `property_edit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `host_id` (`host_id`);

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
-- Indexes for table `refund_logs`
--
ALTER TABLE `refund_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `refund_request_id` (`refund_request_id`),
  ADD KEY `actor_user_id` (`actor_user_id`);

--
-- Indexes for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `requester_user_id` (`requester_user_id`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46721;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `booking_cancellations`
--
ALTER TABLE `booking_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `host_documents`
--
ALTER TABLE `host_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `host_ledger`
--
ALTER TABLE `host_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `property_edit_logs`
--
ALTER TABLE `property_edit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `property_photos`
--
ALTER TABLE `property_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `property_reviews`
--
ALTER TABLE `property_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund_logs`
--
ALTER TABLE `refund_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `refund_requests`
--
ALTER TABLE `refund_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
-- Constraints for table `booking_cancellations`
--
ALTER TABLE `booking_cancellations`
  ADD CONSTRAINT `booking_cancellations_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_cancellations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `host_documents`
--
ALTER TABLE `host_documents`
  ADD CONSTRAINT `host_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `host_ledger`
--
ALTER TABLE `host_ledger`
  ADD CONSTRAINT `host_ledger_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `host_ledger_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `host_ledger_ibfk_3` FOREIGN KEY (`refund_request_id`) REFERENCES `refund_requests` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `property_edit_logs`
--
ALTER TABLE `property_edit_logs`
  ADD CONSTRAINT `property_edit_logs_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_edit_logs_ibfk_2` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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

--
-- Constraints for table `refund_logs`
--
ALTER TABLE `refund_logs`
  ADD CONSTRAINT `refund_logs_ibfk_1` FOREIGN KEY (`refund_request_id`) REFERENCES `refund_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `refund_logs_ibfk_2` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD CONSTRAINT `refund_requests_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `refund_requests_ibfk_2` FOREIGN KEY (`requester_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `refund_requests_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
