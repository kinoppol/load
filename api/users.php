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

    if ($action === 'delete') {
        Auth::requireRole('admin');
        $id   = (int)($d['id'] ?? 0);
        $me   = Auth::user();
        if ($id <= 0)               json_err('ไม่พบผู้ใช้');
        if ($id === (int)$me['id']) json_err('ไม่สามารถลบบัญชีของตนเองได้');

        $target = DB::fetch('SELECT id, role FROM users WHERE id=?', [$id]);
        if (!$target) json_err('ไม่พบผู้ใช้');
        // กันลบผู้ดูแลระบบคนสุดท้าย
        if ($target['role'] === 'admin') {
            $adminCount = (int)(DB::fetch('SELECT COUNT(*) c FROM users WHERE role=?', ['admin'])['c'] ?? 0);
            if ($adminCount <= 1) json_err('ไม่สามารถลบผู้ดูแลระบบคนสุดท้ายได้');
        }

        DB::exec('DELETE FROM users WHERE id=?', [$id]);
        json_ok(null, 'ลบผู้ใช้สำเร็จ');
    }

    if ($action === 'sync_rms') {
        Auth::requireRole('admin');

        // ส่วน path ของ endpoint เก็บไว้ใน code, ส่วน host เก็บในฐานข้อมูล
        $rmsPath = '/api_connection.php?app_name=nutty&data=people';
        $base = rtrim((string)get_setting('rms_base_url', 'http://rms.rvc.ac.th'), '/');
        if ($base === '') json_err('ยังไม่ได้ตั้งค่า URL ของระบบ RMS');
        $url = $base . $rmsPath;

        // ── ดึงข้อมูล JSON ──────────────────────────────
        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw  = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($raw === false) json_err('เชื่อมต่อ RMS ไม่สำเร็จ: ' . $err);
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 30]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) json_err('เชื่อมต่อ RMS ไม่สำเร็จ');
        }

        $people = json_decode($raw, true);
        if (!is_array($people)) json_err('ข้อมูลจาก RMS ไม่อยู่ในรูปแบบ JSON ที่ถูกต้อง');

        $created = 0; $updated = 0; $deactivated = 0; $activeIds = [];

        foreach ($people as $p) {
            if (!is_array($p)) continue;
            // โอนเฉพาะบุคลากรที่ยังไม่พ้นสภาพ (people_exit == 0)
            if ((string)($p['people_exit'] ?? '1') !== '0') continue;

            $peopleId = trim((string)($p['people_id'] ?? ''));
            if ($peopleId === '') continue;
            $activeIds[$peopleId] = true;

            $fullName = trim(($p['people_name'] ?? '') . ' ' . ($p['people_surname'] ?? ''));
            $email    = trim((string)($p['people_email'] ?? '')) ?: null;
            // ath_pass ใช้เป็นรหัสผ่าน; ถ้าว่างใช้ people_id แทนเพื่อไม่ให้รหัสผ่านว่าง
            $rawPass  = trim((string)($p['ath_pass'] ?? ''));
            $hash     = password_hash($rawPass !== '' ? $rawPass : $peopleId, PASSWORD_BCRYPT);

            $existing = DB::fetch('SELECT id FROM users WHERE people_id=? OR username=? LIMIT 1',
                                  [$peopleId, $peopleId]);

            if ($existing) {
                // อัปเดตข้อมูลเดิม — ไม่แตะ created_at
                DB::exec(
                    'UPDATE users
                     SET people_id=?, username=?, full_name=?, email=?, password_hash=?, is_active=1
                     WHERE id=?',
                    [$peopleId, $peopleId, $fullName, $email, $hash, (int)$existing['id']]
                );
                $updated++;
            } else {
                DB::insert(
                    'INSERT INTO users (username, people_id, password_hash, full_name, email, role, is_active)
                     VALUES (?,?,?,?,?,?,1)',
                    [$peopleId, $peopleId, $hash, $fullName, $email, 'teacher']
                );
                $created++;
            }
        }

        // บุคลากรที่โอนจาก RMS แต่ไม่อยู่ในชุดที่ยังไม่พ้นสภาพ (พ้นสภาพ/ไม่มีข้อมูล) → ปิดการใช้งาน
        $rmsUsers = DB::fetchAll('SELECT id, people_id FROM users WHERE people_id IS NOT NULL AND is_active=1');
        foreach ($rmsUsers as $u) {
            if (!isset($activeIds[$u['people_id']])) {
                DB::exec('UPDATE users SET is_active=0 WHERE id=?', [(int)$u['id']]);
                $deactivated++;
            }
        }

        json_ok(
            ['created' => $created, 'updated' => $updated, 'deactivated' => $deactivated,
             'active_source' => count($activeIds)],
            "โอนข้อมูลสำเร็จ: เพิ่ม {$created}, อัปเดต {$updated}, ปิดใช้งาน {$deactivated}"
        );
    }
}
