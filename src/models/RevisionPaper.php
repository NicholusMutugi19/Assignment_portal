<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/FileUploader.php';

class RevisionPaper
{
    public const STORAGE_DIR = __DIR__ . '/../../public/uploads/revision-papers';

    public static function forManager(int $managerId): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        return Database::query(
            'SELECT rp.*, c.code AS course_code, c.title AS course_title
             FROM revision_papers rp JOIN courses c ON c.id = rp.course_id
             WHERE c.lecturer_id = :owner_id OR c.tutor_id = :tutor_id OR EXISTS (
                 SELECT 1 FROM course_teaching_assignments cta
                 WHERE cta.course_id = c.id AND cta.lecturer_id = :assigned_id
             ) ORDER BY rp.created_at DESC',
            [':owner_id' => $managerId, ':tutor_id' => $managerId, ':assigned_id' => $managerId]
        )->fetchAll();
    }

    public static function forStudent(int $studentId): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        $audience = EDUCATION_COURSE_TARGETING_ENABLED
            ? " AND (student.education_level IS NULL OR c.audience = 'both'
                OR (student.education_level = 'campus' AND c.audience = 'campus_only')
                OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))"
            : '';
        $access = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status = 'approved'
            AND (COALESCE(c.price, 0) = 0 OR EXISTS (
                SELECT 1 FROM payments p WHERE p.student_id = e.student_id
                  AND p.course_id = c.id AND p.payment_status = 'success'
            ))" : '';
        return Database::query(
            "SELECT rp.id, rp.course_id, rp.title, rp.description, rp.original_filename,
                    rp.mime_type, rp.file_size, rp.created_at, c.code AS course_code,
                    c.title AS course_title, owner.name AS lecturer_name
             FROM revision_papers rp
             JOIN courses c ON c.id = rp.course_id
             JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
             JOIN users student ON student.id = e.student_id
             JOIN users owner ON owner.id = c.lecturer_id
             WHERE rp.status = 'published' AND c.status = 'published'" . $access . $audience . '
             ORDER BY c.code, rp.created_at DESC',
            [':student_id' => $studentId]
        )->fetchAll();
    }

    public static function create(int $managerId, int $courseId, string $title, string $description, array $file): int
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Revision papers are disabled by deployment configuration.');
        if (!User::canManageCourse($managerId, $courseId)) throw new RuntimeException('You cannot share materials for this course.');
        $title = trim($title);
        if ($title === '' || strlen($title) > 200) throw new InvalidArgumentException('Enter a paper title (maximum 200 characters).');
        if (!Database::query("SELECT id FROM courses WHERE id = :id AND status != 'suspended'", [':id' => $courseId])->fetch()) {
            throw new InvalidArgumentException('Choose an available course.');
        }
        if (!is_dir(self::STORAGE_DIR) && !mkdir(self::STORAGE_DIR, 0755, true) && !is_dir(self::STORAGE_DIR)) {
            throw new RuntimeException('Revision-paper storage is unavailable. Check the Render uploads disk permissions.');
        }
        $privateRule = self::STORAGE_DIR . '/.htaccess';
        if (!is_file($privateRule) && file_put_contents($privateRule, "Require all denied\n", LOCK_EX) === false) {
            throw new RuntimeException('Could not secure revision-paper storage.');
        }
        $uploader = new FileUploader(self::STORAGE_DIR);
        $upload = $uploader->handle($file);
        if (empty($upload['ok'])) throw new InvalidArgumentException(implode(' ', $upload['errors'] ?? ['The file could not be uploaded.']));
        try {
            Database::query(
                "INSERT INTO revision_papers
                    (course_id, uploader_id, title, description, stored_path, original_filename, mime_type, file_size, status)
                 VALUES (:course_id, :uploader_id, :title, :description, :stored_path, :original_filename, :mime_type, :file_size, 'published')",
                [
                    ':course_id' => $courseId,
                    ':uploader_id' => $managerId,
                    ':title' => $title,
                    ':description' => trim($description) === '' ? null : trim($description),
                    ':stored_path' => $upload['stored_name'],
                    ':original_filename' => basename($upload['original_name']),
                    ':mime_type' => $upload['mime'],
                    ':file_size' => $upload['size'],
                ]
            );
            return (int)Database::getInstance()->lastInsertId();
        } catch (Throwable $e) {
            @unlink($upload['path']);
            throw $e;
        }
    }

    public static function setStatus(int $managerId, int $paperId, string $status): bool
    {
        if (!in_array($status, ['published', 'archived'], true)) throw new InvalidArgumentException('Invalid revision-paper status.');
        $paper = Database::query(
            'SELECT rp.id, rp.course_id FROM revision_papers rp WHERE rp.id = :id',
            [':id' => $paperId]
        )->fetch();
        if (!$paper || !User::canManageCourse($managerId, (int)$paper['course_id'])) return false;
        Database::query('UPDATE revision_papers SET status = :status WHERE id = :id', [':status' => $status, ':id' => $paperId]);
        return true;
    }

    public static function findForStudent(int $paperId, int $studentId): ?array
    {
        foreach (self::forStudent($studentId) as $paper) {
            if ((int)$paper['id'] === $paperId) return $paper;
        }
        return null;
    }

    public static function filePath(string $storedPath): ?string
    {
        if ($storedPath === '' || basename($storedPath) !== $storedPath) return null;
        $root = realpath(self::STORAGE_DIR);
        $path = realpath(self::STORAGE_DIR . DIRECTORY_SEPARATOR . $storedPath);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) return null;
        return $path;
    }

    public static function adminSummary(): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return ['total' => 0, 'published' => 0, 'archived' => 0, 'latest' => []];
        return [
            'total' => (int)Database::query('SELECT COUNT(*) FROM revision_papers')->fetchColumn(),
            'published' => (int)Database::query("SELECT COUNT(*) FROM revision_papers WHERE status = 'published'")->fetchColumn(),
            'archived' => (int)Database::query("SELECT COUNT(*) FROM revision_papers WHERE status = 'archived'")->fetchColumn(),
            'latest' => Database::query(
                'SELECT rp.title, rp.status, rp.created_at, c.code, c.title AS course_title, u.name AS uploader_name
                 FROM revision_papers rp JOIN courses c ON c.id = rp.course_id JOIN users u ON u.id = rp.uploader_id
                 ORDER BY rp.created_at DESC LIMIT 8'
            )->fetchAll(),
        ];
    }
}
