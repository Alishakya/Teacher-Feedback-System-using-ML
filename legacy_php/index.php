<?php
include 'db.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
?>
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
    <style>
        .filter-form {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
        }
        .filter-form .form-group {
            margin-bottom: 10px;
        }
        .students-table, .teachers-table, .faculty-table {
            margin-top: 20px;
        }
        .page {
            display: none;
        }
        .page.active {
            display: block;
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="logo">
        <h2>EduAdmin</h2>
    </div>
    <div class="nav-links">
        <a href="index.php" class="active" data-page="dashboard">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="student.php" data-page="students">
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
    <!-- Dashboard Page -->
    <div id="dashboard-page" class="page active">
        <div class="header">
            <h1>Dashboard Overview</h1>
        </div>
        <div class="dashboard-stats">
            <?php
            // Fetch total students
            $studentQuery = "SELECT COUNT(*) AS total_students FROM student";
            $studentResult = $conn->query($studentQuery);
            $totalStudents = $studentResult->fetch_assoc()['total_students'];

            // Fetch total teachers
            $teacherQuery = "SELECT COUNT(*) AS total_teachers FROM teacher";
            $teacherResult = $conn->query($teacherQuery);
            $totalTeachers = $teacherResult->fetch_assoc()['total_teachers'];

            // Fetch total faculties
            $facultyQuery = "SELECT COUNT(*) AS total_faculties FROM faculty";
            $facultyResult = $conn->query($facultyQuery);
            $totalFaculties = $facultyResult->fetch_assoc()['total_faculties'];
            ?>
            <div class="stat-card">
                <h3>Total Students</h3>
                <p class="stat-number" id="totalStudents"><?php echo $totalStudents; ?></p>
                <button class="view-students-btn">View Students</button>
                <button class="hide-students-btn" style="display:none;">Hide Students</button>
            </div>
            <div class="stat-card">
                <h3>Total Teachers</h3>
                <p class="stat-number" id="totalTeachers"><?php echo $totalTeachers; ?></p>
                <button class="view-teachers-btn">View Teachers</button>
                <button class="hide-teachers-btn" style="display:none;">Hide Teachers</button>
            </div>
            <div class="stat-card">
                <h3>Total Faculties</h3>
                <p class="stat-number" id="totalFaculties"><?php echo $totalFaculties; ?></p>
                <button class="view-faculties-btn">View Faculties</button>
                <button class="hide-faculties-btn" style="display:none;">Hide Faculties</button>
            </div>
        </div>
    </div>

    <!-- View Students Section -->
    <div id="view-students-section" class="page">
        <div class="bg-light p-3 rounded mb-4">
            <h1 class="h4">View Students</h1>
            <form id="filter-students-form" class="row g-3">
                <div class="col-md-4">
                    <label for="faculty" class="form-label">Faculty</label>
                    <select id="faculty" name="faculty" class="form-select">
                        <option value="">All Faculties</option>
                        <?php
                        $facultyQuery = "SELECT * FROM faculty";
                        $facultyResult = $conn->query($facultyQuery);
                        while ($faculty = $facultyResult->fetch_assoc()) {
                            echo '<option value="' . $faculty['faculty_id'] . '">' . htmlspecialchars($faculty['faculty_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="semester" class="form-label">Semester</label>
                    <select id="semester" name="semester" class="form-select">
                        <option value="">All Semesters</option>
                        <?php
                        $semesterQuery = "SELECT * FROM semester";
                        $semesterResult = $conn->query($semesterQuery);
                        while ($semester = $semesterResult->fetch_assoc()) {
                            echo '<option value="' . $semester['semester_id'] . '">' . htmlspecialchars($semester['semester_number']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" onclick="filterStudents()" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
        <div class="students-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>UID</th>
                        <th>Username</th>
                        <th>Contact</th>
                        <th>Faculty</th>
                        <th>Semester</th>
                        <th>Enrollment Date</th>
                    </tr>
                </thead>
                <tbody id="students-tbody">
                    <!-- Student data will be inserted here by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Teachers Section -->
    <div id="view-teachers-section" class="page">
        <div class="header">
            <h1>View Teachers</h1>
            <form id="filter-teachers-form" class="filter-form">
                <div class="form-group">
                    <label for="faculty-teacher">Faculty</label>
                    <select id="faculty-teacher" name="faculty">
                        <option value="">All Faculties</option>
                        <?php
                        $facultyQuery = "SELECT * FROM faculty";
                        $facultyResult = $conn->query($facultyQuery);
                        while ($faculty = $facultyResult->fetch_assoc()) {
                            echo '<option value="' . $faculty['faculty_id'] . '">' . htmlspecialchars($faculty['faculty_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="semester-teacher">Semester</label>
                    <select id="semester-teacher" name="semester">
                        <option value="">All Semesters</option>
                        <?php
                        $semesterQuery = "SELECT * FROM semester";
                        $semesterResult = $conn->query($semesterQuery);
                        while ($semester = $semesterResult->fetch_assoc()) {
                            echo '<option value="' . $semester['semester_id'] . '">' . htmlspecialchars($semester['semester_number']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="button" class="filter-btn" onclick="filterTeachers()">Filter</button>
            </form>
        </div>
        <div class="teachers-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>TID</th>
                        <th>Username</th>
                        <th>Contact</th>
                        <th>Assign Faculty</th>
                    </tr>
                </thead>
                <tbody id="teachers-tbody">
                    <!-- Teacher data will be inserted here by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Faculties Section -->
    <div id="view-faculties-section" class="page">
        <div class="header">
            <h1>View Faculties</h1>
        </div>
        <div class="faculty-table">
            <table>
                <thead>
                    <tr>
                        <th>Faculty ID</th>
                        <th>Faculty Name</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody id="faculties-tbody">
                    <?php
                    $facultyQuery = "SELECT * FROM faculty";
                    $facultyResult = $conn->query($facultyQuery);
                    while ($faculty = $facultyResult->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . $faculty['faculty_id'] . '</td>';
                        echo '<td>' . $faculty['faculty_name'] . '</td>';
                        echo '<td>' . $faculty['descriptions'] . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    function hideAllSections() {
        document.querySelectorAll('.page').forEach(function(section) {
            section.classList.remove('active');
        });
    }

    document.querySelector('.view-students-btn').addEventListener('click', function() {
        hideAllSections();
        document.getElementById('view-students-section').classList.add('active');
        document.querySelector('.view-students-btn').style.display = 'none';
        document.querySelector('.hide-students-btn').style.display = 'inline-block';
        filterStudents(); // Load students when the section is shown
    });

    document.querySelector('.hide-students-btn').addEventListener('click', function() {
        document.getElementById('view-students-section').classList.remove('active');
        document.querySelector('.view-students-btn').style.display = 'inline-block';
        document.querySelector('.hide-students-btn').style.display = 'none';
    });

    document.querySelector('.view-teachers-btn').addEventListener('click', function() {
        hideAllSections();
        document.getElementById('view-teachers-section').classList.add('active');
        document.querySelector('.view-teachers-btn').style.display = 'none';
        document.querySelector('.hide-teachers-btn').style.display = 'inline-block';
        filterTeachers(); // Load teachers when the section is shown
    });

    document.querySelector('.hide-teachers-btn').addEventListener('click', function() {
        document.getElementById('view-teachers-section').classList.remove('active');
        document.querySelector('.view-teachers-btn').style.display = 'inline-block';
        document.querySelector('.hide-teachers-btn').style.display = 'none';
    });

    document.querySelector('.view-faculties-btn').addEventListener('click', function() {
        hideAllSections();
        document.getElementById('view-faculties-section').classList.add('active');
        document.querySelector('.view-faculties-btn').style.display = 'none';
        document.querySelector('.hide-faculties-btn').style.display = 'inline-block';
    });

    document.querySelector('.hide-faculties-btn').addEventListener('click', function() {
        document.getElementById('view-faculties-section').classList.remove('active');
        document.querySelector('.view-faculties-btn').style.display = 'inline-block';
        document.querySelector('.hide-faculties-btn').style.display = 'none';
    });

    function filterStudents() {
        const faculty = document.getElementById('faculty').value;
        const semester = document.getElementById('semester').value;

        const xhr = new XMLHttpRequest();
        xhr.open('GET', `filter_students.php?faculty=${faculty}&semester=${semester}`, true);
        xhr.onload = function() {
            if (this.status === 200) {
                document.getElementById('students-tbody').innerHTML = this.responseText;
            }
        };
        xhr.send();
    }

    function filterTeachers() {
        const faculty = document.getElementById('faculty-teacher').value;
        const semester = document.getElementById('semester-teacher').value;

        const xhr = new XMLHttpRequest();
        xhr.open('GET', `filter_teachers.php?faculty=${faculty}&semester=${semester}`, true);
        xhr.onload = function() {
            if (this.status === 200) {
                document.getElementById('teachers-tbody').innerHTML = this.responseText;
            }
        };
        xhr.send();
    }
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
