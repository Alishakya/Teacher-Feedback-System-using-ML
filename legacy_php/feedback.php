<?php
include 'db.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$teachers = [];

// Handle form submission for creating feedback session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_session'])) {
    $faculty_id = $_POST['faculty_id'] ?? '';
    $semester_id = $_POST['semester_id'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    if (empty($faculty_id) || empty($semester_id) || empty($end_date)) {
        $message = 'All fields are required.';
    } elseif (strtotime($end_date) <= time()) {
        $message = 'End date must be in the future.';
    } else {
        $start_date = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO feedback_sessions (faculty_id, semester_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("iiss", $faculty_id, $semester_id, $start_date, $end_date);
        if ($stmt->execute()) {
            $message = 'Feedback session created successfully.';
            $teacher_stmt = $conn->prepare("SELECT t.name, t.contact FROM teacher_assignment ta JOIN teacher t ON ta.teacher_id = t.teacher_id WHERE ta.faculty_id = ? AND ta.semester_id = ?");
            $teacher_stmt->bind_param("ii", $faculty_id, $semester_id);
            $teacher_stmt->execute();
            $result = $teacher_stmt->get_result();
            $teachers = $result->fetch_all(MYSQLI_ASSOC);
            $teacher_stmt->close();
        } else {
            $message = 'Error creating session: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Handle close session action
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];
    $stmt = $conn->prepare("UPDATE feedback_sessions SET status = 'closed' WHERE session_id = ?");
    $stmt->bind_param("i", $session_id);
    if ($stmt->execute()) {
        $message = 'Session closed successfully.';
    }
    $stmt->close();
}

// Fetch faculties and semesters
$faculties = $conn->query("SELECT * FROM faculty");
$semesters = $conn->query("SELECT * FROM semester");
$faculty_list = [];
while ($row = $faculties->fetch_assoc()) {
    $faculty_list[] = $row;
}
$semester_list = [];
while ($row = $semesters->fetch_assoc()) {
    $semester_list[] = $row;
}

// Fetch all feedback sessions
$sessions = $conn->query("SELECT fs.session_id, f.faculty_name, s.semester_number, fs.start_date, fs.end_date, fs.status FROM feedback_sessions fs JOIN faculty f ON fs.faculty_id = f.faculty_id JOIN semester s ON fs.semester_id = s.semester_id ORDER BY fs.created_at DESC");
$session_list = [];
while ($row = $sessions->fetch_assoc()) {
    $session_list[] = $row;
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$sessions_per_page = 5;
$total_sessions = count($session_list);
$total_pages = ceil($total_sessions / $sessions_per_page);
$offset = ($page - 1) * $sessions_per_page;
$paginated_sessions = array_slice($session_list, $offset, $sessions_per_page);

// Get selected session for charts
$selected_session = isset($_GET['session_id']) ? intval($_GET['session_id']) : (count($paginated_sessions) > 0 ? $paginated_sessions[0]['session_id'] : 0);

// Get session overview data
$session_overview = null;
$teacher_results = [];
if ($selected_session) {
    // Get session overview (exclude pending sentiments)
    $stmt = $conn->prepare("
        SELECT 
            fs.session_id, f.faculty_name, s.semester_number,
            fs.start_date, fs.end_date, fs.status,
            COUNT(DISTINCT fr.student_id) AS students_responded,
            COUNT(DISTINCT st.student_id) AS total_students,
            COUNT(fr.response_id) AS total_responses
        FROM feedback_sessions fs
        LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id
        LEFT JOIN semester s ON fs.semester_id = s.semester_id
        LEFT JOIN feedback_responses fr ON fs.session_id = fr.session_id AND fr.sentiment != 'pending'
        LEFT JOIN student st ON fs.faculty_id = st.faculty_id AND fs.semester_id = st.semester_id
        WHERE fs.session_id = ?
        GROUP BY fs.session_id, f.faculty_name, s.semester_number, fs.start_date, fs.end_date, fs.status
    ");
    $stmt->bind_param("i", $selected_session);
    $stmt->execute();
    $result = $stmt->get_result();
    $session_overview = $result->fetch_assoc();
    $stmt->close();
    
    // Get teacher results with percentages (exclude pending)
    $stmt = $conn->prepare("
        SELECT 
            t.teacher_id, t.name AS teacher_name, t.image,
            COUNT(fr.response_id) AS total_feedback,
            SUM(CASE WHEN fr.sentiment = 'positive' THEN 1 ELSE 0 END) AS positive_count,
            SUM(CASE WHEN fr.sentiment = 'neutral' THEN 1 ELSE 0 END) AS neutral_count,
            SUM(CASE WHEN fr.sentiment = 'negative' THEN 1 ELSE 0 END) AS negative_count
        FROM teacher t
        INNER JOIN teacher_assignment ta ON t.teacher_id = ta.teacher_id
        INNER JOIN feedback_sessions fs ON ta.faculty_id = fs.faculty_id AND ta.semester_id = fs.semester_id
        LEFT JOIN feedback_responses fr ON t.teacher_id = fr.teacher_id AND fr.session_id = fs.session_id AND fr.sentiment != 'pending'
        WHERE fs.session_id = ?
        GROUP BY t.teacher_id, t.name, t.image
        ORDER BY t.name
    ");
    $stmt->bind_param("i", $selected_session);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $total = $row['total_feedback'];
        $row['positive_percentage'] = $total > 0 ? round($row['positive_count'] * 100 / $total, 1) : 0;
        $row['neutral_percentage'] = $total > 0 ? round($row['neutral_count'] * 100 / $total, 1) : 0;
        $row['negative_percentage'] = $total > 0 ? round($row['negative_count'] * 100 / $total, 1) : 0;
        $teacher_results[] = $row;
    }
    $stmt->close();
    
    // Get overall sentiment distribution for charts (exclude pending)
    $overall_stmt = $conn->prepare("SELECT sentiment, COUNT(*) as count FROM feedback_responses WHERE session_id = ? AND sentiment != 'pending' GROUP BY sentiment");
    $overall_stmt->bind_param("i", $selected_session);
    $overall_stmt->execute();
    $overall_result = $overall_stmt->get_result();
    $overall_data = [];
    while ($row = $overall_result->fetch_assoc()) {
        $overall_data[$row['sentiment']] = $row['count'];
    }
    $overall_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduAdmin - Feedback Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    .message { padding: 10px; margin: 10px 0; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; }
    .error { background: #f8d7da; color: #721c24; }
    
    /* Dashboard Cards */
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .dashboard-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .dashboard-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .dashboard-card.blue { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }
    .dashboard-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .dashboard-card h3 { font-size: 2rem; margin: 0; font-weight: 700; }
    .dashboard-card p { margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9rem; }
    
    /* Teacher Results Table */
    .results-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .results-table th, .results-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
    .results-table th { background: #f8f9fa; font-weight: 600; }
    .results-table tr:hover { background: #f5f5f5; }
    .results-table .teacher-cell { display: flex; align-items: center; gap: 10px; }
    .results-table .teacher-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    
    /* Percentage Bars */
    .percentage-bar {
        display: flex;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 5px;
    }
    .bar-positive { background: #28a745; }
    .bar-neutral { background: #17a2b8; }
    .bar-negative { background: #dc3545; }
    .percentage-labels { display: flex; gap: 15px; font-size: 0.85rem; margin-top: 5px; }
    .label-positive { color: #28a745; }
    .label-neutral { color: #17a2b8; }
    .label-negative { color: #dc3545; }
    
    /* Session Cards */
    .session-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .session-card:hover { border-color: #667eea; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .session-card.active { border-left: 4px solid #28a745; }
    .session-card.closed { border-left: 4px solid #dc3545; opacity: 0.7; }
    .session-card.selected { background: #f8f9ff; border-color: #667eea; }
    
    /* Pagination */
    .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
    .pagination a {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s;
    }
    .pagination a:hover, .pagination a.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    /* Charts Section */
    .charts-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-top: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .chart-row { display: flex; flex-wrap: wrap; gap: 30px; margin-top: 20px; }
    .chart-container { flex: 1; min-width: 300px; }
    .chart-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; color: #333; }
    
    /* Tab Navigation */
    .tab-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }
    .tab-nav a {
        padding: 10px 20px;
        border-radius: 8px 8px 0 0;
        text-decoration: none;
        color: #666;
        font-weight: 500;
        transition: all 0.3s;
    }
    .tab-nav a.active {
        background: #667eea;
        color: white;
    }
    .tab-nav a:hover:not(.active) { background: #f0f0f0; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="logo">
        <h2>EduAdmin</h2>
    </div>
    <div class="nav-links">
        <a href="index.php" data-page="dashboard"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="student.php" data-page="students"><i class="fas fa-user-graduate"></i> Students</a>
        <a href="teacher.php" data-page="teachers"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
        <a href="faculty.php" data-page="faculty"><i class="fas fa-university"></i> Faculty</a>
        <a href="feedback.php" class="active" data-page="feedback"><i class="fas fa-comments"></i> Feedback</a>
    </div>
    <button class="logout-btn" onclick="window.location.href='logout_admin.php'">
        <i class="fas fa-sign-out-alt"></i> Log Out
    </button>
</aside>

<main>
    <div class="header">
        <h1>Feedback Analytics Dashboard</h1>
    </div>
    
    <div class="content">
        <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="#create" class="tab-link active" data-tab="create">Create Session</a>
            <a href="#sessions" class="tab-link" data-tab="sessions">Sessions</a>
            <a href="#results" class="tab-link" data-tab="results">Teacher Results</a>
        </div>
        
        <!-- Create Session Tab -->
        <div id="create" class="tab-content active">
            <div class="section">
                <h2>Create Feedback Session</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="faculty_id">Faculty</label>
                        <select id="faculty_id" name="faculty_id" required>
                            <option value="">Select Faculty</option>
                            <?php foreach ($faculty_list as $faculty): ?>
                                <option value="<?php echo $faculty['faculty_id']; ?>"><?php echo htmlspecialchars($faculty['faculty_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="semester_id">Semester</label>
                        <select id="semester_id" name="semester_id" required>
                            <option value="">Select Semester</option>
                            <?php foreach ($semester_list as $semester): ?>
                                <option value="<?php echo $semester['semester_id']; ?>"><?php echo htmlspecialchars($semester['semester_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" name="create_session" class="add-btn">Create Session</button>
                </form>
                
                <?php if (!empty($teachers)): ?>
                <h3>Assigned Teachers</h3>
                <table>
                    <thead><tr><th>Name</th><th>Contact</th></tr></thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['contact']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sessions Tab with Pagination -->
        <div id="sessions" class="tab-content">
            <h2>Feedback Sessions</h2>
            <?php if (empty($session_list)): ?>
                <p>No feedback sessions found.</p>
            <?php else: ?>
                <?php foreach ($paginated_sessions as $session): ?>
                <div class="session-card <?php echo $session['status']; ?> <?php echo $session['session_id'] == $selected_session ? 'selected' : ''; ?>" 
                     onclick="window.location.href='?session_id=<?php echo $session['session_id']; ?>&page=<?php echo $page; ?>&tab=sessions'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo htmlspecialchars($session['faculty_name']); ?> - Semester <?php echo $session['semester_number']; ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">
                                <?php echo $session['start_date']; ?> to <?php echo $session['end_date']; ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge <?php echo $session['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                            <?php if ($session['status'] == 'active'): ?>
                            <br><a href="?action=close&session_id=<?php echo $session['session_id']; ?>&tab=sessions" 
                                   style="color: #dc3545; font-size: 0.8rem;" onclick="return confirm('Close this session?');">Close</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&session_id=<?php echo $selected_session; ?>&tab=sessions">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&session_id=<?php echo $selected_session; ?>&tab=sessions" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&session_id=<?php echo $selected_session; ?>&tab=sessions">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Teacher Results Tab -->
        <div id="results" class="tab-content">
            <?php if ($session_overview && $selected_session): ?>
                <!-- Dashboard Cards -->
                <div class="dashboard-cards">
                    <div class="dashboard-card blue">
                        <h3><?php echo $session_overview['faculty_name']; ?></h3>
                        <p>Semester <?php echo $session_overview['semester_number']; ?></p>
                    </div>
                    <div class="dashboard-card green">
                        <h3><?php echo $session_overview['students_responded']; ?>/<?php echo $session_overview['total_students']; ?></h3>
                        <p>Students Responded</p>
                    </div>
                    <div class="dashboard-card orange">
                        <h3><?php echo $session_overview['total_responses']; ?></h3>
                        <p>Total Feedback</p>
                    </div>
                    <div class="dashboard-card">
                        <h3><?php echo $session_overview['status'] == 'active' ? '🟢' : '🔴'; ?></h3>
                        <p><?php echo ucfirst($session_overview['status']); ?></p>
                    </div>
                </div>
                
                <!-- Teacher Results Table -->
                <h3>Teacher Performance Results</h3>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Total Feedback</th>
                            <th>Positive</th>
                            <th>Neutral</th>
                            <th>Negative</th>
                            <th>Sentiment Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teacher_results as $teacher): ?>
                        <tr>
                            <td>
                                <div class="teacher-cell">
                                    <img src="uploads/<?php echo htmlspecialchars($teacher['image'] ?? 'default.png'); ?>" 
                                         class="teacher-img" alt="<?php echo htmlspecialchars($teacher['teacher_name']); ?>">
                                    <span><?php echo htmlspecialchars($teacher['teacher_name']); ?></span>
                                </div>
                            </td>
                            <td><strong><?php echo $teacher['total_feedback']; ?></strong></td>
                            <td>
                                <span class="label-positive"><?php echo $teacher['positive_count']; ?> (<?php echo $teacher['positive_percentage']; ?>%)</span>
                            </td>
                            <td>
                                <span class="label-neutral"><?php echo $teacher['neutral_count']; ?> (<?php echo $teacher['neutral_percentage']; ?>%)</span>
                            </td>
                            <td>
                                <span class="label-negative"><?php echo $teacher['negative_count']; ?> (<?php echo $teacher['negative_percentage']; ?>%)</span>
                            </td>
                            <td style="min-width: 200px;">
                                <div class="percentage-bar">
                                    <div class="bar-positive" style="width: <?php echo $teacher['positive_percentage']; ?>%"></div>
                                    <div class="bar-neutral" style="width: <?php echo $teacher['neutral_percentage']; ?>%"></div>
                                    <div class="bar-negative" style="width: <?php echo $teacher['negative_percentage']; ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Charts Section -->
                <div class="charts-section">
                    <h3>Analytics Charts</h3>
                    <div class="chart-row">
                        <div class="chart-container">
                            <div class="chart-title">Overall Sentiment Distribution</div>
                            <canvas id="overallChart" width="400" height="300"></canvas>
                        </div>
                        <div class="chart-container">
                            <div class="chart-title">Participation Overview</div>
                            <canvas id="participationChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                    
                    <h4 style="margin-top: 30px;">Teacher-wise Sentiment</h4>
                    <div class="chart-row" style="flex-wrap: wrap;">
                        <?php foreach ($teacher_results as $index => $teacher): ?>
                        <div class="chart-container" style="min-width: 250px; max-width: 300px;">
                            <div class="chart-title"><?php echo htmlspecialchars($teacher['teacher_name']); ?></div>
                            <canvas id="teacherChart<?php echo $index; ?>" width="250" height="250"></canvas>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <script>
                // Overall Sentiment Chart
                const overallCtx = document.getElementById('overallChart').getContext('2d');
                new Chart(overallCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Positive', 'Neutral', 'Negative'],
                        datasets: [{
                            data: [
                                <?php echo $overall_data['positive'] ?? 0; ?>,
                                <?php echo $overall_data['neutral'] ?? 0; ?>,
                                <?php echo $overall_data['negative'] ?? 0; ?>
                            ],
                            backgroundColor: ['#28a745', '#17a2b8', '#dc3545'],
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });
                
                // Participation Chart
                const partCtx = document.getElementById('participationChart').getContext('2d');
                new Chart(partCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Students'],
                        datasets: [
                            { label: 'Responded', data: [<?php echo $session_overview['students_responded']; ?>], backgroundColor: '#28a745' },
                            { label: 'Not Responded', data: [<?php echo max(0, $session_overview['total_students'] - $session_overview['students_responded']); ?>], backgroundColor: '#dc3545' }
                        ]
                    },
                    options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
                });
                
                // Teacher Charts
                <?php foreach ($teacher_results as $index => $teacher): ?>
                const teacherCtx<?php echo $index; ?> = document.getElementById('teacherChart<?php echo $index; ?>').getContext('2d');
                new Chart(teacherCtx<?php echo $index; ?>, {
                    type: 'pie',
                    data: {
                        labels: ['Positive', 'Neutral', 'Negative'],
                        datasets: [{
                            data: [<?php echo $teacher['positive_count']; ?>, <?php echo $teacher['neutral_count']; ?>, <?php echo $teacher['negative_count']; ?>],
                            backgroundColor: ['#28a745', '#17a2b8', '#dc3545'],
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });
                <?php endforeach; ?>
                </script>
            <?php else: ?>
                <p>Select a session to view teacher results.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Tab switching
document.querySelectorAll('.tab-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.tab).classList.add('active');
    });
});

// Check URL params for active tab
<?php if (isset($_GET['tab'])): ?>
document.querySelector('[data-tab="<?php echo $_GET['tab']; ?>"]').click();
<?php endif; ?>
</script>

</body>
</html>
