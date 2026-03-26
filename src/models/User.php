<?php
/**
 * User Model
 * assignment_portal/src/models/User.php
 */

require_once __DIR__ . '/../config/Database.php';

class User
{
    public static function findByEmail(string $email): ?array
    {
        $row = Database::query(
            'SELECT * FROM users WHERE email = :email',
            [':email' => $email]
        )->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $row = Database::query(
            'SELECT id, name, email, role, created_at FROM users WHERE id = :id',
            [':id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)',
            [
                ':name'     => $data['name'],
                ':email'    => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':role'     => $data['role'] ?? 'student',
            ]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Courses for a student (enrolled) */
    public static function enrolledCourses(int $studentId): array
    {
        return Database::query(
            'SELECT c.*, u.name AS lecturer_name
             FROM   courses     c
             JOIN   enrollments e ON e.course_id = c.id
             JOIN   users       u ON u.id = c.lecturer_id
             WHERE  e.student_id = :sid',
            [':sid' => $studentId]
        )->fetchAll();
    }

    /** Courses taught by a lecturer */
    public static function taughtCourses(int $lecturerId): array
    {
        return Database::query(
            'SELECT c.*,
                    COUNT(DISTINCT e.student_id) AS student_count
             FROM   courses     c
             LEFT JOIN enrollments e ON e.course_id = c.id
             WHERE  c.lecturer_id = :lid
             GROUP  BY c.id',
            [':lid' => $lecturerId]
        )->fetchAll();
    }
}
