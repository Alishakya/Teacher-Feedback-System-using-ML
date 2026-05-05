"""
Utility functions for feedback application.
"""
import json
import os
import numpy as np
from django.conf import settings
from .models import FeedbackResponse


def generate_uid():
    """Generate next student UID."""
    from .models import Student
    last_student = Student.objects.order_by('uid').last()
    if last_student:
        last_uid = last_student.uid
        next_number = int(last_uid[1:]) + 1
        return f'U{str(next_number).zfill(4)}'
    return 'U0001'


def generate_tid():
    """Generate next teacher TID."""
    from .models import Teacher
    last_teacher = Teacher.objects.order_by('tid').last()
    if last_teacher:
        last_tid = last_teacher.tid
        next_number = int(last_tid[1:]) + 1
        return f'T{str(next_number).zfill(4)}'
    return 'T0001'


def calculate_semester(enrollment_date):
    """Calculate semester based on enrollment date."""
    from .models import Semester
    from datetime import datetime, timedelta
    
    if isinstance(enrollment_date, str):
        enrollment_date = datetime.strptime(enrollment_date, '%Y-%m-%d').date()
    
    current_date = datetime.now().date()
    months_diff = (current_date.year - enrollment_date.year) * 12 + (current_date.month - enrollment_date.month)
    semester_number = (months_diff // 6) + 1
    
    if semester_number < 1:
        semester_number = 1
    elif semester_number > 8:
        semester_number = 8
    
    try:
        return Semester.objects.get(semester_number=semester_number)
    except Semester.DoesNotExist:
        return Semester.objects.first()


def get_sentiment_label(sentiment):
    """Get sentiment label with emoji."""
    labels = {
        'positive': 'Positive 😊',
        'neutral': 'Neutral 😐',
        'negative': 'Negative 😞',
        'pending': 'Pending ⏳',
    }
    return labels.get(sentiment, 'Unknown')


def get_sentiment_color(sentiment):
    """Get sentiment color for UI."""
    colors = {
        'positive': '#28a745',
        'neutral': '#17a2b8',
        'negative': '#dc3545',
        'pending': '#6c757d',
    }
    return colors.get(sentiment, '#6c757d')


def get_feedback_stats(session_id, teacher_id=None):
    """Get feedback statistics for a session."""
    responses = FeedbackResponse.objects.filter(session_id=session_id)
    
    if teacher_id:
        responses = responses.filter(teacher_id=teacher_id)
    
    responses = responses.exclude(sentiment='pending')
    
    total = responses.count()
    positive = responses.filter(sentiment='positive').count()
    neutral = responses.filter(sentiment='neutral').count()
    negative = responses.filter(sentiment='negative').count()
    
    return {
        'total': total,
        'positive': positive,
        'neutral': neutral,
        'negative': negative,
        'positive_percent': round(positive * 100 / total, 1) if total > 0 else 0,
        'neutral_percent': round(neutral * 100 / total, 1) if total > 0 else 0,
        'negative_percent': round(negative * 100 / total, 1) if total > 0 else 0,
    }


def process_pending_feedbacks():
    """Process all pending feedback sentiments."""
    from ml_models.classifier import SentimentClassifier
    import os
    
    model_path = os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'logistic_regression_model.pkl')
    vectorizer_path = os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'tfidf_vectorizer.pkl')
    
    classifier = SentimentClassifier(model_path, vectorizer_path)
    pending = FeedbackResponse.objects.filter(sentiment='pending')
    
    processed = 0
    for feedback in pending:
        sentiment_result = classifier.predict(feedback.feedback_text)
        sentiment = sentiment_result[0] if sentiment_result else 'neutral'
        feedback.sentiment = sentiment
        feedback.save()
        processed += 1
    
    return processed
