"""
Custom template tags for sentiment analysis display.
"""
from django import template

register = template.Library()


@register.filter
def get_sentiment_label(sentiment):
    """Get sentiment label with emoji."""
    labels = {
        'positive': 'Positive 😊',
        'neutral': 'Neutral 😐',
        'negative': 'Negative 😞',
        'pending': 'Pending ⏳',
    }
    return labels.get(sentiment, 'Unknown')


@register.filter
def get_sentiment_color(sentiment):
    """Get sentiment color for UI."""
    colors = {
        'positive': '#28a745',
        'neutral': '#17a2b8',
        'negative': '#dc3545',
        'pending': '#6c757d',
    }
    return colors.get(sentiment, '#6c757d')


@register.filter
def dict_key(d, key):
    """Get value from dictionary by key."""
    return d.get(key) if d else None


@register.filter
def get_sentiment_icon(sentiment):
    """Get Font Awesome icon for sentiment."""
    icons = {
        'positive': 'fa-thumbs-up',
        'neutral': 'fa-minus',
        'negative': 'fa-thumbs-down',
        'pending': 'fa-clock',
    }
    return icons.get(sentiment, 'fa-question')
