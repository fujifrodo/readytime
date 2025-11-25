-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 25, 2025 at 02:40 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: ``
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `expire_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `type`, `expire_at`, `created_by`, `created_at`) VALUES
(1, 'Test', 'test', 'info', '2025-11-25 19:20:00', 1, '2025-11-25 12:18:17');

-- --------------------------------------------------------

--
-- Table structure for table `ban_history`
--

CREATE TABLE `ban_history` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `banned_by` int(11) DEFAULT NULL,
  `ban_reason` varchar(500) NOT NULL,
  `ban_until` datetime DEFAULT NULL,
  `banned_at` datetime NOT NULL,
  `unbanned_at` datetime DEFAULT NULL,
  `unbanned_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ban_history`
--

INSERT INTO `ban_history` (`id`, `user_id`, `banned_by`, `ban_reason`, `ban_until`, `banned_at`, `unbanned_at`, `unbanned_by`) VALUES
(5, 1, NULL, 'เนื้อหาไม่เหมาะสม', '2025-11-22 11:44:49', '2025-11-22 10:44:49', '2025-11-21 19:45:03', NULL),
(7, 827796103, 1, 'สแปม/ส่งข้อความรบกวน', '2025-11-26 19:19:03', '2025-11-25 19:19:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `polls`
--

CREATE TABLE `polls` (
  `id` int(11) NOT NULL,
  `token` varchar(20) NOT NULL,
  `title` varchar(500) NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `allow_maybe` tinyint(1) DEFAULT 0,
  `time_mode` varchar(50) DEFAULT 'fullday',
  `created_at` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `creator_name` varchar(255) NOT NULL,
  `expire_date` date DEFAULT NULL,
  `locked_slot_id` varchar(50) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `polls`
--

INSERT INTO `polls` (`id`, `token`, `title`, `week_start`, `week_end`, `allow_maybe`, `time_mode`, `created_at`, `created_by`, `creator_id`, `creator_name`, `expire_date`, `locked_slot_id`, `locked_at`) VALUES
(143682591, 'c9e9e946a', 'ะำหะ/', '2025-11-25', '2025-12-02', 0, 'default', '2025-11-25 11:31:59', NULL, 760408851, 'test', NULL, NULL, NULL),
(383340336, 'c004578f5', 'testsql', '2025-11-25', '2025-12-02', 0, 'default', '2025-11-25 11:23:08', NULL, 812103751, 'test', NULL, NULL, NULL),
(793556722, 'c0aea481e', 'testfinal', '2025-11-25', '2025-12-02', 0, 'default', '2025-11-25 11:45:53', NULL, 0, 'useradmin', NULL, NULL, NULL),
(997035143, 'b404417ad', 'e', '2025-11-25', '2025-12-02', 0, 'default', '2025-11-25 12:14:59', NULL, 827796103, 'test', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `poll_slots`
--

CREATE TABLE `poll_slots` (
  `id` varchar(50) NOT NULL,
  `poll_id` int(11) NOT NULL,
  `slot_date` date NOT NULL,
  `period` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `poll_slots`
--

INSERT INTO `poll_slots` (`id`, `poll_id`, `slot_date`, `period`, `start_time`, `end_time`) VALUES
('1764069788102006', 383340336, '2025-11-27', 'เช้า', '08:00:00', '12:00:00'),
('1764069788160022', 383340336, '2025-12-02', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788249016', 383340336, '2025-11-30', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788255001', 383340336, '2025-11-25', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788321020', 383340336, '2025-12-01', 'เย็น', '18:00:00', '22:00:00'),
('1764069788349018', 383340336, '2025-12-01', 'เช้า', '08:00:00', '12:00:00'),
('1764069788410010', 383340336, '2025-11-28', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788425021', 383340336, '2025-12-02', 'เช้า', '08:00:00', '12:00:00'),
('1764069788492002', 383340336, '2025-11-25', 'เย็น', '18:00:00', '22:00:00'),
('1764069788501015', 383340336, '2025-11-30', 'เช้า', '08:00:00', '12:00:00'),
('1764069788508004', 383340336, '2025-11-26', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788539012', 383340336, '2025-11-29', 'เช้า', '08:00:00', '12:00:00'),
('1764069788561005', 383340336, '2025-11-26', 'เย็น', '18:00:00', '22:00:00'),
('1764069788590014', 383340336, '2025-11-29', 'เย็น', '18:00:00', '22:00:00'),
('1764069788645011', 383340336, '2025-11-28', 'เย็น', '18:00:00', '22:00:00'),
('1764069788655009', 383340336, '2025-11-28', 'เช้า', '08:00:00', '12:00:00'),
('1764069788738003', 383340336, '2025-11-26', 'เช้า', '08:00:00', '12:00:00'),
('1764069788741017', 383340336, '2025-11-30', 'เย็น', '18:00:00', '22:00:00'),
('1764069788749013', 383340336, '2025-11-29', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788856000', 383340336, '2025-11-25', 'เช้า', '08:00:00', '12:00:00'),
('1764069788894007', 383340336, '2025-11-27', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788915023', 383340336, '2025-12-02', 'เย็น', '18:00:00', '22:00:00'),
('1764069788924019', 383340336, '2025-12-01', 'บ่าย', '13:00:00', '17:00:00'),
('1764069788926008', 383340336, '2025-11-27', 'เย็น', '18:00:00', '22:00:00'),
('1764070319112005', 143682591, '2025-11-26', 'เย็น', '18:00:00', '22:00:00'),
('1764070319165020', 143682591, '2025-12-01', 'เย็น', '18:00:00', '22:00:00'),
('1764070319177010', 143682591, '2025-11-28', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319206004', 143682591, '2025-11-26', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319225000', 143682591, '2025-11-25', 'เช้า', '08:00:00', '12:00:00'),
('1764070319225009', 143682591, '2025-11-28', 'เช้า', '08:00:00', '12:00:00'),
('1764070319251017', 143682591, '2025-11-30', 'เย็น', '18:00:00', '22:00:00'),
('1764070319261019', 143682591, '2025-12-01', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319299016', 143682591, '2025-11-30', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319302013', 143682591, '2025-11-29', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319303011', 143682591, '2025-11-28', 'เย็น', '18:00:00', '22:00:00'),
('1764070319422022', 143682591, '2025-12-02', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319458012', 143682591, '2025-11-29', 'เช้า', '08:00:00', '12:00:00'),
('1764070319530023', 143682591, '2025-12-02', 'เย็น', '18:00:00', '22:00:00'),
('1764070319572018', 143682591, '2025-12-01', 'เช้า', '08:00:00', '12:00:00'),
('1764070319591002', 143682591, '2025-11-25', 'เย็น', '18:00:00', '22:00:00'),
('1764070319627007', 143682591, '2025-11-27', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319650015', 143682591, '2025-11-30', 'เช้า', '08:00:00', '12:00:00'),
('1764070319693021', 143682591, '2025-12-02', 'เช้า', '08:00:00', '12:00:00'),
('1764070319739008', 143682591, '2025-11-27', 'เย็น', '18:00:00', '22:00:00'),
('1764070319840006', 143682591, '2025-11-27', 'เช้า', '08:00:00', '12:00:00'),
('1764070319873001', 143682591, '2025-11-25', 'บ่าย', '13:00:00', '17:00:00'),
('1764070319887003', 143682591, '2025-11-26', 'เช้า', '08:00:00', '12:00:00'),
('1764070319936014', 143682591, '2025-11-29', 'เย็น', '18:00:00', '22:00:00'),
('1764071153110016', 793556722, '2025-11-30', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153148006', 793556722, '2025-11-27', 'เช้า', '08:00:00', '12:00:00'),
('1764071153169007', 793556722, '2025-11-27', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153189012', 793556722, '2025-11-29', 'เช้า', '08:00:00', '12:00:00'),
('1764071153222010', 793556722, '2025-11-28', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153269019', 793556722, '2025-12-01', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153278003', 793556722, '2025-11-26', 'เช้า', '08:00:00', '12:00:00'),
('1764071153283008', 793556722, '2025-11-27', 'เย็น', '18:00:00', '22:00:00'),
('1764071153343022', 793556722, '2025-12-02', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153360017', 793556722, '2025-11-30', 'เย็น', '18:00:00', '22:00:00'),
('1764071153366001', 793556722, '2025-11-25', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153377005', 793556722, '2025-11-26', 'เย็น', '18:00:00', '22:00:00'),
('1764071153421000', 793556722, '2025-11-25', 'เช้า', '08:00:00', '12:00:00'),
('1764071153435002', 793556722, '2025-11-25', 'เย็น', '18:00:00', '22:00:00'),
('1764071153436018', 793556722, '2025-12-01', 'เช้า', '08:00:00', '12:00:00'),
('1764071153457004', 793556722, '2025-11-26', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153493011', 793556722, '2025-11-28', 'เย็น', '18:00:00', '22:00:00'),
('1764071153563015', 793556722, '2025-11-30', 'เช้า', '08:00:00', '12:00:00'),
('1764071153643020', 793556722, '2025-12-01', 'เย็น', '18:00:00', '22:00:00'),
('1764071153668013', 793556722, '2025-11-29', 'บ่าย', '13:00:00', '17:00:00'),
('1764071153799021', 793556722, '2025-12-02', 'เช้า', '08:00:00', '12:00:00'),
('1764071153801009', 793556722, '2025-11-28', 'เช้า', '08:00:00', '12:00:00'),
('1764071153892023', 793556722, '2025-12-02', 'เย็น', '18:00:00', '22:00:00'),
('1764071153971014', 793556722, '2025-11-29', 'เย็น', '18:00:00', '22:00:00'),
('1764072899174009', 997035143, '2025-11-28', 'เช้า', '08:00:00', '12:00:00'),
('1764072899180002', 997035143, '2025-11-25', 'เย็น', '18:00:00', '22:00:00'),
('1764072899328018', 997035143, '2025-12-01', 'เช้า', '08:00:00', '12:00:00'),
('1764072899365015', 997035143, '2025-11-30', 'เช้า', '08:00:00', '12:00:00'),
('1764072899378016', 997035143, '2025-11-30', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899398023', 997035143, '2025-12-02', 'เย็น', '18:00:00', '22:00:00'),
('1764072899416008', 997035143, '2025-11-27', 'เย็น', '18:00:00', '22:00:00'),
('1764072899431012', 997035143, '2025-11-29', 'เช้า', '08:00:00', '12:00:00'),
('1764072899441017', 997035143, '2025-11-30', 'เย็น', '18:00:00', '22:00:00'),
('1764072899477003', 997035143, '2025-11-26', 'เช้า', '08:00:00', '12:00:00'),
('1764072899569021', 997035143, '2025-12-02', 'เช้า', '08:00:00', '12:00:00'),
('1764072899585022', 997035143, '2025-12-02', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899587010', 997035143, '2025-11-28', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899632014', 997035143, '2025-11-29', 'เย็น', '18:00:00', '22:00:00'),
('1764072899696000', 997035143, '2025-11-25', 'เช้า', '08:00:00', '12:00:00'),
('1764072899708006', 997035143, '2025-11-27', 'เช้า', '08:00:00', '12:00:00'),
('1764072899708007', 997035143, '2025-11-27', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899749013', 997035143, '2025-11-29', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899785005', 997035143, '2025-11-26', 'เย็น', '18:00:00', '22:00:00'),
('1764072899785020', 997035143, '2025-12-01', 'เย็น', '18:00:00', '22:00:00'),
('1764072899792011', 997035143, '2025-11-28', 'เย็น', '18:00:00', '22:00:00'),
('1764072899794001', 997035143, '2025-11-25', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899848004', 997035143, '2025-11-26', 'บ่าย', '13:00:00', '17:00:00'),
('1764072899875019', 997035143, '2025-12-01', 'บ่าย', '13:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `poll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `submitted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`id`, `poll_id`, `user_id`, `user_name`, `submitted_at`) VALUES
(214372398, 143682591, 760408851, 'test', '2025-11-25 11:43:08'),
(709020767, 997035143, 827796103, 'test', '2025-11-25 12:15:03'),
(728262309, 793556722, 827796103, 'test', '2025-11-25 12:14:45'),
(972153658, 793556722, 0, 'useradmin', '2025-11-25 11:45:57'),
(2147483647, 383340336, 760408851, 'test', '2025-11-25 11:31:24');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'ReadyTime', '2025-11-25 12:17:50'),
(2, 'site_description', 'ระบบนัดหมายออนไลน์', '2025-11-25 12:17:50'),
(3, 'allow_registration', '1', '2025-11-25 12:17:50'),
(4, 'max_polls_per_user', '50', '2025-11-25 12:17:50'),
(5, 'maintenance_mode', '0', '2025-11-25 12:17:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `banned` tinyint(1) DEFAULT 0,
  `token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `ban_reason` varchar(500) DEFAULT NULL COMMENT 'เหตุผลการแบน',
  `ban_until` datetime DEFAULT NULL COMMENT 'แบนจนถึงวันที่ (NULL = ถาวร)',
  `banned_at` datetime DEFAULT NULL COMMENT 'เวลาที่ถูกแบน',
  `banned_by` int(11) DEFAULT NULL COMMENT 'ID ของ admin ที่แบน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `display_name`, `email`, `role`, `banned`, `token`, `created_at`, `last_login`, `ban_reason`, `ban_until`, `banned_at`, `banned_by`) VALUES
(0, 'useradmin', '$2y$10$wYja1MIjGzImU2A.MS33zeCwGqZa/I3sEWyRtGRep/6ul2PX8Xpvi', 'useradmin', 'hty@rth.tj', 'user', 0, 'b15abf3a2d72c124993cecab2045d02ce9cb4ce4031259b8c29174d75e79a95d', '2025-11-24 16:33:11', '2025-11-25 11:45:36', NULL, NULL, NULL, NULL),
(1, 'admin', '$2y$10$AFWo.IyC.hkgx61zTn7fteXcukx3c.BVFnKOtMz3peumcNmwzlpGG', 'admin', '', 'admin', 0, '8f801ee11bbb91a9d8c7f25e6694b2c85860fdf67093a035b969ae8796e5c6bf', '2025-11-21 04:55:40', '2025-11-25 12:40:02', NULL, NULL, NULL, NULL),
(760408851, 'testsql2', '$2y$10$bYVYtHQhIxoZGTdO045rWeRxJmQRpyjK9BY0UR2nyavXpCLHSWtB6', 'test', NULL, 'user', 0, 'd7d3fff1d503d06aedbf6ecc3992f74bd4a0c6dba025e29175548de89dee4e96', '2025-11-25 11:31:15', '2025-11-25 11:31:15', NULL, NULL, NULL, NULL),
(812103751, 'testsql', '$2y$10$LNWQgLYCyb/f3N576gd4vuw0EAa/vkGsThinBWazerLgMrlyntGli', 'test', NULL, 'user', 0, '09ba7710c35deb297c33c60f3f2bdc95e3e84ecb51bcc653a0a45f786287a560', '2025-11-25 11:22:45', '2025-11-25 11:22:45', NULL, NULL, NULL, NULL),
(827796103, 'testsql3', '$2y$10$kF9a287zKwllzKi0Gt1IK.cXWur3wDz2pz4tIfQMbRuOpT3P2TS8K', 'test', NULL, 'user', 1, 'e9adbb371fe3cb4a3698f616c53bd6b607875b88d9cd1e438ebdb70692d23f36', '2025-11-25 12:14:38', '2025-11-25 12:18:39', 'สแปม/ส่งข้อความรบกวน', '2025-11-26 19:19:03', '2025-11-25 19:19:03', 1),
(2147483647, 'usertest', '$2y$10$MdVMcnEPpi.A5a3IBlUn2OwePFmlH1HxgNn8uXR29c9Z2cMbJ3MMm', 'user', NULL, 'user', 0, '2b71b6e77326708baed3924f851d4fb71dd189da586e22a0cee115fb45fa3dd7', '2025-11-24 15:17:10', '2025-11-24 16:32:25', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` varchar(50) NOT NULL,
  `response_id` int(11) NOT NULL,
  `slot_id` varchar(50) NOT NULL,
  `value` enum('yes','maybe','no') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `response_id`, `slot_id`, `value`) VALUES
('1764070284231', 2147483647, '1764069788539012', 'no'),
('1764070284262', 2147483647, '1764069788255001', 'no'),
('1764070284293', 2147483647, '1764069788508004', 'no'),
('1764070284305', 2147483647, '1764069788492002', 'no'),
('1764070284335', 2147483647, '1764069788590014', 'no'),
('1764070284357', 2147483647, '1764069788655009', 'no'),
('1764070284367', 2147483647, '1764069788425021', 'no'),
('1764070284388', 2147483647, '1764069788249016', 'no'),
('1764070284399', 2147483647, '1764069788349018', 'no'),
('1764070284425', 2147483647, '1764069788645011', 'no'),
('1764070284502', 2147483647, '1764069788741017', 'no'),
('1764070284505', 2147483647, '1764069788321020', 'no'),
('1764070284519', 2147483647, '1764069788738003', 'no'),
('1764070284539', 2147483647, '1764069788410010', 'no'),
('1764070284586', 2147483647, '1764069788749013', 'no'),
('1764070284616', 2147483647, '1764069788501015', 'no'),
('1764070284707', 2147483647, '1764069788926008', 'no'),
('1764070284771', 2147483647, '1764069788856000', 'yes'),
('1764070284782', 2147483647, '1764069788102006', 'no'),
('1764070284820', 2147483647, '1764069788924019', 'no'),
('1764070284824', 2147483647, '1764069788561005', 'no'),
('1764070284837', 2147483647, '1764069788915023', 'no'),
('1764070284895', 2147483647, '1764069788894007', 'no'),
('1764070284998', 2147483647, '1764069788160022', 'no'),
('1764070988122', 214372398, '1764070319302013', 'no'),
('1764070988132', 214372398, '1764070319873001', 'no'),
('1764070988133', 214372398, '1764070319572018', 'no'),
('1764070988224', 214372398, '1764070319303011', 'no'),
('1764070988271', 214372398, '1764070319206004', 'no'),
('1764070988418', 214372398, '1764070319261019', 'no'),
('1764070988475', 214372398, '1764070319936014', 'no'),
('1764070988531', 214372398, '1764070319225009', 'no'),
('1764070988540', 214372398, '1764070319693021', 'no'),
('1764070988587', 214372398, '1764070319887003', 'yes'),
('1764070988605', 214372398, '1764070319530023', 'no'),
('1764070988701', 214372398, '1764070319650015', 'no'),
('1764070988703', 214372398, '1764070319177010', 'no'),
('1764070988740', 214372398, '1764070319458012', 'no'),
('1764070988807', 214372398, '1764070319591002', 'no'),
('1764070988813', 214372398, '1764070319627007', 'no'),
('1764070988836', 214372398, '1764070319225000', 'yes'),
('1764070988843', 214372398, '1764070319251017', 'no'),
('1764070988854', 214372398, '1764070319112005', 'no'),
('1764070988868', 214372398, '1764070319165020', 'no'),
('1764070988878', 214372398, '1764070319739008', 'no'),
('1764070988917', 214372398, '1764070319840006', 'no'),
('1764070988919', 214372398, '1764070319299016', 'no'),
('1764070988986', 214372398, '1764070319422022', 'no'),
('1764071157127', 972153658, '1764071153148006', 'no'),
('1764071157171', 972153658, '1764071153435002', 'no'),
('1764071157276', 972153658, '1764071153169007', 'no'),
('1764071157324', 972153658, '1764071153377005', 'no'),
('1764071157420', 972153658, '1764071153457004', 'no'),
('1764071157439', 972153658, '1764071153971014', 'no'),
('1764071157443', 972153658, '1764071153283008', 'no'),
('1764071157454', 972153658, '1764071153668013', 'no'),
('1764071157495', 972153658, '1764071153493011', 'no'),
('1764071157504', 972153658, '1764071153421000', 'yes'),
('1764071157525', 972153658, '1764071153366001', 'no'),
('1764071157579', 972153658, '1764071153269019', 'no'),
('1764071157582', 972153658, '1764071153892023', 'no'),
('1764071157583', 972153658, '1764071153189012', 'no'),
('1764071157596', 972153658, '1764071153643020', 'no'),
('1764071157621', 972153658, '1764071153360017', 'no'),
('1764071157651', 972153658, '1764071153563015', 'no'),
('1764071157683', 972153658, '1764071153799021', 'no'),
('1764071157886', 972153658, '1764071153801009', 'no'),
('1764071157893', 972153658, '1764071153222010', 'no'),
('1764071157917', 972153658, '1764071153343022', 'no'),
('1764071157926', 972153658, '1764071153110016', 'no'),
('1764071157978', 972153658, '1764071153436018', 'no'),
('1764071157994', 972153658, '1764071153278003', 'no'),
('1764072885143', 728262309, '1764071153801009', 'no'),
('1764072885155', 728262309, '1764071153799021', 'no'),
('1764072885165', 728262309, '1764071153148006', 'no'),
('1764072885203', 728262309, '1764071153278003', 'no'),
('1764072885233', 728262309, '1764071153283008', 'no'),
('1764072885368', 728262309, '1764071153110016', 'no'),
('1764072885441', 728262309, '1764071153189012', 'no'),
('1764072885454', 728262309, '1764071153377005', 'no'),
('1764072885479', 728262309, '1764071153435002', 'no'),
('1764072885522', 728262309, '1764071153269019', 'no'),
('1764072885530', 728262309, '1764071153971014', 'no'),
('1764072885542', 728262309, '1764071153493011', 'no'),
('1764072885549', 728262309, '1764071153169007', 'no'),
('1764072885592', 728262309, '1764071153366001', 'no'),
('1764072885626', 728262309, '1764071153360017', 'no'),
('1764072885646', 728262309, '1764071153563015', 'no'),
('1764072885760', 728262309, '1764071153436018', 'no'),
('1764072885802', 728262309, '1764071153892023', 'no'),
('1764072885843', 728262309, '1764071153222010', 'no'),
('1764072885882', 728262309, '1764071153457004', 'no'),
('1764072885896', 728262309, '1764071153421000', 'yes'),
('1764072885932', 728262309, '1764071153668013', 'no'),
('1764072885936', 728262309, '1764071153643020', 'no'),
('1764072885940', 728262309, '1764071153343022', 'no'),
('1764072903107', 709020767, '1764072899365015', 'no'),
('1764072903142', 709020767, '1764072899398023', 'no'),
('1764072903198', 709020767, '1764072899632014', 'no'),
('1764072903211', 709020767, '1764072899441017', 'no'),
('1764072903212', 709020767, '1764072899708006', 'no'),
('1764072903256', 709020767, '1764072899431012', 'no'),
('1764072903262', 709020767, '1764072899174009', 'no'),
('1764072903274', 709020767, '1764072899180002', 'no'),
('1764072903303', 709020767, '1764072899785005', 'no'),
('1764072903346', 709020767, '1764072899875019', 'no'),
('1764072903387', 709020767, '1764072899696000', 'yes'),
('1764072903400', 709020767, '1764072899569021', 'no'),
('1764072903409', 709020767, '1764072899378016', 'no'),
('1764072903466', 709020767, '1764072899477003', 'no'),
('1764072903500', 709020767, '1764072899785020', 'no'),
('1764072903521', 709020767, '1764072899587010', 'no'),
('1764072903698', 709020767, '1764072899792011', 'no'),
('1764072903759', 709020767, '1764072899708007', 'no'),
('1764072903763', 709020767, '1764072899585022', 'no'),
('1764072903781', 709020767, '1764072899416008', 'no'),
('1764072903788', 709020767, '1764072899794001', 'no'),
('1764072903822', 709020767, '1764072899328018', 'no'),
('1764072903851', 709020767, '1764072899749013', 'no'),
('1764072903861', 709020767, '1764072899848004', 'no');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `ban_history`
--
ALTER TABLE `ban_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banned_by` (`banned_by`),
  ADD KEY `unbanned_by` (`unbanned_by`),
  ADD KEY `idx_ban_history_user` (`user_id`,`banned_at`);

--
-- Indexes for table `polls`
--
ALTER TABLE `polls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`creator_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expire_date` (`expire_date`);

--
-- Indexes for table `poll_slots`
--
ALTER TABLE `poll_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_poll_id` (`poll_id`),
  ADD KEY `idx_slot_date` (`slot_date`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_poll_user` (`poll_id`,`user_id`),
  ADD KEY `idx_poll_id` (`poll_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_submitted_at` (`submitted_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_banned` (`banned`),
  ADD KEY `idx_user_banned` (`banned`,`ban_until`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_response_id` (`response_id`),
  ADD KEY `idx_slot_id` (`slot_id`),
  ADD KEY `idx_value` (`value`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ban_history`
--
ALTER TABLE `ban_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ban_history`
--
ALTER TABLE `ban_history`
  ADD CONSTRAINT `ban_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ban_history_ibfk_2` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ban_history_ibfk_3` FOREIGN KEY (`unbanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `polls`
--
ALTER TABLE `polls`
  ADD CONSTRAINT `polls_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `poll_slots`
--
ALTER TABLE `poll_slots`
  ADD CONSTRAINT `poll_slots_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `poll_slots` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
