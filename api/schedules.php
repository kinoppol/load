<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requireRole('admin', 'director', 'curriculum');
    $list = $_GET['list'] ?? '';

    // ภาคเรียนที่มีข้อมูลตารางสอน
    if ($list === 'semesters') {
        $rows = DB::fetchAll(
            'SELECT semes, COUNT(*) AS n FROM class_schedules GROUP BY semes ORDER BY semes DESC'
        );
        json_ok(['semesters' => $rows]);
    }

    // รายชื่อครูในภาคเรียนที่เลือก พร้อมจำนวนวิชา/คาบรวม
    if ($list === 'teachers') {
        $semes = trim((string)($_GET['semes'] ?? ''));
        if ($semes === '') json_err('ไม่ได้ระบุภาคเรียน');
        $rows = DB::fetchAll(
            'SELECT teacher_id, teacher_name,
                    COUNT(*) AS subject_count,
                    COALESCE(SUM(periods),0) AS total_periods
             FROM class_schedules
             WHERE semes=? AND teacher_name IS NOT NULL AND teacher_name<>""
             GROUP BY teacher_id, teacher_name
             ORDER BY teacher_name',
            [$semes]
        );
        json_ok(['teachers' => $rows]);
    }

    // ตารางสอนของครูคนหนึ่งในภาคเรียนที่เลือก
    $semes     = trim((string)($_GET['semes'] ?? ''));
    $teacherId = trim((string)($_GET['teacher_id'] ?? ''));
    if ($semes === '' || $teacherId === '') json_err('ไม่ได้ระบุภาคเรียนหรือครู');
    $rows = DB::fetchAll(
        'SELECT subject_id, subject_name, student_group_id, day_name, time_range,
                periods, room, building
         FROM class_schedules
         WHERE semes=? AND teacher_id=?
         ORDER BY day_name, time_range',
        [$semes, $teacherId]
    );

    // map รหัสกลุ่ม -> ชื่อย่อ + จำนวนนักเรียน (นับจากตาราง students)
    $codes = array_values(array_unique(array_filter(
        array_map(fn($r) => (string)$r['student_group_id'], $rows),
        fn($c) => $c !== '' && $c !== '00000000'
    )));
    $groupMap = [];
    if ($codes) {
        $ph = implode(',', array_fill(0, count($codes), '?'));
        $srows = DB::fetchAll(
            "SELECT group_code,
                    COALESCE(MAX(NULLIF(group_abbr,'')), MAX(group_name)) AS abbr,
                    COUNT(*) AS cnt
             FROM students WHERE group_code IN ($ph) GROUP BY group_code",
            $codes
        );
        foreach ($srows as $g) {
            $groupMap[$g['group_code']] = ['abbr' => $g['abbr'], 'count' => (int)$g['cnt']];
        }
        // กลุ่มที่ยังไม่มีนักเรียนในระบบ → ดึงชื่อย่อจากตาราง student_groups
        $missing = array_values(array_diff($codes, array_keys($groupMap)));
        if ($missing) {
            $ph2 = implode(',', array_fill(0, count($missing), '?'));
            $grows = DB::fetchAll(
                "SELECT group_code, COALESCE(MAX(NULLIF(group_abbr,'')), MAX(group_name)) AS abbr
                 FROM student_groups WHERE group_code IN ($ph2) GROUP BY group_code",
                $missing
            );
            foreach ($grows as $g) {
                $groupMap[$g['group_code']] = ['abbr' => $g['abbr'], 'count' => 0];
            }
        }
    }

    json_ok([
        'rows'          => $rows,
        'groups'        => $groupMap,
        'total_periods' => array_sum(array_map(fn($r) => (int)$r['periods'], $rows)),
    ]);
}

if ($method === 'POST') {
    Auth::requireRole('admin', 'director');
    $d      = get_input();
    $action = $d['action'] ?? '';

    // ── โหลดตารางเรียนของภาคเรียนปัจจุบันทีละท่อน ──────
    if ($action === 'sync_batch') {
        $sem = get_current_semester();
        if (!$sem) json_err('ยังไม่ได้กำหนดภาคเรียนปัจจุบัน');
        $semes = (int)$sem['semester'] . '/' . (int)$sem['year']; // เช่น 1/2569

        $offset = max(0, (int)($d['offset'] ?? 0));
        $row    = (int)($d['row'] ?? 1000);
        if ($row < 1)    $row = 1000;
        if ($row > 5000) $row = 5000;

        // ท่อนแรก (offset 0) → ล้างข้อมูลตารางเรียนของภาคเรียนนี้ก่อน แล้วโหลดใหม่ทั้งหมด
        if ($offset === 0) {
            DB::exec('DELETE FROM class_schedules WHERE semes=?', [$semes]);
        }

        $rows = rms_fetch_json("data=studing&semes={$semes}&limit={$offset},{$row}");

        $nz = static fn($v) => ($v = trim((string)$v)) !== '' ? $v : null;

        $inserted = 0;
        foreach ($rows as $s) {
            if (!is_array($s)) continue;
            $periods = trim((string)($s['dpr4'] ?? ''));
            $periods = ($periods !== '' && is_numeric($periods)) ? (int)$periods : null;

            DB::insert(
                'INSERT INTO class_schedules
                 (semes, subject_id, subject_name, real_subject_id, student_group_id,
                  teacher_id, teacher_name, day_name, time_range, periods, room, building,
                  timetable_id, timetable_sub_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $semes,
                    $nz($s['subject_id'] ?? ''),
                    $nz($s['subject_name'] ?? ''),
                    $nz($s['real_subject_id'] ?? ''),
                    $nz($s['student_group_id'] ?? ''),
                    $nz($s['teacher_id'] ?? ''),
                    $nz($s['teacher_name'] ?? ''),
                    $nz($s['dpr2'] ?? ''),   // วันในสัปดาห์
                    $nz($s['dpr3'] ?? ''),   // ช่วงเวลา
                    $periods,                // dpr4 = จำนวนคาบ
                    $nz($s['roomName'] ?? ''),
                    $nz($s['ucode'] ?? ''),  // อาคาร
                    $nz($s['timeTableID'] ?? ''),
                    $nz($s['timeTableSubID'] ?? ''),
                ]
            );
            $inserted++;
        }

        json_ok([
            'semes'    => $semes,
            'inserted' => $inserted,
            'fetched'  => count($rows),
        ]);
    }

    json_err('ไม่รู้จักคำสั่ง', 400);
}

json_err('Method not allowed', 405);
