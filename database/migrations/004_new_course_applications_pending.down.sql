-- Safe rollback restores the legacy-compatible default without changing any stored
-- approval decisions. Existing enrollment statuses remain unchanged.
ALTER TABLE enrollments
    MODIFY COLUMN application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved';
