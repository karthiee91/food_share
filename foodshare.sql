-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2026 at 08:44 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `foodshare`
--

-- --------------------------------------------------------

--
-- Table structure for table `food_claims`
--

CREATE TABLE `food_claims` (
  `claim_id` int(11) NOT NULL,
  `donation_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_name` varchar(100) NOT NULL,
  `receiver_phone` varchar(15) NOT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_claims`
--

INSERT INTO `food_claims` (`claim_id`, `donation_id`, `receiver_id`, `receiver_name`, `receiver_phone`, `message`, `status`, `created_at`) VALUES
(1, 3, 5, 'uday', '9346811735', 'ok', 'Pending', '2026-09-21 10:47:41'),
(6, 14, 5, 'uday', '9848888905', 'yuiol;', 'Pending', '2026-10-06 07:37:19'),
(7, 16, 5, 'uday', '9505294599', 'dfghj', 'Pending', '2026-10-27 04:24:35'),
(8, 17, 5, 'uday', '9505294599', 'lkjhgfds', 'Pending', '2026-10-27 10:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `food_donations`
--

CREATE TABLE `food_donations` (
  `donation_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `donor_name` varchar(150) NOT NULL,
  `contact` varchar(150) NOT NULL,
  `food_type` varchar(100) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `pickup_datetime` datetime NOT NULL,
  `expiry_datetime` datetime NOT NULL,
  `locality` varchar(150) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_accuracy` decimal(10,2) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_donations`
--

INSERT INTO `food_donations` (`donation_id`, `user_id`, `donor_name`, `contact`, `food_type`, `quantity`, `pickup_datetime`, `expiry_datetime`, `locality`, `latitude`, `longitude`, `location_accuracy`, `location_name`, `details`, `status`, `created_at`) VALUES
(1, 4, 'uday', '9346811735', 'Cooked Food', '10', '2026-09-17 18:02:00', '2026-09-17 22:10:00', 'kanteshwar', NULL, NULL, NULL, NULL, '', 'Expired', '2026-09-16 14:00:57'),
(3, 4, 'srijaa', '9346811735', 'Vegetables', '22', '2026-09-22 03:47:00', '2026-09-23 15:47:00', '0', 18.72546639, 78.14749651, 163.00, 'NH63, Makloor mandal, Nizamabad, Telangana, 503213, India', '', 'Expired', '2026-09-21 10:17:38'),
(4, 7, 'Srija_k', '9346811735', 'Bakery', '10', '2026-10-03 14:30:00', '2026-10-03 15:00:00', '0', 18.72546771, 78.14750415, 182.00, 'NH63, Makloor mandal, Nizamabad, Telangana, 503213, India', '', 'Expired', '2026-10-03 07:38:39'),
(5, 7, 'Srija_k', '9346811735', 'Bakery', '22', '2026-10-03 15:40:00', '2026-10-03 17:00:00', '0', 18.72546960, 78.14751732, 185.00, 'NH63, Makloor mandal, Nizamabad, Telangana, 503213, India', '', 'Expired', '2026-10-03 09:54:10'),
(6, 4, 'srijaa', '9346811735', 'Packaged Food', '22', '2026-10-05 15:50:00', '2026-10-05 18:06:00', 'Nizamabad', NULL, NULL, NULL, 'NH63, Makloor mandal, Nizamabad, Telangana, 503002, India', '', 'Expired', '2026-10-05 09:48:51'),
(8, 7, 'uday', '9346811735', 'sdrftyu', '22', '2026-10-06 12:12:00', '2026-10-06 12:18:00', 'kanteshwar', NULL, NULL, NULL, 'Makloor mandal, Nizamabad, Telangana, 503002, India', 'drftgbh', 'Expired', '2026-10-06 06:44:20'),
(10, 4, 'srijaa', '6303310358', 'xdcfvghbj', '22', '2026-10-06 12:22:00', '2026-10-06 12:25:00', 'Nizamabad', NULL, NULL, NULL, 'Makloor mandal, Nizamabad, Telangana, 503002, India', '', 'Expired', '2026-10-06 06:53:01'),
(14, 7, 'srijaa', '6303310358', 'rice', '10', '2026-10-06 13:06:00', '2026-10-08 13:06:00', 'kanteshwar', NULL, NULL, NULL, 'NH63, Makloor mandal, Nizamabad, Telangana, 503002, India', '', 'Expired', '2026-10-06 07:36:58'),
(15, 7, 'Srija_k', '9346811735', 'rey', '22', '2026-10-06 15:11:00', '2026-10-08 15:11:00', 'Nizamabad', NULL, NULL, NULL, 'vijay rural engineering college,nizamabad,503003', '', 'Expired', '2026-10-06 09:41:54'),
(16, 4, 'uday', '9346811735', 'biryaniii', '10', '2026-10-27 09:50:00', '2026-10-28 09:51:00', 'kanteshwar', NULL, NULL, NULL, 'NH63, Makloor mandal, Nizamabad, Telangana, 503213, India', 'mghjkl', 'Expired', '2026-10-27 04:21:48'),
(17, 4, 'uday', '9346811735', 'biryaniii', '10', '2026-10-27 16:04:00', '2026-10-28 16:05:00', 'VREC dasnagar', NULL, NULL, NULL, 'NH63, Makloor mandal, Nizamabad, Telangana, 503213, India', 'asdfghjllll', 'Claimed', '2026-10-27 10:36:03');

-- --------------------------------------------------------

--
-- Table structure for table `food_requests`
--

CREATE TABLE `food_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requirement` varchar(200) NOT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `locality` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fund_donations`
--

CREATE TABLE `fund_donations` (
  `donation_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `donor_name` varchar(150) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'UPI',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `donate_for` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fund_donations`
--

INSERT INTO `fund_donations` (`donation_id`, `request_id`, `user_id`, `donor_name`, `amount`, `payment_method`, `transaction_ref`, `payment_status`, `donate_for`, `message`, `status`, `created_at`, `gateway_order_id`, `gateway_payment_id`) VALUES
(1, 7, 7, '', 1.00, 'UPI', '628084714280', 'Paid', NULL, NULL, 'pending', '2026-10-07 07:38:58', NULL, NULL),
(2, 8, 4, '', 2.00, 'UPI', '628028112369', 'Paid', NULL, NULL, 'pending', '2026-10-07 07:46:45', NULL, NULL),
(3, 9, 7, '', 2.00, 'UPI', '628091855920', 'Paid', NULL, NULL, 'pending', '2026-10-07 10:22:07', NULL, NULL),
(4, 7, 7, '', 500.00, 'UPI', '628084714280', 'Paid', NULL, NULL, 'pending', '2026-10-07 10:22:53', NULL, NULL),
(5, 19, 4, '', 55.00, 'UPI', '630154260073', 'approved', NULL, NULL, 'pending', '2026-10-28 06:37:42', NULL, NULL),
(6, 18, 4, '', 123456.00, 'UPI', '652651265256', 'approved', NULL, NULL, 'pending', '2026-10-28 07:01:45', NULL, NULL),
(7, 20, 4, '', 22.00, 'UPI', '630101902367', 'approved', NULL, NULL, 'pending', '2026-10-28 09:45:43', NULL, NULL),
(8, 21, 4, '', 54.97, 'UPI', '630102006010', 'pending_verification', NULL, NULL, 'pending', '2026-10-28 09:53:58', NULL, NULL),
(9, 21, 4, '', 54.97, 'UPI', '630102006011', 'pending_verification', NULL, NULL, 'pending', '2026-10-28 09:59:28', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `fund_requests`
--

CREATE TABLE `fund_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `receiver_name` varchar(150) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `medical_certificate` varchar(255) DEFAULT NULL,
  `payment_qr` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `donated_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(200) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fund_requests`
--

INSERT INTO `fund_requests` (`request_id`, `user_id`, `receiver_name`, `age`, `phone`, `medical_certificate`, `payment_qr`, `amount`, `donated_amount`, `reason`, `details`, `status`, `created_at`) VALUES
(18, 5, 'karthik', 18, '9346811735', 'uploads/fund_certificates/certificate_5_1793162822_3fbeb045.pdf', 'uploads/payment_qr/qr_5_1793162822_5150a41c.jpg', 123456.00, 123456.00, 'cancer', 'niiii', 'completed', '2026-10-28 04:47:02'),
(19, 5, 'uday', 18, '9505294599', 'uploads/fund_certificates/certificate_5_1793167685_a172e387.jpg', 'uploads/payment_qr/qr_5_1793167685_eb06fd5c.jpg', 55.00, 55.00, 'fever', '', 'approved', '2026-10-28 06:08:05'),
(20, 5, 'srija', 23, '6303310358', 'uploads/fund_certificates/certificate_5_1793180517_0c98dd07.jpg', 'uploads/payment_qr/qr_5_1793180517_230252cb.jpg', 22.00, 22.00, 'eye operation', '', 'approved', '2026-10-28 09:41:57'),
(21, 5, 'uday', 5, '9523641872', 'uploads/fund_certificates/certificate_5_1793181148_8ca37198.jpg', 'uploads/payment_qr/qr_5_1793181148_88e3fb5d.jpg', 54.97, 0.00, 'fever', '', 'approved', '2026-10-28 09:52:28'),
(22, 5, 'uday', 18, '9505294599', 'uploads/fund_certificates/certificate_5_1793345884_7b0b62ef.pdf', 'uploads/payment_qr/qr_5_1793345884_22ac6277.jpg', 14.99, 0.00, 'fever', '', 'pending', '2026-10-30 07:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Karipe Srija', 'srijakaripe028@gmail.com', '12345678', '', '2026-09-15 09:42:29'),
(2, 'srija', 'srijakari028@gmail.com', '12345678', 'donor', '2026-09-15 10:32:27'),
(4, 'srijaa', 'Srij@gmail.com', '$2y$10$SeVLKocPmDhdJMcXsVlX2.oQlVC8lkagySP59wh/YrrkiNU/jH6be', 'donor', '2026-09-16 06:34:45'),
(5, 'uday', 'uk9893985@gmail.com', '$2y$10$UvcOEPMYNA99kJ/r2r4wbe6XHwlt5IkfapC5t0T3OpF2mGl6fBM1y', 'receiver', '2026-09-16 06:45:31'),
(6, 'karthik', 'karthikpilli@gmail.com', '$2y$10$sxaalPQNpSM4uEY8jsjBbOGR8NT420qZCx5edhp9YmCljQAPlqIEq', 'expert', '2026-09-16 07:09:15'),
(7, 'Srija_k', 'srijakaripe1723@gmail.com', '$2y$10$rtz8uyXl.zZGnfcVxU0uR.bKIbXGSc0lbmF56aYs9VRHdj43/ksI2', 'donor', '2026-10-03 07:36:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `food_claims`
--
ALTER TABLE `food_claims`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `donation_id` (`donation_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `food_donations`
--
ALTER TABLE `food_donations`
  ADD PRIMARY KEY (`donation_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `food_requests`
--
ALTER TABLE `food_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `fund_donations`
--
ALTER TABLE `fund_donations`
  ADD PRIMARY KEY (`donation_id`),
  ADD UNIQUE KEY `uq_gateway_order_id` (`gateway_order_id`),
  ADD UNIQUE KEY `uq_gateway_payment_id` (`gateway_payment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_fund_donations_request_id` (`request_id`);

--
-- Indexes for table `fund_requests`
--
ALTER TABLE `fund_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `food_claims`
--
ALTER TABLE `food_claims`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `food_donations`
--
ALTER TABLE `food_donations`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `food_requests`
--
ALTER TABLE `food_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fund_donations`
--
ALTER TABLE `fund_donations`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `fund_requests`
--
ALTER TABLE `fund_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `food_claims`
--
ALTER TABLE `food_claims`
  ADD CONSTRAINT `fk_food_claim_donation` FOREIGN KEY (`donation_id`) REFERENCES `food_donations` (`donation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_food_claim_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `food_donations`
--
ALTER TABLE `food_donations`
  ADD CONSTRAINT `food_donations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `food_requests`
--
ALTER TABLE `food_requests`
  ADD CONSTRAINT `food_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `fund_donations`
--
ALTER TABLE `fund_donations`
  ADD CONSTRAINT `fund_donations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `fund_requests`
--
ALTER TABLE `fund_requests`
  ADD CONSTRAINT `fund_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
