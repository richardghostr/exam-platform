<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit();
}

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Traitement du formulaire de création d'examen
if (isset($_POST['create_exam'])) {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    $passingScore = $_POST['passing_score'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $status = $_POST['status'];
    $hasEssay = isset($_POST['has_essay']) ? 1 : 0;
    $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    $allowRetake = isset($_POST['allow_retake']) ? 1 : 0;
    $maxRetakes = $_POST['max_retakes'] ?? 0;
    
    // Paramètres de surveillance
    $enableFaceRecognition = isset($_POST['enable_face_recognition']) ? 1 : 0;
    $enableEyeTracking = isset($_POST['enable_eye_tracking']) ? 1 : 0;
    $enableAudioMonitoring = isset($_POST['enable_audio_monitoring']) ? 1 : 0;
    $enableScreenMonitoring = isset($_POST['enable_screen_monitoring']) ? 1 : 0;
    
    // Insérer l'examen dans la base de données
    $stmt = $conn->prepare("
        INSERT INTO exams (
            teacher_id, title, subject, description, duration, passing_score, 
            start_date, end_date, status, has_essay, randomize_questions, 
            show_results, allow_retake, max_retakes, enable_face_recognition, 
            enable_eye_tracking, enable_audio_monitoring, enable_screen_monitoring
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "isssidssiiiiiiiii",
        $teacherId, $title, $subject, $description, $duration, $passingScore,
        $startDate, $endDate, $status, $hasEssay, $randomizeQuestions,
        $showResults, $allowRetake, $maxRetakes, $enableFaceRecognition,
        $enableEyeTracking, $enableAudioMonitoring, $enableScreenMonitoring
    );
    
    if ($stmt->execute()) {
        $examId = $conn->insert_id;
        
        // Rediriger vers la page d'ajout de questions
        header("Location: add-questions.php?exam_id=$examId");
        exit();
    } else {
        $error = "Une erreur s'est produite lors de la création de l'examen. Veuillez réessayer.";
    }
}

$pageTitle = "Créer un nouvel examen";
include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/teacher.css">

<div class="teacher-container">
    <div class="teacher-sidebar">
        <div class="sidebar-header">
            <div class="logo">ExamSafe</div>
            <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
        </div>
        
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                </li>
                <li>
                    <a href="create-exam.php" class="active">
                        <i class="fas fa-plus-circle"></i> Créer un examen
                    </a>
                </li>
                <li>
                    <a href="manage-exams.php">
                        <i class="fas fa-file-alt"></i> Gérer les examens
                    </a>
                </li>
                <li>
                    <a href="grade-exams.php">
                        <i class="fas fa-check-circle"></i> Noter les examens
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> Rapports
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="teacher-content">
        <div class="teacher-header">
            <h1><?php echo $pageTitle; ?></h1>
            <div class="user-info">
                <span><?php echo date('d F Y'); ?></span>
                <div class="user-dropdown">
                    <button class="user-dropdown-btn">
                        <img src="../assets/images/avatar.png" alt="Avatar" class="avatar">
                        <span><?php echo $_SESSION['username']; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown-content">
                        <a href="../profile.php"><i class="fas fa-user"></i> Profil</a>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="create-exam-container">
            <div class="exam-form-card">
                <form method="post" action="" class="exam-form">
                    <div class="form-tabs">
                        <button type="button" class="form-tab active" data-tab="basic-info">
                            <i class="fas fa-info-circle"></i> Informations de base
                        </button>
                        <button type="button" class="form-tab" data-tab="settings">
                            <i class="fas fa-cog"></i> Paramètres
                        </button>
                        <button type="button" class="form-tab" data-tab="proctoring">
                            <i class="fas fa-video"></i> Surveillance
                        </button>
                    </div>
                    
                    <div class="form-tab-content active" id="basic-info">
                        <div class="form-group">
                            <label for="title">Titre de l'examen <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Matière <span class="required">*</span></label>
                            <input type="text" id="subject" name="subject" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="duration">Durée (minutes) <span class="required">*</span></label>
                                <input type="number" id="duration" name="duration" class="form-control" min="1" value="60" required>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="passing_score">Score de réussite (%) <span class="required">*</span></label>
                                <input type="number" id="passing_score" name="passing_score" class="form-control" min="1" max="100" value="60" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="start_date">Date de début <span class="required">*</span></label>
                                <input type="datetime-local" id="start_date" name="start_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="end_date">Date de fin <span class="required">*</span></label>
                                <input type="datetime-local" id="end_date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Statut <span class="required">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="draft">Brouillon</option>
                                <option value="active">Actif</option>
                                <option value="scheduled">Planifié</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-tab-content" id="settings">
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="has_essay" name="has_essay">
                                <label class="custom-control-label" for="has_essay">Inclure des questions à réponse libre</label>
                            </div>
                            <small class="form-text text-muted">Les questions à réponse libre nécessitent une notation manuelle.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="randomize_questions" name="randomize_questions" checked>
                                <label class="custom-control-label" for="randomize_questions">Mélanger les questions</label>
                            </div>
                            <small class="form-text text-muted">L'ordre des questions sera différent pour chaque étudiant.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="show_results" name="show_results" checked>
                                <label class="custom-control-label" for="show_results">Afficher les résultats immédiatement</label>
                            </div>
                            <small class="form-text text-muted">Les étudiants verront leur score dès qu'ils auront terminé l'examen.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="allow_retake" name="allow_retake">
                                <label class="custom-control-label" for="allow_retake">Autoriser les reprises</label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="max_retakes_group" style="display: none;">
                            <label for="max_retakes">Nombre maximum de reprises</label>
                            <input type="number" id="max_retakes" name="max_retakes" class="form-control" min="1" value="1">
                        </div>
                    </div>
                    
                    <div class="form-tab-content" id="proctoring">
                        <div class="proctoring-info">
                            <p>La surveillance automatisée aide à maintenir l'intégrité de l'examen en détectant les comportements suspects.</p>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enable_face_recognition" name="enable_face_recognition" checked>
                                <label class="custom-control-label" for="enable_face_recognition">Reconnaissance faciale</label>
                            </div>
                            <small class="form-text text-muted">Détecte si l'étudiant quitte le champ de vision ou si une autre personne apparaît.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enable_eye_tracking" name="enable_eye_tracking" checked>
                                <label class="custom-control-label" for="enable_eye_tracking">Suivi oculaire</label>
                            </div>
                            <small class="form-text text-muted">Détecte si l'étudiant regarde ailleurs que l'écran pendant une période prolongée.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enable_audio_monitoring" name="enable_audio_monitoring" checked>
                                <label class="custom-control-label" for="enable_audio_monitoring">Surveillance audio</label>
                            </div>
                            <small class="form-text text-muted">Détecte les conversations ou bruits suspects pendant l'examen.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enable_screen_monitoring" name="enable_screen_monitoring" checked>
                                <label class="custom-control-label" for="enable_screen_monitoring">Surveillance d'écran</label>
                            </div>
                            <small class="form-text text-muted">Détecte si l'étudiant change d'onglet ou ouvre d'autres applications.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="strictness_level">Niveau de rigueur</label>
                            <select id="strictness_level" name="strictness_level" class="form-control">
                                <option value="low">Faible</option>
                                <option value="medium" selected>Moyen</option>
                                <option value="high">Élevé</option>
                                <option value="very_high">Très élevé</option>
                            </select>
                            <small class="form-text text-muted">Détermine la sensibilité de détection des comportements suspects.</small>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" id="prev-btn" class="btn btn-secondary" style="display: none;">Précédent</button>
                        <button type="button" id="next-btn" class="btn btn-primary">Suivant</button>
                        <button type="submit" id="submit-btn" name="create_exam" class="btn btn-success" style="display: none;">Créer l'examen</button>
                    </div>
                </form>
            </div>
            
            <div class="exam-preview-card">
                <h2>Aperçu de l'examen</h2>
                <div class="exam-preview">
                    <div class="preview-header">
                        <h3 id="preview-title">Titre de l'examen</h3>
                        <span class="preview-subject" id="preview-subject">Matière</span>
                    </div>
                    
                    <div class="preview-details">
                        <div class="preview-item">
                            <i class="fas fa-clock"></i>
                            <span id="preview-duration">60 minutes</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span id="preview-dates">Date de début - Date de fin</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-percentage"></i>
                            <span id="preview-passing-score">Score de réussite: 60%</span>
                        </div>
                    </div>
                    
                    <div class="preview-description" id="preview-description">
                        <p>Description de l'examen...</p>
                    </div>
                    
                    <div class="preview-settings">
                        <h4>Paramètres</h4>
                        <ul>
                            <li id="preview-has-essay" style="display: none;">
                                <i class="fas fa-pen"></i> Questions à réponse libre
                            </li>
                            <li id="preview-randomize" class="active">
                                <i class="fas fa-random"></i> Questions mélangées
                            </li>
                            <li id="preview-show-results" class="active">
                                <i class="fas fa-chart-bar"></i> Résultats immédiats
                            </li>
                            <li id="preview-allow-retake" style="display: none;">
                                <i class="fas fa-redo"></i> Reprises autorisées (<span id="preview-max-retakes">1</span>)
                            </li>
                        </ul>
                    </div>
                    
                    <div class="preview-proctoring">
                        <h4>Surveillance</h4>
                        <ul>
                            <li id="preview-face-recognition" class="active">
                                <i class="fas fa-user"></i> Reconnaissance faciale
                            </li>
                            <li id="preview-eye-tracking" class="active">
                                <i class="fas fa-eye"></i> Suivi oculaire
                            </li>
                            <li id="preview-audio-monitoring" class="active">
                                <i class="fas fa-microphone"></i> Surveillance audio
                            </li>
                            <li id="preview-screen-monitoring" class="active">
                                <i class="fas fa-desktop"></i> Surveillance d'écran
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu latéral
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const teacherSidebar = document.querySelector('.teacher-sidebar');
    const teacherContent = document.querySelector('.teacher-content');
    
    sidebarToggle.addEventListener('click', function() {
        teacherSidebar.classList.toggle('collapsed');
        teacherContent.classList.toggle('expanded');
    });
    
    // Gestion du dropdown utilisateur
    const userDropdownBtn = document.querySelector('.user-dropdown-btn');
    const userDropdownContent = document.querySelector('.user-dropdown-content');
    
    userDropdownBtn.addEventListener('click', function() {
        userDropdownContent.classList.toggle('show');
    });
    
    // Fermer le dropdown quand on clique ailleurs
    window.addEventListener('click', function(event) {
        if (!event.target.matches('.user-dropdown-btn') && !event.target.closest('.user-dropdown-btn')) {
            if (userDropdownContent.classList.contains('show')) {
                userDropdownContent.classList.remove('show');
            }
        }
    });
    
    // Gestion des onglets du formulaire
    const formTabs = document.querySelectorAll('.form-tab');
    const formTabContents = document.querySelectorAll('.form-tab-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    
    let currentTab = 0;
    
    function showTab(tabIndex) {
        formTabs.forEach(tab => tab.classList.remove('active'));
        formTabContents.forEach(content => content.classList.remove('active'));
        
        formTabs[tabIndex].classList.add('active');
        formTabContents[tabIndex].classList.add('active');
        
        // Afficher/masquer les boutons de navigation
        if (tabIndex === 0) {
            prevBtn.style.display = 'none';
        } else {
            prevBtn.style.display = 'inline-block';
        }
        
        if (tabIndex === formTabs.length - 1) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-block';
        } else {
            nextBtn.style.display = 'inline-block';
            submitBtn.style.display = 'none';
        }
        
        currentTab = tabIndex;
    }
    
    formTabs.forEach((tab, index) => {
        tab.addEventListener('click', () => showTab(index));
    });
    
    prevBtn.addEventListener('click', () => {
        if (currentTab > 0) {
            showTab(currentTab - 1);
        }
    });
    
    nextBtn.addEventListener('click', () => {
        if (currentTab < formTabs.length - 1) {
            showTab(currentTab + 1);
        }
    });
    
    // Afficher/masquer le champ de nombre maximum de reprises
    const allowRetakeCheckbox = document.getElementById('allow_retake');
    const maxRetakesGroup = document.getElementById('max_retakes_group');
    
    allowRetakeCheckbox.addEventListener('change', function() {
        maxRetakesGroup.style.display = this.checked ? 'block' : 'none';
        document.getElementById('preview-allow-retake').style.display = this.checked ? 'list-item' : 'none';
    });
    
    // Mise à jour de l'aperçu en temps réel
    const titleInput = document.getElementById('title');
    const subjectInput = document.getElementById('subject');
    const descriptionInput = document.getElementById('description');
    const durationInput = document.getElementById('duration');
    const passingScoreInput = document.getElementById('passing_score');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const hasEssayCheckbox = document.getElementById('has_essay');
    const randomizeCheckbox = document.getElementById('randomize_questions');
    const showResultsCheckbox = document.getElementById('show_results');
    const maxRetakesInput = document.getElementById('max_retakes');
    const faceRecognitionCheckbox = document.getElementById('enable_face_recognition');
    const eyeTrackingCheckbox = document.getElementById('enable_eye_tracking');
    const audioMonitoringCheckbox = document.getElementById('enable_audio_monitoring');
    const screenMonitoringCheckbox = document.getElementById('enable_screen_monitoring');
    
    // Mise à jour de l'aperçu du titre
    titleInput.addEventListener('input', function() {
        document.getElementById('preview-title').textContent = this.value || 'Titre de l\'examen';
    });
    
    // Mise à jour de l'aperçu de la matière
    subjectInput.addEventListener('input', function() {
        document.getElementById('preview-subject').textContent = this.value || 'Matière';
    });
    
    // Mise à jour de l'aperçu de la description
    descriptionInput.addEventListener('input', function() {
        document.getElementById('preview-description').innerHTML = '<p>' + (this.value || 'Description de l\'examen...') + '</p>';
    });
    
    // Mise à jour de l'aperçu de la durée
    durationInput.addEventListener('input', function() {
        document.getElementById('preview-duration').textContent = this.value + ' minutes';
    });
    
    // Mise à jour de l'aperçu du score de réussite
    passingScoreInput.addEventListener('input', function() {
        document.getElementById('preview-passing-score').textContent = 'Score de réussite: ' + this.value + '%';
    });
    
    // Mise à jour de l'aperçu des dates
    function updateDates() {
        const startDate = startDateInput.value ? new Date(startDateInput.value) : null;
        const endDate = endDateInput.value ? new Date(endDateInput.value) : null;
        
        if (startDate && endDate) {
            const formattedStartDate = startDate.toLocaleDateString('fr-FR');
            const formattedEndDate = endDate.toLocaleDateString('fr-FR');
            document.getElementById('preview-dates').textContent = formattedStartDate + ' - ' + formattedEndDate;
        } else {
            document.getElementById('preview-dates').textContent = 'Date de début - Date de fin';
        }
    }
    
    startDateInput.addEventListener('input', updateDates);
    endDateInput.addEventListener('input', updateDates);
    
    // Mise à jour de l'aperçu des questions à réponse libre
    hasEssayCheckbox.addEventListener('change', function() {
        document.getElementById('preview-has-essay').style.display = this.checked ? 'list-item' : 'none';
    });
    
    // Mise à jour de l'aperçu du mélange des questions
    randomizeCheckbox.addEventListener('change', function() {
        document.getElementById('preview-randomize').classList.toggle('active', this.checked);
    });
    
    // Mise à jour de l'aperçu des résultats immédiats
    showResultsCheckbox.addEventListener('change', function() {
        document.getElementById('preview-show-results').classList.toggle('active', this.checked);
    });
    
    // Mise à jour de l'aperçu du nombre maximum de reprises
    maxRetakesInput.addEventListener('input', function() {
        document.getElementById('preview-max-retakes').textContent = this.value;
    });
    
    // Mise à jour de l'aperçu de la reconnaissance faciale
    faceRecognitionCheckbox.addEventListener('change', function() {
        document.getElementById('preview-face-recognition').classList.toggle('active', this.checked);
    });
    
    // Mise à jour de l'aperçu du suivi oculaire
    eyeTrackingCheckbox.addEventListener('change', function() {
        document.getElementById('preview-eye-tracking').classList.toggle('active', this.checked);
    });
    
    // Mise à jour de l'aperçu de la surveillance audio
    audioMonitoringCheckbox.addEventListener('change', function() {
        document.getElementById('preview-audio-monitoring').classList.toggle('active', this.checked);
    });
    
    // Mise à jour de l'aperçu de la surveillance d'écran
    screenMonitoringCheckbox.addEventListener('change', function() {
        document.getElementById('preview-screen-monitoring').classList.toggle('active', this.checked);
    });
    
    // Initialiser l'affichage du premier onglet
    showTab(0);
});
</script>

<style>
/* Styles spécifiques à la page de création d'examen */
.create-exam-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.exam-form-card,
.exam-preview-card {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.form-tabs {
    display: flex;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.form-tab {
    padding: 15px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
    flex: 1;
    text-align: center;
}

.form-tab:hover {
    background-color: rgba(74, 108, 247, 0.05);
    color: #4a6cf7;
}

.form-tab.active {
    background-color: #fff;
    color: #4a6cf7;
    border-bottom: 2px solid #4a6cf7;
}

.form-tab i {
    margin-right: 8px;
}

.form-tab-content {
    display: none;
    padding: 20px;
}

.form-tab-content.active {
    display: block;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #4a6cf7;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(74, 108, 247, 0.25);
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #6c757d;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -10px;
    margin-left: -10px;
}

.form-row > .form-group {
    padding-right: 10px;
    padding-left: 10px;
    flex: 1;
}

.form-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.required {
    color: #dc3545;
}

.proctoring-info {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.proctoring-info p {
    margin: 0;
    color: #6c757d;
}

/* Aperçu de l'examen */
.exam-preview-card {
    position: sticky;
    top: 20px;
}

.exam-preview-card h2 {
    padding: 15px 20px;
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    border-bottom: 1px solid #e9ecef;
}

.exam-preview {
    padding: 20px;
}

.preview-header {
    margin-bottom: 15px;
}

.preview-header h3 {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: #333;
}

.preview-subject {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 500;
    background-color: #f8f9fa;
    color: #6c757d;
    border-radius: 4px;
}

.preview-details {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.preview-item {
    display: flex;
    align-items: center;
    font-size: 14px;
    color: #555;
}

.preview-item i {
    margin-right: 8px;
    color: #4a6cf7;
}

.preview-description {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.preview-description p {
    margin: 0;
    color: #555;
}

.preview-settings,
.preview-proctoring {
    margin-bottom: 15px;
}

.preview-settings h4,
.preview-proctoring h4 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 10px 0;
    color: #333;
}

.preview-settings ul,
.preview-proctoring ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.preview-settings li,
.preview-proctoring li {
    display: flex;
    align-items: center;
    padding: 8px 0;
    color: #6c757d;
    opacity: 0.7;
}

.preview-settings li.active,
.preview-proctoring li.active {
    color: #555;
    opacity: 1;
}

.preview-settings li i,
.preview-proctoring li i {
    margin-right: 8px;
    width: 16px;
    text-align: center;
}

/* Switches */
.custom-control {
    position: relative;
    display: block;
    min-height: 1.5rem;
    padding-left: 1.5rem;
}

.custom-control-input {
    position: absolute;
    z-index: -1;
    opacity: 0;
}

.custom-control-label {
    position: relative;
    margin-bottom: 0;
    vertical-align: top;
}

.custom-control-label::before {
    position: absolute;
    top: 0.25rem;
    left: -1.5rem;
    display: block;
    width: 1rem;
    height: 1rem;
    pointer-events: none;
    content: "";
    background-color: #fff;
    border: 1px solid #adb5bd;
    border-radius: 0.25rem;
}

.custom-control-label::after {
    position: absolute;
    top: 0.25rem;
    left: -1.5rem;
    display: block;
    width: 1rem;
    height: 1rem;
    content: "";
    background: no-repeat 50%/50% 50%;
}

.custom-switch {
    padding-left: 2.25rem;
}

.custom-switch .custom-control-label::before {
    left: -2.25rem;
    width: 1.75rem;
    pointer-events: all;
    border-radius: 0.5rem;
}

.custom-switch .custom-control-label::after {
    top: calc(0.25rem + 2px);
    left: calc(-2.25rem + 2px);
    width: calc(1rem - 4px);
    height: calc(1rem - 4px);
    background-color: #adb5bd;
    border-radius: 0.5rem;
    transition: transform 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.custom-switch .custom-control-input:checked ~ .custom-control-label::after {
    background-color: #fff;
    transform: translateX(0.75rem);
}

.custom-switch .custom-control-input:checked ~ .custom-control-label::before {
    background-color: #4a6cf7;
    border-color: #4a6cf7;
}

/* Responsive */
@media (max-width: 992px) {
    .create-exam-container {
        grid-template-columns: 1fr;
    }
    
    .exam-preview-card {
        position: static;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
