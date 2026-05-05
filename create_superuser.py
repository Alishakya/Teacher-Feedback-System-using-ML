#!/usr/bin/env python
"""Create admin superuser for the Feedback System."""
import os
import django
import sys

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'feedback_project.settings')
django.setup()

from feedback.models import User

# Create superuser if not exists
if not User.objects.filter(username='admin').exists():
    User.objects.create_superuser(
        username='admin',
        email='admin@test.com',
        password='admin123'
    )
    print("[OK] Superuser created: admin / admin123")
else:
    u = User.objects.get(username='admin')
    u.role = 'admin'
    u.is_staff = True
    u.is_superuser = True
    u.save()
    print("[OK] Superuser updated: admin / admin123")
