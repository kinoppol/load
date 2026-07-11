<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params(SESSION_LIFETIME, '/');
            session_start();
        }
    }

    public static function login(string $username, string $password): array
    {
        $user = DB::fetch(
            'SELECT u.*, d.name AS dept_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.username = ? AND u.is_active = 1',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        DB::exec(
            'UPDATE users SET last_login = NOW() WHERE id = ?',
            [$user['id']]
        );

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['dept_name'] = $user['dept_name'] ?? '';
        $_SESSION['dept_id']   = $user['department_id'];
        session_regenerate_id(true);

        return ['success' => true, 'role' => $user['role']];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }

    public static function requireApi(): void
    {
        if (!self::check()) {
            self::jsonError('Unauthorized', 401);
        }
    }

    public static function can(string ...$roles): bool
    {
        return in_array($_SESSION['role'] ?? '', $roles, true);
    }

    public static function requireRole(string ...$roles): void
    {
        if (!self::can(...$roles)) {
            self::jsonError('Forbidden', 403);
        }
    }

    public static function user(): array
    {
        return [
            'id'        => $_SESSION['user_id']   ?? 0,
            'username'  => $_SESSION['username']  ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role'      => $_SESSION['role']      ?? '',
            'dept_name' => $_SESSION['dept_name'] ?? '',
            'dept_id'   => $_SESSION['dept_id']   ?? null,
        ];
    }

    private static function jsonError(string $msg, int $code): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}
