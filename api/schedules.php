<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
Auth::requireRole('admin', 'director');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
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
