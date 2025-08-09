-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2025 at 06:26 PM
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
-- Database: `hackathon_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `innovation_score` decimal(3,1) NOT NULL,
  `implementation_score` decimal(3,1) NOT NULL,
  `impact_score` decimal(3,1) NOT NULL,
  `presentation_score` decimal(3,1) NOT NULL,
  `total_score` decimal(4,1) GENERATED ALWAYS AS (`innovation_score` + `implementation_score` + `impact_score` + `presentation_score`) STORED,
  `comments` text DEFAULT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluation_id`, `project_id`, `judge_id`, `innovation_score`, `implementation_score`, `impact_score`, `presentation_score`, `comments`, `evaluated_at`, `updated_at`) VALUES
(1, 11, 4, 2.0, 2.0, 7.0, 2.0, 'you need to improve', '2025-03-21 10:20:54', '2025-03-21 10:20:54'),
(2, 14, 4, 5.0, 5.0, 5.0, 5.0, 'eryery', '2025-03-21 10:20:44', '2025-03-21 10:20:44'),
(3, 15, 4, 10.0, 10.0, 10.0, 10.0, 'sexyyyyyyyyy', '2025-03-21 10:22:33', '2025-03-21 10:22:33'),
(4, 16, 4, 10.0, 10.0, 10.0, 10.0, 'hfhh', '2025-03-27 16:57:48', '2025-03-27 16:57:48'),
(5, 18, 4, 2.0, 5.0, 6.0, 8.0, '', '2025-03-27 16:56:23', '2025-03-27 16:56:23'),
(6, 19, 4, 4.0, 4.0, 4.0, 4.0, 'rg', '2025-03-27 16:56:49', '2025-03-27 16:56:49');

-- --------------------------------------------------------

--
-- Table structure for table `hackathon_settings`
--

CREATE TABLE `hackathon_settings` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Hackhub',
  `description` text DEFAULT NULL,
  `registration_deadline` datetime NOT NULL,
  `submission_deadline` datetime NOT NULL,
  `max_team_size` int(11) NOT NULL DEFAULT 4,
  `min_team_size` int(11) NOT NULL DEFAULT 1,
  `contact_email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hackathon_settings`
--

INSERT INTO `hackathon_settings` (`id`, `name`, `description`, `registration_deadline`, `submission_deadline`, `max_team_size`, `min_team_size`, `contact_email`, `created_at`, `updated_at`) VALUES
(1, 'Hackhub', 'Join us for an amazing hackathon experience! Collaborate, code, and create innovative solutions.', '2025-04-17 21:46:19', '2025-05-17 21:46:19', 4, 1, NULL, '2025-03-18 16:16:19', '2025-03-18 16:16:19'),
(2, 'Hackhub', 'Join us for an amazing hackathon experience!', '2025-04-17 21:56:36', '2025-05-17 21:56:36', 4, 1, NULL, '2025-03-18 16:26:36', '2025-03-18 16:26:36');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `tech_stack` text DEFAULT NULL,
  `github_link` varchar(255) DEFAULT NULL,
  `demo_link` varchar(255) DEFAULT NULL,
  `status` enum('draft','submitted','under_review','rejected','approved') NOT NULL DEFAULT 'draft',
  `submission_date` timestamp NULL DEFAULT NULL,
  `admin_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `team_id`, `title`, `description`, `tech_stack`, `github_link`, `demo_link`, `status`, `submission_date`, `admin_feedback`, `created_at`, `updated_at`) VALUES
(11, 1, '1', '1', '1', '', '', 'approved', '2025-03-21 03:06:27', NULL, '2025-03-21 03:01:33', '2025-03-21 03:06:41'),
(12, 1, '2', '2', '2', '', '', 'rejected', NULL, 'not satisfactory\r\n', '2025-03-21 03:56:06', '2025-03-21 04:01:02'),
(14, 3, '7iui', 'tyiy', 'yit', '', '', 'approved', NULL, NULL, '2025-03-21 09:59:33', '2025-03-21 09:59:54'),
(15, 3, 'wgdwdikquj', 'ewfwf', 'rgeg', '', '', 'approved', '2025-03-21 10:17:56', NULL, '2025-03-21 10:05:33', '2025-03-21 10:18:12'),
(16, 3, 'vsdv', 'fdbve', 'hgerthg', '', '', 'approved', NULL, NULL, '2025-03-21 10:18:47', '2025-03-21 10:19:04'),
(18, 3, 'xyz', 'ssss', 'html', 'https://github.com/topics/open-source-project', 'http://localhost/phpmyadmin/index.php?route=/sql&pos=0&db=hackathon_db&table=users', 'approved', '2025-03-24 05:52:22', NULL, '2025-03-24 05:52:22', '2025-03-27 09:28:03'),
(19, 4, 'dsfsdv', 'asdff', 'dfdsf', '', '', 'approved', '2025-03-25 05:04:41', NULL, '2025-03-25 05:04:41', '2025-03-27 09:27:57'),
(23, 2, 'rada 1', 'only radaaaaaaa', 'xyz', 'https://github.com/khuangaf/CryptocurrencyPrediction', 'https://www.google.com/search?q=ipl&rlz=1C1GIVA_enIN1066IN1066&oq=&gs_lcrp=EgZjaHJvbWUqCQgBECMYJxjqAjIJCAAQIxgnGOoCMgkIARAjGCcY6gIyCQgCECMYJxjqAjIJCAMQIxgnGOoCMgkIBBAjGCcY6gIyCQgFECMYJxjqAjIJCAYQIxgnGOoCMgkIBxAjGCcY6gLSAQkxMzU0ajBqMTWoAgiwAgHxBfex7UR9hBtr', 'approved', NULL, NULL, '2025-03-27 13:56:27', '2025-03-27 13:57:36'),
(25, 2, 'rada 2', 'dvdsv', 'dgew', 'https://github.com/khuangaf/CryptocurrencyPrediction', 'https://www.google.com/search?q=ipl&rlz=1C1GIVA_enIN1066IN1066&oq=&gs_lcrp=EgZjaHJvbWUqCQgBECMYJxjqAjIJCAAQIxgnGOoCMgkIARAjGCcY6gIyCQgCECMYJxjqAjIJCAMQIxgnGOoCMgkIBBAjGCcY6gIyCQgFECMYJxjqAjIJCAYQIxgnGOoCMgkIBxAjGCcY6gLSAQkxMzU0ajBqMTWoAgiwAgHxBfex7UR9hBtr', 'approved', '2025-03-27 09:05:00', NULL, '2025-03-27 15:11:08', '2025-03-27 16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
--

CREATE TABLE `project_files` (
  `file_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_files`
--

INSERT INTO `project_files` (`file_id`, `project_id`, `file_name`, `file_path`, `file_type`, `uploaded_by`, `uploaded_at`) VALUES
(1, 12, '67dce35675445_lic.pdf', 'uploads/project_pdfs/67dce35675445_lic.pdf', 'pdf', 2, '2025-03-21 03:56:06'),
(2, 18, '67e0f316616f5_exp1.pdf', 'uploads/project_pdfs/67e0f316616f5_exp1.pdf', 'pdf', 7, '2025-03-24 05:52:22'),
(3, 23, '67e5590b214b3_print.pdf', 'uploads/project_pdfs/67e5590b214b3_print.pdf', 'pdf', 6, '2025-03-27 13:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `team_id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `team_description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`team_id`, `team_name`, `team_description`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'chaar spothak', 'go hard or go home....!', 2, '2025-03-18 16:19:49', '2025-03-18 16:19:49'),
(2, 'Team cloud1', 'hdhjbebg', 6, '2025-03-21 08:24:01', '2025-03-21 08:24:45'),
(3, 'Team cloud', 'efewfw', 7, '2025-03-21 09:05:42', '2025-03-21 09:05:42'),
(4, 'ai-vengers', 'go hack', 9, '2025-03-25 04:48:46', '2025-03-25 04:48:46'),
(5, 'team 2', 'hrth', 6, '2025-03-27 12:55:45', '2025-03-27 12:55:45');

-- --------------------------------------------------------

--
-- Table structure for table `team_join_requests`
--

CREATE TABLE `team_join_requests` (
  `request_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_join_requests`
--

INSERT INTO `team_join_requests` (`request_id`, `team_id`, `user_id`, `request_message`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 9, 'hello', '', '2025-03-24 05:34:50', '2025-03-25 04:57:56'),
(2, 4, 8, 'im vaishnavi your classmate', '', '2025-03-25 05:11:05', '2025-03-25 05:11:30'),
(6, 3, 8, 'hello', '', '2025-03-25 05:26:42', '2025-03-25 05:27:20');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_leader` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `user_id`, `is_leader`, `joined_at`) VALUES
(1, 1, 2, 1, '2025-03-18 16:19:49'),
(2, 2, 6, 1, '2025-03-21 08:24:01'),
(3, 2, 5, 0, '2025-03-21 08:30:53'),
(4, 3, 7, 1, '2025-03-21 09:05:42'),
(7, 4, 9, 1, '2025-03-25 04:48:46'),
(9, 3, 8, 0, '2025-03-25 05:27:20'),
(10, 5, 6, 1, '2025-03-27 12:55:45'),
(11, 2, 10, 0, '2025-03-27 13:45:05');

-- --------------------------------------------------------

--
-- Table structure for table `team_requests`
--

CREATE TABLE `team_requests` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_requests`
--

INSERT INTO `team_requests` (`id`, `team_id`, `user_id`, `message`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 6, 'You have been invited to join team: chaar spothak', 'rejected', '2025-03-21 08:44:04', '2025-03-27 13:47:05'),
(2, 4, 8, 'You have been invited to join team: ai-vengers', 'pending', '2025-03-25 04:50:57', '2025-03-25 04:50:57'),
(3, 2, 2, 'You have been invited to join team: Team cloud1', 'pending', '2025-03-27 13:04:30', '2025-03-27 13:04:30'),
(4, 1, 8, 'You have been invited to join team: chaar spothak', 'pending', '2025-03-27 13:22:54', '2025-03-27 13:22:54'),
(5, 2, 10, 'You have been invited to join team: Team cloud1', 'approved', '2025-03-27 13:25:18', '2025-03-27 13:45:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `user_type` enum('participant','admin','judge') NOT NULL DEFAULT 'participant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `first_name`, `last_name`, `bio`, `profile_pic`, `skills`, `user_type`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@hackathon.com', '$2y$10$6z5Xm0prWCqnpxbttlIc..eDISbXtXByqfKrBWLr8PWGOxidnw0ia', 'Admin', 'User', NULL, 'uploads/profile_pics/user_1_1743093996.jpg', NULL, 'admin', '2025-03-18 16:13:46', '2025-03-27 16:46:36'),
(2, 'sumitkolhe', 'sumitkolhe057@gmail.com', '$2y$10$N12aqtnHVDylNy82Ba8yseo/xRHLYfJKmwIyi/cEmyQX7To96uB7W', 'sumit', 'kolhe', NULL, 'uploads/profile_pics/user_2_1742318053.jpg', NULL, 'participant', '2025-03-18 16:13:57', '2025-03-18 17:14:13'),
(4, 'judge', 'judge@hackathon.com', '$2y$10$czH4o3vXD6pOe9Wtz9USzuFDfEQg1EXLseW5FeXJJTYgFxVPrm1ou', 'Judge', 'User', NULL, 'uploads/profile_pics/user_4_1743094176.jpg', NULL, 'judge', '2025-03-20 15:54:47', '2025-03-27 16:49:36'),
(5, 'gauravjagtap', 'gaurav@gmail.com', '$2y$10$guHpYRu7mrtrWjLZgGNs5uB8b2aWzQc05.7XEu1bSFpZpaX9gzT1K', 'gaurav', 'jagtap', NULL, NULL, NULL, 'participant', '2025-03-21 05:50:57', '2025-03-21 05:50:57'),
(6, 'onkar', 'onkarindurkar878@gmail.com', '$2y$10$OFPrYEQKM/DdmA83YjRIf.0QXpKfUUJEDWStYPfPcTdfKwlDdtlJO', 'Onkar', 'Indurkar', NULL, 'uploads/profile_pics/user_6_1743074133.jpg', NULL, 'participant', '2025-03-21 08:10:33', '2025-03-27 11:15:33'),
(7, 'gaurav', 'gaurav123@gmail.com', '$2y$10$n8KBQxd0K/SsVpvz/IiJj.LGQ17C2h9RsohpU6qJ98A3fQvf3SGSq', 'gaurav', 'jagtap', NULL, NULL, NULL, 'participant', '2025-03-21 09:04:38', '2025-03-21 09:04:38'),
(8, 'vaishnavi', 'vaishnavi@gmail.com', '$2y$10$MQmgAxSo/zR92Z6ug6qJduOrshxNdC7kgyQ/HNDdRH6KuPrea4VzC', 'vaishnavi', 'koyande', NULL, NULL, NULL, 'participant', '2025-03-24 05:12:50', '2025-03-24 05:12:50'),
(9, 'skawji', 's@gmail.com', '$2y$10$8actKVnBfwXAk4Q1FhHFleZlXnNPSuIfsJ/EpA2TbuXX84yEqm8TS', 'shraddha', 'kawji', NULL, NULL, NULL, 'participant', '2025-03-24 05:32:21', '2025-03-24 05:32:21'),
(10, 'devanshu', 'devanshu@gmail.com', '$2y$10$QbsOvHq9AKVC.P7wn9kUcu9rf0nsVuC9VThYrkF0OOzdacZwuTETS', 'devanshu', 'singh', NULL, NULL, NULL, 'participant', '2025-03-27 13:24:46', '2025-03-27 13:24:46');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 6, 'You have been removed from team \'Team cloud\' by the team leader.', 0, '2025-03-25 05:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `winners`
--

CREATE TABLE `winners` (
  `winner_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `rank_position` int(11) NOT NULL,
  `prize_description` text DEFAULT NULL,
  `announced_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD UNIQUE KEY `project_id` (`project_id`,`judge_id`),
  ADD KEY `judge_id` (`judge_id`);

--
-- Indexes for table `hackathon_settings`
--
ALTER TABLE `hackathon_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`team_id`),
  ADD UNIQUE KEY `team_name` (`team_name`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `team_join_requests`
--
ALTER TABLE `team_join_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `unique_request` (`team_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_id` (`team_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `team_requests`
--
ALTER TABLE `team_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`team_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `winners`
--
ALTER TABLE `winners`
  ADD PRIMARY KEY (`winner_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `team_id` (`team_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `hackathon_settings`
--
ALTER TABLE `hackathon_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `team_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `team_join_requests`
--
ALTER TABLE `team_join_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `team_requests`
--
ALTER TABLE `team_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `winners`
--
ALTER TABLE `winners`
  MODIFY `winner_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`judge_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `team_join_requests`
--
ALTER TABLE `team_join_requests`
  ADD CONSTRAINT `team_join_requests_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_join_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `team_requests`
--
ALTER TABLE `team_requests`
  ADD CONSTRAINT `team_requests_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `winners`
--
ALTER TABLE `winners`
  ADD CONSTRAINT `winners_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`),
  ADD CONSTRAINT `winners_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
