/* Main JavaScript for Student Feedback Management System */

// Utility Functions
function showMessage(message, type = 'success') {
    const container = document.querySelector('.messages') || document.querySelector('.page-header');
    const msgDiv = document.createElement('div');
    msgDiv.className = `message ${type}`;
    msgDiv.textContent = message;
    msgDiv.style.cssText = 'padding: 10px 20px; border-radius: 5px; margin: 10px 0;';
    
    if (type === 'success') {
        msgDiv.style.background = '#d4edda';
        msgDiv.style.color = '#155724';
    } else {
        msgDiv.style.background = '#f8d7da';
        msgDiv.style.color = '#721c24';
    }
    
    document.querySelector('.main-content').insertBefore(msgDiv, document.querySelector('.main-content').firstChild);
    
    setTimeout(() => msgDiv.remove(), 5000);
}

// Tab Navigation
function initTabs() {
    const tabLinks = document.querySelectorAll('.tab-nav a');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            const tabContent = document.getElementById(tabId);
            if (tabContent) {
                tabContent.classList.add('active');
            }
        });
    });
}

// Page Navigation (Sidebar)
function initPageNavigation() {
    const navLinks = document.querySelectorAll('.sidebar .nav-links a');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#') {
                return; // Let normal navigation happen
            }
            
            e.preventDefault();
            const pageId = this.getAttribute('data-page');
            
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            const page = document.getElementById(`${pageId}-page`);
            if (page) {
                page.classList.add('active');
            }
        });
    });
}

// AJAX Filter Functions
function filterStudents() {
    const faculty = document.getElementById('faculty-filter')?.value || '';
    const semester = document.getElementById('semester-filter')?.value || '';
    
    fetch(`/api/filter-students/?faculty=${faculty}&semester=${semester}`)
        .then(response => response.text())
        .then(html => {
            const tbody = document.getElementById('students-tbody');
            if (tbody) {
                tbody.innerHTML = html;
            }
        });
}

function filterTeachers() {
    const faculty = document.getElementById('faculty-filter')?.value || '';
    const semester = document.getElementById('semester-filter')?.value || '';
    
    fetch(`/api/filter-teachers/?faculty=${faculty}&semester=${semester}`)
        .then(response => response.text())
        .then(html => {
            const tbody = document.getElementById('teachers-tbody');
            if (tbody) {
                tbody.innerHTML = html;
            }
        });
}

// Sentiment Analysis Preview
function analyzeSentiment(textarea) {
    const text = textarea.value.trim();
    const preview = textarea.closest('.teacher-feedback')?.querySelector('.sentiment-preview');
    
    if (text.length < 3) {
        if (preview) {
            preview.classList.remove('show', 'positive', 'neutral', 'negative');
            preview.innerHTML = '';
        }
        return;
    }
    
    fetch('/api/analyze-sentiment/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRFToken': getCookie('csrftoken')
        },
        body: JSON.stringify({ text: text })
    })
    .then(response => response.json())
    .then(data => {
        if (preview && data.success) {
            preview.classList.remove('positive', 'neutral', 'negative');
            preview.classList.add('show', data.sentiment);
            preview.innerHTML = `<strong>Preview:</strong> ${capitalizeFirst(data.sentiment)}`;
        }
    })
    .catch(error => {
        console.error('Sentiment analysis error:', error);
    });
}

// Chart Initialization
function initCharts(stats) {
    // Overall Sentiment Chart
    const overallCtx = document.getElementById('overallChart');
    if (overallCtx) {
        new Chart(overallCtx, {
            type: 'pie',
            data: {
                labels: ['Positive', 'Neutral', 'Negative'],
                datasets: [{
                    data: [stats.positive, stats.neutral, stats.negative],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(23, 162, 184, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Overall Sentiment Distribution'
                    }
                }
            }
        });
    }
    
    // Participation Chart
    const participationCtx = document.getElementById('participationChart');
    if (participationCtx) {
        new Chart(participationCtx, {
            type: 'bar',
            data: {
                labels: ['Responded', 'Total Students'],
                datasets: [{
                    label: 'Students',
                    data: [stats.responded, stats.total_students],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(102, 126, 234, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(102, 126, 234, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Participation Overview'
                    }
                }
            }
        });
    }
    
    // Teacher-wise Charts
    document.querySelectorAll('.teacher-chart').forEach((canvas, index) => {
        const teacherStats = stats.teachers[index];
        if (teacherStats) {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Positive', 'Neutral', 'Negative'],
                    datasets: [{
                        data: [teacherStats.positive, teacherStats.neutral, teacherStats.negative],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.7)',
                            'rgba(23, 162, 184, 0.7)',
                            'rgba(220, 53, 69, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: teacherStats.name
                        }
                    }
                }
            });
        }
    });
}

// Session Card Click Handler
function initSessionCards() {
    const sessionCards = document.querySelectorAll('.session-card');
    sessionCards.forEach(card => {
        card.addEventListener('click', function() {
            const sessionId = this.getAttribute('data-session-id');
            if (sessionId) {
                window.location.href = `?session_id=${sessionId}`;
            }
        });
    });
}

// Form Validation
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            input.style.borderColor = '#ddd';
        }
    });
    
    return isValid;
}

// File Upload Preview
function initFilePreview() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = this.closest('.form-group')?.querySelector('img');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Utility: Capitalize First Letter
function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Utility: Get Cookie
function getCookie(name) {
    let cookieValue = null;
    if (document.cookie && document.cookie !== '') {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.substring(0, name.length + 1) === (name + '=')) {
                cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                break;
            }
        }
    }
    return cookieValue;
}

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initPageNavigation();
    initSessionCards();
    initFilePreview();
    
    // Add event listeners for filters
    const facultyFilter = document.getElementById('faculty-filter');
    const semesterFilter = document.getElementById('semester-filter');
    
    if (facultyFilter) {
        facultyFilter.addEventListener('change', filterStudents);
    }
    if (semesterFilter) {
        semesterFilter.addEventListener('change', filterStudents);
    }
    
    // Add event listeners for sentiment textarea
    document.querySelectorAll('.teacher-feedback textarea[name="feedback_text"]').forEach(textarea => {
        textarea.addEventListener('input', function() {
            analyzeSentiment(this);
        });
    });
});
