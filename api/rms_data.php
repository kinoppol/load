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
    'students'   => (int)(DB::fetch('SELECT COUNT(*) c FROM students')['c'] ?? 0),
    'groups'     => (int)(DB::fetch('SELECT COUNT(*) c FROM student_groups')['c'] ?? 0),
    'schedules'  => (int)(DB::fetch('SELECT COUNT(*) c FROM class_schedules')['c'] ?? 0),
];

$resource = $_GET['resource'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = min(100, max(5, (int)($_GET['per'] ?? 20)));
$q        = trim((string)($_GET['q'] ?? ''));
$offset   = ($page - 1) * $per;
$like     = '%' . $q . '%';

// นิยามแต่ละชุดข้อมูล: ตาราง, คอลัมน์ที่ดึง, ฟิลด์ค้นหา, เงื่อนไข/พารามิเตอร์คงที่
$defs = [
    'students' => [
        'table'   => 'students',
        'select'  => 'student_code, firstname, surname, group_name, grade_name, major_name, status_name, gpax',
        'search'  => ['firstname', 'surname', 'student_code', 'idcard', 'group_name'],
        'order'   => 'group_name, surname',
        'where'   => '1=1',
        'params'  => [],
    ],
    'groups' => [
        'table'   => 'student_groups',
        'select'  => 'academic_year, semester, group_code, grade, group_name, group_abbr, teacher_name',
        'search'  => ['group_code', 'group_name', 'group_abbr', 'grade', 'teacher_name'],
        'order'   => 'academic_year DESC, semester DESC, group_code',
        'where'   => '1=1',
        'params'  => [],
    ],
    'schedules' => [
        'table'   => 'class_schedules',
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

$total = (int)(DB::fetch("SELECT COUNT(*) c FROM {$def['table']} WHERE {$where}", $params)['c'] ?? 0);

$rows = DB::fetchAll(
    "SELECT {$def['select']} FROM {$def['table']}
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
