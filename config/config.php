<?php
declare(strict_types=1);

// ─── Database ───────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'teaching_compensation');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ─── App ────────────────────────────────────────────
define('APP_NAME',    'ระบบเบิกค่าตอบแทนการสอน');
define('APP_VERSION', '1.0');
define('BASE_URL',    '/load');

// ─── Session ────────────────────────────────────────
define('SESSION_NAME',     'tc_sess');
define('SESSION_LIFETIME', 7200);

// ─── Upload ─────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD', 5 * 1024 * 1024); // 5 MB

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');
