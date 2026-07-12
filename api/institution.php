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

    if ($action === 'sync_holidays') {
        // host เก็บใน app_settings (ใช้ร่วมกับ RMS), path เก็บใน code
        $rmsPath = '/api_connection.php?app_name=nutty&data=stopday';
        $base = rtrim((string)get_setting('rms_base_url', 'http://rms.rvc.ac.th'), '/');
        if ($base === '') json_err('ยังไม่ได้ตั้งค่า URL ของระบบ RMS');
        $url = $base . $rmsPath;

        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) json_err('เชื่อมต่อ RMS ไม่สำเร็จ: ' . $err);
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 30]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) json_err('เชื่อมต่อ RMS ไม่สำเร็จ');
        }

        $days = json_decode($raw, true);
        if (!is_array($days)) json_err('ข้อมูลจาก RMS ไม่อยู่ในรูปแบบ JSON ที่ถูกต้อง');

        // แคช semester ตามคีย์ "semester/year" (eduyear)
        $semesters = DB::fetchAll('SELECT id, year, semester FROM semesters');
        $semMap = [];
        foreach ($semesters as $s) {
            $semMap[$s['semester'] . '/' . $s['year']] = (int)$s['id'];
        }

        $added = 0; $duplicated = 0; $skipped = 0;

        foreach ($days as $day) {
            if (!is_array($day)) continue;
            $eduyear = trim((string)($day['stopday_eduyear'] ?? ''));
            $date    = trim((string)($day['stopday_date'] ?? ''));
            $name    = trim((string)($day['stopday_name'] ?? ''));

            if ($date === '' || $date === '0000-00-00' || $name === '') { $skipped++; continue; }
            // ไม่มีภาคเรียนในระบบที่ตรงกับ eduyear ของวันหยุดนี้ → ข้าม
            if (!isset($semMap[$eduyear])) { $skipped++; continue; }
            $semId2 = $semMap[$eduyear];

            $exists = DB::fetch(
                'SELECT id FROM holidays WHERE semester_id=? AND holiday_date=? LIMIT 1',
                [$semId2, $date]
            );
            if ($exists) { $duplicated++; continue; }

            DB::insert(
                'INSERT INTO holidays (semester_id, name, holiday_date) VALUES (?,?,?)',
                [$semId2, $name, $date]
            );
            $added++;
        }

        json_ok(
            ['added' => $added, 'duplicated' => $duplicated, 'skipped' => $skipped],
            "โหลดวันหยุดสำเร็จ: เพิ่ม {$added}, ซ้ำ {$duplicated}, ข้าม {$skipped}"
        );
    }
}
