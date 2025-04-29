-- Structure de la base de données

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Table des examens
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration INT NOT NULL, -- en minutes
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    created_by INT NOT NULL,
    status ENUM('draft', 'published', 'completed', 'archived') DEFAULT 'draft',
    passing_score INT DEFAULT 60,
    proctoring_level ENUM('none', 'basic', 'advanced') DEFAULT 'basic',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Table des questions
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') NOT NULL,
    points INT NOT NULL DEFAULT 1,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Table des options de réponse (pour QCM)
CREATE TABLE answer_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Table des inscriptions aux examens
CREATE TABLE exam_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled', 'completed', 'missed') DEFAULT 'enrolled',
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    UNIQUE KEY unique_enrollment (exam_id, student_id)
);

-- Table des tentatives d'examen
CREATE TABLE exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    score DECIMAL(5,2) NULL,
    status ENUM('in_progress', 'completed', 'timed_out', 'flagged') DEFAULT 'in_progress',
    ip_address VARCHAR(45),
    browser_info TEXT,
    FOREIGN KEY (enrollment_id) REFERENCES exam_enrollments(id)
);

-- Table des réponses des étudiants
CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    selected_option_id INT NULL,
    is_correct BOOLEAN NULL,
    points_awarded DECIMAL(5,2) NULL,
    graded_by INT NULL,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
    FOREIGN KEY (question_id) REFERENCES questions(id),
    FOREIGN KEY (selected_option_id) REFERENCES answer_options(id),
    FOREIGN KEY (graded_by) REFERENCES users(id)
);

-- Table des incidents de surveillance
CREATE TABLE proctoring_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    incident_type ENUM('face_not_detected', 'multiple_faces', 'looking_away', 'audio_detected', 'tab_switch', 'window_resize', 'copy_paste', 'other') NOT NULL,
    severity ENUM('low', 'medium', 'high') NOT NULL,
    timestamp DATETIME NOT NULL,
    details TEXT,
    screenshot_path VARCHAR(255),
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id)
);

-- Table des paramètres de surveillance
CREATE TABLE proctoring_settings (
    exam_id INT PRIMARY KEY,
    face_recognition BOOLEAN DEFAULT TRUE,
    eye_tracking BOOLEAN DEFAULT TRUE,
    audio_monitoring BOOLEAN DEFAULT TRUE,
    screen_monitoring BOOLEAN DEFAULT TRUE,
    browser_lockdown BOOLEAN DEFAULT TRUE,
    allow_multiple_displays BOOLEAN DEFAULT FALSE,
    tolerance_level ENUM('strict', 'moderate', 'lenient') DEFAULT 'moderate',
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);