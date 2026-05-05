"""
Forms for feedback application.
"""
from django import forms
from django.contrib.auth.forms import UserCreationForm
from .models import User, Student, Teacher, Faculty, FeedbackSession, FeedbackResponse, AssignClass


class LoginForm(forms.Form):
    username = forms.CharField(
        widget=forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Username/UID/TID'})
    )
    password = forms.CharField(
        widget=forms.PasswordInput(attrs={'class': 'form-control', 'placeholder': 'Password'})
    )
    role = forms.ChoiceField(
        choices=User.ROLE_CHOICES,
        widget=forms.Select(attrs={'class': 'form-control'})
    )


class UserRegistrationForm(UserCreationForm):
    email = forms.EmailField(required=True)
    role = forms.ChoiceField(choices=User.ROLE_CHOICES)

    class Meta:
        model = User
        fields = ('username', 'email', 'first_name', 'last_name', 'role', 'password1', 'password2')


class StudentForm(forms.ModelForm):
    class Meta:
        model = Student
        fields = ('user', 'uid', 'contact', 'image', 'faculty', 'semester', 'enrollment_date')
        widgets = {
            'user': forms.Select(attrs={'class': 'form-control'}),
            'uid': forms.TextInput(attrs={'class': 'form-control', 'readonly': 'readonly'}),
            'contact': forms.TextInput(attrs={'class': 'form-control'}),
            'image': forms.FileInput(attrs={'class': 'form-control'}),
            'faculty': forms.Select(attrs={'class': 'form-control'}),
            'semester': forms.Select(attrs={'class': 'form-control'}),
            'enrollment_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
        }


class TeacherForm(forms.ModelForm):
    class Meta:
        model = Teacher
        fields = ('user', 'tid', 'name', 'contact', 'image')
        widgets = {
            'user': forms.Select(attrs={'class': 'form-control'}),
            'tid': forms.TextInput(attrs={'class': 'form-control', 'readonly': 'readonly'}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'contact': forms.TextInput(attrs={'class': 'form-control'}),
            'image': forms.FileInput(attrs={'class': 'form-control'}),
        }


class AssignClassForm(forms.ModelForm):
    class Meta:
        model = AssignClass
        fields = ('teacher', 'faculty', 'semester')
        widgets = {
            'teacher': forms.Select(attrs={'class': 'form-control'}),
            'faculty': forms.Select(attrs={'class': 'form-control'}),
            'semester': forms.Select(attrs={'class': 'form-control'}),
        }


class FacultyForm(forms.ModelForm):
    class Meta:
        model = Faculty
        fields = ('faculty_name', 'descriptions')
        widgets = {
            'faculty_name': forms.TextInput(attrs={'class': 'form-control'}),
            'descriptions': forms.TextInput(attrs={'class': 'form-control'}),
        }


class FeedbackSessionForm(forms.ModelForm):
    class Meta:
        model = FeedbackSession
        fields = ('faculty', 'semester', 'start_date', 'end_date', 'status')
        widgets = {
            'faculty': forms.Select(attrs={'class': 'form-control'}),
            'semester': forms.Select(attrs={'class': 'form-control'}),
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
        }


class FeedbackResponseForm(forms.ModelForm):
    class Meta:
        model = FeedbackResponse
        fields = ('session', 'student', 'teacher', 'feedback_text', 'sentiment')
        widgets = {
            'session': forms.Select(attrs={'class': 'form-control'}),
            'student': forms.Select(attrs={'class': 'form-control'}),
            'teacher': forms.Select(attrs={'class': 'form-control'}),
            'feedback_text': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'sentiment': forms.Select(attrs={'class': 'form-control'}),
        }


class StudentFeedbackForm(forms.Form):
    session_id = forms.IntegerField(widget=forms.HiddenInput())
    teacher_id = forms.IntegerField(widget=forms.HiddenInput())
    feedback_text = forms.CharField(
        widget=forms.Textarea(attrs={
            'class': 'form-control',
            'rows': 4,
            'placeholder': 'Enter your feedback here...'
        }),
        label='Feedback'
    )
