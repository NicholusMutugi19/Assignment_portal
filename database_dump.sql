/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: mysql-cb961aa-nicholusm-ac.a.aivencloud.com    Database: defaultdb
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int unsigned NOT NULL,
  `lecturer_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT '100.00',
  `deadline` datetime NOT NULL,
  `allow_late` tinyint(1) NOT NULL DEFAULT '0',
  `late_penalty` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percentage deducted for late submissions',
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lecturer_id` (`lecturer_id`),
  KEY `idx_course` (`course_id`),
  KEY `idx_deadline` (`deadline`),
  KEY `idx_status` (`status`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES
(1,1,1,'PHP File Upload System','Build a secure file upload system using PHP. Submit a ZIP containing all source files and a README.',NULL,NULL,100.00,'2026-04-02 20:51:31',1,20.00,'published','2026-03-26 20:51:31','2026-03-26 20:51:31'),
(2,1,1,'REST API Design','Design and implement a RESTful API for a library management system. Document all endpoints.',NULL,NULL,100.00,'2026-04-09 20:51:31',0,0.00,'published','2026-03-26 20:51:31','2026-03-26 20:51:31'),
(3,2,1,'ER Diagram & Normalisation','Create an ER diagram for a hospital management system and normalise to 3NF. Submit as PDF.',NULL,NULL,100.00,'2026-03-24 20:51:31',0,0.00,'closed','2026-03-26 20:51:31','2026-03-26 20:51:31'),
(4,3,1,'Software Requirements Spec','Write an SRS document for an online banking application following IEEE 830 standard.',NULL,NULL,100.00,'2026-03-29 20:51:31',1,10.00,'published','2026-03-26 20:51:31','2026-03-26 20:51:31'),
(5,16,5,'DSA','Assignment II','uploads/assignments/69c5b5a2848b63.89081890_Design_and_Analysis_of_Algirithms_SPC_2302__CATs.docx','Design and Analysis of Algirithms SPC 2302  CATs.docx',100.00,'2026-03-31 01:39:00',0,0.00,'published','2026-03-26 22:39:30','2026-03-26 22:39:30');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `lecturer_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_lecturer` (`lecturer_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES
(1,'CS301','Web Technologies','Advanced web development concepts including PHP, MySQL and CGI.',23,'2026-03-26 20:51:30'),
(2,'CS204','Database Systems','Relational databases, SQL, normalization and transactions.',1,'2026-03-26 20:51:30'),
(3,'CS410','Software Engineering','SDLC, design patterns, testing and project management.',1,'2026-03-26 20:51:30'),
(16,'CS101','Introduction to Computer Science',NULL,21,'2026-03-26 22:30:04'),
(17,'CS201','Data Structures and Algorithms',NULL,21,'2026-03-26 22:30:04'),
(18,'CS302','Database Systems',NULL,15,'2026-03-26 22:30:04'),
(19,'CS303','Web Development',NULL,15,'2026-03-26 22:30:05'),
(20,'CS403','Computer Networks',NULL,21,'2026-03-26 22:30:05'),
(21,'CS404','Cybersecurity',NULL,21,'2026-03-26 22:30:05'),
(22,'CS502','Distributed Systems',NULL,5,'2026-03-26 22:30:05'),
(23,'CS304','Mobile Application Development',NULL,21,'2026-03-26 22:33:58'),
(24,'CS401','Artificial Intelligence',NULL,21,'2026-03-26 22:33:58'),
(25,'CS402','Machine Learning',NULL,21,'2026-03-26 22:33:58'),
(26,'CS501','Advanced Algorithms',NULL,1,'2026-03-26 22:33:58'),
(27,'CS503','Cloud Computing',NULL,1,'2026-03-26 22:33:58'),
(28,'CS504','DevOps and CI/CD',NULL,5,'2026-03-26 22:33:58'),
(29,'CS601','Computer Vision',NULL,1,'2026-03-26 22:33:58'),
(30,'CS602','Natural Language Processing',NULL,1,'2026-03-26 22:33:58'),
(31,'CS603','Blockchain Technology',NULL,5,'2026-03-26 22:33:58'),
(32,'CS604','Internet of Things (IoT)',NULL,1,'2026-03-26 22:33:58'),
(33,'CS701','Research Methods in Computing',NULL,1,'2026-03-26 22:33:58'),
(34,'CS702','Capstone Project',NULL,1,'2026-03-26 22:33:58');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `student_id` int unsigned NOT NULL,
  `course_id` int unsigned NOT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES
(2,1,'2026-03-26 20:51:31'),
(2,2,'2026-03-26 20:51:31'),
(2,3,'2026-03-26 20:51:31'),
(3,1,'2026-03-26 20:51:31'),
(3,2,'2026-03-26 20:51:31'),
(4,2,'2026-03-26 20:51:31'),
(4,3,'2026-03-26 20:51:31'),
(14,1,'2026-03-26 23:11:21'),
(14,2,'2026-03-26 23:11:21'),
(14,16,'2026-03-26 23:11:21'),
(14,17,'2026-03-26 23:11:21'),
(14,20,'2026-03-26 23:11:21'),
(14,28,'2026-03-26 23:11:21'),
(14,29,'2026-03-26 23:11:21'),
(14,30,'2026-03-26 23:11:21'),
(14,31,'2026-03-26 23:11:21'),
(14,32,'2026-03-26 23:11:21'),
(14,33,'2026-03-26 23:11:21'),
(17,1,'2026-03-26 23:54:56'),
(17,2,'2026-03-26 23:54:56'),
(17,16,'2026-03-26 23:54:56'),
(17,17,'2026-03-26 23:54:56'),
(17,22,'2026-03-26 23:54:56'),
(17,26,'2026-03-26 23:54:56'),
(17,27,'2026-03-26 23:54:56'),
(17,28,'2026-03-26 23:54:56'),
(18,1,'2026-03-27 05:02:06'),
(18,2,'2026-03-27 05:02:06'),
(19,16,'2026-03-27 06:14:22'),
(19,21,'2026-03-27 06:14:22'),
(19,22,'2026-03-27 06:14:23'),
(19,24,'2026-03-27 06:14:22'),
(19,28,'2026-03-27 06:14:23'),
(19,31,'2026-03-27 06:14:23'),
(20,1,'2026-03-27 06:43:40'),
(20,2,'2026-03-27 06:43:40'),
(20,17,'2026-03-27 06:43:40'),
(20,18,'2026-03-27 06:43:40'),
(20,33,'2026-03-27 06:43:40'),
(22,1,'2026-03-27 07:16:21'),
(22,2,'2026-03-27 07:16:21'),
(22,3,'2026-03-27 07:16:21'),
(22,16,'2026-03-27 07:16:21'),
(22,17,'2026-03-27 07:16:21'),
(22,18,'2026-03-27 07:16:21'),
(22,19,'2026-03-27 07:16:21'),
(22,21,'2026-03-27 07:16:21'),
(22,24,'2026-03-27 07:16:21'),
(22,25,'2026-03-27 07:16:21'),
(22,31,'2026-03-27 07:16:21'),
(22,32,'2026-03-27 07:16:21'),
(22,34,'2026-03-27 07:16:21');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `submissions`
--

DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int unsigned NOT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(100) NOT NULL,
  `comment` text,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text,
  `graded_at` datetime DEFAULT NULL,
  `graded_by` int unsigned DEFAULT NULL,
  `status` enum('submitted','graded','returned') NOT NULL DEFAULT 'submitted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`),
  KEY `graded_by` (`graded_by`),
  KEY `idx_assignment` (`assignment_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_ibfk_3` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submissions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
INSERT INTO `submissions` VALUES
(1,1,14,'uploads/submissions/69c5b62d45b612.87149302_LECTURE_1A.docx','LECTURE 1A.docx',97899,'application/vnd.openxmlformats-officedocument.wordprocessingml.document','','2026-03-26 22:41:49',0,NULL,NULL,NULL,NULL,'submitted'),
(2,5,14,'uploads/submissions/69c5b6d0470ee0.92820230_Design_Analysis_and_Algorithm_cat_2.docx','Design Analysis and Algorithm cat 2.docx',20305,'application/vnd.openxmlformats-officedocument.wordprocessingml.document','','2026-03-26 22:44:32',0,22.00,'','2026-03-26 23:49:29',5,'graded'),
(3,2,14,'uploads/submissions/69c5be2fe782e4.48233466_Design_Analysis_and_Algorithm_cat_2.docx','Design Analysis and Algorithm cat 2.docx',20305,'application/vnd.openxmlformats-officedocument.wordprocessingml.document','','2026-03-26 23:15:59',0,NULL,NULL,NULL,NULL,'submitted'),
(4,4,22,'uploads/submissions/69c62f4f31f846.56237928_Design_Analysis_and_Algorithm_cat_2.docx','Design Analysis and Algorithm cat 2.docx',20305,'application/vnd.openxmlformats-officedocument.wordprocessingml.document','','2026-03-27 07:18:39',0,NULL,NULL,NULL,NULL,'submitted'),
(5,5,22,'uploads/submissions/69c62f5f53c040.72262740_Design_and_Analysis_of_Algirithms_SPC_2302__CATs.docx','Design and Analysis of Algirithms SPC 2302  CATs.docx',30843,'application/vnd.openxmlformats-officedocument.wordprocessingml.document','','2026-03-27 07:18:55',0,22.00,'','2026-03-27 07:19:49',5,'graded');
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','lecturer') NOT NULL DEFAULT 'student',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Dr. Sarah Kimani','lecturer@portal.ac.ke','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','lecturer',NULL,'2026-03-26 20:51:30','2026-03-26 20:51:30'),
(2,'Alice Wanjiku','alice@student.ac.ke','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',NULL,'2026-03-26 20:51:30','2026-03-26 20:51:30'),
(3,'Brian Otieno','brian@student.ac.ke','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',NULL,'2026-03-26 20:51:30','2026-03-26 20:51:30'),
(4,'Carol Njeri','carol@student.ac.ke','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',NULL,'2026-03-26 20:51:30','2026-03-26 20:51:30'),
(5,'Nicholus Mutugi','nicholusmutugi19@gmail.com','$2y$10$L7GwbEwV5CkOYNNVtBES2urQBuKjenghM2pdy6ks5/J7FXBg/2OTu','lecturer',NULL,'2026-03-26 22:00:28','2026-03-26 22:00:28'),
(14,'Nicholas Mutugi','nicholasmutugi19@gmail.com','$2y$10$W6cm8DHaJcp8cAUdrBG6NeNDhimQrdWwl/74REXK4BhoAQ9C7RiBa','student',NULL,'2026-03-26 22:27:48','2026-03-26 22:27:48'),
(15,'Dennis Mutuma','denismutuma@gmail.com','$2y$10$/YxBX9/hzOspY4n5DAk0Te.sEPuTpgi6reGE91AQUnnibPsIbalsa','lecturer',NULL,'2026-03-26 23:09:59','2026-03-26 23:09:59'),
(16,'tem','tem@gmail.com','$2y$10$ps.wdIsdamQuaGdhUhhQrObwTFKeCePjNMJO5dsjsvgdSLnOjIxRy','student',NULL,'2026-03-26 23:21:27','2026-03-26 23:21:27'),
(17,'Danrri Tina','nicholasmutugii19@gmail.com','$2y$10$t4Vy3xF7lEqWyjU0rH/5Ge9b8ILxZDHi1LLCrd6Im8iZdtqOiLqfa','student',NULL,'2026-03-26 23:54:41','2026-03-26 23:54:41'),
(18,'Thomas','thomas@gmail.com','$2y$10$p84LgYKDdPPgi17pOiGaFuaIgPlRXFfDGDV0RvXNAaXyain419iAW','student',NULL,'2026-03-27 05:01:21','2026-03-27 05:01:21'),
(19,'Jane Nyambura','karorejane19@gmail.com','$2y$10$d31p6A1Ccidkk5vmwkhlJ.TAPR.OrziFEVdDacw17YgiQ4JlQt1oC','student',NULL,'2026-03-27 06:13:25','2026-03-27 06:13:25'),
(20,'Clement','clementngugi024@gmail.com','$2y$10$uzDvzYJmvuAz9YNaO864Z.dFoaHWtBXUdKQz417TyRQD2uvQiFO1e','student',NULL,'2026-03-27 06:42:57','2026-03-27 06:42:57'),
(21,'Alvin Mwangi','alvinmwangi@gmail.com','$2y$10$r10yX4Oq7vl9zg0Q7.L.qOEDGznPxLHrfm5GpK/f1.7NqYRrpQCPG','lecturer',NULL,'2026-03-27 07:07:12','2026-03-27 07:07:12'),
(22,'Vladmir Putin','vladmirputin@gmail.com','$2y$10$zWers6f0mHlxrJpKh5A8febZYXmzX7T2kspU00tTtJfC6jRQKwblG','student',NULL,'2026-03-27 07:15:12','2026-03-27 07:15:12'),
(23,'Jane Karore','karorejane524@gmail.com','$2y$10$gZYy9n.Df1Veb9L6.rURDuyLdCdZflwa2fbID9nV9solJc710gdxe','lecturer',NULL,'2026-03-27 08:37:11','2026-03-27 08:37:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-03-27 11:44:30
