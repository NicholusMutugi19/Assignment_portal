-- Education profiles and course audience targeting.
-- MySQL 8 / Aiven. Apply once to a backed-up staging clone before production.
-- Additive only: existing users remain NULL (prompted non-blockingly) and
-- existing courses retain default audience 'both' and status 'published'.
ALTER TABLE users
    ADD COLUMN education_level ENUM('campus', 'high_school') NULL,
    ADD COLUMN institution_name VARCHAR(180) NULL,
    ADD COLUMN year_or_form VARCHAR(60) NULL;

ALTER TABLE courses
    ADD COLUMN audience ENUM('campus_only', 'high_school_only', 'both') NOT NULL DEFAULT 'both',
    ADD COLUMN status ENUM('draft', 'published', 'suspended') NOT NULL DEFAULT 'published',
    ADD COLUMN category VARCHAR(120) NULL,
    ADD COLUMN duration VARCHAR(120) NULL,
    ADD COLUMN prerequisites TEXT NULL,
    ADD COLUMN syllabus TEXT NULL,
    ADD COLUMN tutor_name VARCHAR(120) NULL;

CREATE INDEX idx_courses_audience_status ON courses (audience, status);
