<?php
/**
 * Assignment Model – CRUD + deadline logic
 * assignment_portal/src/models/Assignment.php
 */

require_once __DIR__ . '/../config/Database.php';

class Assignment
{
    // ------------------------------------------------------------------ //
    //  CREATE
    // ------------------------------------------------------------------ //
    public static function create(array $data): int
    {
        $sql = 'INSERT INTO assignments
                    (course_id, lecturer_id, title, description,
                     attachment_path, attachment_name,
                     max_score, deadline, allow_late, late_penalty, status)
                VALUES
                    (:course_id, :lecturer_id, :title, :description,
                     :attachment_path, :attachment_name,
                     :max_score, :deadline, :allow_late, :late_penalty, :status)';

        Database::query($sql, [
            ':course_id'       => $data['course_id'],
            ':lecturer_id'     => $data['lecturer_id'],
            ':title'           => $data['title'],
            ':description'     => $data['description'],
            ':attachment_path' => $data['attachment_path'] ?? null,
            ':attachment_name' => $data['attachment_name'] ?? null,
            ':max_score'       => $data['max_score']   ?? 100,
            ':deadline'        => $data['deadline'],
            ':allow_late'      => $data['allow_late']  ?? 0,
            ':late_penalty'    => $data['late_penalty'] ?? 0,
            ':status'          => $data['status']      ?? 'published',
        ]);

        return (int) Database::getInstance()->lastInsertId();
    }

    // ------------------------------------------------------------------ //
    //  READ
    // ------------------------------------------------------------------ //
    public static function findById(int $id): ?array
    {
        $row = Database::query(
            'SELECT a.*, c.title AS course_title, c.code AS course_code,
                    u.name AS lecturer_name
             FROM   assignments a
             JOIN   courses c ON c.id = a.course_id
             JOIN   users   u ON u.id = a.lecturer_id
             WHERE  a.id = :id',
            [':id' => $id]
        )->fetch();

        return $row ?: null;
    }

    /** All assignments for a lecturer, with submission counts */
    public static function forLecturer(int $lecturerId): array
    {
        return Database::query(
            'SELECT a.id, a.course_id, a.lecturer_id, a.title, a.description,
                    a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                    a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                    c.title  AS course_title,
                    c.code   AS course_code,
                    COUNT(s.id) AS total_submissions,
                    SUM(CASE WHEN s.score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
             FROM   assignments a
             JOIN   courses     c ON c.id = a.course_id
             LEFT JOIN submissions s ON s.assignment_id = a.id
             WHERE  a.lecturer_id = :lid
             GROUP  BY a.id, a.course_id, a.lecturer_id, a.title, a.description,
                      a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                      a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                      c.title, c.code
             ORDER  BY a.deadline DESC',
            [':lid' => $lecturerId]
        )->fetchAll();
    }

    /** Assignments available to a student (enrolled courses), with submission status */
    public static function forStudent(int $studentId): array
    {
        return Database::query(
            'SELECT a.id, a.course_id, a.lecturer_id, a.title, a.description,
                    a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                    a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                    c.title  AS course_title,
                    c.code   AS course_code,
                    u.name   AS lecturer_name,
                    s.id     AS submission_id,
                    s.status AS submission_status,
                    s.score  AS submission_score,
                    s.is_late,
                    s.submitted_at,
                    CASE
                        WHEN s.id IS NOT NULL                        THEN "submitted"
                        WHEN a.deadline < NOW() AND a.allow_late = 0 THEN "closed"
                        WHEN a.deadline < NOW() AND a.allow_late = 1 THEN "late"
                        ELSE "pending"
                    END AS display_status
             FROM   assignments   a
             JOIN   courses       c ON c.id  = a.course_id
             JOIN   enrollments   e ON e.course_id = a.course_id AND e.student_id = :sid
             JOIN   users         u ON u.id  = a.lecturer_id
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :sid2
             WHERE  a.status != "draft"
             ORDER  BY a.deadline ASC',
            [':sid' => $studentId, ':sid2' => $studentId]
        )->fetchAll();
    }

    public static function forCourse(int $courseId): array
    {
        return Database::query(
            'SELECT a.id, a.course_id, a.lecturer_id, a.title, a.description,
                    a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                    a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                    c.title AS course_title, c.code AS course_code,
                    u.name AS lecturer_name,
                    COUNT(s.id) AS total_submissions,
                    COUNT(CASE WHEN s.score IS NOT NULL THEN 1 END) AS graded_count
             FROM   assignments a
             JOIN   courses      c ON c.id = a.course_id
             JOIN   users        u ON u.id = a.lecturer_id
             LEFT JOIN submissions s ON s.assignment_id = a.id
             WHERE  a.course_id = :cid
             GROUP  BY a.id, a.course_id, a.lecturer_id, a.title, a.description,
                      a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                      a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                      c.title, c.code, u.name
             ORDER  BY a.created_at DESC',
            [':cid' => $courseId]
        )->fetchAll();
    }

    // ------------------------------------------------------------------ //
    //  UPDATE
    // ------------------------------------------------------------------ //
    public static function update(int $id, array $data): bool
    {
        $sql = 'UPDATE assignments
                SET    title       = :title,
                       description = :description,
                       max_score   = :max_score,
                       deadline    = :deadline,
                       allow_late  = :allow_late,
                       late_penalty= :late_penalty,
                       status      = :status
                WHERE  id = :id';

        Database::query($sql, [
            ':title'       => $data['title'],
            ':description' => $data['description'],
            ':max_score'   => $data['max_score'],
            ':deadline'    => $data['deadline'],
            ':allow_late'  => $data['allow_late'],
            ':late_penalty'=> $data['late_penalty'],
            ':status'      => $data['status'],
            ':id'          => $id,
        ]);
        return true;
    }

    // ------------------------------------------------------------------ //
    //  Deadline helpers
    // ------------------------------------------------------------------ //
    public static function isPastDeadline(array $assignment): bool
    {
        return strtotime($assignment['deadline']) < time();
    }

    public static function isAcceptingSubmissions(array $assignment): bool
    {
        if ($assignment['status'] !== 'published') return false;
        if (!self::isPastDeadline($assignment))    return true;   // before deadline
        return (bool) $assignment['allow_late'];                   // past deadline but late allowed
    }

    public static function timeRemaining(array $assignment): string
    {
        $diff = strtotime($assignment['deadline']) - time();
        if ($diff <= 0) return 'Deadline passed';
        $days  = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $mins  = floor(($diff % 3600)  / 60);
        if ($days > 0)  return "{$days}d {$hours}h remaining";
        if ($hours > 0) return "{$hours}h {$mins}m remaining";
        return "{$mins}m remaining";
    }
}
