-- New applications must be pending by default; preserve all existing rows/statuses.
-- Safe after 002: this changes only the default for future enrollments.
ALTER TABLE enrollments
    MODIFY COLUMN application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending';
