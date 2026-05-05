<?php
session_start();
include 'db.php';
include 'sentiment_wrapper.php';

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$sql = "SELECT s.username, s.uid, s.contact, s.image, s.semester_id, s.faculty_id, f.faculty_name
        FROM student s
        JOIN faculty f ON s.faculty_id = f.faculty_id
        WHERE s.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'];
    $teacher_id = $_POST['teacher_id'];
    $feedback_text = trim($_POST['feedback_text']);
    
    if (!empty($feedback_text)) {
        // STEP 1: Store feedback in database first (with pending sentiment)
        $sql = "SELECT response_id FROM feedback_responses WHERE session_id = ? AND student_id = ? AND teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $session_id, $student_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            // Update existing feedback text (keep old sentiment for now)
            $sql = "UPDATE feedback_responses SET feedback_text = ?, sentiment_updated = 0 WHERE session_id = ? AND student_id = ? AND teacher_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siii", $feedback_text, $session_id, $student_id, $teacher_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert new feedback with pending sentiment
            $sql = "INSERT INTO feedback_responses (session_id, student_id, teacher_id, feedback_text, sentiment) VALUES (?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $session_id, $student_id, $teacher_id, $feedback_text);
            $stmt->execute();
            $stmt->close();
        }
        
        // STEP 2: Send feedback to sentiment prediction model
        $sentiment = classify_sentiment($feedback_text);
        
        // STEP 3: Store predicted sentiment back to database
        $sql = "UPDATE feedback_responses SET sentiment = ?, sentiment_updated = 1, updated_at = NOW() WHERE session_id = ? AND student_id = ? AND teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $sentiment, $session_id, $student_id, $teacher_id);
        $stmt->execute();
        $stmt->close();
        
        $message = 'Feedback submitted and analyzed successfully!';
    } else {
        $message = 'Feedback text cannot be empty.';
    }
}

// Fetch teachers assigned to the student's faculty and semester
$sql = "SELECT t.teacher_id, t.name, t.image, t.contact FROM teacher_assignment ta
        JOIN teacher t ON ta.teacher_id = t.teacher_id
        WHERE ta.faculty_id = ? AND ta.semester_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student['faculty_id'], $student['semester_id']);
$stmt->execute();
$result = $stmt->get_result();
$teachers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch active feedback sessions
$sql = "SELECT * FROM feedback_sessions WHERE faculty_id = ? AND semester_id = ? AND status = 'active' AND end_date >= CURDATE()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student['faculty_id'], $student['semester_id']);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sstyles.css">
</head>
<body>
    <aside class="sidebar">
        <div class="logo">
            <h2>Student Portal</h2>
        </div>
        <div class="nav-links">
            <a href="#" class="active" data-page="profile">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="#" data-page="feedback">
                <i class="fas fa-comments"></i> Feedback<?php if (count($sessions) > 0) { echo ' <span class="badge">' . count($sessions) . '</span>'; } ?>
            </a>
        </div>
        <a href="logout_student.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </a>
    </aside>

    <main>
        <div class="page active" id="profile-page">
            <div class="header">
                <h1>Student Profile</h1>
            </div>
            <div class="profile-container">
                <div class="profile-info">
                    <img src="uploads/<?php echo htmlspecialchars($student['image']); ?>" alt="Student Profile" class="profile-image">
                    <div class="info-details">
                        <h2><?php echo htmlspecialchars($student['username']); ?></h2>
                        <p><i class="fas fa-phone"></i> Contact: <?php echo htmlspecialchars($student['contact']); ?></p>
                        <p><i class="fas fa-id-card"></i> Uid: <?php echo htmlspecialchars($student['uid']); ?></p>
                        <p><i class="fas fa-university"></i> Faculty: <?php echo htmlspecialchars($student['faculty_name']); ?></p>
                        <p><i class="fas fa-graduation-cap"></i> Semester: <?php echo htmlspecialchars($student['semester_id']); ?></p>
                    </div>
                </div>
            </div>
            
            <h2>Assigned Teachers</h2>
            <div class="teacher-container">
                <?php foreach ($teachers as $teacher) { ?>
                    <div class="teacher-card">
                        <img src="uploads/<?php echo htmlspecialchars($teacher['image']); ?>" alt="Teacher Image" class="teacher-image">
                        <h3><?php echo htmlspecialchars($teacher['name']); ?></h3>
                        <p><i class="fas fa-phone"></i> Contact: <?php echo htmlspecialchars($teacher['contact']); ?></p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="page" id="feedback-page">
            <div class="header">
                <h1>Feedback Sessions</h1>
            </div>
            <?php if (!empty($message)) { echo "<p class='message success'>$message</p>"; } ?>
            <?php if (empty($sessions)) { ?>
                <p>No active feedback sessions available.</p>
            <?php } else { ?>
                <?php foreach ($sessions as $session) { ?>
                    <div class="session-container">
                        <h2>Session: <?php echo htmlspecialchars($session['start_date']); ?> to <?php echo htmlspecialchars($session['end_date']); ?></h2>
                        <?php foreach ($teachers as $teacher) { ?>
                            <?php
                            // Check existing feedback
                            $sql = "SELECT feedback_text, sentiment FROM feedback_responses WHERE session_id = ? AND student_id = ? AND teacher_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iii", $session['session_id'], $student_id, $teacher['teacher_id']);
                            $stmt->execute();
                            $feedback_result = $stmt->get_result();
                            $existing_feedback = $feedback_result->fetch_assoc();
                            $stmt->close();
                            
                            $sentiment_label = '';
                            $sentiment_color = '';
                            if ($existing_feedback && !empty($existing_feedback['sentiment']) && $existing_feedback['sentiment'] != 'pending') {
                                $sentiment_label = get_sentiment_label($existing_feedback['sentiment']);
                                $sentiment_color = get_sentiment_color($existing_feedback['sentiment']);
                            }
                            ?>
                            <div class="teacher-feedback">
                                <h3><?php echo htmlspecialchars($teacher['name']); ?></h3>
                                <?php if ($sentiment_label): ?>
                                    <p class="sentiment-badge" style="background: <?php echo $sentiment_color; ?>; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; font-size: 0.85rem; margin-bottom: 10px;">
                                        <?php echo $sentiment_label; ?>
                                    </p>
                                <?php endif; ?>
                                <form method="post">
                                    <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['teacher_id']; ?>">
                                    <textarea name="feedback_text" rows="4" cols="50" placeholder="Enter your feedback"><?php echo htmlspecialchars($existing_feedback['feedback_text'] ?? ''); ?></textarea><br>
                                    <button type="submit"><?php echo $existing_feedback ? 'Update Feedback' : 'Submit Feedback'; ?></button>
                                </form>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </main>

    <style>
        .teacher-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .teacher-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
            width: auto;
            text-align: center;
        }

        .teacher-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
        }

        .session-container {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .teacher-feedback {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
        }

        .teacher-feedback h3 {
            margin-top: 0;
        }

        .teacher-feedback textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .teacher-feedback button {
            margin-top: 10px;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .teacher-feedback button:hover {
            background: #0056b3;
        }

        .badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .message {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
    </style>

    <script>
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
                document.getElementById(page + '-page').classList.add('active');
                document.querySelectorAll('.nav-links a').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
