-- =====================================================
-- Migration: makeup_reasons table
-- Run once against teaching_compensation database
-- =====================================================

USE `teaching_compensation`;

-- สร้างตาราง makeup_reasons
CREATE TABLE IF NOT EXISTS `makeup_reasons` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `code`         VARCHAR(50)  NOT NULL UNIQUE,
  `label`        VARCHAR(100) NOT NULL,
  `icon`         VARCHAR(10)  NOT NULL DEFAULT '📋',
  `color`        VARCHAR(20)  NOT NULL DEFAULT '#6B7280',
  `bg_color`     VARCHAR(20)  NOT NULL DEFAULT '#6B728020',
  `is_deletable` TINYINT(1)   NOT NULL DEFAULT 1,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`   INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เหตุผลเริ่มต้น (is_deletable=0 คือลบไม่ได้)
INSERT IGNORE INTO `makeup_reasons` (`code`,`label`,`icon`,`color`,`bg_color`,`is_deletable`,`sort_order`) VALUES
('holiday',  'วันหยุดราชการ',            '🎌', '#3B82F6', '#3B82F620', 0, 1),
('personal', 'ลากิจ',                    '📋', '#F59E0B', '#F59E0B20', 0, 2),
('official', 'ปฏิบัติราชการนอกสถานที่', '✈️', '#8B5CF6', '#8B5CF620', 0, 3),
('sick',     'ลาป่วย',                   '🏥', '#EF4444', '#EF444420', 0, 4);

-- เปลี่ยน reason column จาก ENUM เป็น VARCHAR เพื่อรองรับเหตุผลแบบ dynamic
ALTER TABLE `makeup_records`
  MODIFY COLUMN `reason` VARCHAR(50) NOT NULL DEFAULT 'holiday';
