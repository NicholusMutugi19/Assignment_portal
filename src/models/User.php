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
            $params = [
                ':name'     => $data['name'],
                ':email'    => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':role'     => $data['role'] ?? 'student',
            ];
            if (PORTAL_EXTENSIONS_ENABLED && ($data['role'] ?? 'student') === 'admin') {
                throw new InvalidArgumentException('Admin accounts cannot be created through public registration.');
            }
            if (PORTAL_EXTENSIONS_ENABLED && ($data['role'] ?? 'student') === 'lecturer') {
                Database::query(
                    "INSERT INTO users (name, email, password, role, lecturer_approval_status)
                     VALUES (:name, :email, :password, 'lecturer', 'pending')",
                    [':name' => $data['name'], ':email' => $data['email'], ':password' => $params[':password']]
                );
                $id = (int)Database::getInstance()->lastInsertId();
                Database::query(
                    "INSERT INTO lecturer_approval_history (lecturer_id, decision, note) VALUES (:id, 'pending', 'Registration submitted')",
                    [':id' => $id]
                );
                return $id;
            }
            if (EDUCATION_COURSE_TARGETING_ENABLED && ($data['role'] ?? 'student') === 'student') {
                Database::query(
                    'INSERT INTO users (name, email, password, role, education_level, institution_name, year_or_form)
                     VALUES (:name, :email, :password, :role, :education_level, :institution_name, :year_or_form)',
                    $params + [
                        ':education_level' => $data['education_level'],
                        ':institution_name' => $data['institution_name'],
                        ':year_or_form' => $data['year_or_form'],
                    ]
                );
            } else {
                Database::query(
                    'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)',
                    $params
                );
            }
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
            $eligibilityFilter = EDUCATION_COURSE_TARGETING_ENABLED
                ? " AND (student.education_level IS NULL
                    OR c.audience = 'both'
                    OR (student.education_level = 'campus' AND c.audience = 'campus_only')
                    OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))"
                : '';
            $paymentAccess = PORTAL_EXTENSIONS_ENABLED ? " AND (COALESCE(c.price, 0) = 0 OR EXISTS (
                SELECT 1 FROM payments paid WHERE paid.student_id = :paid_student_id
                AND paid.course_id = c.id AND paid.payment_status = 'success'
            ))" : '';
            $applicationAccess = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status = 'approved'" : '';
            return Database::query(
                'SELECT c.*, u.name AS lecturer_name
                 FROM   courses     c
                 JOIN   enrollments e ON e.course_id = c.id
                 JOIN   users       u ON u.id = c.lecturer_id
                 JOIN   users student ON student.id = e.student_id
                 WHERE  e.student_id = :sid' . $eligibilityFilter . $applicationAccess . $paymentAccess,
                PORTAL_EXTENSIONS_ENABLED ? [':sid' => $studentId, ':paid_student_id' => $studentId] : [':sid' => $studentId]
            )->fetchAll();
        } catch (PDOException $e) {
            ErrorHandler::handle($e);
            return [];
        }
    }

    /** True when a student has any course request, even if it is still pending. */
    public static function hasCourseApplications(int $studentId): bool
    {
        return (bool)Database::query(
            'SELECT 1 FROM enrollments WHERE student_id = :student_id LIMIT 1',
            [':student_id' => $studentId]
        )->fetch();
    }

    public static function courseApplicationStatuses(int $studentId): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        return Database::query(
            'SELECT e.course_id, e.application_status, e.access_status, e.application_note,
                    e.application_reviewed_at, c.price
             FROM enrollments e JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = :student_id',
            [':student_id' => $studentId]
        )->fetchAll();
    }

    /** Courses taught by a lecturer */
    public static function taughtCourses(int $lecturerId): array
    {
        try {
            $statusFilter = PORTAL_EXTENSIONS_ENABLED ? " WHERE c.lecturer_id = :lid OR c.tutor_id = :lid2" : ' WHERE c.lecturer_id = :lid';
            $params = PORTAL_EXTENSIONS_ENABLED ? [':lid' => $lecturerId, ':lid2' => $lecturerId] : [':lid' => $lecturerId];
            return Database::query(
                'SELECT c.*,
                        COUNT(DISTINCT e.student_id) AS student_count
                 FROM   courses     c
                 LEFT JOIN enrollments e ON e.course_id = c.id
                 ' . $statusFilter . '
                 GROUP  BY c.id',
                $params
            )->fetchAll();
        } catch (PDOException $e) {
            ErrorHandler::handle($e);
            return [];
        }
    }

    /** Student education profile. */
    public static function educationProfile(int $userId): ?array
    {
        if (!EDUCATION_COURSE_TARGETING_ENABLED) return null;
        $row = Database::query(
            "SELECT education_level, institution_name, year_or_form FROM users WHERE id = :id AND role = 'student'",
            [':id' => $userId]
        )->fetch();
        return $row ?: null;
    }

    public static function updateEducationProfile(int $userId, string $level, string $institution, string $yearOrForm): void
    {
        if (!EDUCATION_COURSE_TARGETING_ENABLED) {
            throw new RuntimeException('Education profile updates are not enabled.');
        }
        Database::query(
            "UPDATE users SET education_level = :level, institution_name = :institution, year_or_form = :year_or_form WHERE id = :id AND role = 'student'",
            [':level' => $level, ':institution' => $institution, ':year_or_form' => $yearOrForm, ':id' => $userId]
        );
    }

    public static function availableCourses(int $studentId): array
    {
        if (!EDUCATION_COURSE_TARGETING_ENABLED && !PORTAL_EXTENSIONS_ENABLED) {
            return Database::query(
                'SELECT c.*, lecturer.name AS lecturer_name
                 FROM courses c JOIN users lecturer ON lecturer.id = c.lecturer_id
                 ORDER BY c.code, c.title'
            )->fetchAll();
        }
        $eligibilityFilter = EDUCATION_COURSE_TARGETING_ENABLED ? " AND (student.education_level IS NULL
            OR c.audience = 'both'
            OR (student.education_level = 'campus' AND c.audience = 'campus_only')
            OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))" : '';
        // Keep a priced course visible so the student can initiate payment;
        // resource access is independently gated in assignment/session queries.
        $enrollmentStatus = '';
        $catalogJoin = 'JOIN users student ON student.id = :sid';
        $studentLevel = EDUCATION_COURSE_TARGETING_ENABLED ? 'student.education_level' : 'NULL AS education_level';
        $audienceColumn = EDUCATION_COURSE_TARGETING_ENABLED ? ', c.audience' : '';
        return Database::query(
            "SELECT c.*, lecturer.name AS lecturer_name, " . $studentLevel . $audienceColumn . "
             FROM courses c
             JOIN users lecturer ON lecturer.id = c.lecturer_id
             " . $catalogJoin . "
             WHERE c.status = 'published'" . $eligibilityFilter . $enrollmentStatus . '
             ORDER BY c.code, c.title',
            [':sid' => $studentId]
        )->fetchAll();
    }

    public static function saveStudentCourseSelections(int $studentId, array $courseIds): void
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            // Existing enrollments are intentionally never deleted by selection updates.
            foreach (array_unique(array_map('intval', $courseIds)) as $courseId) {
                if (!EDUCATION_COURSE_TARGETING_ENABLED && !PORTAL_EXTENSIONS_ENABLED) {
                    // Keep the legacy path valid before migration 002 adds course prices.
                    $course = Database::query('SELECT id FROM courses WHERE id = :cid', [':cid' => $courseId])->fetch();
                } else {
                    $eligibility = EDUCATION_COURSE_TARGETING_ENABLED ? " AND (student.education_level IS NULL
                        OR c.audience = 'both'
                        OR (student.education_level = 'campus' AND c.audience = 'campus_only')
                        OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))" : '';
                    $priceColumn = PORTAL_EXTENSIONS_ENABLED ? 'c.price' : 'NULL AS price';
                    $course = Database::query(
                        "SELECT c.id, " . $priceColumn . " FROM courses c JOIN users student ON student.id = :sid
                         WHERE c.id = :cid AND c.status = 'published'" . $eligibility,
                        [':sid' => $studentId, ':cid' => $courseId]
                    )->fetch();
                }
                if (!$course) {
                    throw new InvalidArgumentException('One or more selected courses are unavailable for your education level.');
                }
                $accessStatus = PORTAL_EXTENSIONS_ENABLED ? 'pending_payment' : 'active';
                if (PORTAL_EXTENSIONS_ENABLED && $course['price'] !== null && (float)$course['price'] > 0) {
                    $hasPaid = Database::query(
                        "SELECT id FROM payments WHERE student_id = :sid AND course_id = :cid AND payment_status = 'success' LIMIT 1",
                        [':sid' => $studentId, ':cid' => $courseId]
                    )->fetch();
                    $accessStatus = $hasPaid ? 'active' : 'pending_payment';
                }
                if (PORTAL_EXTENSIONS_ENABLED) {
                    Database::query(
                        'INSERT IGNORE INTO enrollments (student_id, course_id, access_status, application_status) VALUES (:sid, :cid, :access_status, \'pending\')',
                        [':sid' => $studentId, ':cid' => $courseId, ':access_status' => $accessStatus]
                    );
                    Database::query(
                        "UPDATE enrollments SET application_status = 'pending', application_reviewed_by = NULL,
                         application_reviewed_at = NULL, application_note = NULL, access_status = :access_status
                         WHERE student_id = :sid AND course_id = :cid AND application_status = 'rejected'",
                        [':access_status' => $accessStatus, ':sid' => $studentId, ':cid' => $courseId]
                    );
                } else {
                    Database::query(
                        'INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (:sid, :cid)',
                        [':sid' => $studentId, ':cid' => $courseId]
                    );
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function createLecturerCourse(int $lecturerId, array $data): int
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Custom course creation is not enabled.');
        $code = strtoupper(trim($data['code']));
        $title = trim($data['title']);
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,19}$/', $code) || $title === '' || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('Invalid course code or title.');
        }
        $columns = EDUCATION_COURSE_TARGETING_ENABLED
            ? '(code, title, description, lecturer_id, audience, status, category, duration, prerequisites, syllabus, tutor_name)'
            : '(code, title, description, lecturer_id, status, category, duration, prerequisites, syllabus, tutor_name)';
        $values = EDUCATION_COURSE_TARGETING_ENABLED
            ? '(:code, :title, :description, :lecturer_id, :audience, \'draft\', :category, :duration, :prerequisites, :syllabus, :tutor_name)'
            : '(:code, :title, :description, :lecturer_id, \'draft\', :category, :duration, :prerequisites, :syllabus, :tutor_name)';
        $params = [
            ':code' => $code,
            ':title' => $title,
            ':description' => trim($data['description']) ?: null,
            ':lecturer_id' => $lecturerId,
            ':category' => trim($data['category']) ?: null,
            ':duration' => trim($data['duration']) ?: null,
            ':prerequisites' => trim($data['prerequisites']) ?: null,
            ':syllabus' => trim($data['syllabus']) ?: null,
            ':tutor_name' => trim($data['tutor_name']) ?: null,
        ];
        if (EDUCATION_COURSE_TARGETING_ENABLED) $params[':audience'] = $data['audience'];
        Database::query('INSERT INTO courses ' . $columns . ' VALUES ' . $values, $params);
        return (int) Database::getInstance()->lastInsertId();
    }

    public static function setCourseAudience(int $lecturerId, int $courseId, string $audience): bool
    {
        if (!PORTAL_EXTENSIONS_ENABLED || !EDUCATION_COURSE_TARGETING_ENABLED) return false;
        if (!EDUCATION_COURSE_TARGETING_ENABLED) return false;
        $stmt = Database::query(
            "UPDATE courses SET audience = :audience WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
            [':audience' => $audience, ':id' => $courseId, ':lecturer_id' => $lecturerId]
        );
        return $stmt->rowCount() > 0 || (bool)Database::query(
            "SELECT id FROM courses WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
            [':id' => $courseId, ':lecturer_id' => $lecturerId]
        )->fetch();
    }

    public static function updateCoursePublication(int $lecturerId, int $courseId, string $status): bool
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return false;
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new InvalidArgumentException('Invalid course publication status.');
        }
        $stmt = Database::query(
            "UPDATE courses SET status = :status WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
            [':status' => $status, ':id' => $courseId, ':lecturer_id' => $lecturerId]
        );
        return $stmt->rowCount() > 0 || (bool)Database::query(
            "SELECT id FROM courses WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
            [':id' => $courseId, ':lecturer_id' => $lecturerId]
        )->fetch();
    }

    public static function updateCourseSettings(int $lecturerId, int $courseId, ?string $audience, string $status, ?float $price): bool
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Course fee controls are not enabled until the feature migration is applied.');
        if (!in_array($status, ['draft', 'published'], true)
            || ($price !== null && ($price < 0 || $price > 1000000))
            || (EDUCATION_COURSE_TARGETING_ENABLED && !in_array($audience, ['campus_only', 'high_school_only', 'both'], true))) {
            throw new InvalidArgumentException('Invalid course settings.');
        }

        if (EDUCATION_COURSE_TARGETING_ENABLED) {
            $stmt = Database::query(
                "UPDATE courses SET audience = :audience, status = :status, price = :price
                 WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
                [':audience' => $audience, ':status' => $status, ':price' => $price, ':id' => $courseId, ':lecturer_id' => $lecturerId]
            );
        } else {
            $stmt = Database::query(
                "UPDATE courses SET status = :status, price = :price
                 WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
                [':status' => $status, ':price' => $price, ':id' => $courseId, ':lecturer_id' => $lecturerId]
            );
        }
        return $stmt->rowCount() > 0 || (bool)Database::query(
            "SELECT id FROM courses WHERE id = :id AND lecturer_id = :lecturer_id AND status != 'suspended'",
            [':id' => $courseId, ':lecturer_id' => $lecturerId]
        )->fetch();
    }

    public static function approvalStatus(int $userId): ?string
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return 'approved';
        return Database::query('SELECT lecturer_approval_status FROM users WHERE id = :id', [':id' => $userId])->fetchColumn() ?: null;
    }

    public static function studentCanAccessCourse(int $studentId, int $courseId): bool
    {
        $eligibility = EDUCATION_COURSE_TARGETING_ENABLED
            ? " AND (student.education_level IS NULL OR c.audience = 'both'
                OR (student.education_level = 'campus' AND c.audience = 'campus_only')
                OR (student.education_level = 'high_school' AND c.audience = 'high_school_only'))"
            : '';
        $payment = PORTAL_EXTENSIONS_ENABLED ? " AND (COALESCE(c.price, 0) = 0 OR EXISTS (
            SELECT 1 FROM payments p WHERE p.student_id = e.student_id AND p.course_id = c.id AND p.payment_status = 'success'
        ))" : '';
        $application = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status = 'approved'" : '';
        return (bool)Database::query(
            "SELECT 1 FROM enrollments e JOIN courses c ON c.id = e.course_id
             JOIN users student ON student.id = e.student_id
             WHERE e.student_id = :sid AND e.course_id = :cid AND c.status = 'published'" . $eligibility . $payment . $application . ' LIMIT 1',
            [':sid' => $studentId, ':cid' => $courseId]
        )->fetch();
    }

    public static function managedCourses(int $userId): array
    {
            $sql = PORTAL_EXTENSIONS_ENABLED
                ? 'SELECT c.*, COUNT(DISTINCT e.student_id) AS student_count FROM courses c
               LEFT JOIN enrollments e ON e.course_id = c.id
               WHERE c.lecturer_id = :uid OR c.tutor_id = :uid2 GROUP BY c.id ORDER BY c.created_at DESC'
            : 'SELECT c.*, COUNT(DISTINCT e.student_id) AS student_count FROM courses c
               LEFT JOIN enrollments e ON e.course_id = c.id
               WHERE c.lecturer_id = :uid GROUP BY c.id ORDER BY c.created_at DESC';
        $params = PORTAL_EXTENSIONS_ENABLED ? [':uid' => $userId, ':uid2' => $userId] : [':uid' => $userId];
        return Database::query($sql, $params)->fetchAll();
    }

    public static function canManageCourse(int $userId, int $courseId): bool
    {
        $condition = PORTAL_EXTENSIONS_ENABLED ? '(lecturer_id = :uid OR tutor_id = :uid2)' : 'lecturer_id = :uid';
        $params = PORTAL_EXTENSIONS_ENABLED ? [':uid' => $userId, ':uid2' => $userId, ':cid' => $courseId] : [':uid' => $userId, ':cid' => $courseId];
        return (bool)Database::query('SELECT id FROM courses WHERE id = :cid AND ' . $condition, $params)->fetch();
    }

    public static function manageableCourseAssignments(int $userId): array
    {
        if (PORTAL_EXTENSIONS_ENABLED) {
            return Database::query(
                'SELECT c.*, COUNT(DISTINCT e.student_id) AS student_count FROM courses c
                 LEFT JOIN enrollments e ON e.course_id = c.id
                 WHERE c.lecturer_id = :uid OR c.tutor_id = :uid2 GROUP BY c.id ORDER BY c.title',
                [':uid' => $userId, ':uid2' => $userId]
            )->fetchAll();
        }
        return self::taughtCourses($userId);
    }

    public static function pendingCourseApplications(int $managerId): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        return Database::query(
            "SELECT e.student_id, e.course_id, e.enrolled_at, e.application_status, u.name AS student_name, u.email,
                    u.education_level, u.institution_name, u.year_or_form, c.code, c.title AS course_title
             FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN users u ON u.id = e.student_id
             WHERE (c.lecturer_id = :lecturer_id OR c.tutor_id = :tutor_id) AND e.application_status = 'pending'
             ORDER BY e.enrolled_at ASC",
            [':lecturer_id' => $managerId, ':tutor_id' => $managerId]
        )->fetchAll();
    }

    public static function decideCourseApplication(int $managerId, int $studentId, int $courseId, string $decision, string $note): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Course application review is not enabled.');
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Invalid application decision.');
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $application = Database::query(
                "SELECT e.application_status, e.access_status, e.course_id FROM enrollments e
                 JOIN courses c ON c.id = e.course_id
                 WHERE e.student_id = :student_id AND e.course_id = :course_id
                   AND (c.lecturer_id = :lecturer_id OR c.tutor_id = :tutor_id) FOR UPDATE",
                [':student_id' => $studentId, ':course_id' => $courseId, ':lecturer_id' => $managerId, ':tutor_id' => $managerId]
            )->fetch();
            if (!$application || $application['application_status'] !== 'pending') throw new RuntimeException('Pending application not found for a course you manage.');
            Database::query(
                'UPDATE enrollments SET application_status = :decision, application_reviewed_by = :reviewer,
                 application_reviewed_at = NOW(), application_note = :note WHERE student_id = :student_id AND course_id = :course_id',
                [':decision' => $decision, ':reviewer' => $managerId, ':note' => mb_substr($note, 0, 500), ':student_id' => $studentId, ':course_id' => $courseId]
            );
            Database::query(
                'INSERT INTO application_history (student_id, course_id, reviewer_id, decision, note)
                 VALUES (:student_id, :course_id, :reviewer_id, :decision, :note)',
                [':student_id' => $studentId, ':course_id' => $courseId, ':reviewer_id' => $managerId, ':decision' => $decision, ':note' => mb_substr($note, 0, 500)]
            );
            if ($decision === 'approved') {
                $price = (float)Database::query('SELECT COALESCE(price, 0) FROM courses WHERE id = :id', [':id' => $courseId])->fetchColumn();
                $hasPaid = PORTAL_EXTENSIONS_ENABLED && $price > 0
                    ? (bool)Database::query("SELECT id FROM payments WHERE student_id = :student_id AND course_id = :course_id AND payment_status = 'success' LIMIT 1", [':student_id' => $studentId, ':course_id' => $courseId])->fetch()
                    : true;
                if ($hasPaid) {
                    Database::query("UPDATE enrollments SET access_status = 'active', access_granted_at = COALESCE(access_granted_at, NOW()) WHERE student_id = :student_id AND course_id = :course_id", [':student_id' => $studentId, ':course_id' => $courseId]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
