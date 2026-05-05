-- =====================================================
-- Student Feedback Management System Database
-- Copy and paste this to create a new database
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS student_managementML;
USE student_managementML;

-- =====================================================
-- Table: admin
-- =====================================================
CREATE TABLE IF NOT EXISTS admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Insert default admin user
INSERT INTO admin (username, password) VALUES 
('admin', 'admin123');

-- =====================================================
-- Table: faculty
-- =====================================================
CREATE TABLE IF NOT EXISTS faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_name VARCHAR(100) NOT NULL,
    descriptions varchar(200)
);

-- Insert sample faculties
INSERT INTO faculty (faculty_name) VALUES 
('BCA'), ('CSIT');

-- =====================================================
-- Table: semester
-- =====================================================
CREATE TABLE IF NOT EXISTS semester (
    semester_id INT AUTO_INCREMENT PRIMARY KEY,
    semester_number INT NOT NULL
);

-- Insert semesters
INSERT INTO semester (semester_number) VALUES 
(1), (2), (3), (4), (5), (6),(7),(8);

-- =====================================================
-- Table: teacher
-- =====================================================
CREATE TABLE IF NOT EXISTS teacher (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    tid VARCHAR(20) NOT NULL UNIQUE,
    contact VARCHAR(20),
    image VARCHAR(255) DEFAULT 'default.png',
    password VARCHAR(255) NOT NULL
);

-- Insert sample teachers
INSERT INTO teacher (name, tid, contact, password) VALUES 
('Abhishek Sir', 'T0001', '9876543210', 'pass123'),
('Arun Sir', 'T0002', '9876543211', 'pass123');

-- =====================================================
-- Table: teacher_assignment
-- =====================================================
CREATE TABLE IF NOT EXISTS teacher_assignment (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    faculty_id INT NOT NULL,
    semester_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teacher(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id) ON DELETE CASCADE
);

-- Insert sample assignments
INSERT INTO teacher_assignment (teacher_id, faculty_id, semester_id) VALUES 
(1, 1, 1), (1, 1, 2),
(2, 1, 3), (2, 1, 4);

-- =====================================================
-- Table: student
-- =====================================================
CREATE TABLE IF NOT EXISTS student (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    uid VARCHAR(20) NOT NULL UNIQUE,
    contact VARCHAR(20),
    image VARCHAR(255) DEFAULT 'default.png',
    faculty_id INT NOT NULL,
    semester_id INT NOT NULL,
    enrollment_date DATE,
    password VARCHAR(255) NOT NULL,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id) ON DELETE CASCADE
);

-- Insert sample students
INSERT INTO student (username, uid, contact, faculty_id, semester_id, enrollment_date, password) VALUES 
('Student1', 'U0001', '9800000001', 1, 1, '2024-01-15', 'pass123'),
('Student2', 'U0002', '9800000002', 1, 1, '2024-01-15', 'pass123');

-- =====================================================
-- Table: feedback_sessions
-- =====================================================
CREATE TABLE IF NOT EXISTS feedback_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    semester_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id) ON DELETE CASCADE
);

-- =====================================================
-- Table: feedback_responses
-- =====================================================
CREATE TABLE IF NOT EXISTS feedback_responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    feedback_text TEXT NOT NULL,
    sentiment ENUM('positive', 'neutral', 'negative', 'pending') DEFAULT 'pending',
    sentiment_updated TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES feedback_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teacher(teacher_id) ON DELETE CASCADE,
    UNIQUE KEY unique_feedback (session_id, student_id, teacher_id)
);

select * from feedback_responses;

-- =====================================================
-- End of Database Script
-- =====================================================
