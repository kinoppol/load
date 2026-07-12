-- =====================================================
-- Migration: students (ข้อมูลผู้เรียนจาก RMS)
-- Run once against teaching_compensation database
-- =====================================================

USE `teaching_compensation`;

CREATE TABLE IF NOT EXISTS `students` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`        VARCHAR(30)  NOT NULL,
  `student_code`      VARCHAR(30)  NULL,
  `idcard`            VARCHAR(20)  NULL,
  `firstname`         VARCHAR(100) NULL,
  `surname`           VARCHAR(100) NULL,
  `gender`            VARCHAR(5)   NULL,
  `group_code`        VARCHAR(50)  NULL,
  `group_name`        VARCHAR(100) NULL,
  `group_abbr`        VARCHAR(100) NULL,
  `grade_name`        VARCHAR(100) NULL,
  `major_name`        VARCHAR(150) NULL,
  `status_code`       VARCHAR(10)  NULL,
  `status_name`       VARCHAR(100) NULL,
  `entrance_year`     INT          NULL,
  `entrance_semester` TINYINT      NULL,
  `email`             VARCHAR(150) NULL,
  `tel`               VARCHAR(30)  NULL,
  `gpax`              DECIMAL(4,2) NULL,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_student_id` (`student_id`),
  KEY `idx_group_code` (`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
