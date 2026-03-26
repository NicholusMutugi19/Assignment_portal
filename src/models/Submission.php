<?php
/**
 * Submission Model
 * assignment_portal/src/models/Submission.php
 */

require_once __DIR__ . '/../config/Database.php';

class Submission
{
    // ------------------------------------------------------------------ //
    //  CREATE
    // ------------------------------------------------------------------ //
    public static function create(array $data): int
    {
        $sql = 'INSERT INTO submissions
                    (assignment_id, student_id, file_path, original_name,
                     file_size, mime_type, comment, is_late)
                VALUES
                    (:assignment_id, :student_id, :file_path, :original_name,
                     :file_size, :mime_type, :comment, :is_late)';

        Database::query($sql, [
            ':assignment_id' => $data['assignment_id'],
            ':student_id'    => $data['student_id'],
            ':file_path'     => $data['file_path'],
            ':original_name' => $data['original_name'],
            ':file_size'     => $data['file_size'],
            ':mime_type'     => $data['mime_type'],
            ':comment'       => $data['comment'] ?? null,
            ':is_late'       => $data['is_late']  ?? 0,
        ]);

        return (int) Database::getInstance()->lastInsertId();
    }

    // ------------------------------------------------------------------ //
    //  READ
    // ------------------------------------------------------------------ //
    public static function findById(int $id): ?array
    {
        $row = Database::query(
            'SELECT s.*,
                    u.name  AS student_name,
                    u.email AS student_email,
                    a.title AS assignment_title,
                    a.max_score,
                    a.deadline,
                    c.title AS course_title,
                    c.code  AS course_code
             FROM   submissions s
             JOIN   users       u ON u.id = s.student_id
             JOIN   assignments a ON a.id = s.assignment_id
             JOIN   courses     c ON c.id = a.course_id
             WHERE  s.id = :id',
            [':id' => $id]
        )->fetch();

        return $row ?: null;
    }

    public static function existsByStudentAndAssignment(int $studentId, int $assignmentId): bool
    {
        $row = Database::query(
            'SELECT id FROM submissions WHERE student_id = :sid AND assignment_id = :aid',
            [':sid' => $studentId, ':aid' => $assignmentId]
        )->fetch();
        return (bool) $row;
    }

    /** All submissions for a given assignment (lecturer view) */
    public static function forAssignment(int $assignmentId): array
    {
        return Database::query(
            'SELECT s.*,
                    u.name  AS student_name,
                    u.email AS student_email
             FROM   submissions s
             JOIN   users       u ON u.id = s.student_id
             WHERE  s.assignment_id = :aid
             ORDER  BY s.submitted_at DESC',
            [':aid' => $assignmentId]
        )->fetchAll();
    }

    /** All submissions by a student */
    public static function forStudent(int $studentId): array
    {
        return Database::query(
            'SELECT s.*,
                    a.title     AS assignment_title,
                    a.max_score,
                    a.deadline,
                    c.title     AS course_title,
                    c.code      AS course_code
             FROM   submissions s
             JOIN   assignments a ON a.id = s.assignment_id
             JOIN   courses     c ON c.id = a.course_id
             WHERE  s.student_id = :sid
             ORDER  BY s.submitted_at DESC',
            [':sid' => $studentId]
        )->fetchAll();
    }

    /** All submissions for assignments taught by a lecturer */
    public static function forLecturer(int $lecturerId): array
    {
        return Database::query(
            'SELECT s.*,
                    u.name  AS student_name,
                    u.email AS student_email,
                    a.title AS assignment_title,
                    a.max_score,
                    a.deadline,
                    c.title AS course_title,
                    c.code  AS course_code
             FROM   submissions s
             JOIN   users       u ON u.id = s.student_id
             JOIN   assignments a ON a.id = s.assignment_id
             JOIN   courses     c ON c.id = a.course_id
             WHERE  a.lecturer_id = :lid
             ORDER  BY s.submitted_at DESC',
            [':lid' => $lecturerId]
        )->fetchAll();
    }

    public static function statsForLecturer(int $lecturerId): array
    {
        $row = Database::query(
            'SELECT
                COUNT(*)                                          AS total,
                SUM(CASE WHEN s.score IS NOT NULL THEN 1 ELSE 0 END) AS graded,
                SUM(s.is_late)                                   AS late_count,
                AVG(CASE WHEN s.score IS NOT NULL THEN s.score END)   AS avg_score
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE a.lecturer_id = :lid',
            [':lid' => $lecturerId]
        )->fetch();
        return $row;
    }

    // ------------------------------------------------------------------ //
    //  UPDATE (grade)
    // ------------------------------------------------------------------ //
    public static function grade(int $id, float $score, ?string $feedback, int $gradedBy): bool
    {
        Database::query(
            'UPDATE submissions
             SET    score      = :score,
                    feedback   = :feedback,
                    graded_by  = :graded_by,
                    graded_at  = NOW(),
                    status     = \'graded\'
             WHERE  id = :id',
            [':score' => $score, ':feedback' => $feedback,
             ':graded_by' => $gradedBy, ':id' => $id]
        );
        return true;
    }

    // ------------------------------------------------------------------ //
    //  Stats for lecturer dashboard
    // ------------------------------------------------------------------ //
    public static function statsForAssignment(int $assignmentId): array
    {
        $row = Database::query(
            'SELECT
                COUNT(*)                                          AS total,
                SUM(CASE WHEN score IS NOT NULL THEN 1 ELSE 0 END) AS graded,
                SUM(is_late)                                      AS late_count,
                AVG(CASE WHEN score IS NOT NULL THEN score END)   AS avg_score
             FROM submissions
             WHERE assignment_id = :aid',
            [':aid' => $assignmentId]
        )->fetch();
        return $row;
    }
}
