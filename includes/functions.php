<?php
declare(strict_types=1);

function json_ok(mixed $data = null, string $message = 'OK'): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message],
                     JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message = 'Error', int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message],
                     JSON_UNESCAPED_UNICODE);
    exit;
}

function get_input(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? $_POST;
}

function thai_date(string $date): string
{
    if (!$date) return '-';
    $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
               'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($date);
    $d  = (int) date('j', $ts);
    $m  = (int) date('n', $ts);
    $y  = (int) date('Y', $ts) + 543;
    return "{$d} {$months[$m]} {$y}";
}

function format_baht(float $amount): string
{
    return '฿' . number_format($amount, 0, '.', ',');
}

function get_current_semester(): ?array
{
    return DB::fetch('SELECT * FROM semesters WHERE is_current = 1 LIMIT 1');
}

function get_setting(string $key, ?string $default = null): ?string
{
    $row = DB::fetch('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
    return $row['setting_value'] ?? $default;
}

function set_setting(string $key, string $value): void
{
    DB::exec(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}

/**
 * ดึงข้อมูล JSON จากระบบ RMS — host จาก app_settings, ต่อ query ที่ให้มา
 * ตัวอย่าง $query: 'data=std2018_student&count=yes'
 * คืน array ที่ decode แล้ว หรือ json_err (exit) เมื่อผิดพลาด
 */
function rms_fetch_json(string $query): array
{
    $base = rtrim((string)get_setting('rms_base_url', 'http://rms.rvc.ac.th'), '/');
    if ($base === '') json_err('ยังไม่ได้ตั้งค่า URL ของระบบ RMS');
    $url = $base . '/api_connection.php?app_name=nutty&' . ltrim($query, '&');

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) json_err('เชื่อมต่อ RMS ไม่สำเร็จ: ' . $err);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 60]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) json_err('เชื่อมต่อ RMS ไม่สำเร็จ');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) json_err('ข้อมูลจาก RMS ไม่อยู่ในรูปแบบ JSON ที่ถูกต้อง');
    return $data;
}

function get_teacher_by_user(int $userId): ?array
{
    return DB::fetch('SELECT * FROM teachers WHERE user_id = ? AND is_active = 1', [$userId]);
}

function calculate_amount(
    int $totalPeriods,
    int $normalLoad,
    int $maxClaimable,
    float $ratePerHour,
    int $students,
    int $minStudents,
    float $perHeadRate
): array {
    $over = max(0, $totalPeriods - $normalLoad);
    $over = min($over, $maxClaimable);
    $amount = $over > 0
        ? ($students >= $minStudents
            ? $over * $ratePerHour
            : $over * $students * $perHeadRate)
        : 0.0;
    return [
        'over_periods' => $over,
        'amount'       => round($amount, 2),
    ];
}

function week_number_in_semester(string $date, string $semStart): int
{
    $d = new DateTime($date);
    $s = new DateTime($semStart);
    $diff = $d->diff($s);
    return (int) floor($diff->days / 7) + 1;
}
