<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
Auth::requireRole('admin','director');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $users = DB::fetchAll(
        'SELECT u.id, u.username, u.full_name, u.email, u.role, u.is_active,
                u.last_login, u.created_at, d.name AS dept_name
         FROM users u
         LEFT JOIN departments d ON d.id=u.department_id
         ORDER BY u.role, u.full_name'
    );
    $depts = DB::fetchAll('SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order');
    json_ok(['users' => $users, 'departments' => $depts]);
}

if ($method === 'POST') {
    $d      = get_input();
    $action = $d['action'] ?? 'create';

    if ($action === 'create') {
        Auth::requireRole('admin');
        if (empty($d['username']) || empty($d['password'])) {
            json_err('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
        }
        $exists = DB::fetch('SELECT id FROM users WHERE username=?', [$d['username']]);
        if ($exists) json_err('ชื่อผู้ใช้นี้มีอยู่แล้ว');

        $hash = password_hash($d['password'], PASSWORD_BCRYPT);
        $id   = DB::insert(
            'INSERT INTO users (username,password_hash,full_name,email,role,department_id)
             VALUES (?,?,?,?,?,?)',
            [
                $d['username'], $hash, $d['full_name'],
                $d['email'] ?? null,
                $d['role']  ?? 'teacher',
                isset($d['department_id']) ? (int)$d['department_id'] : null,
            ]
        );
        json_ok(['id' => $id], 'เพิ่มผู้ใช้สำเร็จ');
    }

    if ($action === 'update') {
        DB::exec(
            'UPDATE users SET full_name=?,email=?,role=?,department_id=? WHERE id=?',
            [
                $d['full_name'],
                $d['email'] ?? null,
                $d['role'],
                isset($d['department_id']) ? (int)$d['department_id'] : null,
                (int)$d['id'],
            ]
        );
        json_ok(null,'แก้ไขผู้ใช้สำเร็จ');
    }

    if ($action === 'toggle_active') {
        $u = DB::fetch('SELECT is_active FROM users WHERE id=?', [(int)$d['id']]);
        if (!$u) json_err('ไม่พบผู้ใช้');
        $new = $u['is_active'] ? 0 : 1;
        DB::exec('UPDATE users SET is_active=? WHERE id=?', [$new, (int)$d['id']]);
        json_ok(['is_active' => $new]);
    }

    if ($action === 'reset_password') {
        Auth::requireRole('admin');
        $hash = password_hash($d['new_password'] ?? 'password', PASSWORD_BCRYPT);
        DB::exec('UPDATE users SET password_hash=? WHERE id=?', [$hash, (int)$d['id']]);
        json_ok(null,'รีเซ็ตรหัสผ่านสำเร็จ');
    }
}
