<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireApi();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$user   = Auth::user();

if ($method === 'GET') {
    $rows = DB::fetchAll(
        'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20',
        [$user['id']]
    );
    $unread = (int)(DB::fetch(
        'SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0',
        [$user['id']]
    )['c'] ?? 0);
    json_ok(['notifications' => $rows, 'unread' => $unread]);
}

if ($method === 'POST') {
    $d      = get_input();
    $action = $d['action'] ?? '';

    if ($action === 'mark_read') {
        DB::exec('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?',
                 [(int)$d['id'], $user['id']]);
        json_ok(null);
    }
    if ($action === 'mark_all_read') {
        DB::exec('UPDATE notifications SET is_read=1 WHERE user_id=?', [$user['id']]);
        json_ok(null);
    }
}
