"""
Admin configuration for feedback models.
"""
from django.contrib import admin
from django.contrib.auth.admin import UserAdmin
from .models import User, Student, Teacher, Faculty, Semester, FeedbackSession, FeedbackResponse, AssignClass

@admin.register(User)
class CustomUserAdmin(UserAdmin):
    list_display = ('username', 'email', 'role', 'is_staff')
    list_filter = ('role', 'is_staff', 'is_active')
    fieldsets = (
        (None, {'fields': ('username', 'password')}),
        ('Personal Info', {'fields': ('email', 'first_name', 'last_name')}),
        ('Role', {'fields': ('role',)}),
        ('Permissions', {'fields': ('is_active', 'is_staff', 'is_superuser', 'groups', 'user_permissions')}),
        ('Dates', {'fields': ('last_login', 'date_joined')}),
    )
    add_fieldsets = (
        (None, {
            'classes': ('wide',),
            'fields': ('username', 'email', 'password1', 'password2', 'role'),
        }),
    )
    search_fields = ('username', 'email', 'first_name', 'last_name')
    ordering = ('username',)

@admin.register(Student)
class StudentAdmin(admin.ModelAdmin):
    list_display = ('uid', 'user', 'faculty', 'semester', 'enrollment_date')
    list_filter = ('faculty', 'semester')
    search_fields = ('uid', 'contact', 'user__username', 'user__email')
    raw_id_fields = ('user', 'faculty', 'semester')

@admin.register(Teacher)
class TeacherAdmin(admin.ModelAdmin):
    list_display = ('tid', 'name', 'contact')
    search_fields = ('name', 'tid', 'contact')
    raw_id_fields = ('user',)

@admin.register(Faculty)
class FacultyAdmin(admin.ModelAdmin):
    list_display = ('faculty_name', 'descriptions')
    search_fields = ('faculty_name',)

@admin.register(Semester)
class SemesterAdmin(admin.ModelAdmin):
    list_display = ('semester_number',)
    list_filter = ('semester_number',)

@admin.register(FeedbackSession)
class FeedbackSessionAdmin(admin.ModelAdmin):
    list_display = ('faculty', 'semester', 'start_date', 'end_date', 'status')
    list_filter = ('faculty', 'semester', 'status')
    date_hierarchy = 'start_date'

@admin.register(FeedbackResponse)
class FeedbackResponseAdmin(admin.ModelAdmin):
    list_display = ('session', 'student', 'teacher', 'sentiment', 'created_at')
    list_filter = ('session', 'sentiment', 'teacher')
    raw_id_fields = ('session', 'student', 'teacher')
    date_hierarchy = 'created_at'

@admin.register(AssignClass)
class AssignClassAdmin(admin.ModelAdmin):
    list_display = ('teacher', 'faculty', 'semester')
    list_filter = ('faculty', 'semester')
    raw_id_fields = ('teacher', 'faculty', 'semester')
