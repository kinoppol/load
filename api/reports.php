<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$semId  = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : null;
$deptId = isset($_GET['dept_id'])     ? (int)$_GET['dept_id']     : null;

if (!$semId) {
    $sem   = get_current_semester();
    $semId = $sem ? (int)$sem['id'] : 0;
}
if (!$semId) json_err('ไม่พบภาคเรียน');

// ── KPI ──────────────────────────────────────────────
$kpi = DB::fetch(
    "SELECT
       COALESCE(SUM(amount),0) AS total_amount,
       COUNT(DISTINCT teacher_id) AS teacher_count,
       SUM(over_periods) AS total_over,
       COUNT(*) AS doc_count,
       SUM(status='paid') AS paid_count,
       SUM(status='pending') AS pending_count
     FROM claim_records WHERE semester_id=?",
    [$semId]
);

// ── By Department ─────────────────────────────────────
$deptSql = "SELECT d.name AS dept, d.id AS dept_id,
                   COALESCE(SUM(cr.amount),0) AS total_amount,
                   COUNT(DISTINCT cr.teacher_id) AS teacher_count,
                   COALESCE(SUM(cr.over_periods),0) AS total_periods,
                   COUNT(cr.id) AS doc_count
            FROM departments d
            LEFT JOIN teachers t ON t.department_id=d.id AND t.is_active=1
            LEFT JOIN claim_records cr ON cr.teacher_id=t.id AND cr.semester_id=?
            WHERE d.is_active=1
            GROUP BY d.id ORDER BY total_amount DESC";
$deptRows = DB::fetchAll($deptSql, [$semId]);

// calc percentages
$maxAmt = max(1, max(array_column($deptRows, 'total_amount')));
foreach ($deptRows as &$dr) {
    $dr['pct'] = $maxAmt > 0 ? round((float)$dr['total_amount'] / $maxAmt * 100) : 0;
}
unset($dr);

// ── By Period ─────────────────────────────────────────
$periodRows = DB::fetchAll(
    "SELECT p.*,
            COUNT(cr.id) AS doc_count,
            COUNT(DISTINCT cr.teacher_id) AS teacher_count
     FROM claim_periods p
     LEFT JOIN claim_records cr ON cr.period_id=p.id AND cr.status!='rejected'
     WHERE p.semester_id=?
     GROUP BY p.id ORDER BY p.period_num",
    [$semId]
);

// ── By Teacher ────────────────────────────────────────
$teachSql = "SELECT t.id, t.full_name, d.name AS dept_name,
                    COALESCE(SUM(cr.over_periods),0) AS total_over,
                    COALESCE(SUM(cr.amount),0) AS total_amount,
                    COUNT(cr.id) AS doc_count,
                    COALESCE(mk.mk_count,0) AS makeup_count,
                    COALESCE(sb.sb_count,0) AS sub_count
             FROM teachers t
             JOIN departments d ON d.id=t.department_id
             LEFT JOIN claim_records cr ON cr.teacher_id=t.id AND cr.semester_id=?
             LEFT JOIN (
               SELECT teacher_id, COUNT(*) AS mk_count
               FROM makeup_records WHERE semester_id=? GROUP BY teacher_id
             ) mk ON mk.teacher_id=t.id
             LEFT JOIN (
               SELECT sub_teacher_id, COUNT(*) AS sb_count
               FROM substitute_records WHERE semester_id=? GROUP BY sub_teacher_id
             ) sb ON sb.sub_teacher_id=t.id
             WHERE t.is_active=1";
$params = [$semId, $semId, $semId];
if ($deptId) { $teachSql .= ' AND t.department_id=?'; $params[] = $deptId; }
$teachSql .= ' GROUP BY t.id ORDER BY total_amount DESC';
$teachRows = DB::fetchAll($teachSql, $params);

// ── Semesters list for filter ─────────────────────────
$semesters = DB::fetchAll('SELECT * FROM semesters ORDER BY year DESC, semester DESC');

json_ok([
    'kpi'         => $kpi,
    'by_dept'     => $deptRows,
    'by_period'   => $periodRows,
    'by_teacher'  => $teachRows,
    'semesters'   => $semesters,
    'current_sem' => $semId,
]);
