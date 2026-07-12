-- =====================================================
-- Migration: class_schedules (ตารางเรียนจาก RMS studing)
-- Run once against teaching_compensation database
-- =====================================================

USE `teaching_compensation`;

CREATE TABLE IF NOT EXISTS `class_schedules` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `semes`            VARCHAR(20)  NOT NULL,
  `subject_id`       VARCHAR(50)  NULL,
  `subject_name`     VARCHAR(200) NULL,
  `real_subject_id`  VARCHAR(50)  NULL,
  `student_group_id` VARCHAR(50)  NULL,
  `teacher_id`       VARCHAR(30)  NULL,
  `teacher_name`     VARCHAR(150) NULL,
  `day_name`         VARCHAR(30)  NULL,
  `time_range`       VARCHAR(50)  NULL,
  `periods`          INT          NULL,
  `room`             VARCHAR(50)  NULL,
  `building`         VARCHAR(100) NULL,
  `timetable_id`     VARCHAR(30)  NULL,
  `timetable_sub_id` VARCHAR(30)  NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_semes` (`semes`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_group` (`student_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
