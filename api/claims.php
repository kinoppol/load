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
$user  = Auth::user();

// ── GET list ──────────────────────────────────────────
if ($method === 'GET') {
    $teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;
    $periodId  = isset($_GET['period_id'])  ? (int)$_GET['period_id']  : null;
    $status    = $_GET['status'] ?? null;
    $mode      = $_GET['mode']   ?? 'normal'; // normal | block

    $sql = 'SELECT cr.*, t.full_name, d.name AS dept_name, d.short_name AS dept_short
            FROM claim_records cr
            JOIN teachers t ON t.id = cr.teacher_id
            JOIN departments d ON d.id = t.department_id
            WHERE cr.semester_id=? AND cr.is_block_course=?';
    $params = [$semId, $mode === 'block' ? 1 : 0];

    if (Auth::can('teacher')) {
        $myTeacher = get_teacher_by_user($user['id']);
        if ($myTeacher) { $sql .= ' AND cr.teacher_id=?'; $params[] = $myTeacher['id']; }
    } elseif ($teacherId) {
        $sql .= ' AND cr.teacher_id=?'; $params[] = $teacherId;
    }
    if ($periodId) { $sql .= ' AND cr.period_id=?'; $params[] = $periodId; }
    if ($status)   { $sql .= ' AND cr.status=?';    $params[] = $status; }
    $sql .= ' ORDER BY cr.week_num DESC, cr.created_at DESC';

    $rows = DB::fetchAll($sql, $params);
    json_ok(['claims' => $rows]);
}

// ── POST create / update / approve / reject ───────────
if ($method === 'POST') {
    $d      = get_input();
    $action = $d['action'] ?? 'create';

    if ($action === 'create' || $action === 'update') {
        Auth::requireRole('admin','curriculum','teacher');

        // load rules
        $rules = DB::fetch('SELECT * FROM compensation_rules WHERE semester_id=?', [$semId]);
        $rate  = DB::fetch(
            'SELECT rate_per_hour FROM teaching_rates WHERE semester_id=? AND level=? AND is_enabled=1',
            [$semId, $d['level']]
        );
        if (!$rate) json_err('ไม่พบอัตราค่าสอนสำหรับระดับนี้');

        $calc = calculate_amount(
            (int)$d['total_periods'],
            (int)($rules['normal_load']   ?? 18),
            (int)($rules['max_claimable'] ?? 10),
            (float)$rate['rate_per_hour'],
            (int)($d['student_count']     ?? 0),
            (int)($rules['min_students']  ?? 25),
            (float)($rules['per_head_rate'] ?? 20)
        );

        // week dates
        $weekStart = $d['week_start_date'] ?? date('Y-m-d');
        $weekEnd   = $d['week_end_date']   ?? date('Y-m-d', strtotime($weekStart . ' +4 days'));
        $weekNum   = week_number_in_semester($weekStart, $sem['start_date']);

        // period
        $period = DB::fetch(
            'SELECT id FROM claim_periods WHERE semester_id=? AND week_start<=? AND week_end>=?',
            [$semId, $weekNum, $weekNum]
        );

        if ($action === 'create') {
            $id = DB::insert(
                'INSERT INTO claim_records
                 (teacher_id,period_id,semester_id,week_num,week_start_date,week_end_date,
                  subject,group_name,level,total_periods,over_periods,rate_per_hour,
                  student_count,amount,status,is_block_course,block_start_date,block_end_date,hours_per_day)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    (int)$d['teacher_id'],
                    $period['id'] ?? null,
                    $semId,
                    $weekNum,
                    $weekStart,
                    $weekEnd,
                    $d['subject']      ?? '',
                    $d['group_name']   ?? '',
                    $d['level'],
                    (int)$d['total_periods'],
                    $calc['over_periods'],
                    (float)$rate['rate_per_hour'],
                    (int)($d['student_count'] ?? 0),
                    $calc['amount'],
                    'pending',
                    (int)($d['is_block_course'] ?? 0),
                    $d['block_start_date'] ?? null,
                    $d['block_end_date']   ?? null,
                    isset($d['hours_per_day']) ? (int)$d['hours_per_day'] : null,
                ]
            );
            // recalc period total
            if ($period) recalc_period_total((int)$period['id']);
            json_ok(['id' => $id, 'amount' => $calc['amount'], 'over_periods' => $calc['over_periods']], 'บันทึกใบเบิกสำเร็จ');
        } else {
            DB::exec(
                'UPDATE claim_records SET subject=?,group_name=?,level=?,total_periods=?,
                 over_periods=?,rate_per_hour=?,student_count=?,amount=?,
                 week_start_date=?,week_end_date=?,week_num=?,period_id=? WHERE id=?',
                [
                    $d['subject']??'', $d['group_name']??'', $d['level'],
                    (int)$d['total_periods'], $calc['over_periods'],
                    (float)$rate['rate_per_hour'], (int)($d['student_count']??0),
                    $calc['amount'], $weekStart, $weekEnd, $weekNum,
                    $period['id']??null, (int)$d['id'],
                ]
            );
            json_ok(['amount'=>$calc['amount'],'over_periods'=>$calc['over_periods']],'แก้ไขสำเร็จ');
        }
    }

    if ($action === 'approve') {
        Auth::requireRole('admin','director');
        DB::exec(
            "UPDATE claim_records SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?",
            [$user['id'], (int)$d['id']]
        );
        json_ok(null,'อนุมัติสำเร็จ');
    }

    if ($action === 'reject') {
        Auth::requireRole('admin','director');
        DB::exec(
            "UPDATE claim_records SET status='rejected',approved_by=?,approved_at=NOW(),note=? WHERE id=?",
            [$user['id'], $d['note']??'', (int)$d['id']]
        );
        json_ok(null,'ปฏิเสธสำเร็จ');
    }

    if ($action === 'pay') {
        Auth::requireRole('admin','accounting');
        DB::exec(
            "UPDATE claim_records SET status='paid' WHERE id=?",
            [(int)$d['id']]
        );
        json_ok(null,'บันทึกการจ่ายสำเร็จ');
    }

    if ($action === 'delete') {
        Auth::requireRole('admin','curriculum');
        $row = DB::fetch('SELECT period_id FROM claim_records WHERE id=?', [(int)$d['id']]);
        DB::exec('DELETE FROM claim_records WHERE id=?', [(int)$d['id']]);
        if ($row && $row['period_id']) recalc_period_total((int)$row['period_id']);
        json_ok(null,'ลบสำเร็จ');
    }

    if ($action === 'calculate') {
        $rules = DB::fetch('SELECT * FROM compensation_rules WHERE semester_id=?', [$semId]);
        $rate  = DB::fetch(
            'SELECT rate_per_hour FROM teaching_rates WHERE semester_id=? AND level=? AND is_enabled=1',
            [$semId, $d['level'] ?? 'pvch']
        );
        $rph  = (float)($rate['rate_per_hour'] ?? 60);
        $calc = calculate_amount(
            (int)($d['total_periods']  ?? 0),
            (int)($rules['normal_load']   ?? 18),
            (int)($rules['max_claimable'] ?? 10),
            $rph,
            (int)($d['student_count']  ?? 0),
            (int)($rules['min_students']  ?? 25),
            (float)($rules['per_head_rate'] ?? 20)
        );
        json_ok(['over_periods'=>$calc['over_periods'],'amount'=>$calc['amount'],'rate'=>$rph]);
    }
}

function recalc_period_total(int $periodId): void
{
    $sum = (float)(DB::fetch(
        "SELECT COALESCE(SUM(amount),0) AS t FROM claim_records WHERE period_id=? AND status!='rejected'",
        [$periodId]
    )['t'] ?? 0);
    DB::exec('UPDATE claim_periods SET total_amount=? WHERE id=?', [$sum, $periodId]);
}
