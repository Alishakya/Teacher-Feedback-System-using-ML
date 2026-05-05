<?php
session_start();
unset($_SESSION['teacher_id']);
unset($_SESSION['teacher_tid']);
header('Location: login.php');
exit();
?>