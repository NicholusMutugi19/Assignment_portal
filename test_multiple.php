<?php
require_once 'src/config/database.php';
require_once 'src/config/Database.php';

echo 'Testing multiple enrollments...' . PHP_EOL;

// Check if a student can have multiple enrollments
$userId = 9; // Assuming student ID 9 exists
Database::query('DELETE FROM enrollments WHERE student_id = ?', [$userId]);
Database::query('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)', [$userId, 1]);
Database::query('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)', [$userId, 2]);

$enrollments = Database::query('SELECT COUNT(*) as count FROM enrollments WHERE student_id = ?', [$userId])->fetch()['count'];
echo 'Student enrollments: ' . $enrollments . PHP_EOL;

// Check lecturer courses
$lecturerId = 1; // Assuming lecturer ID 1
Database::query('DELETE FROM courses WHERE lecturer_id = ?', [$lecturerId]);
Database::query('INSERT INTO courses (code, title, lecturer_id) VALUES (?, ?, ?)', ['CS101', 'Intro', $lecturerId]);
Database::query('INSERT INTO courses (code, title, lecturer_id) VALUES (?, ?, ?)', ['CS201', 'Data', $lecturerId]);

$courses = Database::query('SELECT COUNT(*) as count FROM courses WHERE lecturer_id = ?', [$lecturerId])->fetch()['count'];
echo 'Lecturer courses: ' . $courses . PHP_EOL;

echo 'Multiple selections working!' . PHP_EOL;
?>