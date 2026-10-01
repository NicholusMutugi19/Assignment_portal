-- Additive schema for payments/access control, lecturer approvals, class sessions,
-- online assignments, ranking publication and administration.
-- Apply after migration 001 on a staging clone after taking a verified backup.
-- Existing enrollments remain active by default; existing lecturer accounts are approved.
ALTER TABLE users
    MODIFY COLUMN role ENUM('student','lecturer','tutor','admin') NOT NULL DEFAULT 'student',
    ADD COLUMN account_status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    ADD COLUMN lecturer_approval_status ENUM('pending','approved','rejected') NULL;

UPDATE users SET lecturer_approval_status = 'approved' WHERE role = 'lecturer';

ALTER TABLE courses
    ADD COLUMN price DECIMAL(10,2) NULL,
    ADD COLUMN tutor_id INT UNSIGNED NULL,
    ADD COLUMN results_published TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE courses ADD CONSTRAINT fk_courses_tutor FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE enrollments
    ADD COLUMN access_status ENUM('active','pending_payment') NOT NULL DEFAULT 'active',
    ADD COLUMN access_granted_at DATETIME NULL,
    ADD COLUMN application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    ADD COLUMN application_reviewed_by INT UNSIGNED NULL,
    ADD COLUMN application_reviewed_at DATETIME NULL,
    ADD COLUMN application_note VARCHAR(500) NULL;
ALTER TABLE enrollments
    ADD CONSTRAINT fk_enrollment_reviewed_by FOREIGN KEY (application_reviewed_by) REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX idx_enrollment_applications ON enrollments (course_id, application_status, enrolled_at);

ALTER TABLE assignments
    ADD COLUMN assignment_type ENUM('upload','online') NOT NULL DEFAULT 'upload',
    ADD COLUMN tutor_name VARCHAR(120) NULL;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    transaction_id VARCHAR(120) NULL,
    merchant_request_id VARCHAR(120) NULL,
    checkout_request_id VARCHAR(120) NULL,
    mpesa_receipt VARCHAR(80) NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    phone_number VARCHAR(24) NOT NULL,
    callback_payload JSON NULL,
    callback_received_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_checkout (checkout_request_id),
    UNIQUE KEY uq_payment_receipt (mpesa_receipt),
    INDEX idx_payment_filters (payment_status, created_at),
    INDEX idx_payment_student_course (student_id, course_id),
    CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_callback_inbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checkout_request_id VARCHAR(120) NULL,
    payload JSON NOT NULL,
    processing_status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_callback_retry (processing_status, next_attempt_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    lecturer_id INT UNSIGNED NOT NULL,
    tutor_name VARCHAR(120) NULL,
    meet_link VARCHAR(500) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status ENUM('scheduled','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_course_date (course_id, status, scheduled_at),
    CONSTRAINT fk_session_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_session_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignment_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text','single_choice','multiple_choice') NOT NULL DEFAULT 'text',
    points DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question_assignment (assignment_id, position),
    CONSTRAINT fk_question_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignment_question_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    option_text VARCHAR(1000) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_option_question (question_id, position),
    CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES assignment_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignment_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    response_text TEXT NULL,
    selected_option_ids JSON NULL,
    awarded_score DECIMAL(6,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_response_submission_question (submission_id, question_id),
    CONSTRAINT fk_response_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_response_question FOREIGN KEY (question_id) REFERENCES assignment_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lecturer_approval_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NULL,
    decision ENUM('pending','approved','rejected') NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approval_history (lecturer_id, created_at),
    CONSTRAINT fk_approval_history_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approval_history_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(80) NOT NULL,
    target_id VARCHAR(120) NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_target (target_type, target_id, created_at),
    INDEX idx_audit_admin (admin_id, created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Existing course fees are NULL until an authorized lecturer/admin sets a fee.
-- Do not apply an assumed fee to existing courses or change historical grades.
