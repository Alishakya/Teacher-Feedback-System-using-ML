<?php
/**
 * Sentiment Analysis Wrapper
 * 
 * Uses PHP-based ML classifier (no Python needed)
 * 
 * Flow:
 * 1. Student submits feedback → stored in database with 'pending'
 * 2. This function calls PHP ML classifier for prediction
 * 3. Predicted sentiment saved to database
 * 4. Admin and Teacher view results
 */

require_once __DIR__ . '/ml_classifier.php';

/**
 * Classify the sentiment of given text using PHP ML model
 * 
 * @param string $text The feedback text to analyze
 * @return string Sentiment: 'positive', 'neutral', or 'negative'
 */
function classify_sentiment($text) {
    $text = trim($text);
    
    if (empty($text)) {
        return 'neutral';
    }
    
    // Use PHP ML classifier
    $sentiment = classify_sentiment($text);
    
    // Log for debugging
    error_log("ML Classification: " . substr($text, 0, 50) . " → $sentiment");
    
    return $sentiment;
}

/**
 * Get sentiment label for display
 */
function get_sentiment_label($sentiment) {
    $labels = [
        'positive' => 'Positive 😊',
        'neutral' => 'Neutral 😐',
        'negative' => 'Negative 😞'
    ];
    return $labels[strtolower($sentiment)] ?? 'Unknown';
}

/**
 * Get sentiment color for UI
 */
function get_sentiment_color($sentiment) {
    $colors = [
        'positive' => '#28a745',
        'neutral' => '#17a2b8',
        'negative' => '#dc3545'
    ];
    return $colors[strtolower($sentiment)] ?? '#6c757d';
}
?>
