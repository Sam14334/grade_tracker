-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2026 at 09:49 AM
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
-- Database: `grade_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `grade_templates`
--

CREATE TABLE `grade_templates` (
  `template_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_code` varchar(20) DEFAULT NULL,
  `course_title` varchar(100) DEFAULT NULL,
  `class_code` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_templates`
--

INSERT INTO `grade_templates` (`template_id`, `user_id`, `course_code`, `course_title`, `class_code`, `created_at`) VALUES
(1, 1, 'CS-301', 'Software Engineering', 'CS301-SEC6', '2026-09-25 09:17:48'),
(2, 1, 'CS-301', 'Software Engineering', 'CS301-SEC9', '2026-09-25 09:19:49');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(2, 'Professor'),
(3, 'Student');

-- --------------------------------------------------------

--
-- Table structure for table `template_components`
--

CREATE TABLE `template_components` (
  `id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `component_name` varchar(100) DEFAULT NULL,
  `weight` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `template_components`
--

INSERT INTO `template_components` (`id`, `template_id`, `component_name`, `weight`) VALUES
(1, 1, 'Quizzes & Seatwork', 30),
(2, 1, 'Midterm Examination', 30),
(3, 1, 'Final Project & Exam', 40),
(4, 2, 'Assignments & Homework', 50),
(5, 2, 'Quizzes & Seatwork', 20),
(6, 2, 'Final Project & Exam', 30);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role_id`, `created_at`) VALUES
(1, 'John Carlo Nayan', 'catsovercash@iskolarngbayan.pup.edu.ph', '$2y$10$XHPI3aezc537I/kqKhKvPeuR1CHH9B/PSzZutSHbs1J/YwgBf1.uK', 3, '2026-09-18 11:42:02'),
(2, 'ear', 'ear@iskolarngbayan.pup.edu.ph', '$2y$10$2p7d1hsebkPcOKEefG.sEOvChF7zVE2jz0eMZErL.bjmPHhEoudyi', 3, '2026-09-25 05:26:25'),
(3, '123123123', '123123123@iskolarngbayan.pup.edu.ph', '$2y$10$75ODIti6brphStkmuWQ49u.i3XVmV8H88kV/LejHnwaSx7FupisEW', 3, '2026-09-25 05:31:14'),
(4, 'earlbaterbonia', 'earlbaterbonia@iskolarngbayan.pup.edu.ph', '$2y$10$3z.5jAqXlAl36.I80.9PX.1cCoO4ZVQYwej0k7T1voYLIH3s2uP4a', 3, '2026-09-25 07:20:13'),
(5, 'Kiyumeeehh', 'kiyu@iskolarngbayan.pup.edu.ph', '$2y$10$yqRrOGGQMj/LnGyeYVw9uOfEHVebxBZNZ8/qvepbZOON3/gu.aS.a', 3, '2026-09-25 07:21:52'),
(10, 'Prof. Maria Santos', 'professor@pup.edu.ph', '$2y$10$XHPI3aezc537I/kqKhKvPeuR1CHH9B/PSzZutSHbs1J/YwgBf1.uK', 2, '2026-10-02 06:57:10'),
(11, 'Micheal Samia', 'michealsamia@pup.edu.ph', '$2y$10$9Bon37EuoyowduNZSYgyqeva5yKiB012oEq.lkrpRD4VOZrfudiWq', 2, '2026-10-02 07:01:21'),
(14, 'Lebron James', 'lebronjames@iskolarngbayan.pup.edu.ph', '$2y$10$seHb.kNlRNiSrAHqLN3qQO63LNErKttuLGlbovxMQbwRJSODW6bDi', 3, '2026-10-02 07:45:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `grade_templates`
--
ALTER TABLE `grade_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD UNIQUE KEY `class_code` (`class_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `template_components`
--
ALTER TABLE `template_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `grade_templates`
--
ALTER TABLE `grade_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `template_components`
--
ALTER TABLE `template_components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `grade_templates`
--
ALTER TABLE `grade_templates`
  ADD CONSTRAINT `grade_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `template_components`
--
ALTER TABLE `template_components`
  ADD CONSTRAINT `template_components_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `grade_templates` (`template_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
