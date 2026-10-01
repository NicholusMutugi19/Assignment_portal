-- Additive portal feature controls. Apply after migration 002 on staging.
CREATE TABLE IF NOT EXISTS portal_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(1000) NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_setting_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO portal_settings (setting_key, setting_value) VALUES
('maintenance_mode', '0'),
('new_registrations_enabled', '1'),
('student_course_applications_enabled', '1'),
('payments_enabled', '0');

CREATE TABLE IF NOT EXISTS application_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NULL,
    decision ENUM('pending','approved','rejected') NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_course_application_history (course_id, student_id, created_at),
    CONSTRAINT fk_app_history_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_app_history_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_app_history_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
