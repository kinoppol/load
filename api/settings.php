<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$method  = $_SERVER['REQUEST_METHOD'];
$section = $_GET['section'] ?? '';

// ── GET — เปิดทุก role ─────────────────────────────────
if ($method === 'GET') {
    if ($section === 'makeup_reasons') {
        $rows = DB::fetchAll(
            'SELECT * FROM makeup_reasons ORDER BY sort_order, id'
        );
        json_ok(['reasons' => $rows]);
    }
    if ($section === 'integration') {
        json_ok(['rms_base_url' => get_setting('rms_base_url', 'http://rms.rvc.ac.th')]);
    }
    json_err('ไม่พบส่วนนี้', 404);
}

// ── POST — admin เท่านั้น ──────────────────────────────
if ($method === 'POST') {
    Auth::requireRole('admin');
    $d      = get_input();
    $action = $d['action'] ?? '';

    if ($action === 'add_reason') {
        $label = trim($d['label'] ?? '');
        $code  = trim($d['code']  ?? '');
        if (!$label || !$code) json_err('กรุณากรอกชื่อและรหัสเหตุผล');
        if (DB::fetch('SELECT id FROM makeup_reasons WHERE code=?', [$code])) {
            json_err('รหัสนี้มีอยู่แล้ว');
        }
        $id = DB::insert(
            'INSERT INTO makeup_reasons (code,label,icon,color,bg_color,is_deletable,is_active,sort_order)
             VALUES (?,?,?,?,?,1,1,?)',
            [
                $code, $label,
                $d['icon']     ?? '📋',
                $d['color']    ?? '#6B7280',
                $d['bg_color'] ?? '#6B728020',
                (int)($d['sort_order'] ?? 99),
            ]
        );
        json_ok(['id' => $id], 'เพิ่มเหตุผลสำเร็จ');
    }

    if ($action === 'update_reason') {
        $id    = (int)$d['id'];
        $label = trim($d['label'] ?? '');
        if (!$label) json_err('กรุณาระบุชื่อเหตุผล');
        DB::exec(
            'UPDATE makeup_reasons SET label=?,icon=?,color=?,bg_color=?,sort_order=? WHERE id=?',
            [
                $label,
                $d['icon']       ?? '📋',
                $d['color']      ?? '#6B7280',
                $d['bg_color']   ?? '#6B728020',
                (int)($d['sort_order'] ?? 0),
                $id,
            ]
        );
        json_ok(null, 'บันทึกสำเร็จ');
    }

    if ($action === 'toggle_reason') {
        $id = (int)$d['id'];
        DB::exec(
            'UPDATE makeup_reasons SET is_active = IF(is_active=1,0,1) WHERE id=?',
            [$id]
        );
        json_ok(null, 'เปลี่ยนสถานะสำเร็จ');
    }

    if ($action === 'save_rms_url') {
        $url = trim($d['rms_base_url'] ?? '');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            json_err('กรุณาระบุ URL ที่ถูกต้อง (ขึ้นต้นด้วย http:// หรือ https://)');
        }
        set_setting('rms_base_url', rtrim($url, '/'));
        json_ok(null, 'บันทึก URL ระบบ RMS สำเร็จ');
    }

    if ($action === 'delete_reason') {
        $id     = (int)$d['id'];
        $reason = DB::fetch('SELECT * FROM makeup_reasons WHERE id=?', [$id]);
        if (!$reason) json_err('ไม่พบข้อมูล');
        if (!$reason['is_deletable']) json_err('ไม่สามารถลบเหตุผลนี้ได้ (เป็นค่าเริ่มต้นของระบบ)');
        if (DB::fetch('SELECT id FROM makeup_records WHERE reason=? LIMIT 1', [$reason['code']])) {
            json_err('ไม่สามารถลบได้ เนื่องจากมีรายการสอนชดเชยที่ใช้เหตุผลนี้อยู่');
        }
        DB::exec('DELETE FROM makeup_reasons WHERE id=?', [$id]);
        json_ok(null, 'ลบสำเร็จ');
    }
}
