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
    $weekNum = isset($_GET['week']) ? (int)$_GET['week'] : 1;
    $deptId  = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;

    // Compute dates for the week
    $semStart  = new DateTime($sem['start_date']);
    $weekStart = (clone $semStart)->modify('+' . (($weekNum - 1) * 7) . ' days');
    $dates     = [];
    for ($i = 0; $i < 5; $i++) {
        $dates[] = (clone $weekStart)->modify("+{$i} days")->format('Y-m-d');
    }

    $sql = 'SELECT t.id, t.full_name, d.name AS dept_name
            FROM teachers t JOIN departments d ON d.id=t.department_id
            WHERE t.is_active=1';
    $params = [];
    if ($deptId) { $sql .= ' AND t.department_id=?'; $params[] = $deptId; }
    $sql .= ' ORDER BY d.sort_order, t.full_name';
    $teachers = DB::fetchAll($sql, $params);

    $attRows = DB::fetchAll(
        'SELECT teacher_id, att_date, status
         FROM attendance
         WHERE semester_id=? AND week_num=?',
        [$semId, $weekNum]
    );
    $attMap = [];
    foreach ($attRows as $a) {
        $attMap[$a['teacher_id']][$a['att_date']] = $a['status'];
    }

    $holidays = DB::fetchAll(
        'SELECT holiday_date FROM holidays WHERE semester_id=?', [$semId]
    );
    $holidayDates = array_column($holidays, 'holiday_date');

    $rows = [];
    foreach ($teachers as $t) {
        $days     = [];
        $workDays = 0;
        foreach ($dates as $date) {
            $isHoliday = in_array($date, $holidayDates);
            $status    = $attMap[$t['id']][$date] ?? ($isHoliday ? 'holiday' : 'present');
            if ($status === 'present') $workDays++;
            $days[] = ['date' => $date, 'status' => $status];
        }
        $rows[] = [
            'teacher_id' => $t['id'],
            'full_name'  => $t['full_name'],
            'dept_name'  => $t['dept_name'],
            'days'       => $days,
            'work_days'  => $workDays,
        ];
    }

    $depts = DB::fetchAll('SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order');

    json_ok([
        'week'      => $weekNum,
        'dates'     => $dates,
        'rows'      => $rows,
        'holidays'  => $holidayDates,
        'departments'=> $depts,
        'total_weeks'=> 18,
    ]);
}

if ($method === 'POST') {
    $d = get_input();

    if (($d['action'] ?? '') === 'save') {
        Auth::requireRole('admin','curriculum','teacher');
        $weekNum = (int)($d['week'] ?? 1);
        $updates = $d['updates'] ?? [];

        $semStart  = new DateTime($sem['start_date']);
        $weekStart = (clone $semStart)->modify('+' . (($weekNum - 1) * 7) . ' days');

        $db = DB::getInstance();
        $db->beginTransaction();
        try {
            foreach ($updates as $u) {
                $teacherId = (int)$u['teacher_id'];
                $date      = $u['date'];
                $status    = $u['status'];

                // validate status
                $allowed = ['present','leave_personal','leave_sick','holiday','other'];
                if (!in_array($status, $allowed, true)) continue;

                DB::exec(
                    'INSERT INTO attendance (teacher_id,semester_id,week_num,att_date,status)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE status=VALUES(status)',
                    [$teacherId, $semId, $weekNum, $date, $status]
                );
            }
            $db->commit();
            json_ok(null,'บันทึกการปฏิบัติงานสำเร็จ');
        } catch (Throwable $e) {
            $db->rollBack();
            json_err('บันทึกไม่สำเร็จ: ' . $e->getMessage());
        }
    }
}
