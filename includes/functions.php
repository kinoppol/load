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
