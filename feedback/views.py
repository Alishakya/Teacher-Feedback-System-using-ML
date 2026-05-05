"""
Views for the Feedback Management System.
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required, user_passes_test
from django.http import JsonResponse, HttpResponse
from django.views.decorators.http import require_http_methods
from django.views.decorators.csrf import csrf_exempt
from django.contrib import messages
from django.db.models import Count, Q
from django.core.paginator import Paginator
from django.utils import timezone
import json
import os

from .models import User, Student, Teacher, Faculty, Semester, FeedbackSession, FeedbackResponse, AssignClass
from .forms import LoginForm, UserRegistrationForm, StudentForm, TeacherForm, FacultyForm, FeedbackSessionForm
from ml_models.classifier import SentimentClassifier


# Initialize ML classifier
classifier = None


def get_classifier():
    """Lazy initialization of the sentiment classifier."""
    global classifier
    if classifier is None:
        model_path = os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'logistic_regression_model.pkl')
        vectorizer_path = os.path.join(os.path.dirname(__file__), '..', 'ml_models', 'tfidf_vectorizer.pkl')
        classifier = SentimentClassifier(model_path, vectorizer_path)
    return classifier


# ============ Authentication Views ============

def landing(request):
    """Landing page view - shows landing page for unauthenticated users."""
    if request.user.is_authenticated:
        return redirect_based_on_role(request.user)
    return render(request, 'landing.html')


def login_view(request):
    """User login view with role-based redirect."""
    if request.user.is_authenticated:
        return redirect_based_on_role(request.user)
    
    if request.method == 'POST':
        form = LoginForm(request.POST)
        if form.is_valid():
            username = form.cleaned_data['username']
            password = form.cleaned_data['password']
            role = form.cleaned_data['role']
            
            # Check if login is with UID/TID
            user = None
            if role == 'student':
                # Try to find user by username or UID
                student = Student.objects.filter(uid=username).first()
                if student:
                    user = student.user
            elif role == 'teacher':
                # Try to find user by username or TID
                teacher = Teacher.objects.filter(tid=username).first()
                if teacher:
                    user = teacher.user
            else:
                # For admin, only use username
                user = authenticate(request, username=username, password=password)
            
            # If not found by UID/TID, try username
            if user is None:
                user = authenticate(request, username=username, password=password)
            
            if user is not None and user.role == role:
                login(request, user)
                messages.success(request, f'Welcome back, {user.username}!')
                return redirect_based_on_role(user)
            else:
                messages.error(request, 'Invalid credentials or role mismatch.')
    else:
        form = LoginForm()
    
    return render(request, 'login.html', {'form': form})


def logout_view(request):
    """User logout view."""
    logout(request)
    messages.info(request, 'You have been logged out.')
    return redirect('login')


def register(request):
    """User registration view."""
    if request.method == 'POST':
        form = UserRegistrationForm(request.POST)
        if form.is_valid():
            user = form.save(commit=False)
            user.set_password(form.cleaned_data['password'])
            user.save()
            messages.success(request, 'Registration successful! Please login.')
            return redirect('login')
    else:
        form = UserRegistrationForm()
    
    return render(request, 'register.html', {'form': form})


def redirect_based_on_role(user):
    """Redirect user based on their role."""
    if user.is_admin:
        return redirect('index')
    elif user.is_student:
        return redirect('student_panel')
    elif user.is_teacher:
        return redirect('teacher_panel')
    return redirect('login')


# ============ Admin Views ============

@login_required
@user_passes_test(lambda u: u.is_admin)
def index(request):
    """Admin dashboard view."""
    from django.db.models import Count
    from django.db.models.functions import TruncMonth
    
    total_students = Student.objects.count()
    total_teachers = Teacher.objects.count()
    total_faculties = Faculty.objects.count()
    
    students = Student.objects.select_related('user', 'faculty', 'semester').all()
    teachers = Teacher.objects.select_related('user').prefetch_related('assignclass_set', 'assignclass_set__faculty', 'assignclass_set__semester').all()
    faculties = Faculty.objects.all()
    semesters = Semester.objects.all()
    
    # Monthly feedback data for trend chart
    monthly_feedback = FeedbackResponse.objects.exclude(sentiment='pending').annotate(
        month=TruncMonth('session__start_date')
    ).values('month').annotate(count=Count('id')).order_by('month')
    
    import calendar
    monthly_feedback_data = {
        'labels': [calendar.month_name[r['month'].month] + ' ' + str(r['month'].year) for r in monthly_feedback],
        'values': [r['count'] for r in monthly_feedback],
    }
    
    # Get all feedback sessions with teacher stats (paginated style)
    all_sessions = FeedbackSession.objects.select_related('faculty', 'semester').order_by('-start_date')
    
    session_data = []
    for session in all_sessions[:10]:  # Maximum 10 sessions
        responses = FeedbackResponse.objects.filter(session=session).exclude(sentiment='pending')
        
        # Get teachers for this session
        session_teachers = Teacher.objects.filter(
            assignclass__faculty=session.faculty,
            assignclass__semester=session.semester
        ).distinct()
        
        teacher_stats = []
        for teacher in session_teachers:
            teacher_responses = responses.filter(teacher=teacher)
            total = teacher_responses.count()
            
            if total > 0:
                positive = teacher_responses.filter(sentiment='positive').count()
                neutral = teacher_responses.filter(sentiment='neutral').count()
                negative = teacher_responses.filter(sentiment='negative').count()
                
                teacher_stats.append({
                    'teacher': teacher,
                    'positive': positive,
                    'neutral': neutral,
                    'negative': negative,
                    'total': total,
                })
        
        session_data.append({
            'session': session,
            'teacher_stats': teacher_stats,
            'has_data': len(teacher_stats) > 0,
        })
    
    # Teacher feedback statistics (for summary table)
    teacher_stats_list = []
    all_responses = FeedbackResponse.objects.exclude(sentiment='pending')
    
    for teacher in teachers:
        responses = all_responses.filter(teacher=teacher)
        total = responses.count()
        
        if total > 0:
            positive = responses.filter(sentiment='positive').count()
            neutral = responses.filter(sentiment='neutral').count()
            negative = responses.filter(sentiment='negative').count()
            
            positive_pct = round(positive * 100 / total, 1)
            neutral_pct = round(neutral * 100 / total, 1)
            negative_pct = round(negative * 100 / total, 1)
            
            assignments = list(teacher.assignclass_set.all())
            
            teacher_stats_list.append({
                'teacher': teacher,
                'total': total,
                'positive': positive,
                'neutral': neutral,
                'negative': negative,
                'positive_pct': positive_pct,
                'neutral_pct': neutral_pct,
                'negative_pct': negative_pct,
                'assignments': assignments,
            })
    
    context = {
        'total_students': total_students,
        'total_teachers': total_teachers,
        'total_faculties': total_faculties,
        'students': students,
        'teachers': teachers,
        'faculties': faculties,
        'semesters': semesters,
        'monthly_feedback_data': monthly_feedback_data,
        'teacher_stats': teacher_stats_list,
        'session_data': session_data,
        'has_sessions': all_sessions.exists(),
    }
    return render(request, 'index.html', context)


@login_required
@user_passes_test(lambda u: u.is_admin)
def students(request):
    """Student management view."""
    action = request.GET.get('action')
    error = ''
    
    # Ensure semesters exist
    for i in range(1, 9):
        Semester.objects.get_or_create(semester_number=i)
    
    if request.method == 'POST':
        if 'add_student' in request.POST:
            # Calculate enrollment date based on semester
            from datetime import date
            semester_num = int(request.POST.get('semester', 1))
            # Rough calculation: 6 months per semester
            years_back = (semester_num - 1) * 0.5
            enrollment_date = date(date.today().year - int(years_back), 
                                   date.today().month if years_back < 1 else (date.today().month - 6) % 12 or 12, 1)
            
            # Check for duplicate username
            name = request.POST.get('name')
            if User.objects.filter(username=name).exists():
                messages.error(request, f'Username "{name}" already exists. Please use a different name.')
            else:
                # Generate unique email since User model requires it
                base_email = f"{name.lower().replace(' ', '.')}@student.edu"
                email = base_email
                counter = 1
                while User.objects.filter(email=email).exists():
                    email = f"{name.lower().replace(' ', '.')}{counter}@student.edu"
                    counter += 1
                
                user = User.objects.create_user(
                    username=name,
                    email=email,
                    password=request.POST.get('password'),
                    role='student'
                )
                
                # Auto-generate unique UID
                uid_val = request.POST.get('uid', '')
                if not uid_val:
                    last_student = Student.objects.order_by('-id').first()
                    new_id = (last_student.id + 1) if last_student else 1
                    uid_val = f"U{new_id:04d}"
                
                # Handle image upload
                image = request.FILES.get('image') if 'image' in request.FILES else None
                
                student = Student.objects.create(
                    user=user,
                    uid=uid_val,
                    contact=request.POST.get('contact'),
                    faculty_id=request.POST.get('faculty'),
                    semester_id=request.POST.get('semester'),
                    enrollment_date=enrollment_date,
                    image=image,
                )
                messages.success(request, 'Student added successfully!')
        elif 'edit_student' in request.POST:
            student = Student.objects.get(id=request.POST.get('student_id'))
            student.user.username = request.POST.get('name')
            student.user.save()
            student.contact = request.POST.get('contact')
            student.faculty_id = request.POST.get('faculty')
            student.semester_id = request.POST.get('semester')
            student.enrollment_date = request.POST.get('enroll')
            
            # Handle image update if new image is uploaded
            if 'image' in request.FILES:
                student.image = request.FILES['image']
            
            student.save()
            messages.success(request, 'Student updated successfully!')
    
    if action == 'delete' and 'student_id' in request.GET:
        student = Student.objects.filter(id=request.GET['student_id']).first()
        if student:
            user = student.user
            student.delete()
            user.delete()
        messages.success(request, 'Student deleted successfully!')
    
    students_list = Student.objects.select_related('user', 'faculty', 'semester').all()
    faculties = Faculty.objects.all()
    semesters = Semester.objects.all()
    
    # Get student for view/edit mode
    student = None
    if action in ('view', 'edit') and 'student_id' in request.GET:
        student = Student.objects.select_related('user', 'faculty', 'semester').get(id=request.GET['student_id'])
    
    context = {
        'students': students_list,
        'faculties': faculties,
        'semesters': semesters,
        'action': action,
        'student': student,
    }
    return render(request, 'students.html', context)


@login_required
@user_passes_test(lambda u: u.is_admin)
def teachers(request):
    """Teacher management view."""
    action = request.GET.get('action')
    
    if request.method == 'POST':
        if 'add_teacher' in request.POST:
            # Check for duplicate username
            name = request.POST.get('name')
            if User.objects.filter(username=name).exists():
                messages.error(request, f'Username "{name}" already exists. Please use a different name.')
            else:
                # Generate unique email since User model requires it
                base_email = f"{name.lower().replace(' ', '.')}@teacher.edu"
                email = base_email
                counter = 1
                while User.objects.filter(email=email).exists():
                    email = f"{name.lower().replace(' ', '.')}{counter}@teacher.edu"
                    counter += 1
                
                user = User.objects.create_user(
                    username=name,
                    email=email,
                    password=request.POST.get('password'),
                    role='teacher'
                )
                
                # Auto-generate unique TID
                tid_val = request.POST.get('tid', '')
                if not tid_val:
                    last_teacher = Teacher.objects.order_by('-id').first()
                    new_id = (last_teacher.id + 1) if last_teacher else 1
                    tid_val = f"T{new_id:04d}"
                
                # Handle image upload
                image = request.FILES.get('image') if 'image' in request.FILES else None
                
                teacher = Teacher.objects.create(
                    user=user,
                    tid=tid_val,
                    name=request.POST.get('name'),
                    contact=request.POST.get('contact'),
                    image=image,
                )
                
                # Handle assignments only if teacher was created
                assignment_keys = [k for k in request.POST.keys() if k.startswith('assignments[')]
                # Use a set to track which assignments have been created (to avoid duplicates)
                created_assignments = set()
                for key in assignment_keys:
                    import re
                    match = re.search(r'assignments\[(\d+)\]\[(.+)\]', key)
                    if match:
                        idx = match.group(1)
                        field = match.group(2)
                        if field == 'faculty':
                            fac = request.POST.get(key)
                            sem = request.POST.get(f'assignments[{idx}][semester]')
                            # Create assignment only if both faculty and semester exist
                            if fac and sem:
                                assignment_key = (str(fac), str(sem))
                                if assignment_key not in created_assignments:
                                    AssignClass.objects.create(
                                        teacher=teacher,
                                        faculty_id=fac,
                                        semester_id=sem,
                                    )
                                    created_assignments.add(assignment_key)
                messages.success(request, 'Teacher added successfully!')
        elif 'edit_teacher' in request.POST:
            teacher_id = request.POST.get('teacher_id')
            teacher = Teacher.objects.get(id=teacher_id)
            teacher.name = request.POST.get('name')
            teacher.contact = request.POST.get('contact')
            
            # Handle image update if new image is uploaded
            if 'image' in request.FILES:
                teacher.image = request.FILES['image']
            
            teacher.save()
            # Update user username
            teacher.user.username = request.POST.get('name')
            teacher.user.save()
            # Update password if provided
            if request.POST.get('password'):
                teacher.user.set_password(request.POST.get('password'))
                teacher.user.save()
            # Handle assignments - delete old and create new
            AssignClass.objects.filter(teacher=teacher).delete()
            # Parse assignments from POST data
            assignment_keys = [k for k in request.POST.keys() if k.startswith('assignments[')]
            # Use a set to track which assignments have been created (to avoid duplicates)
            created_assignments = set()
            for key in assignment_keys:
                import re
                match = re.search(r'assignments\[(\d+)\]\[(.+)\]', key)
                if match:
                    idx = match.group(1)
                    field = match.group(2)
                    if field == 'faculty':
                        fac = request.POST.get(key)
                        sem = request.POST.get(f'assignments[{idx}][semester]')
                        # Create assignment only if both faculty and semester exist
                        if fac and sem:
                            assignment_key = (str(fac), str(sem))
                            if assignment_key not in created_assignments:
                                AssignClass.objects.create(
                                    teacher=teacher,
                                    faculty_id=fac,
                                    semester_id=sem,
                                )
                                created_assignments.add(assignment_key)
            messages.success(request, 'Teacher updated successfully!')
    
    if action == 'delete' and 'teacher_id' in request.GET:
        teacher = Teacher.objects.filter(id=request.GET['teacher_id']).first()
        if teacher:
            user = teacher.user
            teacher.delete()
            user.delete()
        messages.success(request, 'Teacher deleted successfully!')
    
    teachers_list = Teacher.objects.all()
    faculties = Faculty.objects.all()
    semesters = Semester.objects.all()
    
    # Get teacher for edit mode
    teacher = None
    teacher_assignments = []
    if action == 'edit' and 'teacher_id' in request.GET:
        teacher = Teacher.objects.get(id=request.GET['teacher_id'])
        teacher_assignments = list(teacher.assignclass_set.all())
    
    context = {
        'teachers': teachers_list,
        'faculties': faculties,
        'semesters': semesters,
        'action': action,
        'teacher': teacher,
        'teacher_assignments': teacher_assignments,
    }
    return render(request, 'teachers.html', context)


@login_required
@user_passes_test(lambda u: u.is_admin)
def faculty(request):
    """Faculty management view."""
    action = request.GET.get('action')
    error = ''
    
    if request.method == 'POST':
        if 'add_faculty' in request.POST:
            Faculty.objects.create(
                faculty_name=request.POST.get('faculty_name'),
                descriptions=request.POST.get('descriptions'),
            )
            messages.success(request, 'Faculty added successfully!')
        elif 'edit_faculty' in request.POST:
            faculty = Faculty.objects.get(id=request.POST.get('faculty_id'))
            faculty.faculty_name = request.POST.get('faculty_name')
            faculty.descriptions = request.POST.get('descriptions')
            faculty.save()
            messages.success(request, 'Faculty updated successfully!')
    
    if action == 'delete' and 'faculty_id' in request.GET:
        Faculty.objects.filter(id=request.GET['faculty_id']).delete()
        messages.success(request, 'Faculty deleted successfully!')
    
    faculties_list = Faculty.objects.all()
    
    context = {
        'faculties': faculties_list,
        'action': action,
        'error': error,
    }
    return render(request, 'faculty.html', context)


@login_required
@user_passes_test(lambda u: u.is_admin)
def total_students(request):
    """View all students with search and filter functionality."""
    # Get filter parameters
    search_query = request.GET.get('search', '')
    faculty_filter = request.GET.get('faculty', '')
    semester_filter = request.GET.get('semester', '')
    
    # Start with all students
    students = Student.objects.select_related('user', 'faculty', 'semester').all()
    
    # Apply search filter (by name or UID)
    if search_query:
        students = students.filter(
            Q(name__icontains=search_query) |
            Q(uid__icontains=search_query) |
            Q(user__username__icontains=search_query)
        )
    
    # Apply faculty filter
    if faculty_filter:
        students = students.filter(faculty_id=faculty_filter)
    
    # Apply semester filter
    if semester_filter:
        students = students.filter(semester_id=semester_filter)
    
    # Get filter options
    faculties = Faculty.objects.all()
    semesters = Semester.objects.all()
    
    context = {
        'students': students,
        'faculties': faculties,
        'semesters': semesters,
        'search_query': search_query,
        'faculty_filter': faculty_filter,
        'semester_filter': semester_filter,
    }
    return render(request, 'total_students.html', context)


@login_required
@user_passes_test(lambda u: u.is_admin)
def total_teachers(request):
    """View all teachers with search and filter functionality."""
    # Get filter parameters
    search_query = request.GET.get('search', '')
    faculty_filter = request.GET.get('faculty', '')
    
    # Start with all teachers
    teachers = Teacher.objects.select_related('user').prefetch_related('assignclass_set', 'assignclass_set__faculty', 'assignclass_set__semester').all()
    
    # Apply search filter (by name or TID)
    if search_query:
        teachers = teachers.filter(
            Q(name__icontains=search_query) |
            Q(tid__icontains=search_query)
        )
    
    # Apply faculty filter (filter teachers who teach at that faculty)
    if faculty_filter:
        teachers = teachers.filter(assignclass__faculty_id=faculty_filter).distinct()
    
    # Get filter options
    faculties = Faculty.objects.all()
    
    context = {
        'teachers': teachers,
        'faculties': faculties,
        'search_query': search_query,
        'faculty_filter': faculty_filter,
    }
    return render(request, 'total_teachers.html', context)


@login_required
@user_passes_test(lambda u: u.is_admin)
def feedback(request):
    """Feedback management view."""
    from datetime import timedelta
    today = timezone.now().date().isoformat()
    max_date = (timezone.now().date() + timedelta(days=5)).isoformat()
    
    # Auto-close expired sessions
    FeedbackSession.objects.filter(status='active', end_date__lt=timezone.now().date()).update(status='closed')
    
    if request.method == 'POST':
        if 'create_session' in request.POST:
            FeedbackSession.objects.create(
                faculty_id=request.POST.get('faculty'),
                semester_id=request.POST.get('semester'),
                start_date=today,
                end_date=request.POST.get('end_date'),
                status='active'
            )
            messages.success(request, 'Feedback session created successfully!')
    
    if 'action' in request.GET and request.GET['action'] == 'close':
        session = get_object_or_404(FeedbackSession, id=request.GET['session_id'])
        session.status = 'closed'
        session.save()
        messages.success(request, 'Session closed successfully!')
    
    sessions = FeedbackSession.objects.select_related('faculty', 'semester').order_by('-start_date')
    faculties = Faculty.objects.all()
    semesters = Semester.objects.all()
    
    # Get selected session
    selected_id = request.GET.get('session_id')
    selected_session = None
    stats = None
    teacher_stats = []
    
    if selected_id:
        selected_session = get_object_or_404(FeedbackSession, id=selected_id)
        # Get all teachers assigned to this faculty and semester
        teachers = Teacher.objects.filter(
            assignclass__faculty=selected_session.faculty,
            assignclass__semester=selected_session.semester
        )
        # Get total students in this session
        total_students = Student.objects.filter(
            faculty=selected_session.faculty,
            semester=selected_session.semester
        ).count()
        # Get stats
        responses = FeedbackResponse.objects.filter(session=selected_session).exclude(sentiment='pending')
        stats = {
            'positive': responses.filter(sentiment='positive').count(),
            'neutral': responses.filter(sentiment='neutral').count(),
            'negative': responses.filter(sentiment='negative').count(),
            'students_responded': responses.values('student').distinct().count(),
            'total_responses': responses.count(),
            'total_students': total_students,
        }
        # Calculate teacher-wise stats
        for teacher in teachers:
            teacher_responses = responses.filter(teacher=teacher)
            positive = teacher_responses.filter(sentiment='positive').count()
            neutral = teacher_responses.filter(sentiment='neutral').count()
            negative = teacher_responses.filter(sentiment='negative').count()
            total = positive + neutral + negative
            teacher_stats.append({
                'teacher': teacher,
                'total': total,
                'positive': positive,
                'neutral': neutral,
                'negative': negative,
                'positive_percent': round(positive * 100 / total, 1) if total > 0 else 0,
                'neutral_percent': round(neutral * 100 / total, 1) if total > 0 else 0,
                'negative_percent': round(negative * 100 / total, 1) if total > 0 else 0,
            })
    
    context = {
        'sessions': sessions,
        'faculties': faculties,
        'semesters': semesters,
        'today': today,
        'max_date': max_date,
        'selected_session': selected_session,
        'stats': stats,
        'teacher_stats': teacher_stats,
    }
    return render(request, 'feedback.html', context)


# ============ Student Views ============

@login_required
@user_passes_test(lambda u: u.is_student)
def student_panel(request):
    """Student panel view - shows profile only."""
    student = get_object_or_404(Student, user=request.user)
    
    # Get assigned teachers
    assigned_teachers = Teacher.objects.filter(
        assignclass__faculty=student.faculty,
        assignclass__semester=student.semester
    )
    
    context = {
        'student': student,
        'assigned_teachers': assigned_teachers,
    }
    return render(request, 'student_panel.html', context)


@login_required
@user_passes_test(lambda u: u.is_student)
def student_feedback(request):
    """Student feedback submission view."""
    student = get_object_or_404(Student, user=request.user)
    
    # Get assigned teachers
    assigned_teachers = Teacher.objects.filter(
        assignclass__faculty=student.faculty,
        assignclass__semester=student.semester
    )
    
    # Auto-close expired sessions
    FeedbackSession.objects.filter(status='active', end_date__lt=timezone.now().date()).update(status='closed')
    
    # Get active sessions for student's faculty and semester
    active_sessions = FeedbackSession.objects.filter(
        faculty=student.faculty,
        semester=student.semester,
        status='active'
    ).filter(end_date__gte=timezone.now().date())
    
    # Get existing feedback
    session_feedback = {}
    for session in active_sessions:
        for teacher in assigned_teachers:
            feedback = FeedbackResponse.objects.filter(
                session=session,
                student=student,
                teacher=teacher
            ).first()
            if feedback:
                if teacher.id not in session_feedback:
                    session_feedback[teacher.id] = feedback
    
    if request.method == 'POST':
        session_id = request.POST.get('session_id')
        teacher_id = request.POST.get('teacher_id')
        feedback_text = request.POST.get('feedback_text')
        
        if feedback_text:
            session = get_object_or_404(FeedbackSession, id=session_id)
            teacher = get_object_or_404(Teacher, id=teacher_id)
            
            # Analyze sentiment
            clf = get_classifier()
            sentiment_result = clf.predict(feedback_text)
            sentiment = sentiment_result[0] if sentiment_result else 'neutral'
            
            # Save feedback
            feedback, created = FeedbackResponse.objects.update_or_create(
                session=session,
                student=student,
                teacher=teacher,
                defaults={
                    'feedback_text': feedback_text,
                    'sentiment': sentiment,
                }
            )
            messages.success(request, 'Feedback submitted successfully!')
    
    context = {
        'student': student,
        'assigned_teachers': assigned_teachers,
        'active_sessions': active_sessions,
        'session_feedback': session_feedback,
    }
    return render(request, 'student_feedback.html', context)


# ============ Teacher Views ============

@login_required
@user_passes_test(lambda u: u.is_teacher)
def teacher_panel(request):
    """Teacher panel view."""
    teacher = get_object_or_404(Teacher, user=request.user)
    
    # Get assigned classes
    assigned_classes = AssignClass.objects.filter(teacher=teacher).select_related('faculty', 'semester')
    unique_semesters = assigned_classes.values_list('semester__semester_number', flat=True).distinct()
    
    stats = None
    monthly_data = {'labels': [], 'values': []}
    wordcloud_data = []
    
    if request.method == 'POST':
        faculty_id = request.POST.get('faculty')
        semester_id = request.POST.get('semester')
        
        if faculty_id and semester_id:
            responses = FeedbackResponse.objects.filter(
                session__faculty_id=faculty_id,
                session__semester_id=semester_id,
                teacher=teacher
            ).exclude(sentiment='pending')
            
            stats = {
                'positive': responses.filter(sentiment='positive').count(),
                'neutral': responses.filter(sentiment='neutral').count(),
                'negative': responses.filter(sentiment='negative').count(),
            }
            
            # Monthly trend data
            from django.db.models.functions import TruncMonth
            monthly_responses = responses.annotate(
                month=TruncMonth('session__start_date')
            ).values('month').annotate(count=Count('id')).order_by('month')
            
            import calendar
            monthly_data['labels'] = [calendar.month_name[r['month'].month] for r in monthly_responses]
            monthly_data['values'] = [r['count'] for r in monthly_responses]
            
            # Word cloud data - extract word frequencies
            from collections import Counter
            import re
            all_words = []
            stop_words = {'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'and', 'but', 'if', 'or', 'because', 'until', 'while', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'its', 'our', 'their', 'what', 'which', 'who', 'whom', 'whose', 'any', 'both', 'down', 'up', 'out', 'off', 'over', 'about', 'teacher', 'class', 'subject'}
            
            for response in responses:
                if response.feedback_text:
                    words = re.findall(r'\b[a-zA-Z]{3,}\b', response.feedback_text.lower())
                    all_words.extend([w for w in words if w not in stop_words])
            
            word_counts = Counter(all_words)
            wordcloud_data = [[word, count] for word, count in word_counts.most_common(50)]
    
    context = {
        'teacher': teacher,
        'assigned_classes': assigned_classes,
        'unique_semesters': unique_semesters,
        'stats': stats,
        'monthly_data': monthly_data,
        'wordcloud_data': wordcloud_data,
    }
    return render(request, 'teacher_panel.html', context)


@login_required
@user_passes_test(lambda u: u.is_teacher)
def assign_classes(request):
    """Teacher view assigned classes."""
    teacher = get_object_or_404(Teacher, user=request.user)
    
    assigned_classes = AssignClass.objects.filter(teacher=teacher).select_related('faculty', 'semester')
    
    # Build list with student counts
    class_data = []
    for ac in assigned_classes:
        student_count = Student.objects.filter(
            faculty=ac.faculty,
            semester=ac.semester
        ).count()
        class_data.append({
            'assignment': ac,
            'faculty': ac.faculty,
            'semester': ac.semester,
            'student_count': student_count,
        })
    
    students = []
    selected_faculty = None
    selected_semester = None
    
    if 'faculty' in request.GET and 'semester' in request.GET:
        students = Student.objects.filter(
            faculty_id=request.GET.get('faculty'),
            semester_id=request.GET.get('semester')
        ).select_related('user', 'faculty', 'semester')
        selected_faculty = get_object_or_404(Faculty, id=request.GET.get('faculty'))
        selected_semester = request.GET.get('semester')
    
    context = {
        'teacher': teacher,
        'class_data': class_data,
        'students': students,
        'selected_faculty': selected_faculty,
        'selected_semester': selected_semester,
    }
    return render(request, 'assign_classes.html', context)


@login_required
@user_passes_test(lambda u: u.is_teacher)
def teacher_feedback(request):
    """Teacher feedback view."""
    return teacher_panel(request)


# ============ API Views ============

@csrf_exempt
@require_http_methods(["POST"])
def analyze_sentiment(request):
    """API endpoint for sentiment analysis."""
    try:
        data = json.loads(request.body)
        text = data.get('text', '')
        
        if not text:
            return JsonResponse({'error': 'No text provided'}, status=400)
        
        clf = get_classifier()
        sentiment_result = clf.predict(text)
        sentiment = sentiment_result[0] if sentiment_result else 'neutral'
        
        return JsonResponse({
            'success': True,
            'sentiment': sentiment,
            'text': text[:50] + '...' if len(text) > 50 else text,
        })
    except Exception as e:
        return JsonResponse({'error': str(e)}, status=500)


def filter_students(request):
    """Filter students by faculty and semester."""
    faculty = request.GET.get('faculty', '')
    semester = request.GET.get('semester', '')
    
    students = Student.objects.select_related('user', 'faculty', 'semester').all()
    
    if faculty:
        students = students.filter(faculty_id=faculty)
    if semester:
        students = students.filter(semester_id=semester)
    
    html = ''
    for student in students:
        html += f'''
        <tr>
            <td><img src="{student.image.url}" alt="Student" width="50"></td>
            <td>{student.uid}</td>
            <td>{student.user.username}</td>
            <td>{student.contact}</td>
            <td>{student.faculty.faculty_name}</td>
            <td>{student.semester.semester_number}</td>
            <td>{student.enrollment_date}</td>
        </tr>
        '''
    
    return HttpResponse(html)


def filter_teachers(request):
    """Filter teachers by faculty and semester."""
    faculty = request.GET.get('faculty', '')
    semester = request.GET.get('semester', '')
    
    teachers = Teacher.objects.all()
    
    html = ''
    for teacher in teachers:
        html += f'''
        <tr>
            <td><img src="{teacher.image.url}" alt="Teacher" width="50"></td>
            <td>{teacher.tid}</td>
            <td>{teacher.name}</td>
            <td>{teacher.contact}</td>
            <td>
                {"; ".join([f"{a.faculty.faculty_name} - Sem {a.semester.semester_number}" for a in teacher.assignclass_set.all()])}
            </td>
        </tr>
        '''
    
    return HttpResponse(html)


@login_required
def get_teachers(request):
    """Get teachers for a faculty and semester."""
    faculty_id = request.GET.get('faculty')
    semester_id = request.GET.get('semester')
    
    if faculty_id and semester_id:
        teachers = Teacher.objects.filter(
            assignclass__faculty_id=faculty_id,
            assignclass__semester_id=semester_id
        )
        data = [{'id': t.id, 'name': t.name} for t in teachers]
        return JsonResponse({'teachers': data})
    
    return JsonResponse({'teachers': []})


@login_required
def get_feedback_stats(request):
    """Get feedback statistics for a session."""
    session_id = request.GET.get('session_id')
    teacher_id = request.GET.get('teacher_id')
    
    if session_id:
        responses = FeedbackResponse.objects.filter(session_id=session_id)
        if teacher_id:
            responses = responses.filter(teacher_id=teacher_id)
        
        responses = responses.exclude(sentiment='pending')
        
        stats = {
            'total': responses.count(),
            'positive': responses.filter(sentiment='positive').count(),
            'neutral': responses.filter(sentiment='neutral').count(),
            'negative': responses.filter(sentiment='negative').count(),
        }
        return JsonResponse(stats)
    
    return JsonResponse({'error': 'No session_id provided'}, status=400)


@login_required
@require_http_methods(["GET"])
def get_cold_start_recommendations(request):
    """API endpoint for cold start feedback recommendations using ML model."""
    import random
    
    # Sample feedback sentences categorized by sentiment
    # These are representative of the training data used for the ML model
    positive_samples = [
        "The teacher explains concepts very clearly and makes the subject interesting.",
        "Excellent teaching methodology, always available for doubts and questions.",
       "The teacher is punctual and covers all topics from the textbook.",
        "The lectures are well organized and the teacher uses great examples.",
        "I really appreciate the teacher's dedication and knowledge of the subject.",
        "The teacher creates a positive learning environment and is very helpful.",
    ]
    
    neutral_samples = [
        "Lectures were monotonous and uninspiring.",
        "Did not feel involved in the learning process.",
        "Very supportive and encouraging teacher who motivates students to learn.",
    ]
    
    negative_samples = [
        "class is okay nothing special.",
        "Lessons were rushed and difficult to follow.",
        "Struggled to keep up with the pace of teaching.",
        "Teaching style feels outdated and repetitive.",
        "Teaching quality is nothing special and rush.",
    ]
    
    # Pick 2 random from each category
    selected = []
    for samples, sentiment in [(positive_samples, 'positive'), (neutral_samples, 'neutral'), (negative_samples, 'negative')]:
        chosen = random.sample(samples, min(2, len(samples)))
        for text in chosen:
            selected.append({'text': text, 'sentiment': sentiment})
    
    # Shuffle so they don't appear grouped by sentiment
    random.shuffle(selected)
    
    return JsonResponse({'recommendations': selected})
