#!/usr/bin/env python3
"""
Direct Python wrapper for sentiment classification
Run this from PHP using exec() or shell_exec()

Usage: python run_classifier.py "text to analyze"
"""

import pickle
import sys
import os

def main():
    if len(sys.argv) < 2:
        print("neutral")  # Default to neutral
        return
    
    # Get the text from command line
    text = sys.argv[1]
    
    # Get current directory and load models
    current_dir = os.path.dirname(os.path.abspath(__file__))
    
    try:
        # Load vectorizer
        with open(os.path.join(current_dir, 'tfidf_vectorizer.pkl'), 'rb') as f:
            vectorizer = pickle.load(f)
        
        # Load model
        with open(os.path.join(current_dir, 'logistic_regression_model.pkl'), 'rb') as f:
            model = pickle.load(f)
        
        # Transform and predict
        vectorized_text = vectorizer.transform([text])
        prediction = model.predict(vectorized_text)[0]
        
        # Map to sentiment (0=negative, 1=neutral, 2=positive)
        sentiment_map = {0: 'negative', 1: 'neutral', 2: 'positive'}
        sentiment = sentiment_map.get(prediction, 'neutral')
        
        print(sentiment)
        
    except Exception as e:
        print(f"Error: {e}")
        print("neutral")  # Fallback to neutral

if __name__ == "__main__":
    main()
