"""
URL configuration for feedback_project project.
"""
from django.contrib import admin
from django.urls import path, include
from django.contrib.auth import views as auth_views
from feedback import views

urlpatterns = [
    # Landing page (public)
    path('', views.landing, name='landing'),
    
    # Authentication
    path('login/', views.login_view, name='login'),
    path('logout/', views.logout_view, name='logout'),
    path('register/', views.register, name='register'),
    
    # Main pages
    path('dashboard/', views.index, name='index'),
    path('students/', views.students, name='students'),
    path('students/all/', views.total_students, name='total_students'),
    path('teachers/', views.teachers, name='teachers'),
    path('teachers/all/', views.total_teachers, name='total_teachers'),
    path('faculty/', views.faculty, name='faculty'),
    path('feedback/', views.feedback, name='feedback'),
    
    # Student portal
    path('student/', views.student_panel, name='student_panel'),
    path('student/feedback/', views.student_feedback, name='student_feedback'),
    
    # Teacher portal
    path('teacher/', views.teacher_panel, name='teacher_panel'),
    path('teacher/assign-classes/', views.assign_classes, name='assign_classes'),
    path('teacher/feedback/', views.teacher_feedback, name='teacher_feedback'),
    
    # API endpoints
    path('api/analyze-sentiment/', views.analyze_sentiment, name='analyze_sentiment'),
    path('api/filter-students/', views.filter_students, name='filter_students'),
    path('api/filter-teachers/', views.filter_teachers, name='filter_teachers'),
    path('api/get-teachers/', views.get_teachers, name='get_teachers'),
    path('api/get-feedback-stats/', views.get_feedback_stats, name='get_feedback_stats'),
    path('api/cold-start-recommendations/', views.get_cold_start_recommendations, name='cold_start_recommendations'),
]
