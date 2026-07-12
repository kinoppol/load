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

    // ── นับจำนวนผู้เรียนทั้งหมดในระบบ RMS ─────────────
    if ($action === 'count') {
        $r     = rms_fetch_json('data=std2018_student&count=yes');
        $total = (int)($r[0]['c'] ?? ($r[0]['count'] ?? 0));
        json_ok(['total' => $total]);
    }

    // ── โหลดผู้เรียนทีละท่อน (offset, row) แล้ว upsert ──
    if ($action === 'sync_batch') {
        $offset = max(0, (int)($d['offset'] ?? 0));
        $row    = (int)($d['row'] ?? 100);
        if ($row < 1)    $row = 100;
        if ($row > 1000) $row = 1000;

        $rows = rms_fetch_json("data=std2018_student&limit={$offset},{$row}");

        $nz = static fn($v) => ($v = trim((string)$v)) !== '' ? $v : null;
        $ni = static fn($v) => ($v = trim((string)$v)) !== '' && is_numeric($v) ? (int)$v : null;

        $added = 0; $updated = 0; $skipped = 0;
        foreach ($rows as $s) {
            if (!is_array($s)) { $skipped++; continue; }
            $sid = trim((string)($s['studentID'] ?? ''));
            if ($sid === '') { $skipped++; continue; }

            $gpax = trim((string)($s['gpax'] ?? ''));
            $gpax = ($gpax !== '' && is_numeric($gpax)) ? (float)$gpax : null;

            $fields = [
                'student_code'      => $nz($s['studentCode'] ?? ''),
                'idcard'            => $nz($s['idcard'] ?? ''),
                'firstname'         => $nz($s['firstname'] ?? ''),
                'surname'           => $nz($s['surname'] ?? ''),
                'gender'            => $nz($s['gender'] ?? ''),
                'group_code'        => $nz($s['groupCode'] ?? ''),
                'group_name'        => $nz($s['groupName'] ?? ''),
                'group_abbr'        => $nz($s['groupAbbr'] ?? ''),
                'grade_name'        => $nz($s['gradeNameTh'] ?? ''),
                'major_name'        => $nz($s['majorNameTh'] ?? ''),
                'status_code'       => $nz($s['studentStatusCode'] ?? ''),
                'status_name'       => $nz($s['studentStatusName'] ?? ''),
                'entrance_year'     => $ni($s['entranceYear'] ?? ''),
                'entrance_semester' => $ni($s['entranceSemester'] ?? ''),
                'email'             => $nz($s['email'] ?? ''),
                'tel'               => $nz($s['tel'] ?? ''),
                'gpax'              => $gpax,
            ];

            $exists = DB::fetch('SELECT id FROM students WHERE student_id=? LIMIT 1', [$sid]);
            if ($exists) {
                $set    = implode('=?, ', array_keys($fields)) . '=?';
                $params = array_merge(array_values($fields), [(int)$exists['id']]);
                DB::exec("UPDATE students SET {$set} WHERE id=?", $params);
                $updated++;
            } else {
                $cols   = 'student_id, ' . implode(', ', array_keys($fields));
                $ph     = implode(', ', array_fill(0, count($fields) + 1, '?'));
                $params = array_merge([$sid], array_values($fields));
                DB::insert("INSERT INTO students ({$cols}) VALUES ({$ph})", $params);
                $added++;
            }
        }

        json_ok([
            'added'   => $added,
            'updated' => $updated,
            'skipped' => $skipped,
            'fetched' => count($rows),
        ]);
    }

    json_err('ไม่รู้จักคำสั่ง', 400);
}

json_err('Method not allowed', 405);
