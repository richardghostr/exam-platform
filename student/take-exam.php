<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un étudiant

// Récupérer l'ID de l'étudiant
$studentId = $_SESSION['user_id'];

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: exams.php');
    exit();
}

$examId = intval($_GET['id']);

// Vérifier si l'examen existe et est publié
$examQuery = $conn->prepare("
    SELECT e.*, s.name as subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject = s.id 
    WHERE e.id = ? AND e.status IN ('published', 'scheduled')
");
$examQuery->bind_param("i", $examId);
$examQuery->execute();
$examResult = $examQuery->get_result();

if ($examResult->num_rows === 0) {
    header('Location: exams.php?error=exam_not_found');
    exit();
}

$exam = $examResult->fetch_assoc();

// Vérifier si l'étudiant est autorisé à passer cet examen
// Modification: Vérifier via exam_enrollments ou si l'examen est public
$accessQuery = $conn->prepare("
    SELECT 1 
    FROM exams e
    WHERE e.id = ? AND (
        EXISTS (SELECT 1 FROM exam_enrollments ee WHERE ee.exam_id = e.id AND ee.student_id = ?)
        OR (e.status = 'published' AND e.start_date <= NOW() AND e.end_date >= NOW())
    )
");
$accessQuery->bind_param("ii", $examId, $studentId);
$accessQuery->execute();
$accessResult = $accessQuery->get_result();

if ($accessResult->num_rows === 0) {
    header('Location: exams.php?error=not_authorized');
    exit();
}

// Vérifier si l'examen est dans la période autorisée
$now = date('Y-m-d H:i:s');
if ($exam['start_date'] > $now) {
    header('Location: exams.php?error=exam_not_started');
    exit();
}
if ($exam['end_date'] < $now) {
    header('Location: exams.php?error=exam_ended');
    exit();
}

// Vérifier si l'étudiant a déjà une tentative en cours ou a atteint le nombre maximum de tentatives
$attemptQuery = $conn->prepare("
    SELECT * 
    FROM exam_attempts 
    WHERE exam_id = ? AND user_id = ? 
    ORDER BY id DESC 
    LIMIT 1
");
$attemptQuery->bind_param("ii", $examId, $studentId);
$attemptQuery->execute();
$attemptResult = $attemptQuery->get_result();
$hasAttempt = $attemptResult->num_rows > 0;
$attempt = $hasAttempt ? $attemptResult->fetch_assoc() : null;

// Vérifier le nombre de tentatives
$attemptsCountQuery = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exam_attempts 
    WHERE exam_id = ? AND user_id = ?
");
$attemptsCountQuery->bind_param("ii", $examId, $studentId);
$attemptsCountQuery->execute();
$attemptsCountResult = $attemptsCountQuery->get_result();
$attemptsCount = $attemptsCountResult->fetch_assoc()['count'];

// Récupérer le paramètre max_attempts depuis les settings
$maxAttemptsQuery = $conn->query("SELECT value FROM settings WHERE setting_key = 'max_retakes'");
$maxAttempts = $maxAttemptsQuery->fetch_assoc()['value'];

if ($attemptsCount >= $maxAttempts && $attempt['status'] !== 'in_progress') {
    header('Location: exams.php?error=max_attempts_reached');
    exit();
}

// Si l'étudiant a une tentative en cours, récupérer cette tentative
// Sinon, créer une nouvelle tentative
if ($hasAttempt && $attempt['status'] === 'in_progress') {
    $attemptId = $attempt['id'];
    $startTime = $attempt['start_time'];
} else {
    // Créer une nouvelle tentative
    $startTime = date('Y-m-d H:i:s');
    $insertAttemptQuery = $conn->prepare("
        INSERT INTO exam_attempts (exam_id, user_id, start_time, status) 
        VALUES (?, ?, ?, 'in_progress')
    ");
    $insertAttemptQuery->bind_param("iis", $examId, $studentId, $startTime);
    $insertAttemptQuery->execute();
    $attemptId = $conn->insert_id;
}

// Calculer le temps restant en secondes
$endTime = strtotime($startTime) + ($exam['duration'] * 60);
$remainingTime = $endTime - time();

// Si le temps est écoulé, rediriger vers la page de résultats
if ($remainingTime <= 0) {
    // Mettre à jour le statut de la tentative
    $updateAttemptQuery = $conn->prepare("
        UPDATE exam_attempts 
        SET status = 'completed', end_time = NOW() 
        WHERE id = ?
    ");
    $updateAttemptQuery->bind_param("i", $attemptId);
    $updateAttemptQuery->execute();

    header('Location: exam-result.php?attempt_id=' . $attemptId);
    exit();
}

// Récupérer les questions de l'examen
$questionsQuery = $conn->prepare(
    "
    SELECT q.* 
    FROM questions q 
    WHERE q.exam_id = ? 
    ORDER BY " . ($exam['randomize_questions'] ? "RAND()" : "q.id ASC")
);
$questionsQuery->bind_param("i", $examId);
$questionsQuery->execute();
$questionsResult = $questionsQuery->get_result();
$questions = [];
while ($question = $questionsResult->fetch_assoc()) {
    $questions[] = $question;
}

// Récupérer les réponses déjà enregistrées pour cette tentative
$answersQuery = $conn->prepare("
    SELECT * 
    FROM user_answers 
    WHERE attempt_id = ?
");
$answersQuery->bind_param("i", $attemptId);
$answersQuery->execute();
$answersResult = $answersQuery->get_result();
$answers = [];
while ($answer = $answersResult->fetch_assoc()) {
    $answers[$answer['question_id']] = $answer;
}

// Déterminer si la surveillance est activée
$proctoringEnabled = isset($exam['is_proctored']) ? $exam['is_proctored'] == 1 : true;

// Récupérer les options pour les questions à choix multiples
$optionsQuery = $conn->prepare("
    SELECT qo.* 
    FROM question_options qo
    JOIN questions q ON qo.question_id = q.id
    WHERE q.exam_id = ?
    
");
$optionsQuery->bind_param("i", $examId);
$optionsQuery->execute();
$optionsResult = $optionsQuery->get_result();
$options = [];
while ($option = $optionsResult->fetch_assoc()) {
    $options[$option['question_id']][] = $option;
}

// Récupérer les incidents de surveillance déjà enregistrés
$incidentsQuery = $conn->prepare("
    SELECT * 
    FROM proctoring_incidents 
    WHERE attempt_id = ?
    ORDER BY timestamp DESC
");
$incidentsQuery->bind_param("i", $attemptId);
$incidentsQuery->execute();
$incidentsResult = $incidentsQuery->get_result();
$incidents = [];
while ($incident = $incidentsResult->fetch_assoc()) {
    $incidents[] = $incident;
}

$pageTitle = "Passer l'examen: " . $exam['title'];
$hideNavigation = true; // Cacher la navigation pendant l'examen
$extraCss = ['../assets/css/exam.css'];
$extraJs = ['../assets/js/exam.js'];
if ($proctoringEnabled) {
    $extraJs[] = '../assets/js/face-api.min.js';
    $extraJs[] = '../assets/js/webgazer.js';
    $extraJs[] = '../assets/js/proctoring.js';
}

include 'includes/header.php';
?>
<!-- Face-API.js -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.2/dist/coco-ssd.min.js"></script>
<!-- WebGazer.js -->
<script src="https://cdn.jsdelivr.net/npm/webgazer@2.1.0/dist/webgazer.min.js"></script>
<div class="exam-container">
    <!-- Barre d'en-tête de l'examen -->
    <div class="exam-header">
        <div class="exam-info">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="exam-meta">
                <span class="subject"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                <span class="separator">•</span>
                <span class="questions-count"><?php echo count($questions); ?> questions</span>
                <span class="separator">•</span>
                <span class="duration"><?php echo $exam['duration']; ?> minutes</span>
            </div>
        </div>

        <div class="exam-timer" id="examTimer" data-remaining="<?php echo $remainingTime; ?>">
            <div class="timer-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="timer-display">
                <span id="hours"><?php echo floor($remainingTime / 3600); ?></span>:<span id="minutes"><?php echo floor(($remainingTime % 3600) / 60); ?></span>:<span id="seconds"><?php echo $remainingTime % 60; ?></span>
            </div>
        </div>
    </div>

    <?php if ($proctoringEnabled): ?>
        <!-- Barre de surveillance -->
        <div class="proctoring-bar">
            <div class="proctoring-status">
                <i class="fas fa-shield-alt"></i> Surveillance active
            </div>
            <div class="proctoring-warnings" id="proctoringWarnings">
                <span class="warning-count"><?php echo count($incidents); ?></span> incidents détectés
            </div>
            <div class="proctoring-toggle">
                <button id="toggleProctoring" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>

        <!-- Conteneur de surveillance -->
        <div class="proctoring-container" id="proctoringContainer">
            <div class="proctoring-grid">
                <div class="webcam-container">
                    <video id="webcam" autoplay playsinline muted></video>
                    <canvas id="canvas" class="overlay"></canvas>
                    <canvas id="gaze-canvas" class="overlay"></canvas>
                </div>

                <div class="proctoring-status-container">
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="exam-body">
        <div class="questions-navigation">
            <div class="navigation-header">
                <h3>Questions</h3>
                <div class="progress-info">
                    <span id="answeredCount"><?php echo count($answers); ?></span> / <?php echo count($questions); ?> répondues
                </div>
            </div>

            <div class="questions-list" id="questionsList">
                <?php foreach ($questions as $index => $question): ?>
                    <button class="question-button <?php echo isset($answers[$question['id']]) ? 'answered' : ''; ?>"
                        data-question-id="<?php echo $question['id']; ?>"
                        data-index="<?php echo $index; ?>">
                        <?php echo $index + 1; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="navigation-actions">
                <button id="prevQuestion" class="nav-button" disabled>
                    <i class="fas fa-chevron-left"></i> Précédent
                </button>
                <button id="nextQuestion" class="nav-button">
                    Suivant <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="submit-section">
                <button id="submitExam" class="btn btn-primary btn-block">
                    <i class="fas fa-check-circle"></i> Terminer l'examen
                </button>
            </div>
        </div>

        <div class="question-container" id="questionContainer" data-attempt-id="<?php echo $attemptId; ?>">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-slide" id="question-<?php echo $question['id']; ?>" data-index="<?php echo $index; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>;">
                    <div class="question-header">
                        <div class="question-number">Question <?php echo $index + 1; ?> sur <?php echo count($questions); ?></div>
                        <div class="question-points"><?php echo isset($question['points']) ? $question['points'] : 1; ?> points</div>
                    </div>

                    <div class="question-content">
                        <div class="question-text"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></div>

                        <?php if (!empty($question['question_image'])): ?>
                            <div class="question-image">
                                <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" alt="Image de la question">
                            </div>
                        <?php endif; ?>

                        <div class="question-answer">
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <?php if (isset($options[$question['id']])): ?>
                                    <div class="options-list multiple-choice">
                                        <?php foreach ($options[$question['id']] as $option): ?>
                                            <?php
                                            $selectedOptions = isset($answers[$question['id']]) ? explode(',', $answers[$question['id']]['selected_options']) : [];
                                            ?>
                                            <div class="option-item">
                                                <label class="option-label">
                                                    <input type="checkbox" name="question_<?php echo $question['id']; ?>[]"
                                                        value="<?php echo $option['id']; ?>"
                                                        class="option-input"
                                                        data-question-id="<?php echo $question['id']; ?>"
                                                        <?php echo in_array($option['id'], $selectedOptions) ? 'checked' : ''; ?>>
                                                    <span class="option-checkbox"></span>
                                                    <span class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($question['question_type'] === 'single_choice'): ?>
                                <?php if (isset($options[$question['id']])): ?>
                                    <?php
                                    $selectedOption = isset($answers[$question['id']]) ? $answers[$question['id']]['selected_options'] : '';
                                    ?>
                                    <div class="options-list single-choice">
                                        <?php foreach ($options[$question['id']] as $option): ?>
                                            <div class="option-item">
                                                <label class="option-label">
                                                    <input type="radio" name="question_<?php echo $question['id']; ?>"
                                                        value="<?php echo $option['id']; ?>"
                                                        class="option-input"
                                                        data-question-id="<?php echo $question['id']; ?>"
                                                        <?php echo $selectedOption == $option['id'] ? 'checked' : ''; ?>>
                                                    <span class="option-radio"></span>
                                                    <span class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <?php $selectedOption = isset($answers[$question['id']]) ? $answers[$question['id']]['selected_options'] : ''; ?>
                                <div class="options-list true-false">
                                    <div class="option-item">
                                        <label class="option-label">
                                            <input type="radio" name="question_<?php echo $question['id']; ?>"
                                                value="true"
                                                class="option-input"
                                                data-question-id="<?php echo $question['id']; ?>"
                                                <?php echo $selectedOption === 'true' ? 'checked' : ''; ?>>
                                            <span class="option-radio"></span>
                                            <span class="option-text">Vrai</span>
                                        </label>
                                    </div>
                                    <div class="option-item">
                                        <label class="option-label">
                                            <input type="radio" name="question_<?php echo $question['id']; ?>"
                                                value="false"
                                                class="option-input"
                                                data-question-id="<?php echo $question['id']; ?>"
                                                <?php echo $selectedOption === 'false' ? 'checked' : ''; ?>>
                                            <span class="option-radio"></span>
                                            <span class="option-text">Faux</span>
                                        </label>
                                    </div>
                                </div>

                            <?php elseif ($question['question_type'] === 'essay' || $question['question_type'] === 'short_answer'): ?>
                                <?php $answerText = isset($answers[$question['id']]) ? $answers[$question['id']]['answer_text'] : ''; ?>
                                <div class="essay-answer">
                                    <textarea name="question_<?php echo $question['id']; ?>"
                                        class="essay-input"
                                        data-question-id="<?php echo $question['id']; ?>"
                                        placeholder="Saisissez votre réponse ici..."
                                        rows="<?php echo $question['question_type'] === 'essay' ? '8' : '3'; ?>"><?php echo htmlspecialchars($answerText); ?></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="question-footer">
                        <div class="save-status" id="saveStatus-<?php echo $question['id']; ?>">
                            <?php if (isset($answers[$question['id']])): ?>
                                <i class="fas fa-check-circle"></i> Réponse enregistrée
                            <?php endif; ?>
                        </div>
                        <div class="question-navigation">
                            <?php if ($index > 0): ?>
                                <button class="btn btn-outline-primary prev-btn" data-index="<?php echo $index - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Question précédente
                                </button>
                            <?php endif; ?>

                            <?php if ($index < count($questions) - 1): ?>
                                <button class="btn btn-primary next-btn" data-index="<?php echo $index + 1; ?>">
                                    Question suivante <i class="fas fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success finish-btn" id="finishBtn">
                                    Terminer l'examen <i class="fas fa-check-circle"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour terminer l'examen -->
<div class="modal" id="finishExamModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Terminer l'examen</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="exam-summary">
                <p>Vous êtes sur le point de terminer l'examen. Veuillez vérifier votre progression :</p>

                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-value" id="totalQuestions"><?php echo count($questions); ?></div>
                        <div class="stat-label">Questions totales</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="answeredQuestionsCount"><?php echo count($answers); ?></div>
                        <div class="stat-label">Questions répondues</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="unansweredQuestionsCount"><?php echo count($questions) - count($answers); ?></div>
                        <div class="stat-label">Questions sans réponse</div>
                    </div>
                </div>

                <div class="warning-message" id="unansweredWarning" <?php echo count($questions) - count($answers) > 0 ? '' : 'style="display: none;"'; ?>>
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Attention : Vous avez des questions sans réponse. Êtes-vous sûr de vouloir terminer l'examen ?</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary close-modal">Retourner à l'examen</button>
            <button class="btn btn-success" id="confirmFinish">Terminer l'examen</button>
        </div>
    </div>
</div>

<?php if ($proctoringEnabled): ?>
    <!-- Modal de calibration du suivi oculaire -->
    <div class="proctoring-modal" id="calibration-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Calibration du suivi oculaire</h2>
            </div>
            <div class="modal-body">
                <p>Pour une surveillance précise, veuillez suivre le point qui apparaîtra à l'écran.</p>
                <p>Regardez fixement chaque point jusqu'à ce qu'il disparaisse.</p>
                <div id="calibration-points"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="start-calibration">Commencer la calibration</button>
            </div>
        </div>
    </div>

    <!-- Indicateur de chargement des modèles -->
    <div class="proctoring-loading" id="proctoring-loading" style="display: none;">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Chargement des systèmes de surveillance...</p>
        </div>
    </div>

    <!-- Point de calibration pour le suivi oculaire -->
    <div id="calibration-point" class="calibration-point" style="display: none;"></div>

    <!-- Notifications de surveillance -->
    <div id="proctoring-notifications" class="proctoring-notifications"></div>
<?php endif; ?>

<!-- Script pour gérer l'examen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables globales
        const attemptId = <?php echo $attemptId; ?>;
        const examId = <?php echo $examId; ?>;
        const questions = <?php echo json_encode(array_column($questions, 'id')); ?>;
        const proctoringEnabled = <?php echo $proctoringEnabled ? 'true' : 'false'; ?>;
        let currentQuestionIndex = 0;
        let answeredQuestions = {};
        let saveTimeout;
        let isSubmitting = false;
        const video = document.getElementById('webcam');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');
        const warningsContainer = document.getElementById('proctoringWarnings');
        let warningsCount = 0;
        let stream = null;
        let faceCheckInterval = null;
        let lastCaptureTime = 0;
        const captureInterval = 10000;
        // Configuration globale
        const FACE_MODELS_PATH = "../assets/models"; // Chemin vers les modèles Face-API.js
        const FACE_MATCH_THRESHOLD = 0.6; // Seuil de correspondance faciale (0.6 est un bon compromis)
        const FACE_CHECK_INTERVAL = 3000; // Vérification du visage toutes les 3 secondes
        const GAZE_OUT_OF_BOUNDS_THRESHOLD = 3000; // 3 secondes de regard hors zone
        const AUDIO_CHECK_INTERVAL = 1000; // Vérification audio toutes les secondes
        const AUDIO_THRESHOLD = 0.2; // Seuil de volume audio (0-1)
        const CONSECUTIVE_AUDIO_VIOLATIONS_THRESHOLD = 3; // Nombre de violations audio consécutives avant signalement
        const INACTIVITY_THRESHOLD = 30000; // 30 secondes d'inactivité avant signalement
        const FACE_DETECTION_CONFIDENCE = 0.5; // Seuil de confiance pour la détection faciale

        // Variables globales
        let faceDetectionInterval;
        let gazeCheckInterval;
        let audioCheckInterval;
        let screenCheckInterval;
        let referenceDescriptor = null;
        let lastGazeCheck = Date.now();
        let gazeOutOfBoundsTime = 0;
        let consecutiveAudioViolations = 0;
        let visibilityChangeCount = 0;
        let lastActiveTime = Date.now();
        let incidentCount = 0;
        let calibrationComplete = false;
        let proctoringActive = false;
        let audioContext = null;
        let audioAnalyser = null;
        let audioDataArray = null;
        let audioStream = null;
        let webgazerInitialized = false; // 10 secondes entre chaque capture

        // Demander l'accès à la webcam
        navigator.mediaDevices.getUserMedia({
                video: true,
                audio: false
            })
            .then(function(mediaStream) {
                stream = mediaStream;
                video.srcObject = mediaStream;

                // Configurer la taille du canvas
                video.addEventListener('loadedmetadata', function() {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                });

                // Démarrer la vérification du visage
                faceCheckInterval = setInterval(checkFace, 2000);
            })
            .catch(function(err) {
                console.error("Erreur d'accès à la webcam: ", err);
                reportProctoringIncident('webcam_access_denied', 'L\'étudiant a refusé l\'accès à la webcam');
            });

        // Vérifier la présence du visage
        function checkFace() {
            if (!stream) return;

            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = canvas.toDataURL('image/jpeg', 0.8);

            // Envoyer l'image pour analyse faciale (simulation)
            const now = Date.now();
            if (now - lastCaptureTime > captureInterval) {
                lastCaptureTime = now;
                sendImageForAnalysis(imageData);
            }

            // Simulation de détection (à remplacer par une vraie API de détection faciale)
            const randomValue = Math.random();
            if (randomValue > 0.95) { // 5% de chance de détecter un problème (pour la démonstration)
                reportProctoringIncident('face_not_detected', 'Visage non détecté dans le champ de la caméra');
            } else if (randomValue > 0.90) {
                reportProctoringIncident('multiple_faces', 'Plusieurs visages détectés');
            }
        }

        // Initialiser le compteur de questions répondues
        function initAnsweredQuestions() {
            let count = 0;
            <?php foreach ($answers as $questionId => $answer): ?>
                answeredQuestions[<?php echo $questionId; ?>] = true;
                count++;
            <?php endforeach; ?>

            document.getElementById('answeredCount').textContent = count;
            updateFinishButtonState();
        }

        // Mettre à jour l'état du bouton de fin d'examen
        function updateFinishButtonState() {
            const answeredCount = Object.keys(answeredQuestions).length;

            if (document.getElementById('answeredQuestionsCount')) {
                document.getElementById('answeredQuestionsCount').textContent = answeredCount;
            }

            if (document.getElementById('unansweredQuestionsCount')) {
                document.getElementById('unansweredQuestionsCount').textContent = questions.length - answeredCount;
            }

            if (document.getElementById('unansweredWarning')) {
                if (answeredCount < questions.length) {
                    document.getElementById('unansweredWarning').style.display = 'flex';
                } else {
                    document.getElementById('unansweredWarning').style.display = 'none';
                }
            }
        }

        // Initialiser le minuteur
        function initTimer() {
            let remainingTime = parseInt(document.getElementById('examTimer').dataset.remaining);

            function updateTimer() {
                const hours = Math.floor(remainingTime / 3600);
                const minutes = Math.floor((remainingTime % 3600) / 60);
                const seconds = remainingTime % 60;

                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');

                if (remainingTime <= 300) { // 5 minutes remaining
                    document.getElementById('examTimer').classList.add('warning');
                }

                if (remainingTime <= 60) { // 1 minute remaining
                    document.getElementById('examTimer').classList.add('danger');
                }

                if (remainingTime <= 0) {
                    clearInterval(timerInterval);
                    finishExam(true);
                }

                remainingTime--;
            }

            updateTimer();
            const timerInterval = setInterval(updateTimer, 1000);
        }

        // Afficher une question
        function showQuestion(index) {
            // Cacher toutes les questions
            document.querySelectorAll('.question-slide').forEach(slide => {
                slide.style.display = 'none';
            });

            // Afficher la question demandée
            const questionElement = document.getElementById(`question-${questions[index]}`);
            if (questionElement) {
                questionElement.style.display = 'block';

                // Mettre à jour l'index courant
                currentQuestionIndex = index;

                // Mettre à jour les boutons de navigation
                document.getElementById('prevQuestion').disabled = index === 0;
                document.getElementById('nextQuestion').disabled = index === questions.length - 1;

                // Mettre à jour la navigation des questions
                document.querySelectorAll('.question-button').forEach(btn => {
                    btn.classList.remove('current');
                });

                const currentButton = document.querySelector(`.question-button[data-index="${index}"]`);
                if (currentButton) {
                    currentButton.classList.add('current');

                    // Faire défiler la liste des questions si nécessaire
                    currentButton.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }
        }

        // Sauvegarder une réponse
        function saveAnswer(questionId, answer, type) {
            // Afficher l'indicateur de sauvegarde
            const saveStatus = document.getElementById(`saveStatus-${questionId}`);
            if (saveStatus) {
                saveStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
                saveStatus.style.display = 'block';
            }

            // Préparer les données
            const formData = new FormData();
            formData.append('attempt_id', attemptId);
            formData.append('question_id', questionId);

            if (type === 'multiple_choice') {
                formData.append('selected_options', Array.isArray(answer) ? answer.join(',') : answer);
            } else if (type === 'single_choice' || type === 'true_false') {
                formData.append('selected_options', answer);
            } else if (type === 'essay' || type === 'short_answer') {
                formData.append('answer_text', answer);
            }

            // Envoyer la réponse au serveur
            fetch('../ajax/save-answer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (saveStatus) {
                            saveStatus.innerHTML = '<i class="fas fa-check-circle"></i> Réponse enregistrée';
                        }

                        // Marquer la question comme répondue
                        answeredQuestions[questionId] = true;
                        const questionButton = document.querySelector(`.question-button[data-question-id="${questionId}"]`);
                        if (questionButton) {
                            questionButton.classList.add('answered');
                        }

                        // Mettre à jour le compteur
                        document.getElementById('answeredCount').textContent = Object.keys(answeredQuestions).length;
                        updateFinishButtonState();
                    } else {
                        if (saveStatus) {
                            saveStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur d\'enregistrement';
                        }
                        console.error('Erreur lors de l\'enregistrement de la réponse:', data.message);
                    }

                    // Cacher le statut après 3 secondes
                    if (saveStatus) {
                        setTimeout(() => {
                            saveStatus.style.opacity = '0';
                            setTimeout(() => {
                                saveStatus.style.display = 'none';
                                saveStatus.style.opacity = '1';
                            }, 300);
                        }, 3000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (saveStatus) {
                        saveStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur d\'enregistrement';
                    }
                });
        }

        // Terminer l'examen
        function finishExam(timeExpired = false) {
            if (isSubmitting) return;
            isSubmitting = true;

            // Sauvegarder la dernière réponse
            clearTimeout(saveTimeout);

            // Afficher un indicateur de chargement
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = `
            <div class="loading-spinner"></div>
            <div class="loading-message">${timeExpired ? 'Temps écoulé! Finalisation de l\'examen...' : 'Finalisation de l\'examen...'}</div>
        `;
            document.body.appendChild(loadingOverlay);

            // Envoyer la requête pour terminer l'examen
            fetch('../ajax/finish-exam.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `attempt_id=${attemptId}&time_expired=${timeExpired ? 1 : 0}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (proctoringEnabled && typeof stopProctoring === 'function') {
                            stopProctoring();
                        }

                        window.location.href = `exam-result.php?attempt_id=${attemptId}`;
                    } else {
                        document.body.removeChild(loadingOverlay);
                        isSubmitting = false;
                        alert('Une erreur est survenue lors de la finalisation de l\'examen. Veuillez réessayer.');
                        console.error('Erreur lors de la finalisation de l\'examen:', data.message);
                    }
                })
                .catch(error => {
                    document.body.removeChild(loadingOverlay);
                    isSubmitting = false;
                    alert('Une erreur est survenue. Veuillez réessayer.');
                    console.error('Error:', error);
                });
        }

        // Initialiser les événements
        function initEvents() {
            // Navigation entre les questions via les boutons numérotés
            document.querySelectorAll('.question-button').forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    showQuestion(index);
                });
            });

            // Boutons précédent/suivant
            document.getElementById('prevQuestion').addEventListener('click', function() {
                if (currentQuestionIndex > 0) {
                    showQuestion(currentQuestionIndex - 1);
                }
            });

            document.getElementById('nextQuestion').addEventListener('click', function() {
                if (currentQuestionIndex < questions.length - 1) {
                    showQuestion(currentQuestionIndex + 1);
                }
            });

            // Boutons de navigation dans les questions
            document.querySelectorAll('.prev-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    showQuestion(index);
                });
            });

            document.querySelectorAll('.next-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    showQuestion(index);
                });
            });

            // Enregistrement des réponses
            document.querySelectorAll('input[type="checkbox"]').forEach(input => {
                input.addEventListener('change', function() {
                    const questionId = this.dataset.questionId;
                    const selectedOptions = [];
                    document.querySelectorAll(`input[name="question_${questionId}[]"]:checked`).forEach(checkbox => {
                        selectedOptions.push(checkbox.value);
                    });

                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        saveAnswer(questionId, selectedOptions, 'multiple_choice');
                    }, 500);
                });
            });

            document.querySelectorAll('input[type="radio"]').forEach(input => {
                input.addEventListener('change', function() {
                    const questionId = this.dataset.questionId;
                    const selectedOption = this.value;

                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        saveAnswer(questionId, selectedOption, this.name.includes('true_false') ? 'true_false' : 'single_choice');
                    }, 500);
                });
            });

            document.querySelectorAll('.essay-input').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    const questionId = this.dataset.questionId;

                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        saveAnswer(questionId, this.value, 'essay');
                    }, 1000);
                });
            });

            // Bouton pour terminer l'examen
            document.getElementById('submitExam').addEventListener('click', function() {
                document.getElementById('finishExamModal').style.display = 'block';
                updateFinishButtonState();
            });

            document.getElementById('finishBtn').addEventListener('click', function() {
                document.getElementById('finishExamModal').style.display = 'block';
                updateFinishButtonState();
            });

            document.getElementById('confirmFinish').addEventListener('click', function() {
                finishExam();
            });

            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('finishExamModal').style.display = 'none';
                });
            });

            // Fermer le modal en cliquant à l'extérieur
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('finishExamModal');
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Toggle de la surveillance
            if (proctoringEnabled) {
                document.getElementById('toggleProctoring').addEventListener('click', function() {
                    const container = document.getElementById('proctoringContainer');
                    const icon = this.querySelector('i');

                    if (container.classList.contains('collapsed')) {
                        container.classList.remove('collapsed');
                        icon.className = 'fas fa-chevron-down';
                    } else {
                        container.classList.add('collapsed');
                        icon.className = 'fas fa-chevron-up';
                    }
                });
            }
        }

        // Empêcher la navigation arrière pendant l'examen
        function preventBackNavigation() {
            history.pushState(null, null, location.href);
            window.onpopstate = () => {
                history.go(1);
            };
        }

        // Empêcher le clic droit
        function preventRightClick() {
            document.addEventListener("contextmenu", (e) => {
                e.preventDefault();
                if (proctoringEnabled && typeof reportProctoringIncident === 'function') {
                    reportProctoringIncident('screen', 'Tentative d\'ouverture du menu contextuel');
                }
            });
        }

        // Détecter quand l'utilisateur quitte la page
        function detectPageLeave() {
            window.addEventListener("beforeunload", (e) => {
                // Annuler l'événement
                e.preventDefault();
                // Chrome requiert returnValue pour être défini
                e.returnValue = "";
            });

            // Détecter quand l'utilisateur change d'onglet ou minimise la fenêtre
            document.addEventListener("visibilitychange", () => {
                if (document.visibilityState === "hidden") {
                    // L'utilisateur a changé d'onglet ou minimisé la fenêtre
                    if (proctoringEnabled && typeof reportProctoringIncident === 'function') {
                        reportProctoringIncident('screen', 'L\'étudiant a changé d\'onglet ou minimisé la fenêtre');
                    }
                }
            });
        }

        // Empêcher les tentatives de copier-coller
        function preventCopyPaste() {
            document.addEventListener("copy", (e) => {
                e.preventDefault();
                if (proctoringEnabled && typeof reportProctoringIncident === 'function') {
                    reportProctoringIncident('screen', 'Tentative de copie de texte');
                }
            });

            document.addEventListener("paste", (e) => {
                e.preventDefault();
                if (proctoringEnabled && typeof reportProctoringIncident === 'function') {
                    reportProctoringIncident('screen', 'Tentative de collage de texte');
                }
            });

            document.addEventListener("cut", (e) => {
                e.preventDefault();
                if (proctoringEnabled && typeof reportProctoringIncident === 'function') {
                    reportProctoringIncident('screen', 'Tentative de coupage de texte');
                }
            });
        }

        // Initialiser l'application
        initAnsweredQuestions();
        initTimer();
        initEvents();
        preventBackNavigation();
        preventRightClick();
        detectPageLeave();
        preventCopyPaste();
        showQuestion(0);

        // Initialiser la surveillance si activée
        if (proctoringEnabled) {
            
            // Définir les fonctions de surveillance si elles n'existent pas encore
            window.reportProctoringIncident = function(type, description, severity = 'medium') {
                console.log(`Incident de surveillance: ${type} - ${description}`);

                // Incrémenter le compteur d'incidents
                const warningsContainer = document.getElementById('proctoringWarnings');
                if (warningsContainer) {
                    const warningCount = warningsContainer.querySelector('.warning-count');
                    if (warningCount) {
                        warningCount.textContent = parseInt(warningCount.textContent || '0') + 1;
                    }
                }

                // Envoyer l'incident au serveur
                fetch('../ajax/report-incident.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            attempt_id: attemptId,
                            incident_type: type,
                            description: description,
                            severity: severity,
                            timestamp: new Date().toISOString()
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Incident signalé:', data);
                    })
                    .catch(error => {
                        console.error('Erreur lors du signalement de l\'incident:', error);
                    });

                // Afficher une notification
                showProctoringNotification("Alerte de surveillance", description, "warning");
            };

            window.captureAndSaveImage = function(incidentType, description) {
                const video = document.getElementById('webcam');
                if (!video || !video.srcObject) return;

                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Convertir en base64
                const imageData = canvas.toDataURL('image/jpeg', 0.7);

                // Envoyer l'image au serveur
                fetch('../ajax/save-incident-image.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            attempt_id: attemptId,
                            incident_type: incidentType,
                            description: description,
                            image_data: imageData,
                            timestamp: new Date().toISOString()
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Image d\'incident sauvegardée:', data);
                    })
                    .catch(error => {
                        console.error('Erreur lors de la sauvegarde de l\'image d\'incident:', error);
                    });
            };

            window.showProctoringNotification = function(title, message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `proctoring-notification notification-${type}`;

                // Définir l'icône en fonction du type
                let icon;
                switch (type) {
                    case 'success':
                        icon = 'fa-check-circle';
                        break;
                    case 'warning':
                        icon = 'fa-exclamation-triangle';
                        break;
                    case 'error':
                        icon = 'fa-times-circle';
                        break;
                    default:
                        icon = 'fa-info-circle';
                }

                notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
            `;

                // Ajouter au conteneur de notifications
                const notificationsContainer = document.getElementById('proctoring-notifications');
                if (notificationsContainer) {
                    notificationsContainer.appendChild(notification);

                    // Animer l'apparition
                    setTimeout(() => {
                        notification.classList.add('show');
                    }, 10);

                    // Masquer après 5 secondes
                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notificationsContainer.removeChild(notification);
                            }
                        }, 300);
                    }, 5000);
                }
            };

            window.updateProctoringStatus = function(module, status, message = '') {
                const statusElement = document.getElementById(`${module}-status`);
                if (!statusElement) return;

                // Définir l'icône et la classe en fonction du statut
                let icon, statusClass;
                switch (status) {
                    case 'active':
                        icon = 'fa-check-circle';
                        statusClass = 'status-active';
                        message = message || 'Actif';
                        break;
                    case 'warning':
                        icon = 'fa-exclamation-triangle';
                        statusClass = 'status-warning';
                        break;
                    case 'error':
                        icon = 'fa-times-circle';
                        statusClass = 'status-error';
                        message = message || 'Erreur';
                        break;
                    case 'calibrating':
                        icon = 'fa-sync fa-spin';
                        statusClass = 'status-calibrating';
                        message = message || 'Calibration en cours';
                        break;
                    case 'initializing':
                        icon = 'fa-spinner fa-spin';
                        statusClass = 'status-initializing';
                        message = message || 'Initialisation...';
                        break;
                    default:
                        icon = 'fa-question-circle';
                        statusClass = '';
                }

                // Mettre à jour le contenu
                statusElement.className = `status-item ${statusClass}`;
                statusElement.innerHTML = `<i class="fas ${icon}"></i> ${getModuleName(module)}: ${message}`;
            };

            function getModuleName(module) {
                switch (module) {
                    case 'face':
                        return 'Reconnaissance faciale';
                    case 'gaze':
                        return 'Suivi oculaire';
                    case 'audio':
                        return 'Surveillance audio';
                    case 'screen':
                        return 'Surveillance d\'écran';
                    default:
                        return module;
                }
            }

            // Initialiser la surveillance
            setTimeout(() => {
                // Vérifier si les scripts de surveillance sont chargés
                if (typeof faceapi !== 'undefined' && typeof webgazer !== 'undefined') {
                    // Accéder à la webcam
                    navigator.mediaDevices.getUserMedia({
                            video: {
                                width: {
                                    ideal: 640
                                },
                                height: {
                                    ideal: 480
                                },
                                facingMode: 'user'
                            },
                            audio: true
                        })
                        .then(stream => {
                            const video = document.getElementById('webcam');
                            if (video) {
                                video.srcObject = stream;
                                video.onloadedmetadata = () => {
                                    video.play();

                                    // Initialiser les modules de surveillance
                                    if (typeof initProctoring === 'function') {
                                        initProctoring();
                                    } else {
                                        console.error('La fonction initProctoring n\'est pas définie');
                                        showProctoringNotification('Erreur de surveillance', 'Impossible d\'initialiser la surveillance', 'error');
                                    }
                                };
                            }
                        })
                        .catch(error => {
                            console.error('Erreur d\'accès à la webcam:', error);
                            showProctoringNotification('Erreur d\'accès', 'Impossible d\'accéder à la webcam ou au microphone', 'error');

                            // Mettre à jour les statuts
                            updateProctoringStatus('face', 'error', 'Accès refusé');
                            updateProctoringStatus('gaze', 'error', 'Accès refusé');
                            updateProctoringStatus('audio', 'error', 'Accès refusé');
                        });
                } else {
                    console.error('Les scripts de surveillance ne sont pas chargés');
                    showProctoringNotification('Erreur de chargement', 'Les scripts de surveillance n\'ont pas été chargés correctement', 'error');
                }
            }, 1000);


            // Initialisation de la reconnaissance faciale avec Face-API.js
            windows.initFaceRecognition() = function() {
                try {
                    // Vérifier si Face-API.js est disponible
                    if (typeof faceapi === "undefined") {
                        throw new Error("Face-API.js n'est pas chargé");
                    }

                    updateProctoringStatus("face", "initializing", "Chargement des modèles...");

                    // Charger les modèles nécessaires
                    Promise.all([
                        faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODELS_PATH),
                        faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODELS_PATH),
                        faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODELS_PATH),
                        faceapi.nets.faceExpressionNet.loadFromUri(FACE_MODELS_PATH),
                    ]);

                    console.log("Modèles de reconnaissance faciale chargés");
                    updateProctoringStatus("face", "initializing", "Accès à la webcam...");

                    // Accéder à la webcam
                    const video = document.getElementById("webcam");
                    if (!video) {
                        throw new Error("Élément vidéo non trouvé");
                    }

                    const stream =
                        video.srcObject ||
                        (navigator.mediaDevices.getUserMedia({
                            video: {
                                width: {
                                    ideal: 640
                                },
                                height: {
                                    ideal: 480
                                },
                                facingMode: "user",
                            },
                        }));

                    if (!video.srcObject) {
                        video.srcObject = stream;
                        new Promise((resolve) => {
                            video.onloadedmetadata = () => {
                                video.play().then(resolve);
                            };
                        });
                    }

                    // Configurer le canvas pour l'affichage des résultats
                    const canvas = document.getElementById("canvas");
                    if (!canvas) {
                        throw new Error("Élément canvas non trouvé");
                    }

                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;

                    updateProctoringStatus("face", "initializing", "Capture de l'image de référence...");

                    // Capturer une image de référence au début
                    const referenceSuccess = captureReferenceImage();
                    if (!referenceSuccess) {
                        throw new Error("Échec de la capture de l'image de référence");
                    }

                    // Démarrer la détection périodique
                    faceDetectionInterval = setInterval(checkFace, FACE_CHECK_INTERVAL);

                    // Mettre à jour le statut
                    updateProctoringStatus("face", "active");

                    return true;
                } catch (error) {
                    console.error("Erreur lors de l'initialisation de la reconnaissance faciale:", error);
                    updateProctoringStatus("face", "error", error.message);
                    reportProctoringIncident("face", "Erreur d'initialisation de la reconnaissance faciale: " + error.message);
                    throw error;
                }
            }

            // Capture d'une image de référence pour la reconnaissance faciale
            async function captureReferenceImage() {
                const video = document.getElementById("webcam");
                if (!video) return false;

                // Attendre que la vidéo soit chargée
                if (video.readyState !== 4) {
                    await new Promise((resolve) => {
                        video.onloadeddata = () => resolve();
                    });
                }

                // Essayer plusieurs fois de détecter un visage
                let attempts = 0;
                const maxAttempts = 5;

                while (attempts < maxAttempts) {
                    try {
                        // Détecter le visage et extraire le descripteur
                        const detection = await faceapi
                            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({
                                scoreThreshold: FACE_DETECTION_CONFIDENCE
                            }))
                            .withFaceLandmarks()
                            .withFaceDescriptor();

                        if (detection) {
                            referenceDescriptor = detection.descriptor;
                            console.log("Image de référence capturée avec succès");

                            // Capturer l'image pour référence
                            const canvas = document.createElement("canvas");
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            const ctx = canvas.getContext("2d");
                            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                            // Sauvegarder l'image de référence
                            const imageData = canvas.toDataURL("image/jpeg", 0.8);
                            saveReferenceImage(imageData);

                            showProctoringNotification(
                                "Image de référence capturée",
                                "Votre visage a été enregistré pour la surveillance.",
                                "success",
                            );

                            return true;
                        }

                        attempts++;
                        await new Promise((resolve) => setTimeout(resolve, 1000)); // Attendre 1 seconde avant de réessayer
                    } catch (error) {
                        console.error("Erreur lors de la capture de l'image de référence:", error);
                        attempts++;
                        await new Promise((resolve) => setTimeout(resolve, 1000));
                    }
                }

                console.error("Aucun visage détecté après plusieurs tentatives");
                showProctoringNotification(
                    "Erreur de reconnaissance faciale",
                    "Aucun visage détecté. Veuillez vous assurer que votre visage est bien visible.",
                    "warning",
                );

                return false;
            }

            // Sauvegarder l'image de référence
            function saveReferenceImage(imageData) {
                const attemptId = document.querySelector("#questionContainer").dataset.attemptId;

                fetch("../ajax/save-reference-image.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            attempt_id: attemptId,
                            image_data: imageData,
                        }),
                    })
                    .then((response) => response.json())
                    .then((data) => {
                        console.log("Image de référence sauvegardée:", data);
                    })
                    .catch((error) => {
                        console.error("Erreur lors de la sauvegarde de l'image de référence:", error);
                    });
            }
            // Initialisation du suivi oculaire avec WebGazer.js
            async function initEyeTracking() {
                try {
                    // Vérifier si WebGazer.js est disponible
                    if (typeof webgazer === "undefined") {
                        throw new Error("WebGazer.js n'est pas chargé");
                    }

                    updateProctoringStatus("gaze", "initializing", "Initialisation du suivi oculaire...");

                    // Initialiser WebGazer
                    await webgazer.setGazeListener(function(data, elapsedTime) {
                        if (data == null) return;

                        // Traiter les données de suivi oculaire
                        processGazeData(data.x, data.y);
                    }).begin();

                    // Configurer WebGazer
                    webgazer.showPredictionPoints(false); // Masquer les points de prédiction
                    webgazer.showVideo(false); // Masquer la vidéo de WebGazer (nous utilisons notre propre élément vidéo)
                    webgazer.showFaceOverlay(false); // Masquer le calque de visage
                    webgazer.showFaceFeedbackBox(false); // Masquer la boîte de feedback

                    // Afficher le modal de calibration
                    showCalibrationModal();

                    // Mettre à jour le statut
                    updateProctoringStatus("gaze", "calibrating", "Calibration nécessaire");
                    webgazerInitialized = true;

                    return true;
                } catch (error) {
                    console.error("Erreur lors de l'initialisation du suivi oculaire:", error);
                    updateProctoringStatus("gaze", "error", error.message);
                    reportProctoringIncident("gaze", "Erreur d'initialisation du suivi oculaire: " + error.message);
                    throw error;
                }
            }

            // Afficher le modal de calibration
            function showCalibrationModal() {
                const modal = document.getElementById("calibration-modal");
                if (!modal) return;

                modal.style.display = "flex";

                // Configurer le bouton de démarrage de la calibration
                const startButton = document.getElementById("start-calibration");
                if (startButton) {
                    startButton.addEventListener("click", startCalibration);
                }
            }

            // Démarrer la calibration du suivi oculaire
            function startCalibration() {
                const modal = document.getElementById("calibration-modal");
                const calibrationPoint = document.getElementById("calibration-point");
                if (!modal || !calibrationPoint) return;

                // Masquer le modal
                modal.style.display = "none";

                // Afficher le point de calibration
                calibrationPoint.style.display = "block";

                // Positions de calibration (9 points)
                const positions = [{
                        x: "10%",
                        y: "10%"
                    },
                    {
                        x: "50%",
                        y: "10%"
                    },
                    {
                        x: "90%",
                        y: "10%"
                    },
                    {
                        x: "10%",
                        y: "50%"
                    },
                    {
                        x: "50%",
                        y: "50%"
                    },
                    {
                        x: "90%",
                        y: "50%"
                    },
                    {
                        x: "10%",
                        y: "90%"
                    },
                    {
                        x: "50%",
                        y: "90%"
                    },
                    {
                        x: "90%",
                        y: "90%"
                    },
                ];

                let currentPosition = 0;

                // Fonction pour déplacer le point de calibration
                function moveCalibrationPoint() {
                    if (currentPosition >= positions.length) {
                        // Calibration terminée
                        calibrationPoint.style.display = "none";
                        calibrationComplete = true;
                        updateProctoringStatus("gaze", "active");
                        showProctoringNotification(
                            "Calibration terminée",
                            "Le suivi oculaire est maintenant actif.",
                            "success",
                        );

                        // Démarrer la vérification périodique du regard
                        gazeCheckInterval = setInterval(checkGaze, 1000);
                        return;
                    }

                    // Déplacer le point
                    const position = positions[currentPosition];
                    calibrationPoint.style.left = position.x;
                    calibrationPoint.style.top = position.y;

                    // Passer à la position suivante après 2 secondes
                    setTimeout(() => {
                        currentPosition++;
                        moveCalibrationPoint();
                    }, 2000);
                }

                // Démarrer la séquence de calibration
                moveCalibrationPoint();
            }

            // Traiter les données de suivi oculaire
            function processGazeData(x, y) {
                if (!calibrationComplete) return;

                // Vérifier si le regard est dans les limites de l'écran
                const screenWidth = window.innerWidth;
                const screenHeight = window.innerHeight;

                const isInBounds = x >= 0 && x <= screenWidth && y >= 0 && y <= screenHeight;

                // Dessiner le point de regard sur le canvas
                const gazeCanvas = document.getElementById("gaze-canvas");
                if (gazeCanvas) {
                    const ctx = gazeCanvas.getContext("2d");
                    ctx.clearRect(0, 0, gazeCanvas.width, gazeCanvas.height);

                    ctx.beginPath();
                    ctx.arc(x, y, 10, 0, 2 * Math.PI);
                    ctx.fillStyle = isInBounds ? "rgba(0, 255, 0, 0.5)" : "rgba(255, 0, 0, 0.5)";
                    ctx.fill();
                }

                // Mettre à jour le temps de regard hors limites
                const now = Date.now();
                if (!isInBounds) {
                    if (gazeOutOfBoundsTime === 0) {
                        gazeOutOfBoundsTime = now;
                    } else if (now - gazeOutOfBoundsTime > GAZE_OUT_OF_BOUNDS_THRESHOLD) {
                        // Le regard est hors limites depuis trop longtemps
                        updateProctoringStatus("gaze", "warning", "Regard hors écran");
                        reportProctoringIncident("gaze", "Regard hors de l'écran pendant plus de " + (GAZE_OUT_OF_BOUNDS_THRESHOLD / 1000) + " secondes");
                        gazeOutOfBoundsTime = now; // Réinitialiser pour éviter les rapports multiples
                    }
                } else {
                    gazeOutOfBoundsTime = 0;
                }

                lastGazeCheck = now;
            }
            window.showProctoringNotification = function(title, message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `proctoring-notification notification-${type}`;

                // Définir l'icône en fonction du type
                let icon;
                switch (type) {
                    case 'success':
                        icon = 'fa-check-circle';
                        break;
                    case 'warning':
                        icon = 'fa-exclamation-triangle';
                        break;
                    case 'error':
                        icon = 'fa-times-circle';
                        break;
                    default:
                        icon = 'fa-info-circle';
                }

                notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
            `;

                // Ajouter au conteneur de notifications
                const notificationsContainer = document.getElementById('proctoring-notifications');
                if (notificationsContainer) {
                    notificationsContainer.appendChild(notification);

                    // Animer l'apparition
                    setTimeout(() => {
                        notification.classList.add('show');
                    }, 10);

                    // Masquer après 5 secondes
                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notificationsContainer.removeChild(notification);
                            }
                        }, 300);
                    }, 5000);
                }
            };

            window.updateProctoringStatus = function(module, status, message = '') {
                const statusElement = document.getElementById(`${module}-status`);
                if (!statusElement) return;

                // Définir l'icône et la classe en fonction du statut
                let icon, statusClass;
                switch (status) {
                    case 'active':
                        icon = 'fa-check-circle';
                        statusClass = 'status-active';
                        message = message || 'Actif';
                        break;
                    case 'warning':
                        icon = 'fa-exclamation-triangle';
                        statusClass = 'status-warning';
                        break;
                    case 'error':
                        icon = 'fa-times-circle';
                        statusClass = 'status-error';
                        message = message || 'Erreur';
                        break;
                    case 'calibrating':
                        icon = 'fa-sync fa-spin';
                        statusClass = 'status-calibrating';
                        message = message || 'Calibration en cours';
                        break;
                    case 'initializing':
                        icon = 'fa-spinner fa-spin';
                        statusClass = 'status-initializing';
                        message = message || 'Initialisation...';
                        break;
                    default:
                        icon = 'fa-question-circle';
                        statusClass = '';
                }

                // Mettre à jour le contenu
                statusElement.className = `status-item ${statusClass}`;
                statusElement.innerHTML = `<i class="fas ${icon}"></i> ${getModuleName(module)}: ${message}`;
            };
            // Vérification périodique du regard
            function checkGaze() {
                if (!calibrationComplete) return;

                const now = Date.now();

                // Vérifier si le suivi oculaire est actif
                if (now - lastGazeCheck > 5000) {
                    updateProctoringStatus("gaze", "warning", "Suivi perdu");
                    reportProctoringIncident("gaze", "Perte du suivi oculaire");
                }
            }


        }
    });
</script>