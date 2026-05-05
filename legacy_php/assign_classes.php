<?php
session_start();
include 'db.php';

// Check if the teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Fetch faculties and semesters assigned to the teacher
$sql = "SELECT ta.faculty_id, ta.semester_id, f.faculty_name FROM teacher_assignment ta
        JOIN faculty f ON ta.faculty_id = f.faculty_id
        WHERE ta.teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$assigned_classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch students based on selected faculty and semester (if set)
$students = [];
if (isset($_GET['faculty']) && isset($_GET['semester'])) {
    $faculty_id = $_GET['faculty'];
    $semester_id = $_GET['semester'];

    $sql = "SELECT s.username, s.uid, s.contact, s.semester_id, f.faculty_name 
            FROM student s 
            JOIN faculty f ON s.faculty_id = f.faculty_id 
            WHERE s.faculty_id = ? AND s.semester_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $faculty_id, $semester_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Panel</title>
    <link rel="stylesheet" href="tstyles.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">
            <h2>TeacherPanel</h2>
        </div>
        <div class="nav-links">
            <a href="teacher_panel.php">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="assign_classes.php" class="active">
                <i class="fas fa-graduation-cap"></i> Assign Classes
            </a>
        </div>
        <a href="logout_teacher.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </a>
    </aside>
    
    <main>
        <div class="page active">
            <h1>Assign Classes</h1>

            <div class="class-container">
                <?php foreach ($assigned_classes as $class) { ?>
                    <div class="class-card">
                        <h3>
                            <a href="#"><?php echo "Faculty: " . $class['faculty_name'] . " - Semester " . $class['semester_id']; ?></a>
                        </h3>
                        <p>Students: 
                            <?php
                            $countQuery = "SELECT COUNT(*) as count FROM student WHERE faculty_id = ? AND semester_id = ?";
                            $countStmt = $conn->prepare($countQuery);
                            $countStmt->bind_param("ii", $class['faculty_id'], $class['semester_id']);
                            $countStmt->execute();
                            $countResult = $countStmt->get_result();
                            $countData = $countResult->fetch_assoc();
                            echo $countData['count'];
                            $countStmt->close();
                            ?>
                        </p>
                        <form method="GET" action="">
                            <input type="hidden" name="faculty" value="<?php echo $class['faculty_id']; ?>">
                            <input type="hidden" name="semester" value="<?php echo $class['semester_id']; ?>">
                            <button type="submit" class="view-btn">View Students</button>
                        </form>
                    </div>
                <?php } ?>
            </div>

            <h2>Students in Selected Faculty & Semester</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Uid</th>
                        <th>Contact</th>
                        <th>Faculty</th>
                        <th>Semester</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student) { ?>
                    <tr>
                        <td><?php echo $student['username']; ?></td>
                        <td><?php echo $student['uid']; ?></td>
                        <td><?php echo $student['contact']; ?></td>
                        <td><?php echo $student['faculty_name']; ?></td>
                        <td><?php echo $student['semester_id']; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </main>

    <style>
      :root {
    --primary-color: #4169E1;
    --secondary-color: #f8f9fa;
    --text-color: #333;
    --border-color: #e0e0e0;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --sidebar-width: 250px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

body {
    background-color: #f5f6f8;
    color: var(--text-color);
    display: flex;
}

.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background-color: white;
    padding: 2rem 0;
    position: fixed;
    box-shadow: 2px 0 4px rgba(0,0,0,0.1);
}

        .sidebar h2 {
            color: #0066cc;
        }

        .nav-links a {
    text-decoration: none;
    color: var(--text-color);
    font-weight: 500;
    padding: 0.75rem 2rem;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 1rem;
}

        .nav-links a.active {
            color: #0066cc;
            font-weight: bold;
        }

        .logout-btn {
            background: #d9534f;
            color: white;
            padding: 12px;
            text-align: center;
            display: block;
            border-radius: 5px;
            text-decoration: none;
        }

        main {
            padding: 20px;
            flex: 1;
        }

        h1, h2 {
            color: #333;
        }

        .class-container {
            display: flex;
            gap: 20px;
        }

        .class-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
            width: 280px;
        }

        .class-card h3 a {
            color: #0066cc;
            text-decoration: none;
        }

        .view-btn {
            background: #007bff;
            color: white;
            padding: 8px;
            width: 100%;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</body>
</html>
