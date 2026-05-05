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
        <a href="teacher.php" data-page="teachers">
            <i class="fas fa-chalkboard-teacher"></i> Teachers
        </a>
        <a href="faculty.php" class="active" data-page="faculty">
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
        <h1>Faculty Management</h1>
        <button class="add-btn" onclick="window.location.href='faculty.php?action=add'">
            <i class="fas fa-plus"></i> Add Faculty
        </button>
    </div>

        <?php
        include 'db.php';

        $error = '';

        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            $description = trim($_POST['descriptions']);

            // Validate input
            if (empty($name)) {
                $error = "Faculty name is required.";
            } elseif (!preg_match("/^[a-zA-Z ]*$/", $name)) {
                $error = "Only letters and white space allowed in faculty name.";
            } elseif (empty($description)) {
                $error = "Description is required.";
            } else {
                if (isset($_POST['add_faculty'])) {
                    $insertQuery = "INSERT INTO faculty (faculty_name, descriptions) VALUES ('$name', '$description')";
                    if ($conn->query($insertQuery) === TRUE) {
                        echo "<p>Faculty added successfully.</p>";
                    } else {
                        $error = "Error: " . $conn->error;
                    }
                } elseif (isset($_POST['edit_faculty'])) {
                    $faculty_id = $_POST['faculty_id'];
                    $updateQuery = "UPDATE faculty SET faculty_name='$name', descriptions='$description' WHERE faculty_id=$faculty_id";
                    if ($conn->query($updateQuery) === TRUE) {
                        echo "<p>Faculty updated successfully.</p>";
                    } else {
                        $error = "Error: " . $conn->error;
                    }
                }
            }
        }

        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['faculty_id'])) {
            $faculty_id = $_GET['faculty_id'];
            $deleteQuery = "DELETE FROM faculty WHERE faculty_id = $faculty_id";
            if ($conn->query($deleteQuery) === TRUE) {
                echo "<p>Faculty deleted successfully.</p>";
            } else {
                echo "Error: " . $conn->error;
            }
        }

        // Fetch faculties
        $facultyQuery = "SELECT * FROM faculty";
        $facultyResult = $conn->query($facultyQuery);
        ?>

        <div class="faculties-table">
            <table>
                <thead>
                    <tr>
                        <th>Faculty Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($faculty = $facultyResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $faculty['faculty_name']; ?></td>
                        <td><?php echo $faculty['descriptions']; ?></td>
                        <td>
                            <a href="faculty.php?action=edit&faculty_id=<?php echo $faculty['faculty_id']; ?>">Edit</a>
                            <a href="faculty.php?action=delete&faculty_id=<?php echo $faculty['faculty_id']; ?>" onclick="return confirm('Are you sure you want to delete this faculty?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php
        // Display add/edit form
        if (isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'edit')) {
            $faculty = [
                'faculty_id' => '',
                'faculty_name' => '',
                'descriptions' => ''
            ];

            if ($_GET['action'] == 'edit' && isset($_GET['faculty_id'])) {
                $faculty_id = $_GET['faculty_id'];
                $query = "SELECT * FROM faculty WHERE faculty_id = $faculty_id";
                $result = $conn->query($query);
                $faculty = $result->fetch_assoc();
            }
        ?>
        <div id="faculty-form">
            <h2><?php echo $_GET['action'] == 'add' ? 'Add New Faculty' : 'Edit Faculty'; ?></h2>
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form method="POST" action="faculty.php">
                <input type="hidden" name="faculty_id" value="<?php echo $faculty['faculty_id']; ?>">
                <div class="form-group">
                    <label for="name">Faculty Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo $faculty['faculty_name']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <input type="text" id="descriptions" name="descriptions" value="<?php echo $faculty['descriptions']; ?>" required>
                </div>
                <button type="submit" name="<?php echo $_GET['action'] == 'add' ? 'add_faculty' : 'edit_faculty'; ?>" class="submit-btn"><?php echo $_GET['action'] == 'add' ? 'Add Faculty' : 'Update Faculty'; ?></button>
            </form>
        </div>
        <?php } ?>

    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
