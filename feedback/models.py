"""
Models for the Feedback Management System.
"""
from django.db import models
from django.contrib.auth.models import AbstractUser
from django.conf import settings
import os


def user_image_path(instance, filename):
    """Generate path for user images."""
    ext = filename.split('.')[-1]
    filename = f"{instance.username}.{ext}"
    return os.path.join('uploads/', filename)


class User(AbstractUser):
    """Custom user model with role-based authentication."""
    ROLE_CHOICES = (
        ('admin', 'Admin'),
        ('student', 'Student'),
        ('teacher', 'Teacher'),
    )
    
    role = models.CharField(max_length=20, choices=ROLE_CHOICES, default='student')
    email = models.EmailField(unique=True)
    
    @property
    def is_admin(self):
        return self.role == 'admin'
    
    @property
    def is_student(self):
        return self.role == 'student'
    
    @property
    def is_teacher(self):
        return self.role == 'teacher'
    
    def __str__(self):
        return self.username


class Faculty(models.Model):
    """Faculty/department model."""
    faculty_name = models.CharField(max_length=100)
    descriptions = models.CharField(max_length=200, blank=True, null=True)
    
    def __str__(self):
        return self.faculty_name
    
    class Meta:
        verbose_name_plural = 'Faculties'


class Semester(models.Model):
    """Semester model."""
    semester_number = models.IntegerField()
    
    def __str__(self):
        return f"Semester {self.semester_number}"
    
    class Meta:
        ordering = ['semester_number']


class Teacher(models.Model):
    """Teacher model."""
    user = models.OneToOneField(User, on_delete=models.CASCADE, related_name='teacher_profile')
    tid = models.CharField(max_length=20, unique=True)
    name = models.CharField(max_length=100)
    contact = models.CharField(max_length=20)
    image = models.ImageField(upload_to='uploads/', default='uploads/default.png')
    
    def __str__(self):
        return self.name


class Student(models.Model):
    """Student model."""
    user = models.OneToOneField(User, on_delete=models.CASCADE, related_name='student_profile')
    uid = models.CharField(max_length=20, unique=True)
    contact = models.CharField(max_length=20)
    image = models.ImageField(upload_to='uploads/', default='uploads/default.png')
    faculty = models.ForeignKey(Faculty, on_delete=models.CASCADE)
    semester = models.ForeignKey(Semester, on_delete=models.CASCADE)
    enrollment_date = models.DateField()
    
    def __str__(self):
        return self.user.username if self.user else self.uid


class AssignClass(models.Model):
    """Model for assigning teachers to classes."""
    teacher = models.ForeignKey(Teacher, on_delete=models.CASCADE)
    faculty = models.ForeignKey(Faculty, on_delete=models.CASCADE)
    semester = models.ForeignKey(Semester, on_delete=models.CASCADE)
    
    def __str__(self):
        return f"{self.teacher.name} - {self.faculty.faculty_name} Sem {self.semester.semester_number}"
    
    @property
    def student_count(self):
        return Student.objects.filter(faculty=self.faculty, semester=self.semester).count()


class FeedbackSession(models.Model):
    """Feedback collection session."""
    STATUS_CHOICES = (
        ('active', 'Active'),
        ('closed', 'Closed'),
    )
    
    faculty = models.ForeignKey(Faculty, on_delete=models.CASCADE)
    semester = models.ForeignKey(Semester, on_delete=models.CASCADE)
    start_date = models.DateField()
    end_date = models.DateField()
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='active')
    created_at = models.DateTimeField(auto_now_add=True)
    
    def __str__(self):
        return f"{self.faculty.faculty_name} - Sem {self.semester.semester_number} ({self.status})"
    
    @property
    def is_active(self):
        from django.utils import timezone
        return self.status == 'active' and self.end_date >= timezone.now().date()


class FeedbackResponse(models.Model):
    """Student feedback response."""
    SENTIMENT_CHOICES = (
        ('positive', 'Positive'),
        ('neutral', 'Neutral'),
        ('negative', 'Negative'),
        ('pending', 'Pending'),
    )
    
    session = models.ForeignKey(FeedbackSession, on_delete=models.CASCADE)
    student = models.ForeignKey(Student, on_delete=models.CASCADE)
    teacher = models.ForeignKey(Teacher, on_delete=models.CASCADE)
    feedback_text = models.TextField()
    sentiment = models.CharField(max_length=20, choices=SENTIMENT_CHOICES, default='pending')
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    
    class Meta:
        unique_together = ['session', 'student', 'teacher']
    
    def __str__(self):
        return f"{self.student} - {self.teacher} ({self.sentiment})"


class Analytics(models.Model):
    """Analytics data for feedback."""
    faculty = models.ForeignKey(Faculty, on_delete=models.CASCADE)
    total_feedbacks = models.IntegerField(default=0)
    positive_count = models.IntegerField(default=0)
    neutral_count = models.IntegerField(default=0)
    negative_count = models.IntegerField(default=0)
    average_rating = models.FloatField(default=0)
    sentiment_score = models.FloatField(default=0)
    updated_at = models.DateTimeField(auto_now=True)
    
    def __str__(self):
        return f"Analytics - {self.faculty.faculty_name}"
    
    @property
    def positive_percent(self):
        if self.total_feedbacks > 0:
            return round(self.positive_count * 100 / self.total_feedbacks, 1)
        return 0
    
    @property
    def neutral_percent(self):
        if self.total_feedbacks > 0:
            return round(self.neutral_count * 100 / self.total_feedbacks, 1)
        return 0
    
    @property
    def negative_percent(self):
        if self.total_feedbacks > 0:
            return round(self.negative_count * 100 / self.total_feedbacks, 1)
        return 0
