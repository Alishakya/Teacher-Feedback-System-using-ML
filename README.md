# Student Feedback Management System with ML Sentiment Analysis

A complete Django-based Student Feedback Management System that integrates Machine Learning for sentiment analysis of student feedback.

## Features

- **Role-based Authentication**: Separate login portals for Admin, Students, and Teachers
- **ML Sentiment Analysis**: Automatic classification of feedback as Positive, Negative, or Neutral
- **Admin Panel**: Manage feedback sessions, users, and view overall analytics
- **Student Portal**: Submit feedback for different faculty members
- **Teacher Portal**: View feedback analytics with interactive charts
- **File Upload**: Support for student profile images
- **Responsive Design**: Bootstrap-based responsive UI

## Tech Stack

- **Backend**: Django 4.x (Python)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript, Bootstrap 5
- **ML Library**: Scikit-learn (Logistic Regression + TF-IDF Vectorizer)
- **Charts**: Chart.js

## Project Structure

```
feedback_project/
├── manage.py
├── requirements.txt
├── README.md
├── feedback_project/
│   ├── __init__.py
│   ├── settings.py
│   ├── urls.py
│   ├── wsgi.py
│   └── asgi.py
├── static/
│   ├── css/
│   │   ├── styles.css
│   │   ├── sstyles.css
│   │   └── tstyles.css
│   ├── js/
│   │   └── main.js
│   └── uploads/
├── templates/
│   ├── base.html
│   ├── login.html
│   ├── index.html
│   ├── students.html
│   ├── teachers.html
│   ├── faculty.html
│   ├── feedback.html
│   ├── student_panel.html
│   ├── teacher_panel.html
│   └── assign_classes.html
├── ml_models/
│   ├── __init__.py
│   ├── classifier.py
│   ├── sentiment_classifier.py
│   ├── export_model.py
│   ├── logistic_regression_model.pkl
│   ├── tfidf_vectorizer.pkl
│   └── model.json
└── feedback/
    ├── __init__.py
    ├── models.py
    ├── views.py
    ├── urls.py
    ├── admin.py
    ├── forms.py
    ├── utils.py
    └── migrations/
```

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd feedback_project
```

### 2. Create Virtual Environment

```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

### 3. Install Dependencies

```bash
pip install -r requirements.txt
```

### 4. Configure Database

Create a MySQL database and update the database configuration in `feedback_project/settings.py`:

```python
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.mysql',
        'NAME': 'feedback_db',
        'USER': 'your_username',
        'PASSWORD': 'your_password',
        'HOST': 'localhost',
        'PORT': '3306',
        'OPTIONS': {
            'charset': 'utf8mb4',
        },
    }
}
```

### 5. Run Migrations

```bash
python manage.py migrate
```

### 6. Create Superuser (Admin)

```bash
python manage.py createsuperuser
```

### 7. Run Development Server

```bash
python manage.py runserver
```

Access the application at `http://127.0.0.1:8000/`

## Default Admin Credentials

- Username: admin
- Password: admin123

## Usage

### Admin Panel
1. Login with admin credentials at `/login/`
2. Manage students, teachers, and feedback sessions
3. View overall analytics and feedback statistics

### Student Portal
1. Login with student credentials
2. Navigate to feedback form
3. Select faculty and submit feedback
4. Feedback is automatically analyzed using ML sentiment analysis

### Teacher Portal
1. Login with teacher credentials
2. View assigned class feedback
3. Analyze sentiment distribution with interactive charts

## ML Model Details

The system uses a Logistic Regression classifier with TF-IDF vectorization for sentiment analysis:

- **Model**: Logistic Regression (trained on labeled feedback data)
- **Vectorizer**: TF-IDF (Term Frequency-Inverse Document Frequency)
- **Classes**: Positive, Negative, Neutral

## API Endpoints

- `POST /api/sentiment/` - Analyze sentiment of feedback text
- `GET /api/feedback/<teacher_id>/` - Get feedback for a teacher

## Customization

### Adding New ML Models
1. Train your model and save as `.pkl` file
2. Update `ml_models/sentiment_classifier.py` to load the new model
3. Run `python ml_models/export_model.py` to export model metadata

### Styling
- Modify CSS files in `static/css/`
- Update Bootstrap theme in `templates/base.html`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License.
