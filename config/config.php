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

// BASE_URL — ตรวจหา path ที่ติดตั้งอัตโนมัติจากตำแหน่งโฟลเดอร์จริง
// รองรับติดตั้งในโฟลเดอร์ชื่อใดก็ได้ (เช่น /load, /web) หรือที่ราก (/) โดยไม่ต้องแก้ค่านี้
// หากต้องการกำหนดเอง ตั้งค่า environment variable APP_BASE_URL แทน
(static function (): void {
    $env = getenv('APP_BASE_URL');
    if ($env !== false && $env !== '') {
        define('BASE_URL', '/' . trim($env, '/'));
        return;
    }
    $root    = str_replace('\\', '/', dirname(__DIR__));               // โฟลเดอร์รากของโปรเจ็ค
    $docroot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $base    = '';
    if ($docroot !== '' && str_starts_with($root, $docroot)) {
        $base = substr($root, strlen($docroot));
    }
    $base = trim($base, '/');
    define('BASE_URL', $base === '' ? '' : '/' . $base);
})();

// ─── Session ────────────────────────────────────────
define('SESSION_NAME',     'tc_sess');
define('SESSION_LIFETIME', 7200);

// ─── Upload ─────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD', 5 * 1024 * 1024); // 5 MB

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');
