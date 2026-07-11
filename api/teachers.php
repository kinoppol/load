<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $deptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
    $sql    = 'SELECT t.*, d.name AS dept_name, d.short_name AS dept_short
               FROM teachers t
               JOIN departments d ON d.id = t.department_id
               WHERE t.is_active = 1';
    $params = [];
    if ($deptId) { $sql .= ' AND t.department_id=?'; $params[] = $deptId; }
    $sql .= ' ORDER BY d.sort_order, t.full_name';

    $teachers = DB::fetchAll($sql, $params);
    $depts    = DB::fetchAll('SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order');
    json_ok(['teachers' => $teachers, 'departments' => $depts]);
}

if ($method === 'POST') {
    Auth::requireRole('admin','curriculum');
    $d = get_input();
    $action = $d['action'] ?? 'create';

    if ($action === 'create') {
        $id = DB::insert(
            'INSERT INTO teachers (user_id,full_name,department_id,position) VALUES (?,?,?,?)',
            [$d['user_id']??null, $d['full_name'], (int)$d['department_id'], $d['position']??null]
        );
        json_ok(['id' => $id], 'เพิ่มครูสำเร็จ');
    }

    if ($action === 'update') {
        DB::exec(
            'UPDATE teachers SET full_name=?,department_id=?,position=? WHERE id=?',
            [$d['full_name'],(int)$d['department_id'],$d['position']??null,(int)$d['id']]
        );
        json_ok(null,'แก้ไขข้อมูลสำเร็จ');
    }

    if ($action === 'delete') {
        DB::exec('UPDATE teachers SET is_active=0 WHERE id=?', [(int)$d['id']]);
        json_ok(null,'ลบข้อมูลสำเร็จ');
    }
}
