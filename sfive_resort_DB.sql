SET FOREIGN_KEY_CHECKS=0;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2026 at 11:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sfive_resort`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_otps`
--

DROP TABLE IF EXISTS `admin_login_otps`;
CREATE TABLE `admin_login_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_login_otps`
--

INSERT INTO `admin_login_otps` (`id`, `user_id`, `otp_hash`, `expires_at`, `attempts`, `used`, `created_at`) VALUES
(1, 1, '$2y$10$vLQT83h2Z90Udh9MzcB7BOopejHDvVE8KNoPeb9C0kk0WpNIDvIJC', '2026-08-22 12:57:15', 0, 0, '2026-08-22 04:47:15'),
(2, 1, '$2y$10$SI0j63dB6zEMIxj464XyjeM1eZ5lM3WNi94uIUrs07Ds72LT41aCS', '2026-08-22 12:57:56', 0, 0, '2026-08-22 04:47:56'),
(3, 1, '$2y$10$uv8sIoqzwGGc87dUfcpR4eM0JhjjmTvsTQtV1xOhieWOl.QgLSjoK', '2026-08-22 12:58:41', 1, 0, '2026-08-22 04:48:41'),
(4, 1, '$2y$10$0W6UzVi1100uO0gBLY3Iv.9XhuQ50sCn/IH0ersvvs/KPeQmoUHsK', '2026-08-22 13:00:51', 0, 0, '2026-08-22 04:50:51'),
(5, 1, '$2y$10$XAIF4sQUZapCuM7RqQQALumiBh4L2dAnEQ/oGLHvoW/BD78aUKhj2', '2026-08-22 14:06:09', 0, 0, '2026-08-22 05:56:09'),
(6, 1, '$2y$10$DQfumwfY68xdVYqnTS6f6.ICLdhqXi35ZbI1q8S1hp4kOpwm/p2Vu', '2026-08-22 14:17:16', 0, 0, '2026-08-22 06:07:16'),
(7, 1, '$2y$10$DiFgp8Gja5tdRS2RAw.kX.ABcDyEMZ.5ZVVN4mKFpFMUHuDuSUIBK', '2026-08-22 14:32:09', 0, 0, '2026-08-22 06:22:09'),
(8, 1, '$2y$10$7juawTu1FbjA/s6/VN1aKec5SFczbw7XFVOzfZ1zmyTQMQfpFs7le', '2026-08-22 14:33:11', 0, 0, '2026-08-22 06:23:11'),
(9, 1, '$2y$10$mE626VnNmlHkmI9D2rm0wuDnkYRA/ZiqtWusDPYAmxGM4/0lQCGpS', '2026-08-22 14:36:21', 0, 0, '2026-08-22 06:26:21'),
(10, 1, '$2y$10$6GfRciJKmEDVV4E/AqbpfuG9vm9VfPivGce.L1qAj7dHLX4kEjEKK', '2026-08-22 14:37:36', 0, 0, '2026-08-22 06:27:36'),
(11, 1, '$2y$10$7wsX6QMmxLofe02XE3NXw.KOBLTCml7XG5Eu2TjNPotxicY6M7Xly', '2026-08-22 14:46:27', 1, 1, '2026-08-22 06:36:27'),
(12, 6, '$2y$10$4g3J0EyVXjuF6UYJpfU3RuZBzqB1VCqHnlnU2f.cDYGH48quy/zEy', '2026-08-22 14:49:58', 1, 1, '2026-08-22 06:39:58'),
(13, 1, '$2y$10$GdRn1D/Bq6S25DkD6uVsSOb8somOKLqVMbxXDvHcojCPP2wlaFW7O', '2026-08-22 15:03:48', 1, 1, '2026-08-22 06:53:48'),
(14, 1, '$2y$10$sNevrjdzcPVJWFmBbbvM4ejEKA1sxCNh6GcB5EdJanzbV38l7MJQa', '2026-08-22 15:07:29', 0, 0, '2026-08-22 06:57:29'),
(15, 1, '$2y$10$yTYRa1zSsNpvor3oRl1FHuNX/S.Sj1ASsE7lpsUaSjIjV0PCplwcK', '2026-08-22 15:07:51', 3, 1, '2026-08-22 06:57:51'),
(16, 1, '$2y$10$.SUrZcrnrk15m2iIo5hES.2NBW0Ycq9A7jbCbOIaNvcThlu6MxP0K', '2026-08-22 22:54:05', 0, 0, '2026-08-22 14:44:05'),
(17, 1, '$2y$10$yoHO5R6Re0vAwsvnp8XCuueAPTbemi.rTjTD9ux0fj72tJAmjV0Fq', '2026-08-22 22:55:06', 0, 0, '2026-08-22 14:45:06'),
(18, 1, '$2y$10$Xb6z3KNWASxbayxq.wq8cubmRbk12E6WrsK/WfcWiJ.Synuk/tqJy', '2026-08-22 22:56:48', 1, 1, '2026-08-22 14:46:48'),
(19, 1, '$2y$10$GNGV02rLVrRSA5LLdLNzJeTpSdLbDOdYEktiEX0.B3BC5S4C9ptQG', '2026-08-22 22:57:14', 1, 1, '2026-08-22 14:47:14'),
(20, 7, '$2y$10$C2EcE/ql/0ogofSeki8Zx.nAqDkQcyhhQH2KdEcasdiZJ.iNTaY4C', '2026-08-22 22:58:34', 1, 1, '2026-08-22 14:48:34'),
(21, 1, '$2y$10$JD7WvTbPxzCRVA3tD12M4.66NwuSEhwAPcnd2yZamMZp2po9jE6Xq', '2026-08-22 23:03:04', 1, 1, '2026-08-22 14:53:04'),
(22, 7, '$2y$10$8AAXYJ3.m.QQJu9AJs6w6.pjpgtd8AI4Pzt35n9q4dSxU7YRKI9oq', '2026-08-24 01:01:20', 0, 0, '2026-08-23 16:51:20'),
(23, 7, '$2y$10$6ferGbT07mlIV83ErVG2zOc1iaiZQtcegMOilJ8yjftwHOEExncF.', '2026-08-24 01:02:43', 0, 0, '2026-08-23 16:52:43'),
(24, 7, '$2y$10$mCcRdqaUuR1EAjTzg44cAug1Td.3IaelLneP1qqR3LQSSqclG6jB.', '2026-08-24 01:03:46', 0, 0, '2026-08-23 16:53:46'),
(25, 7, '$2y$10$fnarrpd7MRhYtVnfFiRhTOnuQOQJAFUXw3NOdBZPLVc7vZTBhfkOG', '2026-08-24 01:04:47', 0, 0, '2026-08-23 16:54:47'),
(26, 7, '$2y$10$6yU7XlF7oLk3zqtgsO1iJeXmQ7dupqIDs6GDf8n.Y16IMLLmfXxcu', '2026-08-24 01:04:57', 0, 0, '2026-08-23 16:54:57'),
(27, 7, '$2y$10$D513C7/6vCVgOq1P8Rr1ZOlmxLIWmSVF9zXMXMmPY82jPmMbg5LC6', '2026-08-24 01:05:46', 0, 0, '2026-08-23 16:55:46'),
(28, 7, '$2y$10$hHtFYrLw4RC4cgH/Pa3NVOUvSfhGWYEsuveO0.p7Dre8Hb0by/OmG', '2026-08-24 01:06:36', 0, 0, '2026-08-23 16:56:36'),
(29, 7, '$2y$10$FeAjhRcYaJrzrXXlHjoPXu3K.cDBh8yKWgIKQk3wX/YyffR7y7TIC', '2026-08-24 01:07:47', 0, 0, '2026-08-23 16:57:47'),
(30, 7, '$2y$10$eIV9Wn8SiTcAamha6cE8Nujuc7MF1cIQujGTH8vHa/8bmX/F1hRj.', '2026-08-24 01:09:34', 0, 0, '2026-08-23 16:59:34'),
(31, 7, '$2y$10$Qx7yl.f3jdvTn/ClNRx4Iel2UuPdXIy3P9TO.uMoKx.0AqoJxN4b6', '2026-08-24 01:09:50', 0, 0, '2026-08-23 16:59:50'),
(32, 7, '$2y$10$w7DBOEsBMWxeauBEVW/n9OAqC.G0QHXi2.gOqj8tuqpz5Reo5hroS', '2026-08-24 01:13:27', 0, 0, '2026-08-23 17:03:27'),
(33, 7, '$2y$10$xpWbtViaBGAOHN0UImaH2ey5c.8qyuD3RKBfBmt40nRdanAk8e5Ai', '2026-08-24 01:14:12', 1, 1, '2026-08-23 17:04:12'),
(34, 7, '$2y$10$2DHc8ZCxbnXfU9d3GIfHt.Kwv8n5rlUpaad8YVLXBU25ZOeIhfpC6', '2026-08-26 09:40:34', 1, 1, '2026-08-26 01:30:34'),
(35, 7, '$2y$10$jnB8Oqm3KXknNVm3SFfwf.BB8ifs21.HsMfPwfPAvasIsDwmHmnY.', '2026-09-02 13:00:32', 1, 1, '2026-09-02 04:50:32'),
(36, 7, '$2y$10$91FhOO06GzOIDxDgBgiZmeJJxKNQbT4PXQ815kK96Z81/gFtiwTaW', '2026-09-03 13:00:22', 1, 1, '2026-09-03 04:50:22'),
(37, 7, '$2y$10$Pcsiz7RiXUCOa9qI/t6nmeVkoQE3fdbwl8gLku/cOyDWhm6bRv3Ka', '2026-09-17 15:10:12', 1, 1, '2026-09-17 07:00:12'),
(38, 1, '$2y$10$m1hyhYfqO3udWZKi0keWT.lued8WFNFt6r.iO0xHA9e1BDYgLtIgW', '2026-09-27 22:11:10', 2, 1, '2026-09-27 14:01:10'),
(39, 7, '$2y$10$wnO/BiRJY3cXDsfiCyHjF.68Lxe36Y7BVJhNhYsfS/LesnZCISxk.', '2026-09-30 03:35:10', 1, 1, '2026-09-29 19:25:10');

-- --------------------------------------------------------

--
-- Table structure for table `cottages`
--

DROP TABLE IF EXISTS `cottages`;
CREATE TABLE `cottages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('Cottage - Kids Pool','Cottage - Adult Pool','Pavilion','Rooms') DEFAULT 'Cottage - Kids Pool',
  `description` text DEFAULT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `capacity` int(11) NOT NULL,
  `images` text DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cottages`
--

INSERT INTO `cottages` (`id`, `name`, `category`, `description`, `price_per_night`, `capacity`, `images`, `amenities`, `is_available`, `created_at`) VALUES
(16, 'cottage 1 kiddie pool', 'Cottage - Kids Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:12:02'),
(17, 'front kiddie pool cottage 1', 'Cottage - Kids Pool', '', 500.00, 15, NULL, '', 1, '2026-08-27 06:13:22'),
(18, 'front kiddie pool cottage 2', 'Cottage - Kids Pool', '', 1000.00, 20, NULL, '', 1, '2026-08-27 06:15:36'),
(19, 'cottage 1 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:17:20'),
(20, 'adult pool cottage - Medium', 'Cottage - Adult Pool', '', 500.00, 15, NULL, '', 1, '2026-08-27 06:18:25'),
(21, 'adult pool cottage - Large', 'Cottage - Adult Pool', '', 1000.00, 20, NULL, '', 1, '2026-08-27 06:19:13'),
(22, 'adult pool cottage - XL', 'Cottage - Adult Pool', '', 1500.00, 25, NULL, '', 1, '2026-08-27 06:19:52'),
(23, 'room 1', 'Rooms', '', 1500.00, 3, NULL, '', 1, '2026-08-27 06:21:32'),
(24, 'aircon room 2', 'Rooms', '', 5500.00, 15, NULL, '', 1, '2026-08-27 06:23:46'),
(25, 'room 3', 'Rooms', '', 3500.00, 8, NULL, '', 1, '2026-08-27 06:24:22'),
(26, 'cottage 2 kiddie pool', 'Cottage - Kids Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:46:15'),
(27, 'cottage 3 kiddie pool', 'Cottage - Kids Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:46:44'),
(28, 'cottage 4 kiddie pool', 'Cottage - Kids Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:47:12'),
(30, 'cottage 2 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:53:15'),
(31, 'cottage 3 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:53:51'),
(32, 'cottage 4 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:54:16'),
(33, 'cottage 5 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:54:43'),
(34, 'cottage 6 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:55:07'),
(35, 'cottage 7 - adult pool', 'Cottage - Adult Pool', '', 300.00, 10, NULL, '', 1, '2026-08-27 06:55:49'),
(36, 'Pavilion', 'Pavilion', '', 7000.00, 100, NULL, '', 1, '2026-08-27 06:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `cottage_images`
--

DROP TABLE IF EXISTS `cottage_images`;
CREATE TABLE `cottage_images` (
  `id` int(11) NOT NULL,
  `cottage_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `label` varchar(50) DEFAULT 'Photo',
  `sort_order` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cottage_images`
--

INSERT INTO `cottage_images` (`id`, `cottage_id`, `filename`, `label`, `sort_order`, `uploaded_at`) VALUES
(14, 16, 'cottage_16_thumb_1787811122.jpg', 'Thumbnail', 0, '2026-08-27 06:12:02'),
(15, 17, 'cottage_17_thumb_1787811202.jpg', 'Thumbnail', 0, '2026-08-27 06:13:22'),
(16, 18, 'cottage_18_thumb_1787811336.jpg', 'Thumbnail', 0, '2026-08-27 06:15:36'),
(17, 19, 'cottage_19_thumb_1787811440.jpg', 'Thumbnail', 0, '2026-08-27 06:17:20'),
(18, 20, 'cottage_20_thumb_1787811505.jpg', 'Thumbnail', 0, '2026-08-27 06:18:25'),
(19, 21, 'cottage_21_thumb_1787811553.jpg', 'Thumbnail', 0, '2026-08-27 06:19:13'),
(20, 22, 'cottage_22_thumb_1787811592.jpg', 'Thumbnail', 0, '2026-08-27 06:19:52'),
(21, 23, 'cottage_23_thumb_1787811692.jpg', 'Thumbnail', 0, '2026-08-27 06:21:32'),
(22, 24, 'cottage_24_thumb_1787811826.jpg', 'Thumbnail', 0, '2026-08-27 06:23:46'),
(23, 25, 'cottage_25_thumb_1787811862.jpg', 'Thumbnail', 0, '2026-08-27 06:24:22'),
(24, 26, 'cottage_26_thumb_1787813175.jpg', 'Thumbnail', 0, '2026-08-27 06:46:15'),
(25, 27, 'cottage_27_thumb_1787813204.jpg', 'Thumbnail', 0, '2026-08-27 06:46:44'),
(26, 28, 'cottage_28_thumb_1787813232.jpg', 'Thumbnail', 0, '2026-08-27 06:47:12'),
(28, 30, 'cottage_30_thumb_1787813595.jpg', 'Thumbnail', 0, '2026-08-27 06:53:15'),
(29, 31, 'cottage_31_thumb_1787813631.jpg', 'Thumbnail', 0, '2026-08-27 06:53:51'),
(30, 32, 'cottage_32_thumb_1787813656.jpg', 'Thumbnail', 0, '2026-08-27 06:54:16'),
(31, 33, 'cottage_33_thumb_1787813683.jpg', 'Thumbnail', 0, '2026-08-27 06:54:43'),
(32, 34, 'cottage_34_thumb_1787813707.jpg', 'Thumbnail', 0, '2026-08-27 06:55:07'),
(33, 35, 'cottage_35_thumb_1787813749.jpg', 'Thumbnail', 0, '2026-08-27 06:55:49'),
(34, 36, 'cottage_36_thumb_1787813978.jpg', 'Thumbnail', 0, '2026-08-27 06:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `gallery_images`
--

DROP TABLE IF EXISTS `gallery_images`;
CREATE TABLE `gallery_images` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Pool',
  `filename` varchar(255) NOT NULL,
  `caption` varchar(150) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery_images`
--

INSERT INTO `gallery_images` (`id`, `category`, `filename`, `caption`, `sort_order`, `created_at`) VALUES
(4, 'Pool', 'gallery_1787900746_5547.jpg', 'ADULT POOL', 1, '2026-08-28 07:05:46'),
(5, 'Pavilion & Events', 'gallery_1787900780_7251.jpg', 'PAVILION', 2, '2026-08-28 07:06:20'),
(6, 'Facilities', 'gallery_1787900821_7730.jpg', 'BASKETBALL COURT', 3, '2026-08-28 07:07:01'),
(7, 'Pool', 'gallery_1787900853_6896.jpg', 'KIDDIE POOL', 4, '2026-08-28 07:07:33'),
(8, 'Facilities', 'gallery_1787900881_9629.jpg', 'STORE', 5, '2026-08-28 07:08:01'),
(9, 'Facilities', 'gallery_1787900957_8278.jpg', 'COMFORT ROOM', 6, '2026-08-28 07:09:17'),
(10, 'Garden', 'gallery_1787900974_6175.jpg', 'ENTRANCE', 7, '2026-08-28 07:09:34');

-- --------------------------------------------------------

--
-- Table structure for table `gcash_payments`
--

DROP TABLE IF EXISTS `gcash_payments`;
CREATE TABLE `gcash_payments` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_number` varchar(20) NOT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `payment_type` enum('Full','Downpayment','Balance') NOT NULL DEFAULT 'Full'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gcash_payments`
--

INSERT INTO `gcash_payments` (`id`, `reservation_id`, `reference_number`, `amount`, `sender_name`, `sender_number`, `proof_image`, `status`, `notes`, `submitted_at`, `verified_at`) VALUES
(16, 13, 'link_f5ec6e1c2b8c6f6359f0a3b3', 800.00, 'vic', '09876543211', NULL, 'Verified', '', '2026-08-10 05:56:42', '2026-08-22 12:17:12'),
(17, 14, 'link_0c785a7c078b59aadb0d8315', 2200.00, 'My mame is Vic', '089876543446', NULL, 'Verified', '', '2026-08-12 15:48:59', '2026-08-21 14:13:49'),
(18, 15, 'link_e96e408e59cefc36d02fa361', 2000.00, 'My mame is Vic', '089876543446', NULL, 'Verified', '', '2026-08-12 18:01:14', '2026-08-22 12:14:13'),
(19, 15, 'pay_EEowUqzMdyak3DzNuKXkidnS', 2000.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 18:04:07', NULL),
(20, 15, 'pay_EEowUqzMdyak3DzNuKXkidnS', 2000.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 18:06:52', NULL),
(21, 15, 'pay_EEowUqzMdyak3DzNuKXkidnS', 2000.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 18:07:07', NULL),
(22, 15, 'pay_EEowUqzMdyak3DzNuKXkidnS', 2000.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 18:09:22', NULL),
(23, 15, 'pay_EEowUqzMdyak3DzNuKXkidnS', 2000.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 18:10:10', NULL),
(24, 16, 'cs_e983fb02a663c333403d2358', 800.00, 'yohooo', '09108694690', NULL, 'Verified', '', '2026-08-12 19:59:46', '2026-08-22 12:06:01'),
(25, 16, 'pay_qGY5tfYqUgops8g5EB4APxh6', 800.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 20:02:18', NULL),
(26, 17, 'cs_a6e05d49ce61aa179e575dc9', 800.00, 'My mame is Vic', '09108694690', NULL, 'Verified', '', '2026-08-12 20:03:29', '2026-08-22 12:04:40'),
(27, 18, 'cs_fd67567a8691b0500b05736e', 800.00, 'My mame is Vic', '09108694690', NULL, 'Verified', '', '2026-08-12 20:24:01', '2026-08-22 12:04:12'),
(28, 19, 'cs_0c751e3c372aadce88f1b165', 800.00, 'My mame is Vic', '09108694690', NULL, 'Verified', '', '2026-08-12 21:06:38', '2026-08-22 12:02:57'),
(29, 20, 'cs_3b01a7bb6e7ed49e017d0087', 800.00, 'vince', '09108694690', NULL, 'Verified', '', '2026-08-12 21:11:22', '2026-08-13 15:11:20'),
(30, 21, 'cs_ba46e72c5996b1cec3ae0f2c', 1.00, 'jenevive sasana', '09108694690', NULL, 'Verified', '', '2026-08-12 21:14:05', '2026-08-13 15:02:29'),
(31, 22, 'cs_a9a429147f2ceae6506fd5c2', 1.00, 'jenevive sasana', '09108694690', NULL, 'Verified', 'thankyou for booking sfive', '2026-08-12 21:17:35', '2026-08-13 15:10:32'),
(32, 22, 'pay_92ZTrDtQNabmWQZxjnaTEp4f', 1.00, 'GCash via PayMongo', 'PayMongo Poll', NULL, 'Verified', NULL, '2026-08-12 21:19:51', NULL),
(33, 24, '945405834', 1.00, 'jen', '09108694690', 'gcash_SFR75C70505_1786631852.jpg', 'Verified', 'thankyou for booking', '2026-08-13 14:37:32', '2026-08-13 14:38:17'),
(34, 25, '945405834', 800.00, 'jen', '09108694690', 'gcash_SFR77509701_1786632061.jpg', 'Verified', 'thankyou for booking', '2026-08-13 14:41:01', '2026-08-13 14:41:25'),
(35, 26, '945405834', 1.00, 'beb', '09108694690', 'gcash_SFRC00AA747_1786632519.jpg', 'Verified', 'thankyou for booking sfive', '2026-08-13 14:48:39', '2026-08-13 14:48:56'),
(36, 27, '945405834', 1.00, 'noy', '09108694690', 'gcash_SFR25E41ACD_1786633339.jpg', 'Verified', 'thankyou for booking sfive', '2026-08-13 15:02:19', '2026-08-13 15:02:47'),
(37, 28, 'cs_9233313d582d393676ccc44d', 1.00, 'test', '09108694690', NULL, 'Verified', '', '2026-08-13 15:20:58', '2026-08-21 14:11:13'),
(38, 29, 'cs_e556955532ec3c301b5a954e', 1.00, 'My mame is Vic', '09108694690', NULL, 'Verified', '', '2026-08-14 02:29:34', '2026-08-22 11:56:19'),
(39, 30, 'cs_8c557bd757d15137a96bdf1e', 800.00, 'My mame is Vic', '09108694690', NULL, 'Verified', '', '2026-08-14 02:36:03', '2026-08-22 11:51:00'),
(40, 31, '945405834', 2000.00, 'My mame is Vic', '09108694690', 'gcash_SFRE7EAE82D_1786675063.jpg', 'Verified', '', '2026-08-14 02:37:43', '2026-08-22 11:50:00'),
(41, 43, '945405834', 800.00, 'buloy', '09108694690', 'gcash_SFR372224F8_1787321462.jpg', 'Verified', '', '2026-08-21 14:11:02', '2026-08-21 14:12:24'),
(42, 44, '945405834', 800.00, 'buloy', '09108694690', 'gcash_SFR97617EE0_1787322056.jpg', 'Verified', '', '2026-08-21 14:20:56', '2026-08-21 15:38:01'),
(43, 47, '945405834bjjbfefbweiofbnwif', 7000.00, 'vicvic', '09108694690', 'gcash_SFR83BC3630_1787328122.jpg', 'Verified', '', '2026-08-21 16:02:02', '2026-08-22 11:42:14'),
(44, 49, '945405834', 5000.00, 'vicvic', '09108694690', 'gcash_SFR7230FB2A_1787372736.png', 'Verified', '', '2026-08-22 04:25:36', '2026-08-22 06:47:11'),
(45, 50, '945405834', 7000.00, 'jenevive', '09108694690', 'gcash_SFR685ADDF6_1787398672.jpg', 'Verified', '', '2026-08-22 11:37:52', '2026-08-22 11:42:02'),
(46, 51, '945405834', 800.00, 'jenevive', '09108694690', 'gcash_SFR2D4FB4D8_1787399042.jpg', 'Verified', '', '2026-08-22 11:44:02', '2026-08-22 11:44:34'),
(47, 52, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFRCF7DEBAD_1787401496.jpg', 'Verified', '', '2026-08-22 12:24:56', '2026-08-22 12:25:39'),
(48, 53, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFRF13FA36F_1787401723.jpg', 'Verified', '', '2026-08-22 12:28:43', '2026-08-22 12:29:05'),
(49, 54, '945405834', 2000.00, 'JENEVIVE RAW AY', '09108694690', 'gcash_SFR26597EBF_1787402251.jpg', 'Verified', '', '2026-08-22 12:37:31', '2026-08-22 12:37:48'),
(50, 55, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFR8178C5D7_1787402445.jpg', 'Verified', '', '2026-08-22 12:40:45', '2026-08-22 12:41:22'),
(51, 56, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFR549AA9B3_1787414955.png', 'Verified', '', '2026-08-22 16:09:15', '2026-08-27 05:37:44'),
(52, 57, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFR7F8E7AFC_1787415112.png', 'Verified', '', '2026-08-22 16:11:52', '2026-08-27 05:37:29'),
(53, 58, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFR7423E525_1787415642.png', 'Verified', '', '2026-08-22 16:20:42', '2026-08-23 17:05:48'),
(54, 60, '945405834', 800.00, 'My mame is Jeff', '09505572496', 'gcash_SFRF4CE08AB_1787503505.jpg', 'Verified', '', '2026-08-23 16:45:05', '2026-08-23 16:46:15'),
(55, 61, '945405834', 800.00, 'My mame is Vic', '09108694690', 'gcash_SFR62B7DE0C_1787504341.jpg', 'Verified', '', '2026-08-23 16:59:01', '2026-08-23 17:04:53'),
(56, 62, '945405834', 5000.00, 'vic michael', '09108694690', 'gcash_SFR0E4FD944_1787707791.jpg', 'Verified', '', '2026-08-26 01:29:51', '2026-08-26 01:33:00'),
(57, 63, '945405834', 2000.00, 'My mame is Vic', '09108694690', 'gcash_SFRBAC166FF_1787711953.jpg', 'Verified', '', '2026-08-26 02:39:13', '2026-08-27 04:39:23'),
(58, 64, '09108694690', 1500.00, 'jenevive', '09108694690', 'gcash_SFR01E157BE_1787821646.jpg', 'Verified', '', '2026-08-27 09:07:26', '2026-08-29 01:56:11'),
(59, 66, '09108694690', 15000.00, 'vic', '09108694690', 'gcash_SFR49D02FD8_1787968227.jpg', 'Verified', '', '2026-08-29 01:50:27', '2026-08-29 01:52:28'),
(60, 89, '0987654456788', 0.00, 'cjcjhjchzxj', '34567887654567', 'gcash_SFR80843D70_1790535908.png', 'Verified', '', '2026-09-27 19:05:08', '2026-09-27 19:06:15'),
(61, 108, '0987654456788', 1500.00, 'cjcjhjchzxj', '34567887654567', 'gcash_SFR6BFB5657_1790569146.png', 'Pending', NULL, '2026-09-28 04:19:06', NULL),
(62, 109, '0987654456788', 1500.00, 'sasasasa', '34567887654567', 'gcash_SFRFCFCF57A_1790569438.png', 'Pending', NULL, '2026-09-28 04:23:58', NULL),
(63, 110, '0987654456788', 300.00, 'cjcjhjchzxj', '34567887654567', 'gcash_SFRDF2AD9E8_1790569626.png', 'Pending', NULL, '2026-09-28 04:27:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `gcash_settings`
--

DROP TABLE IF EXISTS `gcash_settings`;
CREATE TABLE `gcash_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL DEFAULT 'S-Five Inland Resort',
  `qr_image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gcash_settings`
--

INSERT INTO `gcash_settings` (`id`, `account_name`, `qr_image`, `updated_at`) VALUES
(1, 'BE***E M.', 'gcash_qr_1787814126.jpg', '2026-08-27 07:02:06');

-- --------------------------------------------------------

--
-- Table structure for table `pavilion_event_prices`
--

DROP TABLE IF EXISTS `pavilion_event_prices`;
CREATE TABLE `pavilion_event_prices` (
  `id` int(11) NOT NULL,
  `cottage_id` int(11) NOT NULL,
  `event_type` enum('Party Event','Birthday Event','Marriage Event') NOT NULL,
  `price_per_night` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pavilion_event_prices`
--

INSERT INTO `pavilion_event_prices` (`id`, `cottage_id`, `event_type`, `price_per_night`) VALUES
(19, 36, 'Party Event', 7000.00),
(20, 36, 'Birthday Event', 15000.00),
(21, 36, 'Marriage Event', 15000.00);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `guest_email` varchar(100) NOT NULL,
  `guest_phone` varchar(20) NOT NULL,
  `cottage_id` int(11) DEFAULT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `booking_type` enum('Day Use','Overnight','Exclusive') NOT NULL DEFAULT 'Overnight',
  `num_guests` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('Pending','Confirmed','Cancelled') DEFAULT 'Pending',
  `cancelled_by` enum('Guest','Admin') DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `payment_method` enum('GCash','Pay on Arrival') DEFAULT 'Pay on Arrival',
  `payment_status` enum('Unpaid','Pending Verification','Partially Paid','Paid') DEFAULT 'Unpaid',
  `payment_option` enum('Full','Downpayment') NOT NULL DEFAULT 'Full',
  `balance_due_date` date DEFAULT NULL,
  `event_type` enum('Party Event','Birthday Event','Marriage Event') DEFAULT NULL,
  `paymongo_link_id` varchar(100) DEFAULT NULL,
  `paymongo_checkout_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `booking_code`, `guest_name`, `guest_email`, `guest_phone`, `cottage_id`, `check_in`, `check_out`, `booking_type`, `num_guests`, `special_requests`, `total_price`, `status`, `cancelled_by`, `cancelled_at`, `payment_method`, `payment_status`, `event_type`, `paymongo_link_id`, `paymongo_checkout_url`, `created_at`) VALUES
(163, 'SFR-294ECAEE', 'adada', 'sfiveinlandresort2018@gmail.com', '09393042464', NULL, '2026-10-03', '2026-10-04', 'Exclusive', 200, '', 40000.00, 'Pending', NULL, NULL, 'Pay on Arrival', 'Unpaid', NULL, NULL, NULL, '2026-09-30 09:13:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `account_status` enum('pending_verification','pending_approval','active','disabled') NOT NULL DEFAULT 'active',
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `otp_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `otp_last_sent` datetime DEFAULT NULL,
  `otp_purpose` enum('register','reset') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `email_verified`, `account_status`, `is_super_admin`, `otp_hash`, `otp_expiry`, `otp_attempts`, `otp_last_sent`, `otp_purpose`, `phone`, `role`, `created_at`) VALUES
(1, 'Admin', 'admin', 'vicmichaelsandoy@gmail.com', '$2y$10$b43X4rr.EtEOvT/ms.CCMOL8ki1AZrIAe5hcbxYGljOxvMJ8IoXBS', 1, 'active', 1, NULL, NULL, 0, NULL, NULL, NULL, 'admin', '2026-06-24 02:54:12'),
(7, 'nonoyvic', NULL, 'nonoyvicsandoy@gmail.com', '$2y$10$YtVixynXD7RUeLDpm8px0ufXHDJTSoWztrUDYLOADeVprxnk9QLRm', 0, 'active', 0, NULL, NULL, 0, NULL, NULL, NULL, 'admin', '2026-08-22 14:48:26'),
(8, 'S-Five Admin', 'sfiveadmin', 'sfiveinlandresort2018@gmail.com', '$2y$10$Fufkmmscb2sMatfuTgrGHuHFCyO54Zz7Dlyzd/DCcVyieZWqOzwYe', 1, 'active', 1, NULL, NULL, 0, NULL, NULL, NULL, 'admin', '2026-09-30 00:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_login_otps`
--
ALTER TABLE `admin_login_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cottages`
--
ALTER TABLE `cottages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cottage_images`
--
ALTER TABLE `cottage_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cottage_id` (`cottage_id`);

--
-- Indexes for table `gallery_images`
--
ALTER TABLE `gallery_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gcash_payments`
--
ALTER TABLE `gcash_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `gcash_settings`
--
ALTER TABLE `gcash_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pavilion_event_prices`
--
ALTER TABLE `pavilion_event_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cottage_event` (`cottage_id`,`event_type`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `cottage_id` (`cottage_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_login_otps`
--
ALTER TABLE `admin_login_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `cottages`
--
ALTER TABLE `cottages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `cottage_images`
--
ALTER TABLE `cottage_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `gallery_images`
--
ALTER TABLE `gallery_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `gcash_payments`
--
ALTER TABLE `gcash_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `gcash_settings`
--
ALTER TABLE `gcash_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pavilion_event_prices`
--
ALTER TABLE `pavilion_event_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cottage_images`
--
ALTER TABLE `cottage_images`
  ADD CONSTRAINT `cottage_images_ibfk_1` FOREIGN KEY (`cottage_id`) REFERENCES `cottages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gcash_payments`
--
-- NOTE: gcash_payments FK skipped: old payment rows point to reservations that no longer exist.
-- ALTER TABLE `gcash_payments`
--   ADD CONSTRAINT `gcash_payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`);

--
-- Constraints for table `pavilion_event_prices`
--
ALTER TABLE `pavilion_event_prices`
  ADD CONSTRAINT `fk_pep_cottage` FOREIGN KEY (`cottage_id`) REFERENCES `cottages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`cottage_id`) REFERENCES `cottages` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
SET FOREIGN_KEY_CHECKS=1;
