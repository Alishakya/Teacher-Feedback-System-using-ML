<?php
include 'db.php';

$facultyFilter = isset($_GET['faculty']) ? $_GET['faculty'] : '';
$descriptionFilter = isset($_GET['description']) ? $_GET['description'] : '';

$facultyQuery = "SELECT * FROM faculty WHERE 1=1";

if (!empty($facultyFilter)) {
    $facultyQuery .= " AND faculty_id = '$facultyFilter'";
}
if (!empty($descriptionFilter)) {
    $facultyQuery .= " AND description LIKE '%$descriptionFilter%'";
}

$facultyResult = $conn->query($facultyQuery);

if (!$facultyResult) {
    die('Query Error: ' . $conn->error);
}

while ($faculty = $facultyResult->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . $faculty['faculty_id'] . '</td>';
    echo '<td>' . $faculty['faculty_name'] . '</td>';
    echo '<td>' . (isset($faculty['description']) ? $faculty['description'] : 'N/A') . '</td>';
    echo '</tr>';
}
?>