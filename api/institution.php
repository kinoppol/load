<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$sem    = get_current_semester();
$semId  = $sem ? (int)$sem['id'] : 0;

if ($method === 'GET') {
    $inst     = DB::fetch('SELECT * FROM institution_settings LIMIT 1');
    $holidays = DB::fetchAll(
        'SELECT * FROM holidays WHERE semester_id=? ORDER BY holiday_date', [$semId]
    );
    $semesters = DB::fetchAll('SELECT * FROM semesters ORDER BY year DESC, semester DESC');
    json_ok(['institution'=>$inst,'holidays'=>$holidays,'semesters'=>$semesters,'current_semester'=>$sem]);
}

if ($method === 'POST') {
    Auth::requireRole('admin','director');
    $d      = get_input();
    $action = $d['action'] ?? 'save_institution';

    if ($action === 'save_institution') {
        $exists = DB::fetch('SELECT id FROM institution_settings LIMIT 1');
        if ($exists) {
            DB::exec(
                'UPDATE institution_settings SET school_name=?,address=?,phone=?,director_name=? WHERE id=?',
                [$d['school_name'],$d['address']??'',$d['phone']??'',$d['director_name']??'',$exists['id']]
            );
        } else {
            DB::insert(
                'INSERT INTO institution_settings (school_name,address,phone,director_name) VALUES (?,?,?,?)',
                [$d['school_name'],$d['address']??'',$d['phone']??'',$d['director_name']??'']
            );
        }
        json_ok(null,'บันทึกข้อมูลสถานศึกษาสำเร็จ');
    }

    if ($action === 'save_semester') {
        // set all is_current=0 then set current
        DB::exec('UPDATE semesters SET is_current=0');
        $exists = DB::fetch('SELECT id FROM semesters WHERE id=?', [(int)$d['id']]);
        if ($exists) {
            DB::exec(
                'UPDATE semesters SET name=?,start_date=?,end_date=?,is_current=1 WHERE id=?',
                [$d['name'],$d['start_date'],$d['end_date'],(int)$d['id']]
            );
        } else {
            DB::insert(
                'INSERT INTO semesters (name,year,semester,start_date,end_date,is_current) VALUES (?,?,?,?,?,1)',
                [$d['name'],(int)$d['year'],(int)$d['semester'],$d['start_date'],$d['end_date']]
            );
        }
        json_ok(null,'บันทึกปฏิทินการศึกษาสำเร็จ');
    }

    if ($action === 'add_holiday') {
        $id = DB::insert(
            'INSERT INTO holidays (semester_id,name,holiday_date) VALUES (?,?,?)',
            [$semId, $d['name'], $d['holiday_date']]
        );
        json_ok(['id'=>$id],'เพิ่มวันหยุดสำเร็จ');
    }

    if ($action === 'delete_holiday') {
        DB::exec('DELETE FROM holidays WHERE id=?', [(int)$d['id']]);
        json_ok(null,'ลบวันหยุดสำเร็จ');
    }
}
