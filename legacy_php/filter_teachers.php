<?php
include 'db.php';

$facultyFilter = isset($_GET['faculty']) ? $_GET['faculty'] : '';
$semesterFilter = isset($_GET['semester']) ? $_GET['semester'] : '';

$teacherQuery = "SELECT t.*, GROUP_CONCAT(CONCAT(f.faculty_name, ' - ', sem.semester_number) SEPARATOR ', ') AS assignments 
                 FROM teacher t 
                 JOIN teacher_assignment ta ON t.teacher_id = ta.teacher_id
                 JOIN faculty f ON ta.faculty_id = f.faculty_id 
                 JOIN semester sem ON ta.semester_id = sem.semester_id 
                 WHERE 1=1";

if (!empty($facultyFilter)) {
    $teacherQuery .= " AND ta.faculty_id = '$facultyFilter'";
}
if (!empty($semesterFilter)) {
    $teacherQuery .= " AND ta.semester_id = '$semesterFilter'";
}

$teacherQuery .= " GROUP BY t.teacher_id";

$teacherResult = $conn->query($teacherQuery);
while ($teacher = $teacherResult->fetch_assoc()) {
    echo '<tr>';
    echo '<td><img src="uploads/' . $teacher['image'] . '" alt="Teacher Image" width="50"></td>';
    echo '<td>' . $teacher['tid'] . '</td>';
    echo '<td>' . $teacher['name'] . '</td>'; 
    echo '<td>' . $teacher['contact'] . '</td>';
    echo '<td>' . $teacher['assignments'] . '</td>';
    echo '</tr>';
}
?>