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

if ($method === 'GET') {
    $type = $_GET['type'] ?? 'makeup'; // makeup | substitute

    if ($type === 'makeup') {
        $sql = 'SELECT m.*, t.full_name, d.name AS dept_name,
                       u.full_name AS approved_by_name
                FROM makeup_records m
                JOIN teachers t ON t.id=m.teacher_id
                JOIN departments d ON d.id=t.department_id
                LEFT JOIN users u ON u.id=m.approved_by
                WHERE m.semester_id=?';
        $params = [$semId];
        if (Auth::can('teacher')) {
            $myT = get_teacher_by_user($user['id']);
            if ($myT) { $sql .= ' AND m.teacher_id=?'; $params[] = $myT['id']; }
        }
        $sql .= ' ORDER BY m.created_at DESC';
        $rows = DB::fetchAll($sql, $params);
        json_ok(['records' => $rows]);
    }

    if ($type === 'substitute') {
        $sql = 'SELECT s.*,
                       ta.full_name AS absent_name, da.name AS absent_dept,
                       ts.full_name AS sub_name,    ds.name AS sub_dept,
                       u.full_name  AS approved_by_name
                FROM substitute_records s
                JOIN teachers ta ON ta.id=s.absent_teacher_id
                JOIN departments da ON da.id=ta.department_id
                JOIN teachers ts ON ts.id=s.sub_teacher_id
                JOIN departments ds ON ds.id=ts.department_id
                LEFT JOIN users u ON u.id=s.approved_by
                WHERE s.semester_id=?';
        $params = [$semId];
        if (Auth::can('teacher')) {
            $myT = get_teacher_by_user($user['id']);
            if ($myT) {
                $sql .= ' AND (s.absent_teacher_id=? OR s.sub_teacher_id=?)';
                $params[] = $myT['id']; $params[] = $myT['id'];
            }
        }
        $sql .= ' ORDER BY s.created_at DESC';
        $rows = DB::fetchAll($sql, $params);
        json_ok(['records' => $rows]);
    }
}

if ($method === 'POST') {
    $d      = get_input();
    $action = $d['action'] ?? '';

    if ($action === 'add_makeup') {
        Auth::requireRole('admin','curriculum','teacher');
        $id = DB::insert(
            'INSERT INTO makeup_records
             (teacher_id,semester_id,subject,group_name,reason,missed_date,makeup_date,start_time,end_time,periods)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                (int)$d['teacher_id'], $semId,
                $d['subject']??'', $d['group_name']??'',
                $d['reason']??'holiday',
                $d['missed_date'], $d['makeup_date'],
                $d['start_time'], $d['end_time'],
                (int)$d['periods'],
            ]
        );
        json_ok(['id'=>$id],'บันทึกรายการสอนชดเชยสำเร็จ');
    }

    if ($action === 'add_substitute') {
        Auth::requireRole('admin','curriculum','teacher');
        $id = DB::insert(
            'INSERT INTO substitute_records
             (absent_teacher_id,sub_teacher_id,semester_id,subject,group_name,absent_date,periods,note)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                (int)$d['absent_teacher_id'], (int)$d['sub_teacher_id'],
                $semId, $d['subject']??'', $d['group_name']??'',
                $d['absent_date'], (int)$d['periods'], $d['note']??null,
            ]
        );
        json_ok(['id'=>$id],'บันทึกรายการสอนแทนสำเร็จ');
    }

    if ($action === 'approve_makeup') {
        Auth::requireRole('admin','director','curriculum');
        DB::exec(
            "UPDATE makeup_records SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?",
            [$user['id'], (int)$d['id']]
        );
        json_ok(null,'อนุมัติสำเร็จ');
    }

    if ($action === 'approve_substitute') {
        Auth::requireRole('admin','director','curriculum');
        DB::exec(
            "UPDATE substitute_records SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?",
            [$user['id'], (int)$d['id']]
        );
        json_ok(null,'อนุมัติสำเร็จ');
    }

    if ($action === 'delete_makeup') {
        DB::exec('DELETE FROM makeup_records WHERE id=?', [(int)$d['id']]);
        json_ok(null,'ลบสำเร็จ');
    }

    if ($action === 'delete_substitute') {
        DB::exec('DELETE FROM substitute_records WHERE id=?', [(int)$d['id']]);
        json_ok(null,'ลบสำเร็จ');
    }
}
