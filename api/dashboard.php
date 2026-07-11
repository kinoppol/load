<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$sem = get_current_semester();
if (!$sem) { json_err('ไม่พบข้อมูลภาคเรียนปัจจุบัน'); }
$semId = (int)$sem['id'];
$user  = Auth::user();

// ── Stats cards ──────────────────────────────────────
$totalAmount = (float)(DB::fetch(
    'SELECT COALESCE(SUM(amount),0) AS t FROM claim_records WHERE semester_id=?', [$semId]
)['t'] ?? 0);

$teacherCount = (int)(DB::fetch(
    'SELECT COUNT(DISTINCT teacher_id) AS c FROM claim_records WHERE semester_id=?', [$semId]
)['c'] ?? 0);

$periodStats = DB::fetch(
    'SELECT COUNT(*) AS total,
            SUM(status="paid") AS paid
     FROM claim_periods WHERE semester_id=?', [$semId]
);
$periodTotal = (int)($periodStats['total'] ?? 0);
$periodPaid  = (int)($periodStats['paid'] ?? 0);

$docStats = DB::fetch(
    'SELECT COUNT(*) AS total,
            SUM(status NOT IN ("paid","approved")) AS pending
     FROM claim_records WHERE semester_id=?', [$semId]
);
$docTotal   = (int)($docStats['total'] ?? 0);
$docPending = (int)($docStats['pending'] ?? 0);

// ── Weekly chart (group by week_num, sum amount) ─────
$weeklyRows = DB::fetchAll(
    'SELECT week_num, SUM(amount) AS total
     FROM claim_records WHERE semester_id=?
     GROUP BY week_num ORDER BY week_num LIMIT 18', [$semId]
);
$weeklyLabels  = array_column($weeklyRows, 'week_num');
$weeklyAmounts = array_map('floatval', array_column($weeklyRows, 'total'));

// ── Status chart ─────────────────────────────────────
$statusRows = DB::fetchAll(
    'SELECT status, COUNT(*) AS cnt FROM claim_records
     WHERE semester_id=? GROUP BY status', [$semId]
);
$statusMap = array_column($statusRows, 'cnt', 'status');

// ── Recent claims ─────────────────────────────────────
$recentSql = 'SELECT cr.*, t.full_name, d.name AS dept_name
              FROM claim_records cr
              JOIN teachers t ON t.id = cr.teacher_id
              JOIN departments d ON d.id = t.department_id
              WHERE cr.semester_id=?';
$params = [$semId];
if (Auth::can('teacher')) {
    $teacher = get_teacher_by_user($user['id']);
    if ($teacher) {
        $recentSql .= ' AND cr.teacher_id=?';
        $params[] = $teacher['id'];
    }
}
$recentSql .= ' ORDER BY cr.created_at DESC LIMIT 10';
$recentRows = DB::fetchAll($recentSql, $params);

// ── Makeup / Sub summary ──────────────────────────────
$mkTotal   = (int)(DB::fetch('SELECT COUNT(*) AS c FROM makeup_records WHERE semester_id=?',[$semId])['c']??0);
$mkPending = (int)(DB::fetch("SELECT COUNT(*) AS c FROM makeup_records WHERE semester_id=? AND status='pending'",[$semId])['c']??0);
$sbTotal   = (int)(DB::fetch('SELECT COUNT(*) AS c FROM substitute_records WHERE semester_id=?',[$semId])['c']??0);
$sbPending = (int)(DB::fetch("SELECT COUNT(*) AS c FROM substitute_records WHERE semester_id=? AND status='pending'",[$semId])['c']??0);

// ── Upcoming deadlines (open periods) ────────────────
$openPeriods = DB::fetchAll(
    "SELECT * FROM claim_periods WHERE semester_id=? AND status='open' ORDER BY period_num LIMIT 3",
    [$semId]
);

json_ok([
    'semester'     => $sem,
    'stats'        => [
        'totalAmount'  => $totalAmount,
        'teacherCount' => $teacherCount,
        'periodTotal'  => $periodTotal,
        'periodPaid'   => $periodPaid,
        'docTotal'     => $docTotal,
        'docPending'   => $docPending,
    ],
    'weeklyChart'  => ['labels' => $weeklyLabels, 'amounts' => $weeklyAmounts],
    'statusChart'  => [
        'paid'     => (int)($statusMap['paid']     ?? 0),
        'approved' => (int)($statusMap['approved'] ?? 0),
        'pending'  => (int)($statusMap['pending']  ?? 0),
        'rejected' => (int)($statusMap['rejected'] ?? 0),
        'draft'    => (int)($statusMap['draft']    ?? 0),
    ],
    'recentClaims' => $recentRows,
    'mkSummary'    => ['total'=>$mkTotal,'pending'=>$mkPending],
    'sbSummary'    => ['total'=>$sbTotal,'pending'=>$sbPending],
    'openPeriods'  => $openPeriods,
]);
