<?php
include 'db.php';

$facultyFilter = isset($_GET['faculty']) ? $_GET['faculty'] : '';
$semesterFilter = isset($_GET['semester']) ? $_GET['semester'] : '';

$studentQuery = "SELECT s.*, f.faculty_name, sem.semester_number FROM student s 
                 JOIN faculty f ON s.faculty_id = f.faculty_id 
                 JOIN semester sem ON s.semester_id = sem.semester_id 
                 WHERE 1=1";

if (!empty($facultyFilter)) {
    $studentQuery .= " AND s.faculty_id = '$facultyFilter'";
}
if (!empty($semesterFilter)) {
    $studentQuery .= " AND s.semester_id = '$semesterFilter'";
}

$studentResult = $conn->query($studentQuery);
while ($student = $studentResult->fetch_assoc()) {
    echo '<tr>';
    echo '<td><img src="uploads/' . $student['image'] . '" alt="Student Image" width="50"></td>';
    echo '<td>' . $student['uid'] . '</td>';
    echo '<td>' . $student['username'] . '</td>';
    echo '<td>' . $student['contact'] . '</td>';
    echo '<td>' . $student['faculty_name'] . '</td>';
    echo '<td>' . $student['semester_number'] . '</td>';
    echo '<td>' . $student['enrollment_date'] . '</td>';
    echo '</tr>';
}
?>