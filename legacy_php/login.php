<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Determine the correct query based on the role
    if ($role == 'admin') {
        $query = "SELECT admin_id, username, password FROM admin WHERE username = ?";
    } elseif ($role == 'student') {
        $query = "SELECT student_id, uid, password FROM student WHERE uid = ?";
    } elseif ($role == 'teacher') {
        $query = "SELECT teacher_id, tid, password FROM teacher WHERE tid = ?";
    } else {
        $error = "Invalid role selected.";
    }

    if (!isset($error)) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("Query failed: " . $conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Verify password (Assuming passwords are hashed)
            if ($password === $user['password']) {
                $_SESSION['role'] = $role;

                // Store relevant session variables
                if ($role == 'admin') {
                    $_SESSION['admin_id'] = $user['admin_id'];
                    $_SESSION['username'] = $user['username'];
                    header('Location: index.php');
                } elseif ($role == 'student') {
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['uid'] = $user['uid'];
                    header('Location: student_panel.php');
                } elseif ($role == 'teacher') {
                    $_SESSION['teacher_id'] = $user['teacher_id'];
                    $_SESSION['tid'] = $user['tid'];
                    header('Location: teacher_panel.php');
                }
                exit();
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "User not found.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduAdmin Login</title>
</head>

<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
            <div class="form-group">
                <label for="username">Username/UID/TID:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">Login</button>
        </form>
    </div>
    <style>
        body {
            background-image: url('University.jpg');
            /* Adjust the path if needed */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            font-family: 'Inter', sans-serif;
            /* background-color: #f4f4f4; */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.7);
            /* White with 70% opacity */
            border-radius: 10px;
            /* Optional: Adds rounded corners */
            backdrop-filter: blur(5px);
            /* Optional: Adds a blur effect to the background */
            /* background-color: #fff; */
            padding: 20px;
            /* border-radius: 8px; */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }

        .login-container h1 {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }

        .submit-btn {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 4px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background-color: #0056b3;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</body>

</html>