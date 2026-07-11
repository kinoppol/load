<?php
declare(strict_types=1);
/**
 * install.php — ตัวติดตั้งระบบเบิกค่าตอบแทนการสอน
 * เข้าถึงได้ครั้งเดียว: หลังติดตั้งสำเร็จจะสร้าง install.lock
 */

$lockFile = __DIR__ . '/install.lock';
$step     = (int)($_GET['step'] ?? 1);
$error    = '';
$success  = '';

// ── ถ้าติดตั้งแล้ว ─────────────────────────────────────────────────────────
if (file_exists($lockFile) && ($step !== 99)) {
    $alreadyInstalled = true;
} else {
    $alreadyInstalled = false;
}

// ── Helper ─────────────────────────────────────────────────────────────────
function checkPhpVersion(): array {
    $ok  = version_compare(PHP_VERSION, '8.0.0', '>=');
    return ['ok' => $ok, 'value' => PHP_VERSION];
}
function checkExtension(string $ext): array {
    $ok = extension_loaded($ext);
    return ['ok' => $ok, 'value' => $ok ? 'โหลดแล้ว' : 'ไม่พบ'];
}
function checkWritable(string $path): array {
    if (!file_exists($path)) @mkdir($path, 0755, true);
    $ok = is_writable($path);
    return ['ok' => $ok, 'value' => $ok ? 'เขียนได้' : 'ไม่มีสิทธิ์เขียน'];
}
function tryConnect(string $host, string $user, string $pass): array {
    try {
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass,
            [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        if (!preg_match('/^(10\.|11\.|[2-9]\d\.|1[0-9]\d\.)/', $ver)) {
            // try MariaDB ident
        }
        return ['ok' => true, 'version' => $ver, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ── POST: step 2 — save config ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save_config') {
        $dbHost   = trim($_POST['db_host']   ?? 'localhost');
        $dbName   = trim($_POST['db_name']   ?? 'teaching_compensation');
        $dbUser   = trim($_POST['db_user']   ?? 'root');
        $dbPass   = $_POST['db_pass']        ?? '';
        $baseUrl  = rtrim(trim($_POST['base_url'] ?? '/load'), '/');
        $appName  = trim($_POST['app_name']  ?? 'ระบบเบิกค่าตอบแทนการสอน');

        $conn = tryConnect($dbHost, $dbUser, $dbPass);
        if (!$conn['ok']) {
            $error = 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . htmlspecialchars($conn['error']);
        } else {
            // write config
            $configContent = "<?php\ndeclare(strict_types=1);\n\n"
                . "// ─── Database ───────────────────────────────────────\n"
                . "define('DB_HOST',    " . var_export($dbHost, true) . ");\n"
                . "define('DB_NAME',    " . var_export($dbName, true) . ");\n"
                . "define('DB_USER',    " . var_export($dbUser, true) . ");\n"
                . "define('DB_PASS',    " . var_export($dbPass, true) . ");\n"
                . "define('DB_CHARSET', 'utf8mb4');\n\n"
                . "// ─── App ────────────────────────────────────────────\n"
                . "define('APP_NAME',    " . var_export($appName, true) . ");\n"
                . "define('APP_VERSION', '1.0');\n"
                . "define('BASE_URL',    " . var_export($baseUrl, true) . ");\n\n"
                . "// ─── Session ────────────────────────────────────────\n"
                . "define('SESSION_NAME',     'tc_sess');\n"
                . "define('SESSION_LIFETIME', 7200);\n\n"
                . "// ─── Upload ─────────────────────────────────────────\n"
                . "define('UPLOAD_DIR', __DIR__ . '/../uploads/');\n"
                . "define('MAX_UPLOAD', 5 * 1024 * 1024); // 5 MB\n\n"
                . "date_default_timezone_set('Asia/Bangkok');\n"
                . "mb_internal_encoding('UTF-8');\n";

            file_put_contents(__DIR__ . '/config/config.php', $configContent);

            // store in session for next step
            session_start();
            $_SESSION['install_db'] = ['host'=>$dbHost,'name'=>$dbName,'user'=>$dbUser,'pass'=>$dbPass];
            header('Location: install.php?step=3');
            exit;
        }
    }

    if ($_POST['action'] === 'run_install') {
        session_start();
        $db = $_SESSION['install_db'] ?? null;
        if (!$db) { header('Location: install.php?step=2'); exit; }

        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminPass = trim($_POST['admin_pass'] ?? '');
        $adminName = trim($_POST['admin_name'] ?? 'ผู้ดูแลระบบ');
        $schoolName = trim($_POST['school_name'] ?? 'วิทยาลัยเทคนิคสัตหีบ');
        $directorName = trim($_POST['director_name'] ?? '');
        $withSample = !empty($_POST['with_sample']);

        if (strlen($adminPass) < 6) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host={$db['host']};charset=utf8mb4",
                    $db['user'], $db['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // create database
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db['name']}`");

                // run schema (without seed)
                $schema = getSchemaSql();
                foreach (explode(';', $schema) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt !== '') $pdo->exec($stmt);
                }

                // institution settings
                $pdo->prepare("INSERT INTO institution_settings (school_name,director_name) VALUES (?,?) ON DUPLICATE KEY UPDATE school_name=VALUES(school_name),director_name=VALUES(director_name)")
                    ->execute([$schoolName, $directorName]);

                // admin user
                $hash = password_hash($adminPass, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (username,password_hash,full_name,role) VALUES (?,?,?,'admin')")
                    ->execute([$adminUser, $hash, $adminName]);

                // current semester (ภาคเรียน 1/2568)
                $year = (int)date('Y') + 543;
                $sem  = $year . '-' . (date('n') <= 10 ? '1' : '2');
                $startDate = date('Y') . '-05-19';
                $endDate   = date('Y') . '-10-03';
                $pdo->prepare("INSERT INTO semesters (name,year,semester,start_date,end_date,is_current) VALUES (?,?,1,?,?,1)")
                    ->execute(["ภาคเรียนที่ 1/{$year}", $year, $startDate, $endDate]);
                $semId = (int)$pdo->lastInsertId();

                // default rules
                $pdo->prepare("INSERT INTO compensation_rules (semester_id,normal_load,max_claimable,min_students,per_head_rate) VALUES (?,18,10,25,20.00)")
                    ->execute([$semId]);
                foreach ([['pvch','ปวช.',60],['pvs','ปวส.',70],['degree','ปริญญาตรี',80]] as [$lv,$ln,$rate]) {
                    $pdo->prepare("INSERT INTO teaching_rates (semester_id,level,level_name,rate_per_hour) VALUES (?,?,?,?)")
                        ->execute([$semId,$lv,$ln,$rate]);
                }

                if ($withSample) {
                    insertSampleData($pdo, $semId);
                }

                // lock file
                file_put_contents($lockFile, date('Y-m-d H:i:s'));

                header('Location: install.php?step=4');
                exit;
            } catch (Throwable $e) {
                $error = 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ── Schema SQL (tables only, no seed) ─────────────────────────────────────
function getSchemaSql(): string {
    return "
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `departments` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `short_name` VARCHAR(20)  NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `institution_settings` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `school_name`    VARCHAR(200) NOT NULL DEFAULT 'วิทยาลัยเทคนิคสัตหีบ',
  `address`        TEXT         NULL,
  `phone`          VARCHAR(50)  NULL,
  `logo_path`      VARCHAR(255) NULL,
  `director_name`  VARCHAR(100) NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `semesters` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `year`       INT          NOT NULL,
  `semester`   TINYINT      NOT NULL,
  `start_date` DATE         NOT NULL,
  `end_date`   DATE         NOT NULL,
  `is_current` TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `holidays` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `semester_id`  INT         NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `holiday_date` DATE        NOT NULL,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

SET foreign_key_checks = 1";
}

// ── Sample data ────────────────────────────────────────────────────────────
function insertSampleData(PDO $pdo, int $semId): void {
    // departments
    $depts = [
        ['ช่างไฟฟ้ากำลัง','ชฟ.',1],['ช่างยนต์','ชย.',2],
        ['ช่างอิเล็กทรอนิกส์','ชอ.',3],['ช่างกลโรงงาน','ชก.',4],
        ['เทคโนโลยีสารสนเทศ','สท.',5],['บัญชี','บช.',6],
    ];
    $deptIds = [];
    foreach ($depts as [$name,$short,$sort]) {
        $pdo->prepare("INSERT IGNORE INTO departments (name,short_name,sort_order) VALUES (?,?,?)")
            ->execute([$name,$short,$sort]);
        $deptIds[$short] = (int)$pdo->lastInsertId() ?: (int)$pdo->query("SELECT id FROM departments WHERE short_name='$short'")->fetchColumn();
    }

    $pass = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // "password"
    $teachers = [
        ['teacher1','นายประสิทธิ์ ไฟฟ้าดี','teacher','ชฟ.','ครู คศ.2'],
        ['teacher2','นายสมชาย ยนต์เก่ง',   'teacher','ชย.','ครู คศ.2'],
        ['teacher3','นางมาลี อิเล็กดี',     'teacher','ชอ.','ครู คศ.3'],
        ['teacher4','นายวิรัช กลึงดี',      'teacher','ชก.','ครู คศ.1'],
        ['teacher5','นางสาวจิตรา สารสนเทศ', 'teacher','สท.','ครู คศ.1'],
    ];
    $others = [
        ['director2', 'นายสมศักดิ์ วิชาดี', 'director', null],
        ['curriculum2','นางสาวสุดา ใจดี',   'curriculum','ชฟ.'],
        ['accounting2','นายวิชัย การเงิน',   'accounting','บช.'],
    ];
    $teacherIds = [];
    foreach ($teachers as [$uname,$fname,$role,$deptShort,$pos]) {
        $deptId = $deptIds[$deptShort] ?? null;
        $pdo->prepare("INSERT IGNORE INTO users (username,password_hash,full_name,role,department_id) VALUES (?,?,?,?,?)")
            ->execute([$uname,$pass,$fname,$role,$deptId]);
        $uid = (int)$pdo->lastInsertId();
        if ($uid) {
            $pdo->prepare("INSERT INTO teachers (user_id,full_name,department_id,position) VALUES (?,?,?,?)")
                ->execute([$uid,$fname,$deptId,$pos]);
            $teacherIds[] = (int)$pdo->lastInsertId();
        }
    }
    foreach ($others as [$uname,$fname,$role,$deptShort]) {
        $deptId = $deptShort ? ($deptIds[$deptShort] ?? null) : null;
        $pdo->prepare("INSERT IGNORE INTO users (username,password_hash,full_name,role,department_id) VALUES (?,?,?,?,?)")
            ->execute([$uname,$pass,$fname,$role,$deptId]);
    }

    // holidays
    $year = (int)date('Y');
    $holidays = [
        ['วันวิสาขบูชา', "$year-05-12"],
        ['วันอาสาฬหบูชา', "$year-07-10"],
        ['วันเฉลิมพระชนมพรรษา ร.10', "$year-07-28"],
        ['วันแม่แห่งชาติ', "$year-08-12"],
    ];
    foreach ($holidays as [$name,$date]) {
        $pdo->prepare("INSERT INTO holidays (semester_id,name,holiday_date) VALUES (?,?,?)")
            ->execute([$semId,$name,$date]);
    }

    // claim periods
    $periods = [
        [1,1,4, "$year-05-19","$year-06-13"],
        [2,5,8, "$year-06-16","$year-07-11"],
        [3,9,12,"$year-07-14","$year-08-08"],
    ];
    $periodIds = [];
    foreach ($periods as [$num,$ws,$we,$sd,$ed]) {
        $pdo->prepare("INSERT INTO claim_periods (semester_id,period_num,week_start,week_end,start_date,end_date,status) VALUES (?,?,?,?,?,?,'open')")
            ->execute([$semId,$num,$ws,$we,$sd,$ed]);
        $periodIds[] = (int)$pdo->lastInsertId();
    }

    // sample claims (if we have teacher IDs)
    if (count($teacherIds) >= 2 && count($periodIds) >= 1) {
        $pdo->prepare("INSERT INTO claim_records (teacher_id,period_id,semester_id,week_num,week_start_date,week_end_date,subject,group_name,level,total_periods,over_periods,rate_per_hour,student_count,amount,status) VALUES (?,?,?,1,?,?,'วงจรไฟฟ้า','ชฟ.1/1','pvch',24,6,60.00,28,360.00,'pending')")
            ->execute([$teacherIds[0],$periodIds[0],$semId,"$year-05-19","$year-05-23"]);
    }
}

// ── Run checks for step 1 ──────────────────────────────────────────────────
$checks = [];
if ($step === 1) {
    $checks = [
        ['label'=>'PHP Version ≥ 8.0', ...(checkPhpVersion())],
        ['label'=>'PDO Extension',      ...(checkExtension('pdo'))],
        ['label'=>'PDO MySQL Driver',   ...(checkExtension('pdo_mysql'))],
        ['label'=>'mbstring Extension', ...(checkExtension('mbstring'))],
        ['label'=>'openssl Extension',  ...(checkExtension('openssl'))],
        ['label'=>'โฟลเดอร์ config/ เขียนได้', ...(checkWritable(__DIR__.'/config'))],
        ['label'=>'โฟลเดอร์ uploads/ เขียนได้', ...(checkWritable(__DIR__.'/uploads'))],
    ];
}

$allOk = empty(array_filter($checks, fn($c) => !$c['ok']));

// ── Defaults for step 2 form ───────────────────────────────────────────────
$formDefaults = [
    'db_host'  => $_POST['db_host']  ?? 'localhost',
    'db_name'  => $_POST['db_name']  ?? 'teaching_compensation',
    'db_user'  => $_POST['db_user']  ?? 'root',
    'db_pass'  => '',
    'base_url' => $_POST['base_url'] ?? '/load',
    'app_name' => $_POST['app_name'] ?? 'ระบบเบิกค่าตอบแทนการสอน',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดตั้งระบบเบิกค่าตอบแทนการสอน</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;background:#F3F4F6;color:#111827;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px}
.installer{width:100%;max-width:640px}
.logo{text-align:center;margin-bottom:32px}
.logo-icon{font-size:48px;line-height:1}
.logo h1{font-size:22px;font-weight:700;color:#4A1425;margin-top:10px}
.logo p{font-size:13px;color:#6B7280;margin-top:4px}
.steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:28px}
.step-item{display:flex;align-items:center;gap:8px}
.step-circle{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.step-circle.done{background:#22C55E;color:#fff}
.step-circle.active{background:#7B1F32;color:#fff}
.step-circle.idle{background:#E5E7EB;color:#9CA3AF}
.step-label{font-size:12px;font-weight:600;color:#6B7280}
.step-label.active{color:#7B1F32}
.step-sep{width:40px;height:2px;background:#E5E7EB;margin:0 4px}
.step-sep.done{background:#22C55E}
.card{background:#fff;border-radius:14px;border:1px solid #E5E7EB;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.06)}
.card-header{padding:18px 24px;border-bottom:1px solid #E5E7EB;background:#FAFAFA}
.card-header h2{font-size:16px;font-weight:700;color:#111827}
.card-header p{font-size:12px;color:#6B7280;margin-top:4px}
.card-body{padding:24px}
.check-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;margin-bottom:6px;background:#F9FAFB;border:1px solid #E5E7EB}
.check-icon{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.check-icon.ok{background:#22C55E20;color:#22C55E}
.check-icon.fail{background:#EF444420;color:#EF4444}
.check-label{flex:1;font-size:13px;font-weight:500}
.check-value{font-size:12px;color:#6B7280;font-family:monospace}
.form-group{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:600;color:#6B7280;margin-bottom:6px}
input[type=text],input[type=password],input[type=url]{width:100%;background:#F9FAFB;border:1px solid #E5E7EB;color:#111827;padding:10px 14px;border-radius:8px;font-size:14px;font-family:inherit;transition:border-color .2s}
input:focus{outline:none;border-color:#7B1F32}
.hint{font-size:11px;color:#9CA3AF;margin-top:4px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;border:none;transition:opacity .2s;font-family:inherit}
.btn:hover{opacity:.88}
.btn-primary{background:#7B1F32;color:#fff}
.btn-ghost{background:#F3F4F6;color:#6B7280;border:1px solid #E5E7EB}
.btn-block{width:100%;justify-content:center;padding:12px}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.alert-error{background:#EF444415;border:1px solid #EF444440;color:#B91C1C}
.alert-success{background:#22C55E15;border:1px solid #22C55E40;color:#15803D}
.alert-warning{background:#F59E0B15;border:1px solid #F59E0B40;color:#B45309}
.footer-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
.divider{height:1px;background:#E5E7EB;margin:20px 0}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.success-icon{font-size:64px;text-align:center;margin-bottom:16px}
.success-title{font-size:20px;font-weight:700;text-align:center;color:#15803D;margin-bottom:8px}
.success-sub{font-size:13px;color:#6B7280;text-align:center;margin-bottom:24px}
.info-box{background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:14px 16px;margin-bottom:14px}
.info-box h4{font-size:13px;font-weight:700;color:#1D4ED8;margin-bottom:8px}
.info-row{display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid #DBEAFE}
.info-row:last-child{border-bottom:none}
.info-label{color:#6B7280}
.info-value{font-weight:600;font-family:monospace;color:#1D4ED8}
.already-installed{text-align:center;padding:40px 24px}
.already-installed h2{font-size:18px;font-weight:700;color:#92400E;margin:16px 0 8px}
.already-installed p{font-size:13px;color:#6B7280;margin-bottom:24px}
.lock-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#FEF3C7;border:1px solid #FDE68A;border-radius:20px;font-size:12px;color:#92400E;font-weight:600}
.checkbox-row{display:flex;align-items:center;gap:10px;cursor:pointer}
.checkbox-row input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#7B1F32}
.checkbox-row span{font-size:13px;font-weight:500}
</style>
</head>
<body>

<div class="installer">

  <!-- Logo -->
  <div class="logo">
    <div class="logo-icon">🎓</div>
    <h1>ระบบเบิกค่าตอบแทนการสอนเกินภาระงาน</h1>
    <p>วิทยาลัยเทคนิคสัตหีบ — ตัวติดตั้งระบบ</p>
  </div>

  <?php if ($alreadyInstalled): ?>
  <!-- Already Installed -->
  <div class="card">
    <div class="already-installed">
      <div style="font-size:52px">🔒</div>
      <h2>ระบบได้รับการติดตั้งแล้ว</h2>
      <p>ไฟล์ <code>install.lock</code> ถูกสร้างแล้ว<br>เพื่อความปลอดภัย ไม่สามารถรันตัวติดตั้งซ้ำได้</p>
      <div class="lock-badge">🔐 ติดตั้งเมื่อ: <?= htmlspecialchars(file_get_contents($lockFile)) ?></div>
      <div style="margin-top:24px">
        <a href="/load/login.php" class="btn btn-primary">เข้าสู่ระบบ →</a>
      </div>
      <div style="margin-top:16px;font-size:11px;color:#9CA3AF">
        หากต้องการติดตั้งใหม่ ให้ลบไฟล์ <code>install.lock</code> ออกก่อน
      </div>
    </div>
  </div>

  <?php else: ?>

  <!-- Step indicator -->
  <div class="steps">
    <?php
    $stepDefs = ['ตรวจสอบระบบ','ตั้งค่าฐานข้อมูล','ติดตั้ง','เสร็จสิ้น'];
    foreach ($stepDefs as $i => $label):
        $num = $i + 1;
        $cls = $num < $step ? 'done' : ($num === $step ? 'active' : 'idle');
        $sepCls = $num < $step ? 'done' : '';
    ?>
      <?php if ($i > 0): ?><div class="step-sep <?= $sepCls ?>"></div><?php endif; ?>
      <div class="step-item">
        <div class="step-circle <?= $cls ?>"><?= $num < $step ? '✓' : $num ?></div>
        <div class="step-label <?= $cls === 'active' ? 'active' : '' ?>"><?= $label ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ════ STEP 1: ตรวจสอบระบบ ════ -->
  <?php if ($step === 1): ?>
  <div class="card">
    <div class="card-header">
      <h2>ขั้นตอนที่ 1 — ตรวจสอบความพร้อมของระบบ</h2>
      <p>ตรวจสอบ PHP extensions และสิทธิ์ไฟล์ที่จำเป็น</p>
    </div>
    <div class="card-body">
      <?php foreach ($checks as $c): ?>
      <div class="check-row">
        <div class="check-icon <?= $c['ok'] ? 'ok' : 'fail' ?>">
          <?= $c['ok'] ? '✓' : '✗' ?>
        </div>
        <div class="check-label"><?= htmlspecialchars($c['label']) ?></div>
        <div class="check-value"><?= htmlspecialchars($c['value']) ?></div>
      </div>
      <?php endforeach; ?>

      <?php if (!$allOk): ?>
      <div class="alert alert-error" style="margin-top:16px">
        ⚠️ กรุณาแก้ไขรายการที่ล้มเหลวก่อนดำเนินการต่อ
      </div>
      <?php else: ?>
      <div class="alert alert-success" style="margin-top:16px">
        ✅ ระบบพร้อมสำหรับการติดตั้ง
      </div>
      <?php endif; ?>

      <div class="footer-actions">
        <a href="install.php?step=2" class="btn btn-primary <?= !$allOk ? 'disabled' : '' ?>"
           <?= !$allOk ? 'style="pointer-events:none;opacity:.5"' : '' ?>>
          ถัดไป →
        </a>
      </div>
    </div>
  </div>

  <!-- ════ STEP 2: ตั้งค่าฐานข้อมูล ════ -->
  <?php elseif ($step === 2): ?>
  <div class="card">
    <div class="card-header">
      <h2>ขั้นตอนที่ 2 — ตั้งค่าฐานข้อมูลและระบบ</h2>
      <p>กรอกข้อมูลการเชื่อมต่อ MariaDB และ URL ของระบบ</p>
    </div>
    <div class="card-body">
      <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="save_config">

        <h3 style="font-size:13px;font-weight:700;color:#4A1425;margin-bottom:14px">🗄️ การเชื่อมต่อฐานข้อมูล</h3>
        <div class="grid-2">
          <div class="form-group">
            <label>DB Host</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($formDefaults['db_host']) ?>" required>
          </div>
          <div class="form-group">
            <label>ชื่อฐานข้อมูล (DB Name)</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($formDefaults['db_name']) ?>" required>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>DB Username</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($formDefaults['db_user']) ?>" required autocomplete="off">
          </div>
          <div class="form-group">
            <label>DB Password</label>
            <input type="text" name="db_pass" value="" placeholder="เว้นว่างถ้าไม่มีรหัสผ่าน" autocomplete="off">
          </div>
        </div>

        <div class="divider"></div>
        <h3 style="font-size:13px;font-weight:700;color:#4A1425;margin-bottom:14px">⚙️ ตั้งค่าแอปพลิเคชัน</h3>

        <div class="form-group">
          <label>Base URL (path ของระบบ)</label>
          <input type="text" name="base_url" value="<?= htmlspecialchars($formDefaults['base_url']) ?>" required>
          <div class="hint">เช่น <code>/load</code> หรือ <code>/teaching</code> (ไม่ต้องมี / ต่อท้าย)</div>
        </div>
        <div class="form-group">
          <label>ชื่อระบบ</label>
          <input type="text" name="app_name" value="<?= htmlspecialchars($formDefaults['app_name']) ?>" required>
        </div>

        <div class="footer-actions">
          <a href="install.php?step=1" class="btn btn-ghost">← ย้อนกลับ</a>
          <button type="submit" class="btn btn-primary">ทดสอบและบันทึก →</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ════ STEP 3: ติดตั้ง ════ -->
  <?php elseif ($step === 3): ?>
  <div class="card">
    <div class="card-header">
      <h2>ขั้นตอนที่ 3 — ตั้งค่าผู้ดูแลระบบและข้อมูลเริ่มต้น</h2>
      <p>สร้างตารางฐานข้อมูลและบัญชีผู้ดูแลระบบ</p>
    </div>
    <div class="card-body">
      <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="alert alert-warning" style="margin-bottom:20px">
        ⚠️ การดำเนินการนี้จะ <strong>สร้างตาราง</strong> ในฐานข้อมูล ข้อมูลเดิมจะ<strong>ไม่ถูกลบ</strong> (ใช้ CREATE TABLE IF NOT EXISTS)
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="run_install">

        <h3 style="font-size:13px;font-weight:700;color:#4A1425;margin-bottom:14px">🏫 ข้อมูลสถานศึกษา</h3>
        <div class="form-group">
          <label>ชื่อสถานศึกษา</label>
          <input type="text" name="school_name" value="วิทยาลัยเทคนิคสัตหีบ" required>
        </div>
        <div class="form-group">
          <label>ชื่อผู้อำนวยการ</label>
          <input type="text" name="director_name" value="" placeholder="นายชื่อ นามสกุล">
        </div>

        <div class="divider"></div>
        <h3 style="font-size:13px;font-weight:700;color:#4A1425;margin-bottom:14px">👤 บัญชีผู้ดูแลระบบ (Admin)</h3>
        <div class="grid-2">
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="admin_user" value="admin" required>
          </div>
          <div class="form-group">
            <label>ชื่อ-นามสกุล</label>
            <input type="text" name="admin_name" value="ผู้ดูแลระบบ" required>
          </div>
        </div>
        <div class="form-group">
          <label>รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)</label>
          <input type="password" name="admin_pass" placeholder="กรอกรหัสผ่าน" required minlength="6">
        </div>

        <div class="divider"></div>
        <div class="form-group">
          <label class="checkbox-row">
            <input type="checkbox" name="with_sample" value="1" checked>
            <span>เพิ่มข้อมูลตัวอย่าง (แผนก, ผู้สอน, งวดเบิก, ใบเบิกตัวอย่าง)</span>
          </label>
          <div class="hint" style="padding-left:26px">แนะนำสำหรับการทดสอบระบบ สามารถลบออกได้ในภายหลัง</div>
        </div>

        <div class="footer-actions">
          <a href="install.php?step=2" class="btn btn-ghost">← ย้อนกลับ</a>
          <button type="submit" class="btn btn-primary">🚀 ติดตั้งระบบ</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ════ STEP 4: เสร็จสิ้น ════ -->
  <?php elseif ($step === 4): ?>
  <div class="card">
    <div class="card-header">
      <h2>ขั้นตอนที่ 4 — ติดตั้งสำเร็จ</h2>
      <p>ระบบพร้อมใช้งานแล้ว</p>
    </div>
    <div class="card-body">
      <div class="success-icon">🎉</div>
      <div class="success-title">ติดตั้งสำเร็จ!</div>
      <div class="success-sub">ระบบเบิกค่าตอบแทนการสอนได้รับการติดตั้งเรียบร้อยแล้ว</div>

      <div class="info-box">
        <h4>📋 ข้อมูลสำหรับเข้าใช้งาน</h4>
        <div class="info-row"><span class="info-label">URL ระบบ</span><span class="info-value">/load/login.php</span></div>
        <div class="info-row"><span class="info-label">Username</span><span class="info-value">ตามที่กำหนดในขั้นตอน 3</span></div>
        <div class="info-row"><span class="info-label">รหัสผ่านตัวอย่าง</span><span class="info-value">password (ถ้าเลือกข้อมูลตัวอย่าง)</span></div>
      </div>

      <div class="alert alert-warning">
        🔐 <strong>สำคัญ:</strong> ไฟล์ <code>install.lock</code> ถูกสร้างแล้ว — ตัวติดตั้งจะถูกล็อคโดยอัตโนมัติ
        แนะนำให้ลบไฟล์ <code>install.php</code> ออกหลังติดตั้งเสร็จเพื่อความปลอดภัย
      </div>

      <div style="text-align:center;margin-top:24px">
        <a href="/load/login.php" class="btn btn-primary btn-block" style="max-width:260px;margin:0 auto">
          เข้าสู่ระบบ →
        </a>
      </div>
    </div>
  </div>

  <?php endif; ?>
  <?php endif; ?>

  <div style="text-align:center;margin-top:20px;font-size:11px;color:#9CA3AF">
    ระบบเบิกค่าตอบแทนการสอนเกินภาระงาน v1.0 — วิทยาลัยเทคนิคสัตหีบ
  </div>
</div>

</body>
</html>
