<?php
session_start();
unset($_SESSION['student_id']);
unset($_SESSION['student_uid']);
header('Location: login.php');
exit();
?>