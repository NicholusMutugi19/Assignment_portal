-- Allow lecturers to teach published catalog courses without taking ownership
-- away from the lecturer who created/owns the course. Apply after migration 004.
CREATE TABLE IF NOT EXISTS course_teaching_assignments (
    lecturer_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lecturer_id, course_id),
    KEY idx_teaching_course (course_id, lecturer_id),
    CONSTRAINT fk_teaching_assignment_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_teaching_assignment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Preserve current teaching behavior by migrating existing course owners into
-- the relationship table. INSERT IGNORE makes retries safe.
INSERT IGNORE INTO course_teaching_assignments (lecturer_id, course_id)
SELECT lecturer_id, id FROM courses;
