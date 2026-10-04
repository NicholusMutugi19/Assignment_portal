<?php
require_once __DIR__ . '/../config/Database.php';

class Admin
{
    public static function audit(int $adminId, string $action, string $targetType, ?string $targetId, ?array $before, ?array $after): void
    {
        Database::query(
            'INSERT INTO audit_logs (admin_id, action, target_type, target_id, before_data, after_data)
             VALUES (:admin_id, :action, :target_type, :target_id, :before_data, :after_data)',
            [
                ':admin_id' => $adminId,
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                ':after_data' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            ]
        );
    }

    public static function ensureAuditTable(): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return;
        // Small, deliberate exception for the admin bootstrap path: retain an
        // audit trail even if a feature-specific migration is incomplete.
        Database::query("CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(80) NOT NULL,
            target_id VARCHAR(120) NULL,
            before_data JSON NULL,
            after_data JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_target (target_type, target_id, created_at),
            INDEX idx_audit_admin (admin_id, created_at)
        ) ENGINE=InnoDB");
    }

    public static function setting(string $key, string $default = ''): string
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return $default;
        $value = Database::query('SELECT setting_value FROM portal_settings WHERE setting_key = :key', [':key' => $key])->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    public static function updateSetting(int $adminId, string $key, string $value): void
    {
        $allowed = ['maintenance_mode', 'new_registrations_enabled', 'student_course_applications_enabled', 'payments_enabled'];
        if (!in_array($key, $allowed, true) || !in_array($value, ['0','1'], true)) throw new InvalidArgumentException('Invalid site setting.');
        $before = self::setting($key, '0');
        Database::query(
            'INSERT INTO portal_settings (setting_key, setting_value, updated_by) VALUES (:key, :value, :admin_id)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [':key' => $key, ':value' => $value, ':admin_id' => $adminId]
        );
        self::audit($adminId, 'setting.update', 'setting', $key, ['value' => $before], ['value' => $value]);
    }

    public static function stats(): array
    {
        return [
            'users' => (int)Database::query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'courses' => (int)Database::query(PORTAL_EXTENSIONS_ENABLED
                ? "SELECT COUNT(*) FROM courses WHERE status = 'published'"
                : 'SELECT COUNT(*) FROM courses')->fetchColumn(),
            'pending' => PORTAL_EXTENSIONS_ENABLED ? (int)Database::query("SELECT COUNT(*) FROM users WHERE role = 'lecturer' AND lecturer_approval_status = 'pending'")->fetchColumn() : 0,
            'revenue' => PORTAL_EXTENSIONS_ENABLED ? (float)Database::query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'success'")->fetchColumn() : 0.0,
        ];
    }

    public static function ensureBootstrapTables(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS portal_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value VARCHAR(1000) NOT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        Database::query("INSERT IGNORE INTO portal_settings (setting_key, setting_value) VALUES
            ('maintenance_mode', '0'),
            ('new_registrations_enabled', '1'),
            ('student_course_applications_enabled', '1'),
            ('payments_enabled', '0')");
    }

    public static function pendingLecturers(): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        return Database::query(
            "SELECT id, name, email, created_at FROM users WHERE role = 'lecturer' AND lecturer_approval_status = 'pending' ORDER BY created_at ASC LIMIT 100"
        )->fetchAll();
    }

    public static function decideLecturer(int $adminId, int $lecturerId, string $decision, string $note): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Lecturer approvals are not enabled.');
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Invalid decision.');
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $before = Database::query("SELECT id, lecturer_approval_status FROM users WHERE id = :id AND role = 'lecturer' FOR UPDATE", [':id' => $lecturerId])->fetch();
            if (!$before) throw new RuntimeException('Lecturer not found.');
            Database::query('UPDATE users SET lecturer_approval_status = :decision WHERE id = :id', [':decision' => $decision, ':id' => $lecturerId]);
            Database::query(
                'INSERT INTO lecturer_approval_history (lecturer_id, admin_id, decision, note) VALUES (:lecturer_id, :admin_id, :decision, :note)',
                [':lecturer_id' => $lecturerId, ':admin_id' => $adminId, ':decision' => $decision, ':note' => $note]
            );
            self::audit($adminId, 'lecturer.' . $decision, 'user', (string)$lecturerId, $before, ['lecturer_approval_status' => $decision, 'note' => $note]);
            if ($decision === 'approved') {
                Database::query("UPDATE users SET lecturer_approval_status = 'approved' WHERE id = :id", [':id' => $lecturerId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function provisionFirstAdminFromEnvironment(): int
    {
        $email = strtolower(trim((string)getenv('BOOTSTRAP_ADMIN_EMAIL')));
        $name = trim((string)getenv('BOOTSTRAP_ADMIN_NAME'));
        $password = (string)getenv('BOOTSTRAP_ADMIN_PASSWORD');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 16) {
            throw new RuntimeException('Bootstrap admin env values are missing or invalid.');
        }
        self::ensureBootstrapTables();
        $adminCount = (int)Database::query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount > 0) throw new RuntimeException('Admin users already exist; bootstrap is disabled.');
        $existing = Database::query('SELECT id FROM users WHERE email = :email', [':email' => $email])->fetchColumn();
        if ($existing) throw new RuntimeException('The requested bootstrap email already belongs to an account. No account was modified.');
        if (defined('PORTAL_EXTENSIONS_ENABLED') && PORTAL_EXTENSIONS_ENABLED) {
            Database::query(
                "INSERT INTO users (name, email, password, role, account_status, lecturer_approval_status)
                 VALUES (:name, :email, :password, 'admin', 'active', NULL)",
                [':name' => $name, ':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT)]
            );
        } else {
            throw new RuntimeException('Apply the portal role-extension migration before provisioning the first admin.');
        }
        $id = (int)Database::query('SELECT id FROM users WHERE email = :email', [':email' => $email])->fetchColumn();
        error_log('Bootstrap administrator provisioned from environment.');
        return $id;
    }

    public static function users(string $search, string $role, int $page, int $pageSize = 25): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;
        $where = [];
        $params = [];
        if ($search !== '') { $where[] = '(name LIKE :search OR email LIKE :search2)'; $params[':search'] = '%' . $search . '%'; $params[':search2'] = '%' . $search . '%'; }
        if (in_array($role, ['student', 'lecturer', 'tutor', 'admin'], true)) { $where[] = 'role = :role'; $params[':role'] = $role; }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int)Database::query('SELECT COUNT(*) FROM users' . $clause, $params)->fetchColumn();
        $extra = PORTAL_EXTENSIONS_ENABLED ? ', account_status, lecturer_approval_status' : '';
        $stmt = Database::getInstance()->prepare('SELECT id, name, email, role' . $extra . ', created_at FROM users' . $clause . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => (int)ceil($total / $pageSize)];
    }

    public static function updateUser(int $adminId, int $targetId, string $role, string $status): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Admin tools are disabled.');
        if (!in_array($role, ['student', 'lecturer', 'tutor', 'admin'], true) || !in_array($status, ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Invalid role or account status.');
        }
        if ($adminId === $targetId) throw new InvalidArgumentException('Administrators cannot change their own role or status.');
        $before = Database::query('SELECT id, name, role, account_status FROM users WHERE id = :id', [':id' => $targetId])->fetch();
        if (!$before) throw new RuntimeException('User not found.');
        if ($before['role'] === 'admin' && ($status === 'suspended' || $role !== 'admin')) {
            $activeAdmins = (int)Database::query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'")->fetchColumn();
            if ($activeAdmins <= 1) throw new InvalidArgumentException('Cannot suspend the last active administrator.');
        }
        if (PORTAL_EXTENSIONS_ENABLED && $role === 'lecturer' && $before['role'] === 'lecturer'
            && Database::query('SELECT lecturer_approval_status FROM users WHERE id = :id', [':id' => $targetId])->fetchColumn() !== 'approved') {
            throw new InvalidArgumentException('Pending or rejected lecturer applications must use the approval workflow.');
        }
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            if ($role === 'lecturer' && PORTAL_EXTENSIONS_ENABLED && $before['role'] !== 'lecturer') {
                Database::query("UPDATE users SET lecturer_approval_status = 'pending' WHERE id = :id", [':id' => $targetId]);
                Database::query("INSERT INTO lecturer_approval_history (lecturer_id, admin_id, decision, note) VALUES (:lecturer_id, :admin_id, 'pending', 'Role promotion requires lecturer approval')", [':lecturer_id' => $targetId, ':admin_id' => $adminId]);
            }
            Database::query('UPDATE users SET role = :role, account_status = :status WHERE id = :id', [':role' => $role, ':status' => $status, ':id' => $targetId]);
            self::audit($adminId, 'user.update', 'user', (string)$targetId, $before, ['role' => $role, 'account_status' => $status, 'lecturer_approval_status' => $role === 'lecturer' && PORTAL_EXTENSIONS_ENABLED && $before['role'] !== 'lecturer' ? 'pending' : null]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function setCoursePrice(int $adminId, int $courseId, ?float $price): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Course fee controls are disabled.');
        if ($price !== null && ($price < 0 || $price > 1000000)) throw new InvalidArgumentException('Invalid course fee.');
        $before = Database::query('SELECT id, code, title, price FROM courses WHERE id = :id', [':id' => $courseId])->fetch();
        if (!$before) throw new RuntimeException('Course not found.');
        Database::query('UPDATE courses SET price = :price WHERE id = :id', [':price' => $price, ':id' => $courseId]);
        self::audit($adminId, 'course.fee.update', 'course', (string)$courseId, $before, ['price' => $price]);
    }

    public static function payments(array $filters): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        $conditions = [];
        $params = [];
        if (in_array($filters['status'] ?? '', ['pending','success','failed'], true)) { $conditions[] = 'p.payment_status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['course_id'])) { $conditions[] = 'p.course_id = :course_id'; $params[':course_id'] = (int)$filters['course_id']; }
        if (!empty($filters['student_id'])) { $conditions[] = 'p.student_id = :student_id'; $params[':student_id'] = (int)$filters['student_id']; }
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) { $conditions[] = 'p.created_at >= :date_from'; $params[':date_from'] = $filters['date_from'] . ' 00:00:00'; }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) { $conditions[] = 'p.created_at < :date_to'; $params[':date_to'] = date('Y-m-d H:i:s', strtotime($filters['date_to'] . ' +1 day')); }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        return Database::query(
            'SELECT p.*, u.name AS student_name, c.title AS course_title FROM payments p JOIN users u ON u.id = p.student_id JOIN courses c ON c.id = p.course_id' . $where . ' ORDER BY p.created_at DESC LIMIT 500',
            $params
        )->fetchAll();
    }

    public static function suspendCourse(int $adminId, int $courseId): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Admin tools are disabled.');
        $before = Database::query('SELECT id, code, title, status FROM courses WHERE id = :id', [':id' => $courseId])->fetch();
        if (!$before) throw new RuntimeException('Course not found.');
        Database::query("UPDATE courses SET status = 'suspended' WHERE id = :id", [':id' => $courseId]);
        self::audit($adminId, 'course.suspend', 'course', (string)$courseId, $before, ['status' => 'suspended']);
    }

    public static function deleteCourse(int $adminId, int $courseId): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Admin tools are disabled.');
        $before = Database::query('SELECT id, code, title, lecturer_id FROM courses WHERE id = :id', [':id' => $courseId])->fetch();
        if (!$before) throw new RuntimeException('Course not found.');
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $files = self::deleteCourseData($courseId);
            self::audit($adminId, 'course.delete', 'course', (string)$courseId, $before, ['deleted' => true]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::removeStoredFiles($files);
    }

    /** Delete course-bound records inside an existing transaction and return associated file paths. */
    private static function deleteCourseData(int $courseId): array
    {
        $files = [];
        foreach (Database::query('SELECT attachment_path FROM assignments WHERE course_id = :id AND attachment_path IS NOT NULL', [':id' => $courseId])->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $files[] = (string)$path;
        }
        foreach (Database::query(
            'SELECT s.file_path FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_id = :id',
            [':id' => $courseId]
        )->fetchAll(PDO::FETCH_COLUMN) as $path) {
            if (is_string($path) && !str_starts_with($path, 'online:')) $files[] = $path;
        }
        foreach (Database::query('SELECT stored_path FROM revision_papers WHERE course_id = :id', [':id' => $courseId])->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $files[] = 'uploads/revision-papers/' . basename((string)$path);
        }
        Database::query(
            'DELETE FROM payment_callback_inbox WHERE checkout_request_id IN (SELECT checkout_request_id FROM payments WHERE course_id = :id AND checkout_request_id IS NOT NULL)',
            [':id' => $courseId]
        );
        Database::query('DELETE FROM payments WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM application_history WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM class_sessions WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM revision_papers WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM enrollments WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM course_teaching_assignments WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM assignments WHERE course_id = :id', [':id' => $courseId]);
        Database::query('DELETE FROM courses WHERE id = :id', [':id' => $courseId]);
        return $files;
    }

    private static function removeStoredFiles(array $paths): void
    {
        $publicRoot = realpath(__DIR__ . '/../../public');
        if ($publicRoot === false) return;
        foreach (array_unique($paths) as $relativePath) {
            $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim((string)$relativePath, '/'));
            if ($candidate !== false && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR) && is_file($candidate)) {
                if (!@unlink($candidate)) error_log('Could not remove orphaned course file after database deletion.');
            }
        }
    }

    public static function setCourseStatus(int $adminId, int $courseId, string $status): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Admin tools are disabled.');
        if (!in_array($status, ['published', 'suspended'], true)) throw new InvalidArgumentException('Invalid course status.');
        $before = Database::query('SELECT id, code, title, status FROM courses WHERE id = :id', [':id' => $courseId])->fetch();
        if (!$before) throw new RuntimeException('Course not found.');
        Database::query('UPDATE courses SET status = :status WHERE id = :id', [':status' => $status, ':id' => $courseId]);
        self::audit($adminId, 'course.' . $status, 'course', (string)$courseId, $before, ['status' => $status]);
    }

    public static function securitySummary(): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return ['suspended_users' => 0, 'pending_payments' => 0, 'failed_callbacks' => 0, 'audit_events_24h' => 0, 'unmatched_paid_enrollments' => 0];
        $paymentMetrics = PORTAL_EXTENSIONS_ENABLED ? [
            'pending_payments' => (int)Database::query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn(),
            'failed_callbacks' => (int)Database::query("SELECT COUNT(*) FROM payment_callback_inbox WHERE processing_status = 'pending' AND attempts > 0")->fetchColumn(),
            'unmatched_paid_enrollments' => (int)Database::query("SELECT COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.application_status = 'approved' AND COALESCE(c.price,0) > 0 AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.student_id=e.student_id AND p.course_id=e.course_id AND p.payment_status='success')")->fetchColumn(),
        ] : ['pending_payments' => 0, 'failed_callbacks' => 0, 'unmatched_paid_enrollments' => 0];
        return array_merge([
            'suspended_users' => PORTAL_EXTENSIONS_ENABLED ? (int)Database::query("SELECT COUNT(*) FROM users WHERE account_status = 'suspended'")->fetchColumn() : 0,
            'audit_events_24h' => (int)Database::query('SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetchColumn(),
        ], $paymentMetrics);
    }

    public static function health(): array
    {
        $checks = [];
        $checks['database'] = Database::query('SELECT 1')->fetchColumn() == 1;
        $checks['portal_extensions_enabled'] = PORTAL_EXTENSIONS_ENABLED;
        $checks['education_targeting_enabled'] = EDUCATION_COURSE_TARGETING_ENABLED;
        $checks['mpesa_enabled'] = MPESA_ENABLED;
        $checks['payment_configured'] = (string)getenv('MPESA_CONSUMER_KEY') !== ''
            && (string)getenv('MPESA_CONSUMER_SECRET') !== ''
            && (string)getenv('MPESA_SHORTCODE') !== ''
            && (string)getenv('MPESA_PASSKEY') !== ''
            && (string)getenv('MPESA_CALLBACK_URL') !== ''
            && (string)getenv('MPESA_CALLBACK_SECRET') !== '';
        $checks['callback_url_https'] = strtolower((string)parse_url((string)getenv('MPESA_CALLBACK_URL'), PHP_URL_SCHEME)) === 'https';
        $checks['php_curl'] = function_exists('curl_init');
        $checks['php_openssl'] = extension_loaded('openssl');
        $checks['storage_writable'] = is_writable(__DIR__ . '/../../public/uploads/submissions');
        $checks['revision_storage_writable'] = is_dir(__DIR__ . '/../../public/uploads/revision-papers')
            ? is_writable(__DIR__ . '/../../public/uploads/revision-papers')
            : @mkdir(__DIR__ . '/../../public/uploads/revision-papers', 0755, true);
        if (PORTAL_EXTENSIONS_ENABLED) {
            try {
                Database::query('SELECT setting_key FROM portal_settings LIMIT 1');
                $checks['portal_settings_table'] = true;
            } catch (Throwable $e) {
                $checks['portal_settings_table'] = false;
            }
            try {
                Database::query('SELECT id FROM page_views LIMIT 1');
                $checks['page_analytics_table'] = true;
            } catch (Throwable $e) {
                $checks['page_analytics_table'] = false;
            }
        }
        return $checks;
    }

    public static function paymentReadiness(): array
    {
        $required = ['MPESA_CONSUMER_KEY', 'MPESA_CONSUMER_SECRET', 'MPESA_SHORTCODE', 'MPESA_PASSKEY', 'MPESA_CALLBACK_URL', 'MPESA_CALLBACK_SECRET'];
        $credentialsPresent = true;
        foreach ($required as $name) {
            if (trim((string)getenv($name)) === '') $credentialsPresent = false;
        }
        $callbackHttps = strtolower((string)parse_url((string)getenv('MPESA_CALLBACK_URL'), PHP_URL_SCHEME)) === 'https';
        $environment = strtolower(trim((string)(getenv('MPESA_ENV') ?: 'sandbox')));
        return [
            'environment' => in_array($environment, ['sandbox', 'production'], true) ? $environment : 'invalid',
            'credentials_present' => $credentialsPresent,
            'callback_https' => $callbackHttps,
            'curl_available' => function_exists('curl_init'),
            'extensions_enabled' => PORTAL_EXTENSIONS_ENABLED,
            'mpesa_enabled' => MPESA_ENABLED,
            'payments_site_switch' => self::setting('payments_enabled', '0') === '1',
            'integration_mode' => 'CustomerPayBillOnline',
            'supports_direct_personal_number' => false,
        ];
    }

    public static function recentAudit(int $limit = 100): array
    {
        if (!PORTAL_EXTENSIONS_ENABLED) return [];
        $limit = max(1, min(250, $limit));
        return Database::query(
            'SELECT a.*, u.name AS admin_name FROM audit_logs a LEFT JOIN users u ON u.id = a.admin_id ORDER BY a.created_at DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public static function deleteUser(int $adminId, int $userId): void
    {
        if (!PORTAL_EXTENSIONS_ENABLED) throw new RuntimeException('Admin tools are disabled.');
        if ($adminId === $userId) throw new InvalidArgumentException('You cannot delete your own account.');
        $user = Database::query('SELECT id, name, email, role, account_status FROM users WHERE id = :id', [':id' => $userId])->fetch();
        if (!$user) throw new RuntimeException('User not found.');
        if ($user['role'] === 'admin') {
            $admins = (int)Database::query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'")->fetchColumn();
            if ($admins <= 1) throw new InvalidArgumentException('Cannot delete the last active administrator.');
        }
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        $files = [];
        try {
            $ownedCourses = Database::query('SELECT id FROM courses WHERE lecturer_id = :id', [':id' => $userId])->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ownedCourses as $courseId) $files = array_merge($files, self::deleteCourseData((int)$courseId));
            foreach (Database::query('SELECT stored_path FROM revision_papers WHERE uploader_id = :id', [':id' => $userId])->fetchAll(PDO::FETCH_COLUMN) as $path) {
                $files[] = 'uploads/revision-papers/' . basename((string)$path);
            }
            foreach (Database::query('SELECT file_path FROM submissions WHERE student_id = :id', [':id' => $userId])->fetchAll(PDO::FETCH_COLUMN) as $path) {
                if (is_string($path) && !str_starts_with($path, 'online:')) $files[] = $path;
            }
            Database::query(
                'DELETE FROM payment_callback_inbox WHERE checkout_request_id IN (SELECT checkout_request_id FROM payments WHERE student_id = :id AND checkout_request_id IS NOT NULL)',
                [':id' => $userId]
            );
            Database::query('DELETE FROM payments WHERE student_id = :id', [':id' => $userId]);
            Database::query('DELETE FROM application_history WHERE student_id = :id', [':id' => $userId]);
            Database::query('DELETE FROM class_sessions WHERE lecturer_id = :id', [':id' => $userId]);
            Database::query('DELETE FROM revision_papers WHERE uploader_id = :id', [':id' => $userId]);
            Database::query('DELETE FROM course_teaching_assignments WHERE lecturer_id = :id', [':id' => $userId]);
            Database::query('UPDATE courses SET tutor_id = NULL WHERE tutor_id = :id', [':id' => $userId]);
            Database::query('DELETE FROM lecturer_approval_history WHERE lecturer_id = :id', [':id' => $userId]);
            self::audit($adminId, 'user.delete', 'user', (string)$userId, ['id' => $userId, 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']], ['deleted' => true, 'course_count' => count($ownedCourses)]);
            Database::query('DELETE FROM users WHERE id = :id', [':id' => $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::removeStoredFiles($files);
    }

    public static function systemMetrics(): array
    {
        return [
            'students' => (int)Database::query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
            'lecturers' => (int)Database::query("SELECT COUNT(*) FROM users WHERE role='lecturer'")->fetchColumn(),
            'tutors' => (int)Database::query("SELECT COUNT(*) FROM users WHERE role='tutor'")->fetchColumn(),
            'active_enrollments' => (int)Database::query(PORTAL_EXTENSIONS_ENABLED ? "SELECT COUNT(*) FROM enrollments WHERE application_status='approved'" : 'SELECT COUNT(*) FROM enrollments')->fetchColumn(),
            'submissions' => (int)Database::query('SELECT COUNT(*) FROM submissions')->fetchColumn(),
            'graded' => (int)Database::query('SELECT COUNT(*) FROM submissions WHERE score IS NOT NULL')->fetchColumn(),
            'active_sessions' => PORTAL_EXTENSIONS_ENABLED ? (int)Database::query("SELECT COUNT(*) FROM class_sessions WHERE status='scheduled' AND scheduled_at >= NOW()")->fetchColumn() : 0,
            'pending_applications' => PORTAL_EXTENSIONS_ENABLED ? (int)Database::query("SELECT COUNT(*) FROM enrollments WHERE application_status='pending'")->fetchColumn() : 0,
            'draft_courses' => PORTAL_EXTENSIONS_ENABLED ? (int)Database::query("SELECT COUNT(*) FROM courses WHERE status='draft'")->fetchColumn() : 0,
            'pending_lecturer_approvals' => PORTAL_EXTENSIONS_ENABLED ? (int)Database::query("SELECT COUNT(*) FROM users WHERE role='lecturer' AND lecturer_approval_status='pending'")->fetchColumn() : 0,
        ];
    }

    public static function courseAudit(int $adminId, string $action, int $courseId): void
    {
        $before = Database::query('SELECT id, code, title, status' . (PORTAL_EXTENSIONS_ENABLED ? ', price' : '') . ' FROM courses WHERE id = :id', [':id' => $courseId])->fetch();
        if (!$before) throw new RuntimeException('Course not found.');
        self::audit($adminId, $action, 'course', (string)$courseId, $before, null);
    }
}
