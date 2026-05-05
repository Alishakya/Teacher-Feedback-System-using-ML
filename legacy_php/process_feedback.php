<?php
/**
 * Process Pending Feedbacks Script
 * Run this manually or set as cron job to process pending feedbacks
 * 
 * This ensures:
 * 1. Student submits feedback → stored with 'pending'
 * 2. This script processes pending feedbacks → sends to ML model
 * 3. Predicted sentiment saved to database
 * 4. Admin and Teacher can view results
 * 
 * Usage: http://localhost/process_feedback.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';
include 'sentiment_wrapper.php';

echo "<h1>Processing Pending Feedbacks</h1>";

$processed = 0;
$errors = 0;

// Get all pending feedbacks
$sql = "SELECT response_id, feedback_text FROM feedback_responses WHERE sentiment = 'pending' OR sentiment_updated = 0";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<p>Found {$result->num_rows} pending feedbacks...</p>";
    echo "<ul>";
    
    while ($row = $result->fetch_assoc()) {
        $response_id = $row['response_id'];
        $feedback_text = $row['feedback_text'];
        
        echo "<li>Processing feedback ID: $response_id</li>";
        
        // Send to ML model for prediction
        $sentiment = classify_sentiment($feedback_text);
        
        // Update database with predicted sentiment
        $update_sql = "UPDATE feedback_responses SET sentiment = ?, sentiment_updated = 1, updated_at = NOW() WHERE response_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $sentiment, $response_id);
        
        if ($stmt->execute()) {
            echo "<li style='color: green;'>✓ Saved sentiment: $sentiment</li>";
            $processed++;
        } else {
            echo "<li style='color: red;'>✗ Error: " . $conn->error . "</li>";
            $errors++;
        }
        $stmt->close();
    }
    
    echo "</ul>";
    echo "<h3>Summary: $processed processed, $errors errors</h3>";
} else {
    echo "<p>No pending feedbacks found!</p>";
}

// Also process pending from student_panel submissions
echo "<hr><h3>Testing ML Model Directly:</h3>";

$test_texts = [
    'sir explains topics clearly',
    'teacher is friendly and helpful',
    'class is okay nothing special',
    'teaching is good overall',
    'Terrible teacher, always late and rude'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Text</th><th>Predicted Sentiment</th></tr>";

foreach ($test_texts as $text) {
    $sentiment = classify_sentiment($text);
    echo "<tr><td>" . substr($text, 0, 50) . "...</td><td><strong>$sentiment</strong></td></tr>";
}

echo "</table>";

echo "<p><a href='student_panel.php'>Go to Student Panel</a></p>";
echo "<p><a href='feedback.php'>Go to Admin Feedback Dashboard</a></p>";
?>
