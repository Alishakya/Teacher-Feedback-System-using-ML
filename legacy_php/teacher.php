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
        <a href="student.php" data-page="students">
            <i class="fas fa-user-graduate"></i> Students
        </a>
        <a href="teacher.php" class="active" data-page="teachers">
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
        <h1>Teacher Management</h1>
        <button class="add-btn" onclick="window.location.href='teacher.php?action=add'">
            <i class="fas fa-plus"></i> Add Teacher
        </button>
    </div>

        <?php
        include 'db.php';

        // Function to generate the next TID
        function generateTID($conn)
        {
            $query = "SELECT MAX(tid) AS max_tid FROM teacher";
            $result = $conn->query($query);
            $row = $result->fetch_assoc();
            $max_tid = $row['max_tid'];
            $next_number = intval(substr($max_tid, 1)) + 1;
            return 'T' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        }

        $error = '';

        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            $contact = trim($_POST['contact']);
            $image = $_FILES['image']['name'];
            $password = trim($_POST['password']);
            $tid = isset($_POST['tid']) ? $_POST['tid'] : generateTID($conn);

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
                    if (isset($_POST['add_teacher'])) {
                        $insertQuery = "INSERT INTO teacher (name, tid, contact, image, password) VALUES ('$name', '$tid', '$contact', '$image', '$password')";
                        if ($conn->query($insertQuery) === TRUE) {
                            $teacher_id = $conn->insert_id;
                            foreach ($_POST['assignments'] as $assignment) {
                                $faculty_id = $assignment['faculty'];
                                $semester_id = $assignment['semester'];
                                $assignmentQuery = "INSERT INTO teacher_assignment (teacher_id, faculty_id, semester_id) VALUES ('$teacher_id', '$faculty_id', '$semester_id')";
                                $conn->query($assignmentQuery);
                            }
                            header("Location: teacher.php?success=1");
                            exit();
                        } else {
                            $error = "Error: " . $conn->error;
                        }
                    } elseif (isset($_POST['edit_teacher'])) {
                        $teacher_id = $_POST['teacher_id'];
                        if (!empty($image)) {
                            $updateQuery = "UPDATE teacher SET name='$name', tid='$tid', contact='$contact', image='$image', password='$password' WHERE teacher_id=$teacher_id";
                        } else {
                            $updateQuery = "UPDATE teacher SET name='$name', tid='$tid', contact='$contact', password='$password' WHERE teacher_id=$teacher_id";
                        }
                        if ($conn->query($updateQuery) === TRUE) {
                            $deleteAssignmentsQuery = "DELETE FROM teacher_assignment WHERE teacher_id=$teacher_id";
                            $conn->query($deleteAssignmentsQuery);
                            foreach ($_POST['assignments'] as $assignment) {
                                $faculty_id = $assignment['faculty'];
                                $semester_id = $assignment['semester'];
                                $assignmentQuery = "INSERT INTO teacher_assignment (teacher_id, faculty_id, semester_id) VALUES ('$teacher_id', '$faculty_id', '$semester_id')";
                                $conn->query($assignmentQuery);
                            }
                            header("Location: teacher.php?success=1");
                            exit();
                        } else {
                            $error = "Error: " . $conn->error;
                        }
                    }
                }
            }
        }

        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['teacher_id'])) {
            $teacher_id = $_GET['teacher_id'];
            $deleteQuery = "DELETE FROM teacher WHERE teacher_id = $teacher_id";
            if ($conn->query($deleteQuery) === TRUE) {
                $deleteAssignmentsQuery = "DELETE FROM teacher_assignment WHERE teacher_id=$teacher_id";
                $conn->query($deleteAssignmentsQuery);
                header("Location: teacher.php?success=1");
                exit();
            } else {
                echo "Error: " . $conn->error;
            }
        }

        // Fetch teachers
        $teacherQuery = "SELECT * FROM teacher";
        $teacherResult = $conn->query($teacherQuery);

        // Fetch faculties and semesters for the form
        $facultyQuery = "SELECT * FROM faculty";
        $facultyResult = $conn->query($facultyQuery);
        $facultyOptions = '';
        while ($faculty = $facultyResult->fetch_assoc()) {
            $facultyOptions .= '<option value="' . $faculty['faculty_id'] . '">' . htmlspecialchars($faculty['faculty_name']) . '</option>';
        }

        $semesterQuery = "SELECT * FROM semester";
        $semesterResult = $conn->query($semesterQuery);
        $semesterOptions = '';
        while ($semester = $semesterResult->fetch_assoc()) {
            $semesterOptions .= '<option value="' . $semester['semester_id'] . '">' . htmlspecialchars($semester['semester_number']) . '</option>';
        }
        ?>

        <div class="teachers-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Tid</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Contact</th>
                        <th>Assign Class</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($teacher = $teacherResult->fetch_assoc()): ?>
                        <tr>
                            <td><img src="uploads/<?php echo $teacher['image']; ?>" alt="Teacher Image" width="50"></td>
                            <td><?php echo $teacher['tid']; ?></td>
                            <td><?php echo $teacher['name']; ?></td>
                            <td><?php echo $teacher['password']; ?></td>
                            <td><?php echo $teacher['contact']; ?></td>
                            <td>
                                <?php
                                $assignmentsQuery = "SELECT faculty.faculty_name, semester.semester_number FROM teacher_assignment
                                                 JOIN faculty ON teacher_assignment.faculty_id = faculty.faculty_id
                                                 JOIN semester ON teacher_assignment.semester_id = semester.semester_id
                                                 WHERE teacher_assignment.teacher_id = " . $teacher['teacher_id'];
                                $assignmentsResult = $conn->query($assignmentsQuery);
                                while ($assignment = $assignmentsResult->fetch_assoc()) {
                                    echo $assignment['faculty_name'] . " - " . $assignment['semester_number'] . "<br>";
                                }
                                ?>
                            </td>
                            <td>
                                <a href="teacher.php?action=edit&teacher_id=<?php echo $teacher['teacher_id']; ?>">Edit</a>
                                <a href="teacher.php?action=delete&teacher_id=<?php echo $teacher['teacher_id']; ?>" onclick="return confirm('Are you sure you want to delete this teacher?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php
        // Display add/edit form
        if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')) {
            $teacher = [
                'teacher_id' => '',
                'name' => '',
                'tid' => generateTID($conn),
                'contact' => '',
                'image' => '',
                'password' => ''
            ];

            $assignments = [];

            if ($_GET['action'] == 'edit' && isset($_GET['teacher_id'])) {
                $teacher_id = $_GET['teacher_id'];
                $query = "SELECT * FROM teacher WHERE teacher_id = $teacher_id";
                $result = $conn->query($query);
                $teacher = $result->fetch_assoc();

                $assignmentsQuery = "SELECT * FROM teacher_assignment WHERE teacher_id = $teacher_id";
                $assignmentsResult = $conn->query($assignmentsQuery);
                while ($assignment = $assignmentsResult->fetch_assoc()) {
                    $assignments[] = $assignment;
                }
            }
        ?>
            <div id="teacher-form">
                <h2><?php echo $_GET['action'] == 'add' ? 'Add New Teacher' : 'Edit Teacher'; ?></h2>
                <?php if (!empty($error)): ?>
                    <p class="error"><?php echo $error; ?></p>
                <?php endif; ?>
                <form method="POST" action="teacher.php" enctype="multipart/form-data">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['teacher_id']; ?>">
                    <input type="hidden" name="existing_image" value="<?php echo $teacher['image']; ?>">
                    <div class="form-group">
                        <label for="name">Username:</label>
                        <input type="text" id="name" name="name" value="<?php echo $teacher['name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tid">TID</label>
                        <input type="text" id="tid" name="tid" value="<?php echo $teacher['tid']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number</label>
                        <input type="tel" id="contact" name="contact" value="<?php echo $teacher['contact']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Profile Image</label>
                        <input type="file" id="image" name="image">
                        <?php if (!empty($teacher['image'])): ?>
                            <img src="uploads/<?php echo $teacher['image']; ?>" alt="Teacher Image" width="50">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" value="<?php echo $teacher['password']; ?>" required>
                    </div>
                    <div id="assignments">
                        <?php foreach ($assignments as $index => $assignment): ?>
                            <div class="assignment-group">
                                <div class="form-group">
                                    <label for="faculty_<?php echo $index; ?>">Faculty</label>
                                    <select id="faculty_<?php echo $index; ?>" name="assignments[<?php echo $index; ?>][faculty]" required>
                                        <option value="">Select Faculty</option>
                                        <?php echo str_replace('value="' . $assignment['faculty_id'] . '"', 'value="' . $assignment['faculty_id'] . '" selected', $facultyOptions); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="semester_<?php echo $index; ?>">Semester</label>
                                    <select id="semester_<?php echo $index; ?>" name="assignments[<?php echo $index; ?>][semester]" required>
                                        <option value="">Select Semester</option>
                                        <?php echo str_replace('value="' . $assignment['semester_id'] . '"', 'value="' . $assignment['semester_id'] . '" selected', $semesterOptions); ?>
                                    </select>
                                </div>
                                <button type="button" onclick="removeAssignment(this)">- Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addAssignment()">+ Add Assignment</button>
                    <button type="submit" name="<?php echo $_GET['action'] == 'add' ? 'add_teacher' : 'edit_teacher'; ?>" class="submit-btn"><?php echo $_GET['action'] == 'add' ? 'Add Teacher' : 'Update Teacher'; ?></button>
                </form>
            </div>
        <?php } ?>
    </main>

    <script>
        let assignmentIndex = <?php echo count($assignments); ?>;
        const facultyOptions = `<?php echo $facultyOptions; ?>`;
        const semesterOptions = `<?php echo $semesterOptions; ?>`;

        function addAssignment() {
            const assignmentsDiv = document.getElementById('assignments');
            const newAssignmentDiv = document.createElement('div');
            newAssignmentDiv.classList.add('assignment-group');

            const facultySelect = document.createElement('select');
            facultySelect.name = `assignments[${assignmentIndex}][faculty]`;
            facultySelect.required = true;
            facultySelect.innerHTML = `
                <option value="">Select Faculty</option>
                ${facultyOptions}
            `;
            newAssignmentDiv.appendChild(facultySelect);

            const semesterSelect = document.createElement('select');
            semesterSelect.name = `assignments[${assignmentIndex}][semester]`;
            semesterSelect.required = true;
            semesterSelect.innerHTML = `
                <option value="">Select Semester</option>
                ${semesterOptions}
            `;
            newAssignmentDiv.appendChild(semesterSelect);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.textContent = '- Remove';
            removeButton.onclick = function() {
                removeAssignment(removeButton);
            };
            newAssignmentDiv.appendChild(removeButton);

            assignmentsDiv.appendChild(newAssignmentDiv);
            assignmentIndex++;
        }

        function removeAssignment(button) {
            const assignmentGroup = button.parentElement;
            assignmentGroup.remove();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>