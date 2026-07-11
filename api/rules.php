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
$semId  = (int)$sem['id'];

if ($method === 'GET') {
    $rules = DB::fetch('SELECT * FROM compensation_rules WHERE semester_id=?', [$semId]);
    $rates = DB::fetchAll('SELECT * FROM teaching_rates WHERE semester_id=? ORDER BY level', [$semId]);
    json_ok(['rules' => $rules, 'rates' => $rates]);
}

if ($method === 'POST') {
    Auth::requireRole('admin','director','curriculum');
    $d = get_input();

    // upsert compensation_rules
    $existing = DB::fetch('SELECT id FROM compensation_rules WHERE semester_id=?', [$semId]);
    if ($existing) {
        DB::exec(
            'UPDATE compensation_rules SET normal_load=?,max_claimable=?,min_students=?,
             per_head_rate=?,holiday_rule=? WHERE semester_id=?',
            [
                (int)($d['normal_load']   ?? 18),
                (int)($d['max_claimable'] ?? 10),
                (int)($d['min_students']  ?? 25),
                (float)($d['per_head_rate'] ?? 20),
                $d['holiday_rule'] ?? 'proportional',
                $semId,
            ]
        );
    } else {
        DB::insert(
            'INSERT INTO compensation_rules (semester_id,normal_load,max_claimable,min_students,per_head_rate,holiday_rule)
             VALUES (?,?,?,?,?,?)',
            [$semId,(int)($d['normal_load']??18),(int)($d['max_claimable']??10),
             (int)($d['min_students']??25),(float)($d['per_head_rate']??20),
             $d['holiday_rule']??'proportional']
        );
    }

    // upsert teaching_rates
    if (!empty($d['rates']) && is_array($d['rates'])) {
        foreach ($d['rates'] as $r) {
            DB::exec(
                'INSERT INTO teaching_rates (semester_id,level,level_name,rate_per_hour,is_enabled)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE rate_per_hour=VALUES(rate_per_hour), is_enabled=VALUES(is_enabled)',
                [$semId, $r['level'], $r['level_name'], (float)$r['rate_per_hour'], (int)($r['is_enabled']??1)]
            );
        }
    }

    json_ok(null, 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
}
