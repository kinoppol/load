<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
Auth::requireRole('admin', 'director');
header('Content-Type: application/json; charset=utf-8');

// จำนวนรวมของแต่ละชุดข้อมูล (แสดงบนแท็บ)
$counts = [
    'personnel'  => (int)(DB::fetch('SELECT COUNT(*) c FROM users WHERE people_id IS NOT NULL')['c'] ?? 0),
    'semesters'  => (int)(DB::fetch('SELECT COUNT(*) c FROM semesters')['c'] ?? 0),
    'holidays'   => (int)(DB::fetch('SELECT COUNT(*) c FROM holidays')['c'] ?? 0),
    'groups'     => (int)(DB::fetch('SELECT COUNT(*) c FROM student_groups')['c'] ?? 0),
    'students'   => (int)(DB::fetch('SELECT COUNT(*) c FROM students')['c'] ?? 0),
    'schedules'  => (int)(DB::fetch('SELECT COUNT(*) c FROM class_schedules')['c'] ?? 0),
];

$resource = $_GET['resource'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = min(100, max(5, (int)($_GET['per'] ?? 20)));
$q        = trim((string)($_GET['q'] ?? ''));
$offset   = ($page - 1) * $per;
$like     = '%' . $q . '%';

// นิยามแต่ละชุดข้อมูล: FROM (รองรับ JOIN), คอลัมน์, ฟิลด์ค้นหา, เงื่อนไข, การเรียง
$defs = [
    'personnel' => [
        'from'    => 'users',
        'select'  => 'people_id, username, full_name, email, is_active',
        'search'  => ['full_name', 'username', 'people_id', 'email'],
        'order'   => 'full_name',
        'where'   => 'people_id IS NOT NULL',
        'params'  => [],
    ],
    'semesters' => [
        'from'    => 'semesters',
        'select'  => 'name, year, semester, start_date, end_date, is_current',
        'search'  => ['name'],
        'order'   => 'year DESC, semester DESC',
        'where'   => '1=1',
        'params'  => [],
    ],
    'holidays' => [
        'from'    => 'holidays h LEFT JOIN semesters s ON s.id = h.semester_id',
        'select'  => 'h.name AS name, h.holiday_date, s.name AS sem_name',
        'search'  => ['h.name', 's.name'],
        'order'   => 'h.holiday_date DESC',
        'where'   => '1=1',
        'params'  => [],
    ],
    'groups' => [
        'from'    => 'student_groups',
        'select'  => 'academic_year, semester, group_code, grade, group_name, group_abbr, teacher_name',
        'search'  => ['group_code', 'group_name', 'group_abbr', 'grade', 'teacher_name'],
        'order'   => 'academic_year DESC, semester DESC, group_code',
        'where'   => '1=1',
        'params'  => [],
    ],
    'students' => [
        'from'    => 'students',
        'select'  => 'student_code, firstname, surname, group_name, grade_name, major_name, status_name, gpax',
        'search'  => ['firstname', 'surname', 'student_code', 'idcard', 'group_name'],
        'order'   => 'group_name, surname',
        'where'   => '1=1',
        'params'  => [],
    ],
    'schedules' => [
        'from'    => 'class_schedules',
        'select'  => 'subject_id, subject_name, teacher_name, student_group_id, day_name, time_range, periods, room, building',
        'search'  => ['subject_name', 'subject_id', 'teacher_name', 'student_group_id', 'room'],
        'order'   => 'teacher_name, day_name',
        'where'   => '1=1',
        'params'  => [],
    ],
];

if (!isset($defs[$resource])) {
    // ไม่ระบุ resource → คืนเฉพาะจำนวนรวม (ใช้ตอนเปิดหน้าแรก)
    json_ok(['counts' => $counts]);
}

$def   = $defs[$resource];
$where = $def['where'];
$params = $def['params'];

if ($q !== '') {
    $conds = array_map(fn($c) => "$c LIKE ?", $def['search']);
    $where .= ' AND (' . implode(' OR ', $conds) . ')';
    foreach ($def['search'] as $_) $params[] = $like;
}

$total = (int)(DB::fetch("SELECT COUNT(*) c FROM {$def['from']} WHERE {$where}", $params)['c'] ?? 0);

$rows = DB::fetchAll(
    "SELECT {$def['select']} FROM {$def['from']}
     WHERE {$where} ORDER BY {$def['order']} LIMIT {$per} OFFSET {$offset}",
    $params
);

json_ok([
    'counts' => $counts,
    'rows'   => $rows,
    'total'  => $total,
    'page'   => $page,
    'per'    => $per,
]);
