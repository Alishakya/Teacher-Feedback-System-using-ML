<?php
include 'db.php';

// SQL to create feedback_sessions table
$sql1 = "CREATE TABLE IF NOT EXISTS feedback_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    semester_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id),
    FOREIGN KEY (semester_id) REFERENCES semester(semester_id)
) ENGINE=InnoDB";

// SQL to create feedback_responses table
$sql2 = "CREATE TABLE IF NOT EXISTS feedback_responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    feedback_text TEXT,
    sentiment ENUM('positive', 'neutral', 'negative'),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES feedback_sessions(session_id),
    FOREIGN KEY (student_id) REFERENCES student(student_id),
    FOREIGN KEY (teacher_id) REFERENCES teacher(teacher_id)
) ENGINE=InnoDB";

// Execute the first query
if ($conn->query($sql1) === TRUE) {
    echo "Table feedback_sessions created successfully.<br>";
} else {
    echo "Error creating table feedback_sessions: " . $conn->error . "<br>";
}

// Execute the second query
if ($conn->query($sql2) === TRUE) {
    echo "Table feedback_responses created successfully.<br>";
} else {
    echo "Error creating table feedback_responses: " . $conn->error . "<br>";
}

// Close the connection
$conn->close();
?>