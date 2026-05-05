"""
Sentiment Classifier for ML-based feedback analysis.
"""
import os
import pickle
import numpy as np


class SentimentClassifier:
    """ML sentiment classifier using Logistic Regression and TF-IDF."""
    
    def __init__(self, model_path, vectorizer_path):
        """
        Initialize the classifier with model and vectorizer paths.
        
        Args:
            model_path: Path to the trained logistic regression model (.pkl)
            vectorizer_path: Path to the TF-IDF vectorizer (.pkl)
        """
        self.model_path = model_path
        self.vectorizer_path = vectorizer_path
        self.model = None
        self.vectorizer = None
        # Support both integer and string labels
        self.label_mapping = {0: 'negative', 1: 'neutral', 2: 'positive'}
        self.reverse_mapping = {'negative': 0, 'neutral': 1, 'positive': 2}
        self._load_models()
    
    def _load_models(self):
        """Load the trained model and vectorizer from pickle files."""
        try:
            if os.path.exists(self.model_path):
                with open(self.model_path, 'rb') as f:
                    self.model = pickle.load(f)
            else:
                raise FileNotFoundError(f"Model file not found: {self.model_path}")
            
            if os.path.exists(self.vectorizer_path):
                with open(self.vectorizer_path, 'rb') as f:
                    self.vectorizer = pickle.load(f)
            else:
                raise FileNotFoundError(f"Vectorizer file not found: {self.vectorizer_path}")
                
        except Exception as e:
            print(f"Error loading models: {e}")
            # Create dummy models for demonstration
            self.model = None
            self.vectorizer = None
    
    def preprocess_text(self, text):
        """
        Preprocess text for sentiment analysis.
        
        Args:
            text: Raw text input
            
        Returns:
            Preprocessed text string
        """
        if not text:
            return ""
        
        # Basic preprocessing
        text = text.lower().strip()
        
        # Remove extra whitespace
        text = ' '.join(text.split())
        
        return text
    
    def predict(self, text):
        """
        Predict sentiment of the given text.
        
        Args:
            text: Input text to analyze
            
        Returns:
            Tuple of (sentiment_label, confidence_score)
        """
        if not text or not self.model or not self.vectorizer:
            return ('neutral', 0.5)
        
        try:
            # Preprocess text
            processed_text = self.preprocess_text(text)
            
            # Vectorize text
            text_vector = self.vectorizer.transform([processed_text])
            
            # Get prediction
            prediction = self.model.predict(text_vector)[0]
            
            # Handle both integer and string predictions
            if isinstance(prediction, (int, float)):
                sentiment = self.label_mapping.get(int(prediction), 'neutral')
            else:
                # Already a string, use it directly (lowercase)
                prediction_str = str(prediction).lower()
                if prediction_str in self.reverse_mapping:
                    sentiment = prediction_str
                else:
                    sentiment = 'neutral'
            
            # Get probability scores
            if hasattr(self.model, 'predict_proba'):
                try:
                    probabilities = self.model.predict_proba(text_vector)[0]
                    # Find index of predicted class
                    if isinstance(prediction, (int, float)):
                        pred_idx = int(prediction)
                    else:
                        pred_idx = self.reverse_mapping.get(prediction_str, 1)
                    confidence = float(probabilities[pred_idx]) if pred_idx < len(probabilities) else 0.5
                except:
                    confidence = 0.5
            else:
                confidence = 0.5
            
            return (sentiment, float(confidence))
            
        except Exception as e:
            print(f"Prediction error: {e}")
            return ('neutral', 0.5)
    
    def predict_batch(self, texts):
        """
        Predict sentiment for multiple texts.
        
        Args:
            texts: List of input texts
            
        Returns:
            List of (sentiment, confidence) tuples
        """
        results = []
        for text in texts:
            sentiment, confidence = self.predict(text)
            results.append((sentiment, confidence))
        return results
    
    def analyze_feedback(self, feedback_text):
        """
        Analyze feedback and return detailed results.
        
        Args:
            feedback_text: Input feedback text
            
        Returns:
            Dictionary with sentiment analysis results
        """
        if not feedback_text:
            return {
                'sentiment': 'neutral',
                'confidence': 0.0,
                'label': 'Neutral',
                'score': 0.0
            }
        
        sentiment, confidence = self.predict(feedback_text)
        
        # Calculate sentiment score (-1 to 1)
        score_map = {'positive': 1, 'neutral': 0, 'negative': -1}
        score = score_map.get(sentiment, 0) * confidence
        
        return {
            'sentiment': sentiment,
            'confidence': confidence,
            'label': sentiment.capitalize(),
            'score': score,
            'original_text': feedback_text[:100] + '...' if len(feedback_text) > 100 else feedback_text
        }
    
    def get_model_info(self):
        """Return information about the loaded model."""
        if not self.model or not self.vectorizer:
            return {
                'model_type': 'None',
                'vectorizer_type': 'None',
                'status': 'Not loaded'
            }
        
        return {
            'model_type': type(self.model).__name__,
            'vectorizer_type': type(self.vectorizer).__name__,
            'status': 'Loaded',
            'n_features': self.vectorizer.max_features if hasattr(self.vectorizer, 'max_features') else 'N/A'
        }
