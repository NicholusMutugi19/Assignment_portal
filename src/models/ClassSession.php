<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/User.php';

class ClassSession
{
    public static function create(int $courseId, int $ownerId, array $data): int
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Class sessions are disabled until the feature migration is enabled.');
        if (!User::canManageCourse($ownerId, $courseId)) throw new RuntimeException('Course access denied.');
        $url = filter_var($data['meet_link'], FILTER_VALIDATE_URL);
        $host = strtolower((string)parse_url((string)$url, PHP_URL_HOST));
        if (!$url || strtolower((string)parse_url((string)$url, PHP_URL_SCHEME)) !== 'https'
            || !in_array($host, ['meet.google.com', 'www.meet.google.com'], true)) {
            throw new InvalidArgumentException('Enter a valid Google Meet URL.');
        }
        $lecturerId = PORTAL_EXTENSIONS_ENABLED
            ? (int)Database::query('SELECT lecturer_id FROM courses WHERE id = :id', [':id' => $courseId])->fetchColumn()
            : $ownerId;
        if (!PORTAL_EXTENSIONS_ENABLED) {
            $data['tutor_name'] = null;
        }
        $sessionSql = PORTAL_EXTENSIONS_ENABLED
            ? "INSERT INTO class_sessions (course_id, lecturer_id, tutor_name, meet_link, scheduled_at, title, description)
               VALUES (:course_id, :owner_id, :tutor_name, :meet_link, :scheduled_at, :title, :description)"
            : "INSERT INTO class_sessions (course_id, lecturer_id, meet_link, scheduled_at, title, description)
               VALUES (:course_id, :owner_id, :meet_link, :scheduled_at, :title, :description)";
        $params = [
                ':course_id' => $courseId, ':owner_id' => $lecturerId,
                ':meet_link' => $url,
                ':scheduled_at' => $data['scheduled_at'],
                ':title' => trim($data['title']),
                ':description' => trim($data['description']) ?: null,
            ];
        if (PORTAL_EXTENSIONS_ENABLED) $params[':tutor_name'] = trim($data['tutor_name']) ?: null;
        Database::query($sessionSql, $params);
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function forManager(int $ownerId): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        $tutorColumn = PORTAL_EXTENSIONS_ENABLED
            ? 'c.tutor_id = :tutor_id OR EXISTS (SELECT 1 FROM course_teaching_assignments cta WHERE cta.course_id = c.id AND cta.lecturer_id = :assigned_id)'
            : 'c.lecturer_id = :tutor_id';
        return PORTAL_EXTENSIONS_ENABLED
            ? Database::query(
                'SELECT s.*, c.code, c.title AS course_title FROM class_sessions s JOIN courses c ON c.id = s.course_id
                 WHERE c.lecturer_id = :lecturer_id OR ' . $tutorColumn . ' ORDER BY s.scheduled_at DESC',
                [':lecturer_id' => $ownerId, ':tutor_id' => $ownerId, ':assigned_id' => $ownerId]
            )->fetchAll()
            : Database::query(
                'SELECT s.*, c.code, c.title AS course_title FROM class_sessions s JOIN courses c ON c.id = s.course_id
                 WHERE c.lecturer_id = :lecturer_id ORDER BY s.scheduled_at DESC',
                [':lecturer_id' => $ownerId]
            )->fetchAll();
    }

    public static function forStudent(int $studentId): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        $audience = EDUCATION_COURSE_TARGETING_ENABLED
            ? " AND (c.audience = 'both' OR (" .
                "EXISTS (SELECT 1 FROM users student WHERE student.id = :level_student_id AND student.education_level = 'campus') AND c.audience = 'campus_only') OR (" .
                "EXISTS (SELECT 1 FROM users student2 WHERE student2.id = :level_student_id2 AND student2.education_level = 'high_school') AND c.audience = 'high_school_only'))"
            : '';
        $application = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status = 'approved'" : '';
        $payment = PORTAL_EXTENSIONS_ENABLED ? " AND (COALESCE(c.price, 0) = 0 OR EXISTS (
            SELECT 1 FROM payments p WHERE p.student_id = :paid_student_id AND p.course_id = c.id AND p.payment_status = 'success'
        ))" : '';
        $params = [':student_id' => $studentId];
        if (EDUCATION_COURSE_TARGETING_ENABLED) {
            $params[':level_student_id'] = $studentId;
            $params[':level_student_id2'] = $studentId;
        }
        if (PORTAL_EXTENSIONS_ENABLED) $params[':paid_student_id'] = $studentId;
        $tutorSelect = PORTAL_EXTENSIONS_ENABLED ? ', s.tutor_name' : '';
        $sql = "SELECT s.id, s.course_id, s.title, s.description, s.meet_link, s.scheduled_at,
                c.title AS course_title, c.code AS course_code, lecturer.name AS lecturer_name" . $tutorSelect . "
             FROM class_sessions s JOIN courses c ON c.id = s.course_id
             JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
             JOIN users lecturer ON lecturer.id = s.lecturer_id
             WHERE s.status = 'scheduled' AND s.scheduled_at >= NOW() AND c.status = 'published'" . $application . $audience . $payment . "
             ORDER BY s.scheduled_at ASC LIMIT 100";
        return Database::query($sql, $params)->fetchAll();
    }
}
