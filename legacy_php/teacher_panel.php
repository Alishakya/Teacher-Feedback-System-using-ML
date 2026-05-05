<?php
session_start();
include 'db.php';

// Check if the teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Fetch teacher details
$sql = "SELECT name, tid, contact, image FROM teacher WHERE teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

// Fetch assigned faculties and semesters
$sql = "SELECT ta.faculty_id, ta.semester_id, f.faculty_name FROM teacher_assignment ta
        JOIN faculty f ON ta.faculty_id = f.faculty_id
        WHERE ta.teacher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$assigned_classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="tstyles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">
            <h2>TeacherPanel</h2>
        </div>
        <div class="nav-links">
            <a href="teacher_panel.php" class="active" data-page="profile">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="assign_classes.php" data-page="assign-classes">
                <i class="fas fa-graduation-cap"></i> Assign Classes
            </a>
            <a href="#" data-page="feedback">
                <i class="fas fa-chart-bar"></i> Feedback
            </a>
        </div>
        <a href="logout_teacher.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </a>
    </aside>

    <main>
        <!-- Profile Page -->
        <div class="page active" id="profile-page">
            <div class="header">
                <h1>Teacher Profile</h1>
            </div>
            <div class="profile-container">
                <div class="profile-info">
                    <img src="uploads/<?php echo htmlspecialchars($teacher['image']); ?>" alt="Teacher Profile" class="profile-image">
                    <div class="info-details">
                        <h2><?php echo htmlspecialchars($teacher['name']); ?></h2>
                        <p><i class="fas fa-phone"></i> Contact: <?php echo htmlspecialchars($teacher['contact']); ?></p>
                        <p><i class="fas fa-id-card"></i> Tid: <?php echo htmlspecialchars($teacher['tid']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Page -->
        <div class="page" id="feedback-page">
            <div class="header">
                <h1>Feedback Performance</h1>
            </div>
            <div class="feedback-container">
                <form method="POST" action="">
                    <label for="faculty">Select Faculty:</label>
                    <select name="faculty" id="faculty" required>
                        <option value="">-- Select Faculty --</option>
                        <?php foreach ($assigned_classes as $class): ?>
                            <option value="<?php echo $class['faculty_id']; ?>" <?php if (isset($_POST['faculty']) && $_POST['faculty'] == $class['faculty_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($class['faculty_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="semester">Select Semester:</label>
                    <select name="semester" id="semester" required>
                        <option value="">-- Select Semester --</option>
                        <?php
                        $unique_semesters = array_unique(array_column($assigned_classes, 'semester_id'));
                        foreach ($unique_semesters as $sem): ?>
                            <option value="<?php echo $sem; ?>" <?php if (isset($_POST['semester']) && $_POST['semester'] == $sem) echo 'selected'; ?>>
                                Semester <?php echo $sem; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">View Charts</button>
                </form>

                <?php if (isset($_POST['faculty']) && isset($_POST['semester'])): ?>
                    <?php
                    $faculty_id = $_POST['faculty'];
                    $semester_id = $_POST['semester'];

                    // Get session_id for the selected faculty and semester
                    $sql = "SELECT session_id FROM feedback_sessions WHERE faculty_id = ? AND semester_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $faculty_id, $semester_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $session = $result->fetch_assoc();
                    $stmt->close();

                    if ($session) {
                        $session_id = $session['session_id'];

                        // Count sentiments (exclude pending)
                        $sql = "SELECT sentiment, COUNT(*) as count FROM feedback_responses WHERE session_id = ? AND teacher_id = ? AND sentiment != 'pending' GROUP BY sentiment";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $session_id, $teacher_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $sentiments = $result->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();

                        $positive = 0;
                        $neutral = 0;
                        $negative = 0;
                        foreach ($sentiments as $sent) {
                            if ($sent['sentiment'] == 'positive') $positive = $sent['count'];
                            elseif ($sent['sentiment'] == 'neutral') $neutral = $sent['count'];
                            elseif ($sent['sentiment'] == 'negative') $negative = $sent['count'];
                        }
                    } else {
                        $positive = $neutral = $negative = 0;
                    }
                    ?>
                    <div class="chart-container">
                        <canvas id="sentimentChart"></canvas>
                    </div>
                    <script>
                        const ctx = document.getElementById('sentimentChart').getContext('2d');
                        const sentimentChart = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: ['Positive', 'Neutral', 'Negative'],
                                datasets: [{
                                    label: 'Feedback Sentiments',
                                    data: [<?php echo $positive; ?>, <?php echo $neutral; ?>, <?php echo $negative; ?>],
                                    backgroundColor: [
                                        'rgba(75, 192, 192, 0.2)',
                                        'rgba(255, 206, 86, 0.2)',
                                        'rgba(255, 99, 132, 0.2)'
                                    ],
                                    borderColor: [
                                        'rgba(75, 192, 192, 1)',
                                        'rgba(255, 206, 86, 1)',
                                        'rgba(255, 99, 132, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        text: 'Feedback Sentiment Distribution'
                                    }
                                }
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Page switching logic
        const navLinks = document.querySelectorAll('.nav-links a');
        const pages = document.querySelectorAll('.page');

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const pageId = this.getAttribute('data-page');

                // Remove active class from all links and pages
                navLinks.forEach(l => l.classList.remove('active'));
                pages.forEach(p => p.classList.remove('active'));

                // Add active class to clicked link and corresponding page
                this.classList.add('active');
                document.getElementById(pageId + '-page').classList.add('active');
            });
        });
    </script>
</body>
</html>