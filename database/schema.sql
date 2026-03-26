-- ============================================
-- Assignment Portal Database Schema
-- Database: assignment_portal
-- ============================================

CREATE DATABASE IF NOT EXISTS defaultdb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE defaultdb;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)        NOT NULL,
    email       VARCHAR(180)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    role        ENUM('student','lecturer') NOT NULL DEFAULT 'student',
    avatar      VARCHAR(255)        DEFAULT NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================
-- COURSES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS courses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)         NOT NULL UNIQUE,
    title       VARCHAR(200)        NOT NULL,
    description TEXT                DEFAULT NULL,
    lecturer_id INT UNSIGNED        NOT NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lecturer (lecturer_id)
) ENGINE=InnoDB;

-- ============================================
-- COURSE ENROLLMENTS (many-to-many)
-- ============================================
CREATE TABLE IF NOT EXISTS enrollments (
    student_id  INT UNSIGNED NOT NULL,
    course_id   INT UNSIGNED NOT NULL,
    enrolled_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- ASSIGNMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS assignments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED        NOT NULL,
    lecturer_id     INT UNSIGNED        NOT NULL,
    title           VARCHAR(255)        NOT NULL,
    description     TEXT                NOT NULL,
    attachment_path VARCHAR(500)        DEFAULT NULL,
    attachment_name VARCHAR(255)        DEFAULT NULL,
    max_score       DECIMAL(5,2)        NOT NULL DEFAULT 100.00,
    deadline        DATETIME            NOT NULL,
    allow_late      TINYINT(1)          NOT NULL DEFAULT 0,
    late_penalty    DECIMAL(5,2)        NOT NULL DEFAULT 0.00  COMMENT 'Percentage deducted for late submissions',
    status          ENUM('draft','published','closed') NOT NULL DEFAULT 'published',
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id)   REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_course    (course_id),
    INDEX idx_deadline  (deadline),
    INDEX idx_status    (status)
) ENGINE=InnoDB;

-- ============================================
-- SUBMISSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS submissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id   INT UNSIGNED        NOT NULL,
    student_id      INT UNSIGNED        NOT NULL,
    file_path       VARCHAR(500)        NOT NULL,
    original_name   VARCHAR(255)        NOT NULL,
    file_size       INT UNSIGNED        NOT NULL COMMENT 'Size in bytes',
    mime_type       VARCHAR(100)        NOT NULL,
    comment         TEXT                DEFAULT NULL,
    submitted_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late         TINYINT(1)          NOT NULL DEFAULT 0,
    score           DECIMAL(5,2)        DEFAULT NULL,
    feedback        TEXT                DEFAULT NULL,
    graded_at       DATETIME            DEFAULT NULL,
    graded_by       INT UNSIGNED        DEFAULT NULL,
    status          ENUM('submitted','graded','returned') NOT NULL DEFAULT 'submitted',
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)    REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (graded_by)     REFERENCES users(id)       ON DELETE SET NULL,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    INDEX idx_assignment (assignment_id),
    INDEX idx_student    (student_id),
    INDEX idx_status     (status)
) ENGINE=InnoDB;

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Passwords are bcrypt of "password123"
INSERT INTO users (name, email, password, role) VALUES
('Dr. Sarah Kimani',    'lecturer@portal.ac.ke', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lecturer'),
('Alice Wanjiku',       'alice@student.ac.ke',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Brian Otieno',        'brian@student.ac.ke',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Carol Njeri',         'carol@student.ac.ke',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO courses (code, title, description, lecturer_id) VALUES
('CS301', 'Web Technologies',       'Advanced web development concepts including PHP, MySQL and CGI.', 1),
('CS204', 'Database Systems',       'Relational databases, SQL, normalization and transactions.',       1),
('CS410', 'Software Engineering',   'SDLC, design patterns, testing and project management.',           1);

INSERT INTO enrollments (student_id, course_id) VALUES
(2,1),(2,2),(2,3),
(3,1),(3,2),
(4,2),(4,3);

INSERT INTO assignments (course_id, lecturer_id, title, description, max_score, deadline, allow_late, late_penalty, status) VALUES
(1, 1, 'PHP File Upload System',
 'Build a secure file upload system using PHP. Submit a ZIP containing all source files and a README.',
 100, DATE_ADD(NOW(), INTERVAL 7 DAY),  1, 20.00, 'published'),

(1, 1, 'REST API Design',
 'Design and implement a RESTful API for a library management system. Document all endpoints.',
 100, DATE_ADD(NOW(), INTERVAL 14 DAY), 0, 0.00,  'published'),

(2, 1, 'ER Diagram & Normalisation',
 'Create an ER diagram for a hospital management system and normalise to 3NF. Submit as PDF.',
 100, DATE_SUB(NOW(), INTERVAL 2 DAY),  0, 0.00,  'closed'),

(3, 1, 'Software Requirements Spec',
 'Write an SRS document for an online banking application following IEEE 830 standard.',
 100, DATE_ADD(NOW(), INTERVAL 3 DAY),  1, 10.00, 'published');
