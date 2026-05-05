<?php
/**
 * Test script for PHP ML Sentiment Classifier
 * Run this in browser: http://localhost/test_sentiment.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the PHP ML classifier
require_once 'ml_classifier.php';

$test_texts = [
    // Positive
    'This teacher is amazing and very helpful!',
    'Great teaching style, I love this class',
    'Best teacher ever! Highly recommend!',
    'Very supportive and patient with students',
    'Excellent explanations, very knowledgeable',
    
    // Neutral
    'The teacher is okay but could be better',
    'Normal teaching, nothing special',
    'Average class, neither good nor bad',
    'It was fine, nothing to complain about',
    'Standard lectures, as expected',
    
    // Negative
    'Boring lectures, never explains clearly',
    'Terrible teacher, always late and rude',
    'Worst experience ever, very disappointed',
    'Very confusing and unhelpful',
    'Rude behavior and poor communication'
];

echo "<h1>PHP ML Sentiment Classifier Test</h1>";
echo "<p>Testing " . count($test_texts) . " text samples...</p>";

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Text</th><th>Sentiment</th><th>Probabilities</th></tr>";

foreach ($test_texts as $text) {
    $sentiment = classify_sentiment($text);
    $proba = get_sentiment_proba($text);
    $color = get_sentiment_color($sentiment);
    $label = get_sentiment_label($sentiment);
    
    // Format probabilities
    $proba_str = "";
    foreach ($proba as $class => $p) {
        $proba_str .= ucfirst($class) . ": " . round($p * 100, 1) . "% ";
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($text) . "</td>";
    echo "<td style='background: $color; color: white; font-weight: bold; text-align: center;'>$label</td>";
    echo "<td><small>$proba_str</small></td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Expected Results:</h2>";
echo "<ul>";
echo "<li><span style='color: #28a745;'>Positive 😊</span>: Amazing, great, excellent, best, helpful...</li>";
echo "<li><span style='color: #17a2b8;'>Neutral 😐</span>: Okay, average, normal, fine...</li>";
echo "<li><span style='color: #dc3545;'>Negative 😞</span>: Boring, terrible, worst, rude, confusing...</li>";
echo "</ul>";

echo "<p><a href='process_feedback.php'>Process Pending Feedbacks</a></p>";
echo "<p><a href='feedback.php'>Go to Admin Dashboard</a></p>";
?>
