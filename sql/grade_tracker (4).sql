-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: grade_tracker
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `enrolled_courses`
--

DROP TABLE IF EXISTS `enrolled_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrolled_courses` (
  `enrollment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `unique_student_template` (`user_id`,`template_id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `enrolled_courses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `enrolled_courses_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `grade_templates` (`template_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrolled_courses`
--

LOCK TABLES `enrolled_courses` WRITE;
/*!40000 ALTER TABLE `enrolled_courses` DISABLE KEYS */;
INSERT INTO `enrolled_courses` VALUES (4,14,6,'2026-10-02 09:08:17'),(5,14,7,'2026-10-02 09:10:52');
/*!40000 ALTER TABLE `enrolled_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_templates`
--

DROP TABLE IF EXISTS `grade_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `course_code` varchar(20) DEFAULT NULL,
  `course_title` varchar(100) DEFAULT NULL,
  `class_code` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `class_code` (`class_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `grade_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_templates`
--

LOCK TABLES `grade_templates` WRITE;
/*!40000 ALTER TABLE `grade_templates` DISABLE KEYS */;
INSERT INTO `grade_templates` VALUES (1,1,'CS-301','Software Engineering','CS301-SEC6','2026-09-25 09:17:48'),(2,1,'CS-301','Software Engineering','CS301-SEC9','2026-09-25 09:19:49'),(6,14,'CS-69','COUNTER STRIKE','CS69-SEC9','2026-10-02 09:07:47'),(7,11,'MS-01','Micheal Samia','MS01-SEC2','2026-10-02 09:10:34');
/*!40000 ALTER TABLE `grade_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (2,'Professor'),(3,'Student');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `template_components`
--

DROP TABLE IF EXISTS `template_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `template_components` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) DEFAULT NULL,
  `component_name` varchar(100) DEFAULT NULL,
  `weight` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_template_components_template` (`template_id`),
  CONSTRAINT `fk_template_components_template` FOREIGN KEY (`template_id`) REFERENCES `grade_templates` (`template_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `template_components`
--

LOCK TABLES `template_components` WRITE;
/*!40000 ALTER TABLE `template_components` DISABLE KEYS */;
INSERT INTO `template_components` VALUES (1,1,'Quizzes & Seatwork',30),(2,1,'Midterm Examination',30),(3,1,'Final Project & Exam',40),(4,2,'Assignments & Homework',50),(5,2,'Quizzes & Seatwork',20),(6,2,'Final Project & Exam',30),(15,6,'Quizzes & Seatwork',30),(16,6,'Midterm Examination',30),(17,6,'Final Project & Exam',40),(18,7,'MIC',30),(19,7,'HEAL',30),(20,7,'SAMIA',40);
/*!40000 ALTER TABLE `template_components` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'John Carlo Nayan','catsovercash@iskolarngbayan.pup.edu.ph','$2y$10$XHPI3aezc537I/kqKhKvPeuR1CHH9B/PSzZutSHbs1J/YwgBf1.uK',3,'2026-09-18 11:42:02'),(2,'ear','ear@iskolarngbayan.pup.edu.ph','$2y$10$2p7d1hsebkPcOKEefG.sEOvChF7zVE2jz0eMZErL.bjmPHhEoudyi',3,'2026-09-25 05:26:25'),(3,'123123123','123123123@iskolarngbayan.pup.edu.ph','$2y$10$75ODIti6brphStkmuWQ49u.i3XVmV8H88kV/LejHnwaSx7FupisEW',3,'2026-09-25 05:31:14'),(4,'earlbaterbonia','earlbaterbonia@iskolarngbayan.pup.edu.ph','$2y$10$3z.5jAqXlAl36.I80.9PX.1cCoO4ZVQYwej0k7T1voYLIH3s2uP4a',3,'2026-09-25 07:20:13'),(5,'Kiyumeeehh','kiyu@iskolarngbayan.pup.edu.ph','$2y$10$yqRrOGGQMj/LnGyeYVw9uOfEHVebxBZNZ8/qvepbZOON3/gu.aS.a',3,'2026-09-25 07:21:52'),(10,'Prof. Maria Santos','professor@pup.edu.ph','$2y$10$XHPI3aezc537I/kqKhKvPeuR1CHH9B/PSzZutSHbs1J/YwgBf1.uK',2,'2026-10-02 06:57:10'),(11,'Micheal Samia','michealsamia@pup.edu.ph','$2y$10$9Bon37EuoyowduNZSYgyqeva5yKiB012oEq.lkrpRD4VOZrfudiWq',2,'2026-10-02 07:01:21'),(14,'Lebron James','lebronjames@iskolarngbayan.pup.edu.ph','$2y$10$seHb.kNlRNiSrAHqLN3qQO63LNErKttuLGlbovxMQbwRJSODW6bDi',3,'2026-10-02 07:45:21');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-02 17:14:51
