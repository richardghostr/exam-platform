<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Définir le titre de la page
$pageTitle = "Créer un nouvel examen";

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];
$subjects = [];
$subjectResult = $conn->query("SELECT id, name FROM subjects");
while ($row = $subjectResult->fetch_assoc()) {
    $subjects[$row['id']] = $row['name'];
}

// Traitement du formulaire de création d'examen
if (isset($_POST['create_exam'])) {
    // Validation et nettoyage des données
    $title = trim($_POST['title']);
    $subjectId = (int)$_POST['subject']; // Changé pour correspondre au type INT de la table
    $description = trim($_POST['description']);
    $duration = (int)$_POST['duration'];
    $passingScore = (int)$_POST['passing_score'];
    $startDate = $_POST['start_date']; // Gardé comme texte comme dans la table
    $endDate = $_POST['end_date']; // Gardé comme texte comme dans la table
    $status = in_array($_POST['status'], ['draft', 'published', 'scheduled', 'completed']) ? $_POST['status'] : 'draft';

    // Options booléennes
    $hasEssay = isset($_POST['has_essay']) ? 1 : 0;
    $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    $allowRetake = isset($_POST['allow_retake']) ? 1 : 0;
    $maxRetakes = isset($_POST['max_retakes']) ? (int)$_POST['max_retakes'] : 0;

    // Paramètres de surveillance
    $enableFaceRecognition = isset($_POST['enable_face_recognition']) ? 1 : 0;
    $enableEyeTracking = isset($_POST['enable_eye_tracking']) ? 1 : 0;
    $enableAudioMonitoring = isset($_POST['enable_audio_monitoring']) ? 1 : 0;
    $enableScreenMonitoring = isset($_POST['enable_screen_monitoring']) ? 1 : 0;

    // Insérer l'examen dans la base de données
    $stmt = $conn->prepare("
        INSERT INTO exams (
            title, description, subject, duration, passing_score,
            start_date, end_date, status, teacher_id, has_essay, 
            randomize_questions, show_results, allow_retake, max_retakes,
            enable_face_recognition, enable_eye_tracking, 
            enable_audio_monitoring, enable_screen_monitoring,
            created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    // Types des paramètres: s=string, i=integer, d=double
    $stmt->bind_param(
        "ssiidsssiiiiiiiiiii", // 20 caractères pour 20 paramètres
        $title,
        $description,
        $subjectId, // maintenant un INT
        $duration,
        $passingScore,
        $startDate,
        $endDate,
        $status,
        $teacherId,
        $hasEssay,
        $randomizeQuestions,
        $showResults,
        $allowRetake,
        $maxRetakes,
        $enableFaceRecognition,
        $enableEyeTracking,
        $enableAudioMonitoring,
        $enableScreenMonitoring,
        $teacherId // created_by
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

// Inclure le header
include 'includes/header.php';
?>

<!-- Create Exam Content -->
<div class="dashboard">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="create-exam-container">
        <div class="card exam-form-card">
            <div class="card-header">
                <ul class="form-tabs">
                    <li class="tab-item active" data-tab="basic-info">
                        <i class="fas fa-info-circle"></i> Informations de base
                    </li>
                    <li class="tab-item" data-tab="settings">
                        <i class="fas fa-cog"></i> Paramètres
                    </li>
                    <li class="tab-item" data-tab="proctoring">
                        <i class="fas fa-video"></i> Surveillance
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <form method="post" action="" class="exam-form">
                    <div class="tab-content active" id="basic-info-tab">
                        <div class="form-group">
                            <label for="title">Titre de l'examen <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="subject">Matière <span class="required">*</span></label>
                            <select id="subject" name="subject" class="form-control" required>
                                <option value="">Sélectionnez une matière</option>
                                <?php foreach ($subjects as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo isset($_POST['subject']) && $_POST['subject'] == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration">Durée (minutes) <span class="required">*</span></label>
                                <input type="number" id="duration" name="duration" class="form-control" min="1" value="60" required>
                            </div>

                            <div class="form-group">
                                <label for="passing_score">Score de réussite (%) <span class="required">*</span></label>
                                <input type="number" id="passing_score" name="passing_score" class="form-control" min="1" max="100" value="60" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Date de début <span class="required">*</span></label>
                                <input type="datetime-local" id="start_date" name="start_date" class="form-control" required>
                            </div>

                            <div class="form-group">
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

                    <div class="tab-content" id="settings-tab">
                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="has_essay" name="has_essay">
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="has_essay">Inclure des questions à réponse libre</label>
                                    <small class="form-text">Les questions à réponse libre nécessitent une notation manuelle.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="randomize_questions" name="randomize_questions" checked>
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="randomize_questions">Mélanger les questions</label>
                                    <small class="form-text">L'ordre des questions sera différent pour chaque étudiant.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="show_results" name="show_results" checked>
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="show_results">Afficher les résultats immédiatement</label>
                                    <small class="form-text">Les étudiants verront leur score dès qu'ils auront terminé l'examen.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="allow_retake" name="allow_retake">
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="allow_retake">Autoriser les reprises</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="max_retakes_group" style="display: none;">
                            <label for="max_retakes">Nombre maximum de reprises</label>
                            <input type="number" id="max_retakes" name="max_retakes" class="form-control" min="1" value="1">
                        </div>
                    </div>

                    <div class="tab-content" id="proctoring-tab">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <p>La surveillance automatisée aide à maintenir l'intégrité de l'examen en détectant les comportements suspects.</p>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="enable_face_recognition" name="enable_face_recognition" checked>
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="enable_face_recognition">Reconnaissance faciale</label>
                                    <small class="form-text">Détecte si l'étudiant quitte le champ de vision ou si une autre personne apparaît.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="enable_eye_tracking" name="enable_eye_tracking" checked>
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="enable_eye_tracking">Suivi oculaire</label>
                                    <small class="form-text">Détecte si l'étudiant regarde ailleurs que l'écran pendant une période prolongée.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="enable_audio_monitoring" name="enable_audio_monitoring" checked>
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="enable_audio_monitoring">Surveillance audio</label>
                                    <small class="form-text">Détecte les conversations ou bruits suspects pendant l'examen.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="switch-container">
                                <label class="switch">
                                    <input type="checkbox" id="enable_screen_monitoring" name="enable_screen_monitoring" checked>
                                    <span class="slider round"></span>
                                </label>
                                <div class="switch-label">
                                    <label for="enable_screen_monitoring">Surveillance d'écran</label>
                                    <small class="form-text">Détecte si l'étudiant change d'onglet ou ouvre d'autres applications.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="strictness_level">Niveau de rigueur</label>
                            <select id="strictness_level" name="strictness_level" class="form-control">
                                <option value="low">Faible</option>
                                <option value="medium" selected>Moyen</option>
                                <option value="high">Élevé</option>
                                <option value="very_high">Très élevé</option>
                            </select>
                            <small class="form-text">Détermine la sensibilité de détection des comportements suspects.</small>
                        </div>
                    </div>

                    <div class="form-buttons">
                        <button type="button" id="prev-btn" class="btn btn-secondary" style="display: none;">Précédent</button>
                        <button type="button" id="next-btn" class="btn btn-primary">Suivant</button>
                        <button type="submit" id="submit-btn" name="create_exam" class="btn btn-success" style="display: none;">Créer l'examen</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card exam-preview-card">
            <div class="card-header">
                <h2 class="card-title">Aperçu de l'examen</h2>
            </div>
            <div class="card-body">
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
        // Gestion des onglets du formulaire
        const tabItems = document.querySelectorAll('.tab-item');
        const tabContents = document.querySelectorAll('.tab-content');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');

        let currentTab = 0;

        function showTab(tabIndex) {
            tabItems.forEach(tab => tab.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            tabItems[tabIndex].classList.add('active');
            tabContents[tabIndex].classList.add('active');

            // Afficher/masquer les boutons de navigation
            if (tabIndex === 0) {
                prevBtn.style.display = 'none';
            } else {
                prevBtn.style.display = 'inline-block';
            }

            if (tabIndex === tabItems.length - 1) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-block';
            } else {
                nextBtn.style.display = 'inline-block';
                submitBtn.style.display = 'none';
            }

            currentTab = tabIndex;
        }

        tabItems.forEach((tab, index) => {
            tab.addEventListener('click', () => showTab(index));
        });

        prevBtn.addEventListener('click', () => {
            if (currentTab > 0) {
                showTab(currentTab - 1);
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentTab < tabItems.length - 1) {
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