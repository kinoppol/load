-- =====================================================
-- ระบบเบิกค่าตอบแทนการสอน วิทยาลัยเทคนิคสัตหีบ
-- Schema: MariaDB 10+
-- =====================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `teaching_compensation`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `teaching_compensation`;

-- --------------------------------------------------
-- departments
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `short_name` VARCHAR(20)  NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- users
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `email`         VARCHAR(100) NULL,
  `role`          ENUM('admin','director','curriculum','teacher','accounting') NOT NULL DEFAULT 'teacher',
  `department_id` INT          NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`    DATETIME     NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- teachers
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `teachers` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT          NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `department_id` INT          NOT NULL,
  `position`      VARCHAR(100) NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
  FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- institution_settings
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `institution_settings` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `school_name`    VARCHAR(200) NOT NULL DEFAULT 'วิทยาลัยเทคนิคสัตหีบ',
  `address`        TEXT         NULL,
  `phone`          VARCHAR(50)  NULL,
  `logo_path`      VARCHAR(255) NULL,
  `director_name`  VARCHAR(100) NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- semesters
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `semesters` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `year`       INT          NOT NULL,
  `semester`   TINYINT      NOT NULL,
  `start_date` DATE         NOT NULL,
  `end_date`   DATE         NOT NULL,
  `is_current` TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- compensation_rules (one per semester)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `compensation_rules` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `semester_id`      INT           NOT NULL,
  `normal_load`      INT           NOT NULL DEFAULT 18,
  `max_claimable`    INT           NOT NULL DEFAULT 10,
  `min_students`     INT           NOT NULL DEFAULT 25,
  `per_head_rate`    DECIMAL(10,2) NOT NULL DEFAULT 20.00,
  `holiday_rule`     ENUM('proportional','skip','full') NOT NULL DEFAULT 'proportional',
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- teaching_rates
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `teaching_rates` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `semester_id`   INT           NOT NULL,
  `level`         ENUM('pvch','pvs','degree') NOT NULL,
  `level_name`    VARCHAR(50)   NOT NULL,
  `rate_per_hour` DECIMAL(10,2) NOT NULL,
  `is_enabled`    TINYINT(1)    NOT NULL DEFAULT 1,
  UNIQUE KEY `uk_sem_level` (`semester_id`,`level`),
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- holidays
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `holidays` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `semester_id`  INT         NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `holiday_date` DATE        NOT NULL,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- claim_periods (งวดการเบิก)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `claim_periods` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `semester_id`  INT           NOT NULL,
  `period_num`   INT           NOT NULL,
  `week_start`   INT           NOT NULL,
  `week_end`     INT           NOT NULL,
  `start_date`   DATE          NOT NULL,
  `end_date`     DATE          NOT NULL,
  `status`       ENUM('open','locked','paid') NOT NULL DEFAULT 'open',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- claim_records (ใบเบิก)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `claim_records` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id`       INT           NOT NULL,
  `period_id`        INT           NULL,
  `semester_id`      INT           NOT NULL,
  `week_num`         INT           NOT NULL,
  `week_start_date`  DATE          NOT NULL,
  `week_end_date`    DATE          NOT NULL,
  `subject`          VARCHAR(200)  NOT NULL DEFAULT '',
  `group_name`       VARCHAR(50)   NOT NULL DEFAULT '',
  `level`            ENUM('pvch','pvs','degree') NOT NULL DEFAULT 'pvch',
  `total_periods`    INT           NOT NULL DEFAULT 0,
  `over_periods`     INT           NOT NULL DEFAULT 0,
  `rate_per_hour`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `student_count`    INT           NOT NULL DEFAULT 0,
  `amount`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status`           ENUM('draft','pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `approved_by`      INT           NULL,
  `approved_at`      DATETIME      NULL,
  `note`             TEXT          NULL,
  `is_block_course`  TINYINT(1)    NOT NULL DEFAULT 0,
  `block_start_date` DATE          NULL,
  `block_end_date`   DATE          NULL,
  `hours_per_day`    INT           NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`)  REFERENCES `teachers`(`id`),
  FOREIGN KEY (`period_id`)   REFERENCES `claim_periods`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- attendance (ลงชื่อปฏิบัติงาน)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id`  INT  NOT NULL,
  `semester_id` INT  NOT NULL,
  `week_num`    INT  NOT NULL,
  `att_date`    DATE NOT NULL,
  `status`      ENUM('present','leave_personal','leave_sick','holiday','other') NOT NULL DEFAULT 'present',
  `note`        VARCHAR(200) NULL,
  UNIQUE KEY `uk_teacher_date` (`teacher_id`,`att_date`),
  FOREIGN KEY (`teacher_id`)  REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- makeup_records (สอนชดเชย)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `makeup_records` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id`  INT          NOT NULL,
  `semester_id` INT          NOT NULL,
  `subject`     VARCHAR(200) NOT NULL DEFAULT '',
  `group_name`  VARCHAR(50)  NOT NULL DEFAULT '',
  `reason`      ENUM('holiday','personal','official','sick') NOT NULL DEFAULT 'holiday',
  `missed_date`  DATE        NOT NULL,
  `makeup_date`  DATE        NOT NULL,
  `start_time`   TIME        NOT NULL,
  `end_time`     TIME        NOT NULL,
  `periods`      INT         NOT NULL DEFAULT 1,
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by`  INT         NULL,
  `approved_at`  DATETIME    NULL,
  `created_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`)  REFERENCES `teachers`(`id`),
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- substitute_records (สอนแทน)
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `substitute_records` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `absent_teacher_id` INT          NOT NULL,
  `sub_teacher_id`    INT          NOT NULL,
  `semester_id`       INT          NOT NULL,
  `subject`           VARCHAR(200) NOT NULL DEFAULT '',
  `group_name`        VARCHAR(50)  NOT NULL DEFAULT '',
  `absent_date`       DATE         NOT NULL,
  `periods`           INT          NOT NULL DEFAULT 1,
  `note`              TEXT         NULL,
  `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by`       INT          NULL,
  `approved_at`       DATETIME     NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`absent_teacher_id`) REFERENCES `teachers`(`id`),
  FOREIGN KEY (`sub_teacher_id`)    REFERENCES `teachers`(`id`),
  FOREIGN KEY (`semester_id`)       REFERENCES `semesters`(`id`),
  FOREIGN KEY (`approved_by`)       REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------
-- notifications
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT          NOT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `message`      TEXT         NULL,
  `type`         VARCHAR(50)  NOT NULL DEFAULT 'info',
  `icon`         VARCHAR(10)  NOT NULL DEFAULT '🔔',
  `is_read`      TINYINT(1)   NOT NULL DEFAULT 0,
  `related_id`   INT          NULL,
  `related_type` VARCHAR(50)  NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- =====================================================
-- SEED DATA
-- =====================================================

INSERT INTO `departments` (`name`,`short_name`,`sort_order`) VALUES
('ช่างไฟฟ้ากำลัง',   'ชฟ.',  1),
('ช่างยนต์',          'ชย.',  2),
('ช่างอิเล็กทรอนิกส์','ชอ.',  3),
('ช่างกลโรงงาน',      'ชก.',  4),
('เทคโนโลยีสารสนเทศ', 'สท.',  5),
('บัญชี',             'บช.',  6);

INSERT INTO `institution_settings` (`school_name`,`address`,`phone`,`director_name`) VALUES
('วิทยาลัยเทคนิคสัตหีบ',
 '8/8 หมู่ 8 ต.สัตหีบ อ.สัตหีบ จ.ชลบุรี 20180',
 '038-438-159',
 'นายสมศักดิ์ วิชาดี');

-- users (password = "1234" => bcrypt)
INSERT INTO `users` (`username`,`password_hash`,`full_name`,`role`,`department_id`) VALUES
('admin',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ดูแลระบบ',           'admin',       NULL),
('director',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายสมศักดิ์ วิชาดี',   'director',    NULL),
('curriculum','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสาวสุดา ใจดี',       'curriculum',  1),
('accounting','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายวิชัย การเงิน',      'accounting',  6),
('teacher1',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายประสิทธิ์ ไฟฟ้าดี',  'teacher',     1),
('teacher2',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายสมชาย ยนต์เก่ง',     'teacher',     2),
('teacher3',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางมาลี อิเล็กดี',      'teacher',     3),
('teacher4',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายวิรัช กลึงดี',       'teacher',     4),
('teacher5',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสาวจิตรา สารสนเทศ',  'teacher',     5),
('teacher6',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายบุญชัย มอเตอร์',     'teacher',     2),
('teacher7',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสาวรัตนา วงจรดี',    'teacher',     3),
('teacher8',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายอนันต์ เครื่องกล',   'teacher',     4);

INSERT INTO `teachers` (`user_id`,`full_name`,`department_id`,`position`) VALUES
(5,  'นายประสิทธิ์ ไฟฟ้าดี',  1, 'ครู คศ.2'),
(6,  'นายสมชาย ยนต์เก่ง',     2, 'ครู คศ.2'),
(7,  'นางมาลี อิเล็กดี',      3, 'ครู คศ.3'),
(8,  'นายวิรัช กลึงดี',       4, 'ครู คศ.1'),
(9,  'นางสาวจิตรา สารสนเทศ',  5, 'ครู คศ.1'),
(10, 'นายบุญชัย มอเตอร์',     2, 'ครู คศ.2'),
(11, 'นางสาวรัตนา วงจรดี',    3, 'ครู คศ.1'),
(12, 'นายอนันต์ เครื่องกล',   4, 'ครู คศ.2');

INSERT INTO `semesters` (`name`,`year`,`semester`,`start_date`,`end_date`,`is_current`) VALUES
('ภาคเรียนที่ 1/2568', 2568, 1, '2025-05-19', '2025-10-03', 1),
('ภาคเรียนที่ 2/2567', 2567, 2, '2024-11-04', '2025-03-07', 0),
('ภาคเรียนที่ 1/2567', 2567, 1, '2024-05-20', '2024-10-04', 0);

INSERT INTO `compensation_rules` (`semester_id`,`normal_load`,`max_claimable`,`min_students`,`per_head_rate`,`holiday_rule`) VALUES
(1, 18, 10, 25, 20.00, 'proportional'),
(2, 18, 10, 25, 20.00, 'proportional');

INSERT INTO `teaching_rates` (`semester_id`,`level`,`level_name`,`rate_per_hour`,`is_enabled`) VALUES
(1,'pvch',  'ปวช.',        60.00, 1),
(1,'pvs',   'ปวส.',        70.00, 1),
(1,'degree','ปริญญาตรี',   80.00, 1),
(2,'pvch',  'ปวช.',        60.00, 1),
(2,'pvs',   'ปวส.',        70.00, 1),
(2,'degree','ปริญญาตรี',   80.00, 1);

INSERT INTO `holidays` (`semester_id`,`name`,`holiday_date`) VALUES
(1,'วันวิสาขบูชา',     '2025-05-12'),
(1,'วันอาสาฬหบูชา',   '2025-07-10'),
(1,'วันเฉลิมพระชนมพรรษา ร.10','2025-07-28'),
(1,'วันแม่แห่งชาติ',  '2025-08-12'),
(1,'วันมหิดล',        '2025-09-24');

INSERT INTO `claim_periods` (`semester_id`,`period_num`,`week_start`,`week_end`,`start_date`,`end_date`,`status`,`total_amount`) VALUES
(1, 1, 1,  4,  '2025-05-19','2025-06-13','paid',   182400.00),
(1, 2, 5,  8,  '2025-06-16','2025-07-11','paid',   176800.00),
(1, 3, 9,  12, '2025-07-14','2025-08-08','locked', 127000.00),
(1, 4, 13, 16, '2025-08-11','2025-09-05','open',   0),
(1, 5, 17, 18, '2025-09-08','2025-10-03','open',   0);

-- Sample claim records
INSERT INTO `claim_records` (`teacher_id`,`period_id`,`semester_id`,`week_num`,`week_start_date`,`week_end_date`,`subject`,`group_name`,`level`,`total_periods`,`over_periods`,`rate_per_hour`,`student_count`,`amount`,`status`) VALUES
(1,1,1,1,'2025-05-19','2025-05-23','วงจรไฟฟ้ากระแสตรง','ชฟ.1/1','pvch',24,6,60.00,28,360.00,'paid'),
(1,1,1,2,'2025-05-26','2025-05-30','วงจรไฟฟ้ากระแสตรง','ชฟ.1/1','pvch',22,4,60.00,28,240.00,'paid'),
(1,1,1,3,'2025-06-02','2025-06-06','เครื่องกลไฟฟ้า','ชฟ.2/1','pvch',25,7,60.00,30,420.00,'paid'),
(1,1,1,4,'2025-06-09','2025-06-13','เครื่องกลไฟฟ้า','ชฟ.2/1','pvch',23,5,60.00,30,300.00,'paid'),
(2,1,1,1,'2025-05-19','2025-05-23','งานเครื่องยนต์เล็ก','ชย.1/1','pvch',26,8,60.00,27,480.00,'paid'),
(2,1,1,2,'2025-05-26','2025-05-30','งานเครื่องยนต์เล็ก','ชย.1/1','pvch',24,6,60.00,27,360.00,'paid'),
(3,2,1,5,'2025-06-16','2025-06-20','วงจรอิเล็กทรอนิกส์','ชอ.1/1','pvch',28,10,60.00,26,600.00,'paid'),
(3,2,1,6,'2025-06-23','2025-06-27','วงจรอิเล็กทรอนิกส์','ชอ.1/1','pvch',25,7,60.00,26,420.00,'paid'),
(4,3,1,9,'2025-07-14','2025-07-18','งานกลึงโลหะ','ชก.2/1','pvch',27,9,60.00,29,540.00,'approved'),
(5,3,1,10,'2025-07-21','2025-07-25','การโปรแกรมคอมพิวเตอร์','สท.1/1','pvch',22,4,60.00,31,240.00,'approved'),
(1,4,1,13,'2025-08-11','2025-08-15','วงจรไฟฟ้ากระแสสลับ','ชฟ.2/2','pvs',24,6,70.00,25,420.00,'pending'),
(2,4,1,13,'2025-08-11','2025-08-15','เครื่องยนต์ดีเซล','ชย.2/1','pvs',26,8,70.00,24,560.00,'pending');

-- Sample makeup records
INSERT INTO `makeup_records` (`teacher_id`,`semester_id`,`subject`,`group_name`,`reason`,`missed_date`,`makeup_date`,`start_time`,`end_time`,`periods`,`status`) VALUES
(1,1,'วงจรไฟฟ้ากระแสตรง','ชฟ.1/1','holiday','2025-07-10','2025-07-12','08:00:00','11:00:00',3,'approved'),
(2,1,'งานเครื่องยนต์เล็ก','ชย.1/1','personal','2025-06-20','2025-06-28','13:00:00','15:00:00',2,'pending'),
(3,1,'วงจรอิเล็กทรอนิกส์','ชอ.1/1','official','2025-07-28','2025-08-02','08:00:00','12:00:00',4,'pending');

-- Sample substitute records
INSERT INTO `substitute_records` (`absent_teacher_id`,`sub_teacher_id`,`semester_id`,`subject`,`group_name`,`absent_date`,`periods`,`status`) VALUES
(2,1,1,'งานเครื่องยนต์เล็ก','ชย.1/1','2025-06-20',3,'approved'),
(3,7,1,'วงจรอิเล็กทรอนิกส์','ชอ.1/1','2025-07-15',2,'pending');

-- Sample notifications for director
INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`,`icon`) VALUES
(2,'ใบเบิกรอการอนุมัติ 2 รายการ','มีใบเบิกใหม่จาก นายประสิทธิ์ และ นายสมชาย','claim','📋'),
(2,'การสอนชดเชยรออนุมัติ','นายสมชาย ยนต์เก่ง ยื่นคำขอสอนชดเชย 2 คาบ','makeup','📚'),
(3,'รายการเบิกได้รับการอนุมัติ','ใบเบิกประจำสัปดาห์ที่ 9 ได้รับการอนุมัติแล้ว','approved','✅');
