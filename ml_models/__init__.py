"""
ML Models package for Sentiment Analysis.
"""

from .classifier import SentimentClassifier
from .sentiment_classifier import SentimentClassifier as Classifier

__all__ = ['SentimentClassifier', 'Classifier']
