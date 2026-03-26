<?php
require_once 'src/config/database.php';
require_once 'src/config/Database.php';
require_once 'src/models/User.php';

// Test course selection and dashboard loading
echo "Testing course selection and dashboard loading...\n\n";

// Simulate a student selecting courses
$userId = 9; // Test student ID

// Clear existing enrollments
Database::query('DELETE FROM enrollments WHERE student_id = ?', [$userId]);

// Select multiple courses (using valid course IDs from earlier)
$selectedCourses = [26, 6]; // CS101, CS201

foreach ($selectedCourses as $courseId) {
    Database::query('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)', [$userId, $courseId]);
}

echo "Selected courses for student $userId: " . implode(', ', $selectedCourses) . "\n";

// Now check what the dashboard would load
$courses = User::enrolledCourses($userId);
echo "Dashboard loaded courses: " . count($courses) . "\n";

foreach ($courses as $course) {
    echo "- {$course['code']}: {$course['title']}\n";
}

echo "\nCourse selection and dashboard loading test completed!\n";
?>