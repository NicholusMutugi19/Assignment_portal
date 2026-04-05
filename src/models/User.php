<?php
/**
 * User Model
 * assignment_portal/src/models/User.php
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/ErrorHandler.php';

class User
{
    public static function findByEmail(string $email): ?array
    {
        try {
            $row = Database::query(
                'SELECT * FROM users WHERE email = :email',
                [':email' => $email]
            )->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            ErrorHandler::handle($e);
            return null;
        }
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
        try {
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
        } catch (PDOException $e) {
            throw new Exception(ErrorHandler::handle($e, 'Failed to create account. Please try again.'));
        }
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Courses for a student (enrolled) */
    public static function enrolledCourses(int $studentId): array
    {
        try {
            return Database::query(
                'SELECT c.*, u.name AS lecturer_name
                 FROM   courses     c
                 JOIN   enrollments e ON e.course_id = c.id
                 JOIN   users       u ON u.id = c.lecturer_id
                 WHERE  e.student_id = :sid',
                [':sid' => $studentId]
            )->fetchAll();
        } catch (PDOException $e) {
            ErrorHandler::handle($e);
            return [];
        }
    }

    /** Courses taught by a lecturer */
    public static function taughtCourses(int $lecturerId): array
    {
        try {
            return Database::query(
                'SELECT c.*,
                        COUNT(DISTINCT e.student_id) AS student_count
                 FROM   courses     c
                 LEFT JOIN enrollments e ON e.course_id = c.id
                 WHERE  c.lecturer_id = :lid
                 GROUP  BY c.id',
                [':lid' => $lecturerId]
            )->fetchAll();
        } catch (PDOException $e) {
            ErrorHandler::handle($e);
            return [];
        }
    }
}
