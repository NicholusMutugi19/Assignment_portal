-- Run before/after migration on staging and compare row counts.
SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'courses', COUNT(*) FROM courses
UNION ALL SELECT 'enrollments', COUNT(*) FROM enrollments
UNION ALL SELECT 'assignments', COUNT(*) FROM assignments
UNION ALL SELECT 'submissions', COUNT(*) FROM submissions;

-- Verify every pre-existing lecturer is approved and new nullable values are not guessed.
SELECT role, lecturer_approval_status, COUNT(*) AS users
FROM users GROUP BY role, lecturer_approval_status;
SELECT COUNT(*) AS courses_without_configured_price FROM courses WHERE price IS NULL;
SELECT access_status, COUNT(*) AS enrollments FROM enrollments GROUP BY access_status;
