-- ============================================
-- Station Management System — Database Backup
-- Generated: 2026-09-23T15:14:36+00:00
-- Database: church_db
-- ============================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- --------------------------------------------
-- Table: `activity_logs`
-- --------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `activity_logs` VALUES ('1','1','admin','view_admin','Dashboard','::1','2026-09-22 17:21:10');
INSERT INTO `activity_logs` VALUES ('2','1','admin','view_admin','Security','::1','2026-09-22 17:46:19');
INSERT INTO `activity_logs` VALUES ('3','1','admin','view_admin','Security','::1','2026-09-22 17:47:59');
INSERT INTO `activity_logs` VALUES ('4','1','admin','view_admin','Security','::1','2026-09-22 17:51:24');
INSERT INTO `activity_logs` VALUES ('5','1','admin','view_admin','Users','::1','2026-09-22 17:52:23');
INSERT INTO `activity_logs` VALUES ('6','1','admin','view_admin','Users','::1','2026-09-22 17:53:41');
INSERT INTO `activity_logs` VALUES ('7','1','admin','view_admin','Backups','::1','2026-09-22 17:55:36');
INSERT INTO `activity_logs` VALUES ('8','1','admin','view_admin','Backups','::1','2026-09-22 17:55:45');
INSERT INTO `activity_logs` VALUES ('9','1','admin','backup','backup_2026-09-22_195545.sql','::1','2026-09-22 17:55:46');
INSERT INTO `activity_logs` VALUES ('10','1','admin','view_admin','Backups','::1','2026-09-22 17:55:46');
INSERT INTO `activity_logs` VALUES ('11','1','admin','view_admin','Backups','::1','2026-09-22 17:55:52');
INSERT INTO `activity_logs` VALUES ('12','1','admin','view_admin','Dashboard','::1','2026-09-23 10:59:20');
INSERT INTO `activity_logs` VALUES ('13','1','admin','view_admin','Dashboard','::1','2026-09-23 10:59:56');
INSERT INTO `activity_logs` VALUES ('14','1','admin','view_admin','Users','::1','2026-09-23 11:00:04');
INSERT INTO `activity_logs` VALUES ('15','1','admin','view_admin','Users','::1','2026-09-23 11:00:19');
INSERT INTO `activity_logs` VALUES ('16','1','admin','change_role','User #7 → member','::1','2026-09-23 11:00:19');
INSERT INTO `activity_logs` VALUES ('17','1','admin','change_role','User #7 → member','::1','2026-09-23 11:01:54');
INSERT INTO `activity_logs` VALUES ('18','1','admin','view_admin','Users','::1','2026-09-23 11:01:54');
INSERT INTO `activity_logs` VALUES ('19','1','admin','view_admin','Roles','::1','2026-09-23 11:02:26');
INSERT INTO `activity_logs` VALUES ('20','1','admin','view_admin','Roles','::1','2026-09-23 11:03:29');
INSERT INTO `activity_logs` VALUES ('21','1','admin','view_admin','Permissions','::1','2026-09-23 11:05:44');
INSERT INTO `activity_logs` VALUES ('22','1','admin','view_admin','Security','::1','2026-09-23 11:06:16');
INSERT INTO `activity_logs` VALUES ('23','1','admin','view_admin','Database','::1','2026-09-23 11:06:32');
INSERT INTO `activity_logs` VALUES ('24','1','admin','view_admin','Backups','::1','2026-09-23 11:06:46');
INSERT INTO `activity_logs` VALUES ('25','1','admin','view_admin','System Logs','::1','2026-09-23 11:07:00');
INSERT INTO `activity_logs` VALUES ('26','1','admin','view_admin','System Logs','::1','2026-09-23 11:08:07');
INSERT INTO `activity_logs` VALUES ('27','1','admin','view_admin','Activity Logs','::1','2026-09-23 11:08:33');
INSERT INTO `activity_logs` VALUES ('28','1','admin','view_admin','Settings','::1','2026-09-23 11:08:49');
INSERT INTO `activity_logs` VALUES ('29','1','admin','view_admin','Settings','::1','2026-09-23 11:12:04');
INSERT INTO `activity_logs` VALUES ('30','1','admin','settings_update','System settings updated','::1','2026-09-23 11:13:07');
INSERT INTO `activity_logs` VALUES ('31','1','admin','view_admin','Settings','::1','2026-09-23 11:13:07');
INSERT INTO `activity_logs` VALUES ('32','1','admin','view_admin','Maintenance','::1','2026-09-23 11:13:13');
INSERT INTO `activity_logs` VALUES ('33','1','admin','view_admin','Maintenance','::1','2026-09-23 11:13:30');
INSERT INTO `activity_logs` VALUES ('34','1','admin','maintenance_update','Maintenance enabled','::1','2026-09-23 11:16:31');
INSERT INTO `activity_logs` VALUES ('35','1','admin','view_admin','Maintenance','::1','2026-09-23 11:16:31');
INSERT INTO `activity_logs` VALUES ('36','1','admin','view_admin','Dashboard','::1','2026-09-23 11:19:12');
INSERT INTO `activity_logs` VALUES ('37','1','admin','view_admin','Dashboard','::1','2026-09-23 11:20:39');
INSERT INTO `activity_logs` VALUES ('38','1','admin','view_admin','Maintenance','::1','2026-09-23 11:20:42');
INSERT INTO `activity_logs` VALUES ('39','1','admin','view_admin','Dashboard','::1','2026-09-23 11:22:26');
INSERT INTO `activity_logs` VALUES ('40','1','admin','view_admin','Maintenance','::1','2026-09-23 11:24:30');
INSERT INTO `activity_logs` VALUES ('41','1','admin','maintenance_update','Maintenance enabled','::1','2026-09-23 11:24:35');
INSERT INTO `activity_logs` VALUES ('42','1','admin','view_admin','Maintenance','::1','2026-09-23 11:24:35');
INSERT INTO `activity_logs` VALUES ('43','1','admin','view_admin','Dashboard','::1','2026-09-23 11:35:29');
INSERT INTO `activity_logs` VALUES ('44','1','admin','view_admin','Permissions','::1','2026-09-23 11:35:33');
INSERT INTO `activity_logs` VALUES ('45','1','admin','view_admin','Permissions','::1','2026-09-23 11:36:08');
INSERT INTO `activity_logs` VALUES ('46','1','admin','permissions_update','Role #3','::1','2026-09-23 11:37:21');
INSERT INTO `activity_logs` VALUES ('47','1','admin','view_admin','Permissions','::1','2026-09-23 11:37:22');
INSERT INTO `activity_logs` VALUES ('48','1','admin','view_admin','Permissions','::1','2026-09-23 11:37:29');
INSERT INTO `activity_logs` VALUES ('49','1','admin','permissions_update','Role #5','::1','2026-09-23 11:39:02');
INSERT INTO `activity_logs` VALUES ('50','1','admin','view_admin','Permissions','::1','2026-09-23 11:39:02');
INSERT INTO `activity_logs` VALUES ('51','1','admin','view_admin','Activity Logs','::1','2026-09-23 11:39:05');
INSERT INTO `activity_logs` VALUES ('52','1','admin','view_admin','Database','::1','2026-09-23 11:39:47');
INSERT INTO `activity_logs` VALUES ('53','1','admin','view_admin','Security','::1','2026-09-23 11:41:15');
INSERT INTO `activity_logs` VALUES ('54','1','admin','security_update','Security settings updated','::1','2026-09-23 11:43:20');
INSERT INTO `activity_logs` VALUES ('55','1','admin','view_admin','Security','::1','2026-09-23 11:43:20');
INSERT INTO `activity_logs` VALUES ('56','1','admin','security_update','Security settings updated','::1','2026-09-23 11:43:45');
INSERT INTO `activity_logs` VALUES ('57','1','admin','view_admin','Security','::1','2026-09-23 11:43:45');
INSERT INTO `activity_logs` VALUES ('58','1','admin','view_admin','Permissions','::1','2026-09-23 11:43:52');
INSERT INTO `activity_logs` VALUES ('59','1','admin','view_admin','Dashboard','::1','2026-09-23 11:53:17');
INSERT INTO `activity_logs` VALUES ('60','1','admin','view_admin','Maintenance','::1','2026-09-23 11:53:20');
INSERT INTO `activity_logs` VALUES ('61','1','admin','maintenance_update','Maintenance disabled','::1','2026-09-23 11:53:30');
INSERT INTO `activity_logs` VALUES ('62','1','admin','view_admin','Maintenance','::1','2026-09-23 11:53:30');
INSERT INTO `activity_logs` VALUES ('63','1','admin','view_admin','Dashboard','::1','2026-09-23 13:40:41');
INSERT INTO `activity_logs` VALUES ('64','1','admin','view_admin','Dashboard','::1','2026-09-23 13:41:26');
INSERT INTO `activity_logs` VALUES ('65','1','admin','view_admin','Users','::1','2026-09-23 13:41:34');
INSERT INTO `activity_logs` VALUES ('66','1','admin','view_admin','Roles','::1','2026-09-23 13:41:37');
INSERT INTO `activity_logs` VALUES ('67','1','admin','view_admin','Permissions','::1','2026-09-23 13:41:39');
INSERT INTO `activity_logs` VALUES ('68','1','admin','view_admin','Dashboard','::1','2026-09-23 13:58:01');
INSERT INTO `activity_logs` VALUES ('69','1','admin','view_admin','Maintenance','::1','2026-09-23 13:58:15');
INSERT INTO `activity_logs` VALUES ('70','1','admin','view_admin','Dashboard','127.0.0.1','2026-09-23 15:01:32');
INSERT INTO `activity_logs` VALUES ('71','1','admin','view_admin','Dashboard','127.0.0.1','2026-09-23 15:10:22');
INSERT INTO `activity_logs` VALUES ('72','1','admin','view_admin','Dashboard','127.0.0.1','2026-09-23 15:10:41');
INSERT INTO `activity_logs` VALUES ('73','1','admin','view_admin','Dashboard','127.0.0.1','2026-09-23 15:12:46');
INSERT INTO `activity_logs` VALUES ('74','1','admin','view_admin','Backups','127.0.0.1','2026-09-23 15:12:52');
INSERT INTO `activity_logs` VALUES ('75','1','admin','view_admin','Backups','127.0.0.1','2026-09-23 15:12:58');
INSERT INTO `activity_logs` VALUES ('76','1','admin','backup','backup_2026-09-23_151258.sql','127.0.0.1','2026-09-23 15:12:58');

-- --------------------------------------------
-- Table: `attendance_records`
-- --------------------------------------------

DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `status` enum('present','absent','excused') DEFAULT 'present',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_session_member` (`session_id`,`member_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance_records` VALUES ('1','2','4','present');

-- --------------------------------------------
-- Table: `attendance_sessions`
-- --------------------------------------------

DROP TABLE IF EXISTS `attendance_sessions`;
CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `session_date` date NOT NULL,
  `category` varchar(60) DEFAULT 'Catechism',
  `note` varchar(255) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance_sessions` VALUES ('1','Catechist class','2026-09-23','First Communion Class','','Catechist','2026-09-23 12:06:06');
INSERT INTO `attendance_sessions` VALUES ('2','Catechist class','2026-09-23','First Communion Class','','Catechist','2026-09-23 12:07:36');

-- --------------------------------------------
-- Table: `day_born_groups`
-- --------------------------------------------

DROP TABLE IF EXISTS `day_born_groups`;
CREATE TABLE `day_born_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day_name` varchar(10) NOT NULL,
  `group_name` varchar(30) NOT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day_name` (`day_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `day_born_groups` VALUES ('1','Sunday','Sunday Born','1');
INSERT INTO `day_born_groups` VALUES ('2','Monday','Monday Born','2');
INSERT INTO `day_born_groups` VALUES ('3','Tuesday','Tuesday Born','3');
INSERT INTO `day_born_groups` VALUES ('4','Wednesday','Wednesday Born','4');
INSERT INTO `day_born_groups` VALUES ('5','Thursday','Thursday Born','5');
INSERT INTO `day_born_groups` VALUES ('6','Friday','Friday Born','6');
INSERT INTO `day_born_groups` VALUES ('7','Saturday','Saturday Born','7');

-- --------------------------------------------
-- Table: `finances`
-- --------------------------------------------

DROP TABLE IF EXISTS `finances`;
CREATE TABLE `finances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) DEFAULT NULL,
  `beneficiary_name` varchar(150) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(80) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_finances_member` (`member_id`),
  KEY `idx_finances_date` (`date`),
  KEY `idx_finances_beneficiary` (`beneficiary_name`),
  CONSTRAINT `fk_finances_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `finances` VALUES ('1','4',NULL,NULL,NULL,'expense','Welfare Contribution','699.97','2026-09-22','','Finance Officer','2026-09-22 13:00:06');
INSERT INTO `finances` VALUES ('2','4',NULL,NULL,NULL,'income','First Collection','700.00','2026-09-22','[Deposited] First Collection — Mr. Kwabena Nkrumah','Finance Officer','2026-09-22 13:14:25');
INSERT INTO `finances` VALUES ('3','4',NULL,NULL,NULL,'income','First Collection','500.00','2026-09-22','[Deposited] Second Collection — Mr. Kwabena Nkrumah','Finance Officer','2026-09-22 13:25:20');
INSERT INTO `finances` VALUES ('5','4',NULL,NULL,'Cash','income','Fundraising','900.00','2026-09-22','','Finance Officer','2026-09-22 15:54:39');
INSERT INTO `finances` VALUES ('6',NULL,NULL,NULL,NULL,'income','First Collection','700.00','2026-09-22','[Deposited] First Collection — Mr. Kwabena','Finance Officer','2026-09-22 16:35:35');
INSERT INTO `finances` VALUES ('7',NULL,NULL,NULL,NULL,'income','Day Born Collection','1000.00','2026-09-22','[Deposited] Monday Born Born','Finance Officer','2026-09-22 16:36:19');
INSERT INTO `finances` VALUES ('8',NULL,NULL,NULL,NULL,'income','Day Born Collection','1009.00','2026-09-22','[Deposited] Tuesday Born Born','Finance Officer','2026-09-22 16:37:11');

-- --------------------------------------------
-- Table: `maintenance_mode`
-- --------------------------------------------

DROP TABLE IF EXISTS `maintenance_mode`;
CREATE TABLE `maintenance_mode` (
  `id` int(11) NOT NULL DEFAULT 1,
  `enabled` tinyint(4) DEFAULT 0,
  `message` varchar(500) DEFAULT 'System is under maintenance. Please check back shortly.',
  `allowed_roles` varchar(200) DEFAULT 'admin',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `maintenance_mode` VALUES ('1','0','System is under maintenance. Please check back shortly.','sysadmin,admin','2026-09-23 11:53:30');

-- --------------------------------------------
-- Table: `members`
-- --------------------------------------------

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `birth_year` smallint(6) DEFAULT NULL,
  `approx_age` tinyint(4) DEFAULT NULL,
  `dob_known` enum('full','year','age','unknown') DEFAULT 'full',
  `place_of_birth` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `marital_status` enum('Single','Married','Widowed','Divorced') DEFAULT NULL,
  `baptism` enum('Baptized Catholic','Not Baptized','Other Denomination') DEFAULT NULL,
  `day_born` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `members` VALUES ('4','Kwabena Nkrumah  Douglas','m_20260923_123804_2ef515ca.jpg','Male','1996-06-04',NULL,NULL,'full','Hiakose','0541923081','Married','Baptized Catholic','Tuesday','2026-09-22 15:41:08');

-- --------------------------------------------
-- Table: `permissions`
-- --------------------------------------------

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_perm` (`role_id`,`permission`),
  CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `permissions` VALUES ('3','3','cashbook.view');
INSERT INTO `permissions` VALUES ('2','3','finance.edit');
INSERT INTO `permissions` VALUES ('1','3','finance.view');
INSERT INTO `permissions` VALUES ('4','3','reports.view');
INSERT INTO `permissions` VALUES ('6','5','dayborn.view');
INSERT INTO `permissions` VALUES ('5','5','sacraments.view');

-- --------------------------------------------
-- Table: `roles`
-- --------------------------------------------

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` VALUES ('1','admin','Station Administrator — church operations','2026-09-22 17:11:28');
INSERT INTO `roles` VALUES ('2','secretary','Station Secretary — records & members','2026-09-22 17:11:28');
INSERT INTO `roles` VALUES ('3','finance','Station Finance Officer','2026-09-22 17:11:28');
INSERT INTO `roles` VALUES ('4','catechist','Station Catechist','2026-09-22 17:11:28');
INSERT INTO `roles` VALUES ('5','member','Station Member','2026-09-22 17:11:28');
INSERT INTO `roles` VALUES ('6','sysadmin','System Administrator — technical only','2026-09-22 17:22:27');

-- --------------------------------------------
-- Table: `sacraments`
-- --------------------------------------------

DROP TABLE IF EXISTS `sacraments`;
CREATE TABLE `sacraments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `type` varchar(60) NOT NULL,
  `date_received` date DEFAULT NULL,
  `minister` varchar(120) DEFAULT NULL,
  `place` varchar(150) DEFAULT NULL,
  `certificate_no` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `sacraments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sacraments` VALUES ('3','4','First Holy Communion','2000-05-06','Fr. John Mensah','Hiakose','BAP-2000-005','2026-09-22 15:44:39');

-- --------------------------------------------
-- Table: `security_settings`
-- --------------------------------------------

DROP TABLE IF EXISTS `security_settings`;
CREATE TABLE `security_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `min_password_length` int(11) DEFAULT 6,
  `require_numbers` tinyint(4) DEFAULT 0,
  `require_symbols` tinyint(4) DEFAULT 0,
  `max_login_attempts` int(11) DEFAULT 5,
  `session_timeout_minutes` int(11) DEFAULT 120,
  `force_https` tinyint(4) DEFAULT 0,
  `two_factor_enabled` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `security_settings` VALUES ('1','6','1','1','3','100','0','0');

-- --------------------------------------------
-- Table: `settings`
-- --------------------------------------------

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `church_name` varchar(150) DEFAULT 'Roman Catholic Church',
  `currency` varchar(10) DEFAULT 'GH₵',
  `timezone` varchar(60) DEFAULT 'Africa/Accra',
  `default_currency_code` varchar(10) DEFAULT 'GHS',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` VALUES ('1','Hiakose Roman Catholic Church','GH₵','Africa/Accra','GHS');

-- --------------------------------------------
-- Table: `system_logs`
-- --------------------------------------------

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('info','warning','error') DEFAULT 'info',
  `message` varchar(500) NOT NULL,
  `context` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------
-- Table: `users`
-- --------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('sysadmin','admin','secretary','finance','catechist','member') NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_users_member` (`member_id`),
  CONSTRAINT `fk_users_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES ('1','admin','$2y$10$26Lg4G9SEQkiH92qp2RCzeOn3QKMrQhAazljKMjpKjeBjVi4ReXJO','System Administrator','sysadmin',NULL,'2026-09-22 12:05:42');
INSERT INTO `users` VALUES ('2','secretary','$2y$10$26Lg4G9SEQkiH92qp2RCzeOn3QKMrQhAazljKMjpKjeBjVi4ReXJO','Parish Secretary','secretary',NULL,'2026-09-22 12:05:42');
INSERT INTO `users` VALUES ('3','finance','$2y$10$26Lg4G9SEQkiH92qp2RCzeOn3QKMrQhAazljKMjpKjeBjVi4ReXJO','Finance Officer','finance',NULL,'2026-09-22 12:05:42');
INSERT INTO `users` VALUES ('4','catechist','$2y$10$26Lg4G9SEQkiH92qp2RCzeOn3QKMrQhAazljKMjpKjeBjVi4ReXJO','Catechist','catechist',NULL,'2026-09-22 12:05:42');
INSERT INTO `users` VALUES ('7','0541923081','$2y$10$iWJfRSpH.f2P9PH28H3/d.klP4D6bt0BLF5F3BrZfxvzu.uHF0apq','Kwabena Nkrumah  Douglas','member','4','2026-09-22 15:41:08');

-- --------------------------------------------
-- Table: `wa_group_members`
-- --------------------------------------------

DROP TABLE IF EXISTS `wa_group_members`;
CREATE TABLE `wa_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_group_member` (`group_id`,`member_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `wa_group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `wa_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wa_group_members_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------
-- Table: `wa_groups`
-- --------------------------------------------

DROP TABLE IF EXISTS `wa_groups`;
CREATE TABLE `wa_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `invite_link` varchar(255) DEFAULT NULL,
  `audience` varchar(60) DEFAULT 'all',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
