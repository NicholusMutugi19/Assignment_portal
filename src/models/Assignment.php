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

        if (PORTAL_EXTENSIONS_ENABLED) {
            $sql = 'INSERT INTO assignments
                        (course_id, lecturer_id, title, description, attachment_path, attachment_name,
                         max_score, deadline, allow_late, late_penalty, status, assignment_type, tutor_name)
                    VALUES
                        (:course_id, :lecturer_id, :title, :description, :attachment_path, :attachment_name,
                         :max_score, :deadline, :allow_late, :late_penalty, :status, \'upload\', :tutor_name)';
        }

        $params = [
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
        ];
        if (PORTAL_EXTENSIONS_ENABLED) $params[':tutor_name'] = $data['tutor_name'] ?? null;
        Database::query($sql, $params);

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

    public static function isAvailableToStudent(int $assignmentId, int $studentId): bool
    {
        $eligibility = EDUCATION_COURSE_TARGETING_ENABLED
            ? " AND (student.education_level IS NULL
                OR c.audience = 'both'
                OR (student.education_level = 'campus' AND c.audience = 'campus_only')
                OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))"
            : '';
        $paidOnly = PORTAL_EXTENSIONS_ENABLED ? " AND (COALESCE(c.price, 0) = 0 OR EXISTS (
            SELECT 1 FROM payments paid WHERE paid.student_id = :paid_student_id AND paid.course_id = c.id AND paid.payment_status = 'success'
        ))" : '';
        $application = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status = 'approved'" : '';
        $row = Database::query(
            "SELECT 1 FROM assignments a
             JOIN courses c ON c.id = a.course_id
             JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
             JOIN users student ON student.id = e.student_id
             WHERE a.id = :assignment_id" . $eligibility . $application . $paidOnly . ' LIMIT 1',
            PORTAL_EXTENSIONS_ENABLED
                ? [':student_id' => $studentId, ':assignment_id' => $assignmentId, ':paid_student_id' => $studentId]
                : [':student_id' => $studentId, ':assignment_id' => $assignmentId]
        )->fetch();
            return (bool)$row; // Confirm enrollment application approval is required for access
    }

    public static function isOnline(int $assignmentId): bool
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return false;
        return (bool)Database::query(
            "SELECT id FROM assignments WHERE id = :id AND assignment_type = 'online'",
            [':id' => $assignmentId]
        )->fetch();
    }

    /** All assignments for a lecturer, with submission counts */
    public static function forLecturer(int $lecturerId): array
    {
        $extraColumns = PORTAL_EXTENSIONS_ENABLED ? ', a.assignment_type, a.tutor_name' : '';
        $groupExtra = PORTAL_EXTENSIONS_ENABLED ? ', a.assignment_type, a.tutor_name' : '';
        $courseFilter = PORTAL_EXTENSIONS_ENABLED
            ? 'c.lecturer_id = :lid OR c.tutor_id = :lid2 OR EXISTS (SELECT 1 FROM course_teaching_assignments cta WHERE cta.course_id = c.id AND cta.lecturer_id = :assigned_lid)'
            : 'c.lecturer_id = :lid';
        $params = PORTAL_EXTENSIONS_ENABLED
            ? [':lid' => $lecturerId, ':lid2' => $lecturerId, ':assigned_lid' => $lecturerId]
            : [':lid' => $lecturerId];
        return Database::query(
            'SELECT a.id, a.course_id, a.lecturer_id, a.title, a.description' . $extraColumns . ',
                    a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                    a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                    c.title  AS course_title,
                    c.code   AS course_code,
                    COUNT(s.id) AS total_submissions,
                    SUM(CASE WHEN s.score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
             FROM   assignments a
             JOIN   courses     c ON c.id = a.course_id
             LEFT JOIN submissions s ON s.assignment_id = a.id
             WHERE  ' . $courseFilter . '
             GROUP  BY a.id, a.course_id, a.lecturer_id, a.title, a.description,
                      a.attachment_path, a.attachment_name, a.max_score, a.deadline,
                      a.allow_late, a.late_penalty, a.status, a.created_at, a.updated_at,
                      c.title, c.code' . $groupExtra . '
             ORDER  BY a.deadline DESC',
            $params
        )->fetchAll();
    }

    /** Assignments available to a student (enrolled courses), with submission status */
    public static function forStudent(int $studentId): array
    {
        $eligibilityFilter = EDUCATION_COURSE_TARGETING_ENABLED
            ? " AND (student.education_level IS NULL
                OR c.audience = 'both'
                OR (student.education_level = 'campus' AND c.audience = 'campus_only')
                OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))"
            : '';
        $paymentAccess = PORTAL_EXTENSIONS_ENABLED ? " AND (COALESCE(c.price, 0) = 0 OR EXISTS (
            SELECT 1 FROM payments paid WHERE paid.student_id = :payment_student_id AND paid.course_id = c.id AND paid.payment_status = 'success'
        ))" : '';
        $typeColumn = PORTAL_EXTENSIONS_ENABLED ? ', a.assignment_type' : '';
        $studentAccess = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status = 'approved'" : '';
        return Database::query(
            "SELECT a.id, a.course_id, a.lecturer_id, a.title, a.description" . $typeColumn . ",
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
                        WHEN s.id IS NOT NULL                        THEN 'submitted'
                        WHEN a.deadline < NOW() AND a.allow_late = 0 THEN 'closed'
                        WHEN a.deadline < NOW() AND a.allow_late = 1 THEN 'late'
                        ELSE 'pending'
                    END AS display_status
             FROM   assignments   a
             JOIN   courses       c ON c.id  = a.course_id
             JOIN   enrollments   e ON e.course_id = a.course_id AND e.student_id = :sid
             JOIN   users         student ON student.id = e.student_id
             JOIN   users         u ON u.id  = a.lecturer_id
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :sid2
             WHERE  a.status != 'draft'" . $eligibilityFilter . $studentAccess . $paymentAccess . '
             ORDER  BY a.deadline ASC',
            PORTAL_EXTENSIONS_ENABLED
                ? [':sid' => $studentId, ':sid2' => $studentId, ':payment_student_id' => $studentId]
                : [':sid' => $studentId, ':sid2' => $studentId]
        )->fetchAll();
    }

    public static function managedBy(int $userId): array
    {
        $tutorFilter = PORTAL_EXTENSIONS_ENABLED
            ? 'c.tutor_id = :tutor_id OR EXISTS (SELECT 1 FROM course_teaching_assignments cta WHERE cta.course_id = c.id AND cta.lecturer_id = :assigned_id)'
            : 'c.lecturer_id = :tutor_id';
        $params = PORTAL_EXTENSIONS_ENABLED
            ? [':lecturer_id' => $userId, ':tutor_id' => $userId, ':assigned_id' => $userId]
            : [':lecturer_id' => $userId, ':tutor_id' => $userId];
        return Database::query(
            'SELECT a.*, c.title AS course_title, c.code AS course_code,
                    COUNT(s.id) AS total_submissions,
                    SUM(CASE WHEN s.score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
             FROM assignments a JOIN courses c ON c.id = a.course_id
             LEFT JOIN submissions s ON s.assignment_id = a.id
             WHERE c.lecturer_id = :lecturer_id OR ' . $tutorFilter . '
             GROUP BY a.id ORDER BY a.created_at DESC',
            $params
        )->fetchAll();
    }

    public static function forCourse(int $courseId): array
    {
        $extraColumns = PORTAL_EXTENSIONS_ENABLED ? ', a.assignment_type, a.tutor_name' : '';
        $groupExtra = PORTAL_EXTENSIONS_ENABLED ? ', a.assignment_type, a.tutor_name' : '';
        return Database::query(
            'SELECT a.id, a.course_id, a.lecturer_id, a.title, a.description' . $extraColumns . ',
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
                      c.title, c.code, u.name' . $groupExtra . '
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
