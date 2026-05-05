<?php
/**
 * PHP Sentiment Analysis API
 * 
 * Uses PHP-based ML classifier (no Python needed)
 * 
 * Flow: Student submits feedback → PHP API → PHP ML Model → Returns sentiment
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON response
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    exit;
}

// Include PHP ML classifier
require_once __DIR__ . '/ml_classifier.php';

// Get input text
$input = json_decode(file_get_contents('php://input'), true);
$text = $input['text'] ?? '';

if (empty($text)) {
    echo json_encode(['success' => false, 'error' => 'No text provided']);
    exit;
}

// Classify sentiment using PHP ML model
$sentiment = classify_sentiment($text);

// Log for debugging
error_log("API Classification: " . substr($text, 0, 50) . " → $sentiment");

echo json_encode([
    'success' => true,
    'sentiment' => $sentiment,
    'mode' => 'php_ml'
]);
?>
