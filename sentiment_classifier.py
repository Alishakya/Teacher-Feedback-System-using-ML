import pickle
import sys
import os

# Get the directory where this script is located
script_dir = os.path.dirname(os.path.abspath(__file__))

try:
    # Load the models from the same directory as this script
    vectorizer_path = os.path.join(script_dir, 'tfidf_vectorizer.pkl')
    model_path = os.path.join(script_dir, 'logistic_regression_model.pkl')
    
    with open(vectorizer_path, 'rb') as f:
        vectorizer = pickle.load(f)
    
    with open(model_path, 'rb') as f:
        model = pickle.load(f)
    
    # Get input text from command line
    if len(sys.argv) < 2:
        print("Error: No input text provided")
        sys.exit(1)
    
    # Get the text argument
    text = sys.argv[1]
    
    # Vectorize the text
    vectorized_text = vectorizer.transform([text])
    
    # Predict the sentiment
    prediction = model.predict(vectorized_text)[0]
    
    # Map prediction to sentiment labels (0=negative, 1=neutral, 2=positive)
    sentiment_map = {0: 'negative', 1: 'neutral', 2: 'positive'}
    sentiment = sentiment_map.get(prediction, 'unknown')
    
    # Output the sentiment
    print(sentiment)
    
except FileNotFoundError as e:
    print(f"Error: Model file not found - {e}")
    sys.exit(2)
except Exception as e:
    print(f"Error: {e}")
    sys.exit(3)
