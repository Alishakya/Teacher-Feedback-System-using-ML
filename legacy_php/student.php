<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduAdmin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>

<body>
<aside class="sidebar">
    <div class="logo">
        <h2>EduAdmin</h2>
    </div>
    <div class="nav-links">
        <a href="index.php" data-page="dashboard">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="student.php" class="active" data-page="students">
            <i class="fas fa-user-graduate"></i> Students
        </a>
        <a href="teacher.php" data-page="teachers">
            <i class="fas fa-chalkboard-teacher"></i> Teachers
        </a>
        <a href="faculty.php" data-page="faculty">
            <i class="fas fa-university"></i> Faculty
        </a>
        <a href="feedback.php" data-page="feedback">
            <i class="fas fa-comments"></i> Feedback
        </a>
    </div>
    <button class="logout-btn" onclick="window.location.href='logout_admin.php'">
        <i class="fas fa-sign-out-alt"></i> Log Out
    </button>
</aside>

<main>
    <div class="header">
        <h1>Student Management</h1>
        <button class="add-btn" onclick="window.location.href='student.php?action=add'">
            <i class="fas fa-plus"></i> Add Student
        </button>
    </div>

        <?php
        include 'db.php';

        // Function to generate the next UID
        function generateUID($conn) {
            $query = "SELECT MAX(uid) AS max_uid FROM student";
            $result = $conn->query($query);
            $row = $result->fetch_assoc();
            $max_uid = $row['max_uid'];
            $next_number = intval(substr($max_uid, 1)) + 1;
            return 'U' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        }

        $error = '';

        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            $contact = trim($_POST['contact']);
            $faculty_id = $_POST['faculty'];
            $enroll = $_POST['enroll'];
            $password = trim($_POST['password']);
            $image = $_FILES['image']['name'];

            // Calculate semester based on enrollment date
            $enrollmentDate = new DateTime($enroll);
            $currentDate = new DateTime();
            $interval = $currentDate->diff($enrollmentDate);
            $monthsDifference = $interval->y * 12 + $interval->m;
            $semesterNumber = floor($monthsDifference / 6) + 1;

            // Fetch the semester ID based on the calculated semester number
            $semesterQuery = "SELECT semester_id FROM semester WHERE semester_number = $semesterNumber";
            $semesterResult = $conn->query($semesterQuery);
            $semester = $semesterResult->fetch_assoc();
            $semester_id = $semester['semester_id'];

            // Validate input
            if (!preg_match("/^[a-zA-Z ]*$/", $name)) {
                $error = "Only letters and white space allowed in name.";
            } elseif (!preg_match("/^[0-9]{10}$/", $contact)) {
                $error = "Invalid contact number.";
            } elseif (!preg_match("/^[a-zA-Z0-9]*$/", $password)) {
                $error = "Invalid password.";
            } else {
                // Handle file upload
                if (!empty($image)) {
                    $target_dir = "uploads/";
                    $target_file = $target_dir . basename($_FILES["image"]["name"]);
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        $image = basename($_FILES["image"]["name"]);
                    } else {
                        $error = "Sorry, there was an error uploading your file.";
                    }
                } else {
                    $image = $_POST['existing_image'];
                }

                if (empty($error)) {
                    if (isset($_POST['add_student'])) {
                        $uid = generateUID($conn);
                        $insertQuery = "INSERT INTO student (username, uid, contact, image, faculty_id, semester_id, enrollment_date, password) VALUES ('$name', '$uid', '$contact', '$image', '$faculty_id', '$semester_id', '$enroll', '$password')";
                        if ($conn->query($insertQuery) === TRUE) {
                            echo "<p>Student added successfully.</p>";
                        } else {
                            $error = "Error: " . $conn->error;
                        }
                    } elseif (isset($_POST['edit_student'])) {
                        $student_id = $_POST['student_id'];
                        $uid = $_POST['uid'];
                        if (!empty($image)) {
                            $updateQuery = "UPDATE student SET username='$name', uid='$uid', contact='$contact', image='$image', faculty_id='$faculty_id', semester_id='$semester_id', enrollment_date='$enroll', password='$password' WHERE student_id=$student_id";
                        } else {
                            $updateQuery = "UPDATE student SET username='$name', uid='$uid', contact='$contact', faculty_id='$faculty_id', semester_id='$semester_id', enrollment_date='$enroll', password='$password' WHERE student_id=$student_id";
                        }

                        if ($conn->query($updateQuery) === TRUE) {
                            echo "<p>Student updated successfully.</p>";
                        } else {
                            $error = "Error: " . $conn->error;
                        }
                    }
                }
            }
        }

        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['student_id'])) {
            $student_id = $_GET['student_id'];
            $deleteQuery = "DELETE FROM student WHERE student_id = $student_id";
            if ($conn->query($deleteQuery) === TRUE) {
                echo "<p>Student deleted successfully.</p>";
            } else {
                echo "Error: " . $conn->error;
            }
        }

        // Fetch students
        $studentQuery = "SELECT s.*, f.faculty_name FROM student s JOIN faculty f ON s.faculty_id = f.faculty_id";
        $studentResult = $conn->query($studentQuery);

        // Fetch faculties for the form
        $facultyQuery = "SELECT * FROM faculty";
        $facultyResult = $conn->query($facultyQuery);
        ?>

        <div class="students-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Uid</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Contact</th>
                        <th>Faculty</th>
                        <th>Semester</th>
                        <th>Enrollment Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $studentResult->fetch_assoc()): ?>
                    <tr>
                        <td><img src="uploads/<?php echo $student['image']; ?>" alt="Student Image" width="50"></td>
                        <td><?php echo $student['uid']; ?></td>
                        <td><?php echo $student['username']; ?></td>
                        <td><?php echo $student['password']; ?></td>
                        <td><?php echo $student['contact']; ?></td>
                        <td><?php echo $student['faculty_name']; ?></td>
                        <td><?php echo $student['semester_id']; ?></td>
                        <td><?php echo $student['enrollment_date']; ?></td>
                        <td>
                            <a href="student.php?action=edit&student_id=<?php echo $student['student_id']; ?>">Edit</a>
                            <a href="student.php?action=delete&student_id=<?php echo $student['student_id']; ?>" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php
        // Display add/edit form
        if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')) {
            $student = [
                'student_id' => '',
                'username' => '',
                'uid' => '',
                'contact' => '',
                'image' => '',
                'faculty_id' => '',
                'semester_id' => '',
                'enrollment_date' => '',
                'password' => ''
            ];

            if ($_GET['action'] == 'edit' && isset($_GET['student_id'])) {
                $student_id = $_GET['student_id'];
                $query = "SELECT * FROM student WHERE student_id = $student_id";
                $result = $conn->query($query);
                $student = $result->fetch_assoc();
            }
        ?>
        <div id="student-form">
            <h2><?php echo $_GET['action'] == 'add' ? 'Add New Student' : 'Edit Student'; ?></h2>
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form method="POST" action="student.php" enctype="multipart/form-data">
                <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                <input type="hidden" name="existing_image" value="<?php echo $student['image']; ?>">
                <div class="form-group">
                    <label for="name">Username*:</label>
                    <input type="text" id="name" name="name" value="<?php echo $student['username']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="uid">UID*</label>
                    <input type="text" id="uid" name="uid" value="<?php echo $student['uid']; ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number*</label>
                    <input type="tel" id="contact" name="contact" value="<?php echo $student['contact']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="image">Profile Image*</label>
                    <input type="file" id="image" name="image">
                    <?php if (!empty($student['image'])): ?>
                        <img src="uploads/<?php echo $student['image']; ?>" alt="Student Image" width="50">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="faculty">Faculty*</label>
                    <select id="faculty" name="faculty" required>
                        <option value="">Select Faculty</option>
                        <?php while ($faculty = $facultyResult->fetch_assoc()): ?>
                            <option value="<?php echo $faculty['faculty_id']; ?>" <?php echo $faculty['faculty_id'] == $student['faculty_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($faculty['faculty_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="enroll">Enrollment Date*</label>
                    <input type="date" id="enroll" name="enroll" value="<?php echo $student['enrollment_date']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password*</label>
                    <input type="password" id="password" name="password" value="<?php echo $student['password']; ?>" required>
                </div>
                <button type="submit" name="<?php echo $_GET['action'] == 'add' ? 'add_student' : 'edit_student'; ?>" class="submit-btn"><?php echo $_GET['action'] == 'add' ? 'Add Student' : 'Update Student'; ?></button>
            </form>
        </div>
        <?php } ?>

    </main>
    <script>
        let today = new Date().toISOString().split('T')[0];
        document.getElementById('enroll').setAttribute('max', today);

        document.getElementById('enroll').addEventListener('change', function() {
            const enrollmentDate = new Date(this.value);
            const currentDate = new Date();
            const monthsDifference = (currentDate.getFullYear() - enrollmentDate.getFullYear()) * 12 + currentDate.getMonth() - enrollmentDate.getMonth();
            const semesterNumber = Math.floor(monthsDifference / 6) + 1;

            // Fetch the semester ID based on the calculated semester number
            fetch(`get_semester_id.php?semester_number=${semesterNumber}`)
                .then(response => response.json())
                .then(data => {
                    if (data.semester_id) {
                        document.getElementById('semester').value = data.semester_id;
                    }
                });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
