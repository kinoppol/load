-- =====================================================
-- Migration: RMS personnel sync
-- Run once against teaching_compensation database
-- =====================================================

USE `teaching_compensation`;

-- ตารางเก็บค่าตั้งค่าระบบแบบ key-value (เช่น URL ต้นทาง RMS)
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key`   VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- URL ฐาน (host) ของระบบ RMS — ส่วน path เก็บไว้ใน code
INSERT IGNORE INTO `app_settings` (`setting_key`,`setting_value`) VALUES
('rms_base_url', 'http://rms.rvc.ac.th');

-- เพิ่มคอลัมน์ people_id เพื่อผูกบัญชีกับบุคลากรจาก RMS
-- (มีค่าเฉพาะบัญชีที่โอนมาจาก RMS, บัญชีที่สร้างเองจะเป็น NULL)
ALTER TABLE `users`
  ADD COLUMN `people_id` VARCHAR(50) NULL AFTER `username`,
  ADD UNIQUE KEY `uk_people_id` (`people_id`);
