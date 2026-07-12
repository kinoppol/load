-- =====================================================
-- Migration: student_groups (กลุ่มผู้เรียนจาก RMS)
-- Run once against teaching_compensation database
-- =====================================================

USE `teaching_compensation`;

CREATE TABLE IF NOT EXISTS `student_groups` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `academic_year`  INT          NOT NULL,
  `semester`       TINYINT      NOT NULL,
  `group_code`     VARCHAR(50)  NOT NULL,
  `grade`          VARCHAR(50)  NULL,
  `group_name`     VARCHAR(100) NULL,
  `group_abbr`     VARCHAR(100) NULL,
  `teacher_idcard` VARCHAR(20)  NULL,
  `teacher_name`   VARCHAR(150) NULL,
  `classroom_id`   VARCHAR(50)  NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_group` (`academic_year`,`semester`,`group_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
