<?php
/**
 * PHP ML Classifier using exported JSON model
 * 
 * This loads the trained ML model from model.json (exported from pickle files).
 * 
 * Usage:
 * 1. Run: python export_model.py  (to create model.json from pickle files)
 * 2. This PHP classifier uses model.json for predictions
 */

class MLClassifier {
    private $vectorizer = [];
    private $model = [];
    private $classes = [];
    
    public function __construct($model_path = null) {
        if ($model_path === null) {
            $model_path = __DIR__ . '/model.json';
        }
        
        if (!file_exists($model_path)) {
            throw new Exception("Model file not found: $model_path\n"
                . "Please run: python export_model.py\n"
                . "This will create model.json from your pickle files.");
        }
        
        $json = file_get_contents($model_path);
        $data = json_decode($json, true);
        
        if (empty($data)) {
            throw new Exception("Failed to load model file");
        }
        
        $this->vectorizer = $data['vectorizer'] ?? [];
        $this->model = $data['model'] ?? [];
        $this->classes = $this->model['classes'] ?? [0, 1, 2];
    }
    
    /**
     * Transform text using TF-IDF vectorizer
     */
    private function transformText($text) {
        $vocabulary = $this->vectorizer['vocabulary'] ?? [];
        $idf = $this->vectorizer['idf'] ?? [];
        
        // Tokenize and clean text
        $text = strtolower(preg_replace('/[^\w\s]/', '', $text));
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Count word frequencies
        $word_counts = array_count_values($words);
        
        // Create feature vector
        $vector = [];
        foreach ($vocabulary as $word => $idx) {
            $tf = $word_counts[$word] ?? 0;
            $idf_val = $idf[$idx] ?? 1.0;
            $vector[$idx] = $tf * $idf_val;
        }
        
        return $vector;
    }
    
    /**
     * Predict sentiment for given text
     */
    public function predict($text) {
        // Transform text to TF-IDF features
        $features = $this->transformText($text);
        
        // Get model parameters
        $coef = $this->model['coef'] ?? [];
        $intercept = $this->model['intercept'] ?? 0.0;
        
        // Calculate prediction scores for each class
        $scores = [];
        foreach ($this->classes as $class_idx => $class) {
            $score = $intercept;
            
            // Handle different coef formats
            if (isset($coef[$class_idx])) {
                $class_coef = $coef[$class_idx];
                foreach ($features as $feature_idx => $feature_value) {
                    if (isset($class_coef[$feature_idx])) {
                        $score += $feature_value * $class_coef[$feature_idx];
                    }
                }
            } elseif (is_array($coef) && count($coef) > 0 && is_numeric($coef[0])) {
                // 1D coef array
                foreach ($features as $feature_idx => $feature_value) {
                    if (isset($coef[$feature_idx])) {
                        $score += $feature_value * $coef[$feature_idx];
                    }
                }
            }
            
            $scores[$class] = $score;
        }
        
        // Find class with highest score
        $predicted_class = 'neutral';
        $max_score = -PHP_FLOAT_MAX;
        
        foreach ($scores as $class => $score) {
            if ($score > $max_score) {
                $max_score = $score;
                $predicted_class = $class;
            }
        }
        
        // Map numeric class to sentiment label
        $sentiment_map = [0 => 'negative', 1 => 'neutral', 2 => 'positive'];
        return $sentiment_map[$predicted_class] ?? 'neutral';
    }
    
    /**
     * Get prediction probabilities
     */
    public function predictProba($text) {
        $features = $this->transformText($text);
        $coef = $this->model['coef'] ?? [];
        $intercept = $this->model['intercept'] ?? 0.0;
        
        $scores = [];
        foreach ($this->classes as $class_idx => $class) {
            $score = $intercept;
            
            if (isset($coef[$class_idx])) {
                $class_coef = $coef[$class_idx];
                foreach ($features as $feature_idx => $feature_value) {
                    if (isset($class_coef[$feature_idx])) {
                        $score += $feature_value * $class_coef[$feature_idx];
                    }
                }
            }
            
            $scores[$class] = $score;
        }
        
        // Softmax to probabilities
        $max_score = max($scores);
        $exp_scores = [];
        $total = 0;
        
        foreach ($scores as $class => $score) {
            $exp_scores[$class] = exp($score - $max_score);
            $total += $exp_scores[$class];
        }
        
        $proba = [];
        foreach ($exp_scores as $class => $exp_score) {
            $proba[$class] = $exp_score / $total;
        }
        
        return $proba;
    }
}

/**
 * Main classification function
 */
function classify_sentiment($text) {
    static $classifier = null;
    
    if ($classifier === null) {
        try {
            $model_path = __DIR__ . '/model.json';
            $classifier = new MLClassifier($model_path);
        } catch (Exception $e) {
            error_log("ML Classifier Error: " . $e->getMessage());
            return 'neutral';
        }
    }
    
    $text = trim($text);
    if (empty($text)) {
        return 'neutral';
    }
    
    $sentiment = $classifier->predict($text);
    error_log("ML Model: " . substr($text, 0, 50) . " → $sentiment");
    
    return $sentiment;
}

function get_sentiment_proba($text) {
    static $classifier = null;
    
    if ($classifier === null) {
        try {
            $model_path = __DIR__ . '/model.json';
            $classifier = new MLClassifier($model_path);
        } catch (Exception $e) {
            return ['negative' => 0.33, 'neutral' => 0.34, 'positive' => 0.33];
        }
    }
    
    return $classifier->predictProba($text);
}

function get_sentiment_label($sentiment) {
    $labels = [
        'positive' => 'Positive 😊',
        'neutral' => 'Neutral 😐',
        'negative' => 'Negative 😞'
    ];
    return $labels[strtolower($sentiment)] ?? 'Unknown';
}

function get_sentiment_color($sentiment) {
    $colors = [
        'positive' => '#28a745',
        'neutral' => '#17a2b8',
        'negative' => '#dc3545'
    ];
    return $colors[strtolower($sentiment)] ?? '#6c757d';
}

// CLI usage
if (php_sapi_name() === 'cli') {
    echo "PHP ML Classifier (using model.json)\n";
    
    $model_path = __DIR__ . '/model.json';
    if (!file_exists($model_path)) {
        echo "ERROR: model.json not found!\n";
        echo "Please run: python export_model.py\n";
        exit(1);
    }
    
    if (isset($argv[1])) {
        $sentiment = classify_sentiment($argv[1]);
        $proba = get_sentiment_proba($argv[1]);
        
        echo "Text: {$argv[1]}\n";
        echo "Sentiment: $sentiment\n";
        echo "Probabilities:\n";
        foreach ($proba as $class => $p) {
            echo "  $class: " . round($p * 100, 2) . "%\n";
        }
    }
}
?>
