<?php
/**
 * Authentication Middleware
 * assignment_portal/src/middleware/Auth.php
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }

    public static function requireLogin(string $redirectTo = '../auth/login.php'): void
    {
        self::start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireRole(string $role, string $redirectTo = '../auth/login.php'): void
    {
        self::requireLogin($redirectTo);
        if ($_SESSION['user_role'] !== $role) {
            header('Location: ' . $redirectTo . '?error=unauthorized');
            exit;
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): array
    {
        self::start();
        return [
            'id'    => $_SESSION['user_id']    ?? null,
            'name'  => $_SESSION['user_name']  ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role'  => $_SESSION['user_role']  ?? '',
        ];
    }

    public static function isLoggedIn(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function hasSelectedCourses(): bool
    {
        $user = self::user();
        if (empty($user['id'])) return false;

        require_once __DIR__ . '/../models/User.php';

        if ($user['role'] === 'student') {
            // Check if student has any enrollments
            $enrollments = User::enrolledCourses((int)$user['id']);
            return !empty($enrollments);
        } elseif ($user['role'] === 'lecturer') {
            // Check if lecturer has any courses they're teaching
            $courses = User::taughtCourses((int)$user['id']);
            return !empty($courses);
        }

        return true; // Admin or other roles
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        self::start();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
