<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$sem    = get_current_semester();
if (!$sem) json_err('ไม่พบภาคเรียน');
$semId = (int)$sem['id'];

if ($method === 'GET') {
    $periods = DB::fetchAll(
        'SELECT p.*,
                COUNT(cr.id) AS doc_count,
                COALESCE(SUM(CASE WHEN cr.status IN ("approved","paid") THEN 1 ELSE 0 END),0) AS submitted_count
         FROM claim_periods p
         LEFT JOIN claim_records cr ON cr.period_id=p.id
         WHERE p.semester_id=?
         GROUP BY p.id
         ORDER BY p.period_num',
        [$semId]
    );
    json_ok(['periods' => $periods, 'semester' => $sem]);
}

if ($method === 'POST') {
    $d      = get_input();
    $action = $d['action'] ?? 'create';

    if ($action === 'create') {
        Auth::requireRole('admin','curriculum');
        // auto period_num
        $last = DB::fetch('SELECT MAX(period_num) AS m FROM claim_periods WHERE semester_id=?', [$semId]);
        $num  = (int)($last['m'] ?? 0) + 1;
        $id   = DB::insert(
            'INSERT INTO claim_periods (semester_id,period_num,week_start,week_end,start_date,end_date)
             VALUES (?,?,?,?,?,?)',
            [$semId, $num, (int)$d['week_start'], (int)$d['week_end'], $d['start_date'], $d['end_date']]
        );
        json_ok(['id' => $id], 'สร้างงวดสำเร็จ');
    }

    if ($action === 'toggle_lock') {
        Auth::requireRole('admin','curriculum','director');
        $period  = DB::fetch('SELECT * FROM claim_periods WHERE id=?', [(int)$d['id']]);
        if (!$period) json_err('ไม่พบงวด');
        $newStatus = $period['status'] === 'open' ? 'locked' : 'open';
        DB::exec('UPDATE claim_periods SET status=? WHERE id=?', [$newStatus, (int)$d['id']]);
        json_ok(['status' => $newStatus], 'เปลี่ยนสถานะสำเร็จ');
    }

    if ($action === 'mark_paid') {
        Auth::requireRole('admin','accounting');
        DB::exec("UPDATE claim_periods SET status='paid' WHERE id=?", [(int)$d['id']]);
        DB::exec(
            "UPDATE claim_records SET status='paid' WHERE period_id=? AND status='approved'",
            [(int)$d['id']]
        );
        json_ok(null,'บันทึกการจ่ายเงินสำเร็จ');
    }

    if ($action === 'delete') {
        Auth::requireRole('admin');
        $count = (int)(DB::fetch('SELECT COUNT(*) AS c FROM claim_records WHERE period_id=?',[(int)$d['id']])['c']??0);
        if ($count > 0) json_err('ไม่สามารถลบงวดที่มีใบเบิกอยู่');
        DB::exec('DELETE FROM claim_periods WHERE id=?', [(int)$d['id']]);
        json_ok(null,'ลบงวดสำเร็จ');
    }
}
