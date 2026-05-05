<!-- filepath: /d:/demo3/includes/db.php -->
<?php
$servername = "localhost";
$username = "root";
$password = "123456";
$dbname = "student_managementML";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>