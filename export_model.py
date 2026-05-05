#!/usr/bin/env python3
"""
Export ML model parameters to JSON for PHP consumption
Run this once to export your trained model:
    python export_model.py
This creates model.json that PHP can load directly.
"""
import pickle
import json
import sys
import os
import numpy as np

def export_model():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    
    # File paths
    vectorizer_path = os.path.join(script_dir, 'tfidf_vectorizer.pkl')
    model_path = os.path.join(script_dir, 'logistic_regression_model.pkl')
    
    # Check if files exist
    if not os.path.exists(vectorizer_path):
        print(f"ERROR: {vectorizer_path} not found!")
        sys.exit(1)
    
    if not os.path.exists(model_path):
        print(f"ERROR: {model_path} not found!")
        sys.exit(1)
    
    try:
        # Load vectorizer
        print("Loading vectorizer...")
        with open(vectorizer_path, 'rb') as f:
            vectorizer = pickle.load(f)
        
        # Load model
        print("Loading model...")
        with open(model_path, 'rb') as f:
            model = pickle.load(f)
        
        # Export vectorizer
        vectorizer_data = {}
        
        if hasattr(vectorizer, 'vocabulary_'):
            vectorizer_data['vocabulary'] = vectorizer.vocabulary_
            print(f"✓ Vocabulary loaded: {len(vectorizer.vocabulary_)} words")
        
        if hasattr(vectorizer, 'idf_'):
            vectorizer_data['idf'] = vectorizer.idf_.tolist()
            print(f"✓ IDF values loaded: {len(vectorizer.idf_)} features")
        
        # Export model
        model_data = {}
        
        if hasattr(model, 'coef_'):
            coef = model.coef_
            print(f"✓ Model coefficients shape: {coef.shape}")
            
            # Handle both 1D and 2D coef arrays
            if len(coef.shape) == 1:
                model_data['coef'] = coef.tolist()
            else:
                # Convert 2D array to list of lists
                model_data['coef'] = [row.tolist() for row in coef]
        
        if hasattr(model, 'intercept_'):
            intercept = model.intercept_
            print(f"✓ Intercept: {intercept}")
            
            # FIX: Handle intercept properly
            if isinstance(intercept, np.ndarray):
                # Convert array to list first
                model_data['intercept'] = intercept.tolist()
            else:
                model_data['intercept'] = float(intercept)
        else:
            model_data['intercept'] = 0.0
        
        if hasattr(model, 'classes_'):
            model_data['classes'] = model.classes_.tolist()
            print(f"✓ Classes: {model_data['classes']}")
        else:
            model_data['classes'] = [0, 1, 2]
        
        # Validate export
        if not vectorizer_data.get('vocabulary'):
            print("\nERROR: Vocabulary is empty!")
            sys.exit(1)
        
        if not model_data.get('coef'):
            print("\nERROR: Model coefficients are empty!")
            sys.exit(1)
        
        # Save to JSON
        export = {
            'vectorizer': vectorizer_data,
            'model': model_data
        }
        
        output_path = os.path.join(script_dir, 'model.json')
        
        print("\nExporting to JSON...")
        with open(output_path, 'w') as f:
            json.dump(export, f, indent=2)
        
        # Verify
        file_size = os.path.getsize(output_path)
        print(f"\n✓ SUCCESS! Model exported to: {output_path}")
        print(f"  File size: {file_size / 1024:.2f} KB")
        print(f"  Vocabulary size: {len(vectorizer_data.get('vocabulary', {}))}")
        print(f"  Classes: {model_data.get('classes', [])}")
        print(f"\nYou can now use ml_classifier.php!")
            
    except Exception as e:
        print(f"\nERROR: {type(e).__name__}: {str(e)}")
        import traceback
        traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    print("=" * 60)
    print("ML Model to JSON Exporter")
    print("=" * 60)
    export_model()