<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
file_put_contents('incident_log.txt', date('Y-m-d H:i:s') . " - " . print_r($_POST, true) . "\n", FILE_APPEND);
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Ajouter les fichiers CSS et JS pour la surveillance
if ($proctoringEnabled) {
    $extraCss[] = '../assets/css/proctoring.css';
    $extraJs[] = '../assets/js/face-api.min.js';
    $extraJs[] = '../assets/js/proctoring-system.js';

    // Ajouter les fichiers pour la détection d'objets
    $extraJs[] = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js';
    $extraJs[] = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.2/dist/coco-ssd.min.js';
    $extraJs[] = '../assets/js/object-detection.js';
}

include 'includes/header.php';
?>

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
                    <canvas id="object-canvas" class="overlay"></canvas>
                </div>

                <div class="proctoring-status-container">
                    <div id="proctoring-status">
                        <div class="status-item" id="face-status">
                            <i class="fas fa-spinner fa-spin"></i> Reconnaissance faciale: Initialisation...
                        </div>
                        <div class="status-item" id="audio-status">
                            <i class="fas fa-spinner fa-spin"></i> Surveillance audio: Initialisation...
                        </div>
                        <div class="status-item" id="screen-status">
                            <i class="fas fa-spinner fa-spin"></i> Surveillance d'écran: Initialisation...
                        </div>
                        <div class="status-item" id="object-status">
                            <i class="fas fa-spinner fa-spin"></i> Détection d'objets: Initialisation...
                        </div>
                    </div>

                    <!-- Indicateur de volume audio -->
                    <div class="audio-volume">
                        <div class="volume-label">Niveau audio:</div>
                        <div class="volume-bar">
                            <div id="audio-volume-indicator"></div>
                        </div>
                    </div>
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
                                <button class="btn btn-primary next-btn" id="nextQuestion" data-index="<?php echo $index + 1; ?>">
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
    <!-- Aucun modal de calibration n'est nécessaire car le suivi oculaire a été supprimé -->

    <!-- Indicateur de chargement des modèles -->
    <div class="proctoring-loading" id="proctoring-loading" style="display: none;">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Chargement des systèmes de surveillance...</p>
        </div>
    </div>

    <!-- Aucun point de calibration n'est nécessaire car le suivi oculaire a été supprimé -->

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


        // Fonction pour signaler automatiquement un incident
        async function reportProctoringIncident(type, description, imageData = null) {
            try {
                // Préparer les données
                const data = {
                    attempt_id: attemptId,
                    incident_type: type,
                    description: description,
                    image_data: imageData
                };

                // Options pour la requête fetch
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                };

                // Envoyer la requête AJAX
                const response = await fetch('../ajax/report-incident.php', options);
                const result = await response.json();

                if (result.success) {
                    console.log('Incident enregistré:', result);
                    updateIncidentCounter();
                    // showNotification(type, description);
                } else {
                    console.error('Erreur lors de l\'enregistrement:', result);
                    // showNotification('error', 'Échec de l\'enregistrement de l\'incident');
                }
            } catch (error) {
                console.error('Erreur réseau:', error);
                // showNotification('error', 'Erreur réseau lors de l\'enregistrement');
            }
        }

        // Exemple d'utilisation pour la détection faciale
        function checkFaceDetection(detections) {
            if (detections.length === 0) {
                // Aucun visage détecté
                reportProctoringIncident('face_missing', 'Aucun visage détecté dans le champ de la caméra', captureWebcamImage());
            } else if (detections.length > 1) {
                // Plusieurs visages détectés
                reportProctoringIncident('multiple_faces', `${detections.length} visages détectés dans le champ de la caméra`, captureWebcamImage());
            }
        }

        // Fonction pour capturer l'image de la webcam
        function captureWebcamImage() {
            const video = document.getElementById('webcam');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            return canvas.toDataURL('image/jpeg', 0.7); // Qualité 70% pour réduire la taille
        }

        // Fonction pour mettre à jour le compteur d'incidents dans l'interface
        function updateIncidentCounter() {
            const warningsElement = document.getElementById('proctoringWarnings');
            if (warningsElement) {
                const countElement = warningsElement.querySelector('.warning-count');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = currentCount + 1;
                }
            }
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
                        if (proctoringEnabled) {
                            // Arrêter tous les systèmes de surveillance
                            if (typeof stopProctoring === 'function') {
                                stopProctoring();
                            }
                            if (typeof stopObjectDetection === 'function') {
                                stopObjectDetection();
                            }
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

        // Exemple d'utilisation pour la détection faciale
        function checkFaceDetection(detections) {
            if (detections.length === 0) {
                // Aucun visage détecté
                reportProctoringIncident('face_missing', 'Aucun visage détecté dans le champ de la caméra', captureWebcamImage());
            } else if (detections.length > 1) {
                // Plusieurs visages détectés
                reportProctoringIncident('multiple_faces', `${detections.length} visages détectés dans le champ de la caméra`, captureWebcamImage());
            }
        }

        // Fonction pour capturer l'image de la webcam
        function captureWebcamImage() {
            const video = document.getElementById('webcam');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            return canvas.toDataURL('image/jpeg', 0.7); // Qualité 70% pour réduire la taille
        }

        // Fonction pour mettre à jour le compteur d'incidents dans l'interface
        function updateIncidentCounter() {
            const warningsElement = document.getElementById('proctoringWarnings');
            if (warningsElement) {
                const countElement = warningsElement.querySelector('.warning-count');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent);
                    countElement.textContent = currentCount + 1;
                }
            }
        }

        // Fonction pour afficher une notification d'incident
        function showNotification(type, message) {
            const notificationsElement = document.getElementById('proctoring-notifications');
            if (!notificationsElement) return;

            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${type === 'face_missing' || type === 'multiple_faces' ? 'fa-user-alt-slash' : 
                           type === 'audio_detected' ? 'fa-volume-up' : 'fa-desktop'}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${getNotificationTitle(type)}</div>
            <div class="notification-message">${message}</div>
        </div>
    `;

            notificationsElement.appendChild(notification);

            // Supprimer la notification après 5 secondes
            setTimeout(() => {
                notification.classList.add('fade-out');
                setTimeout(() => {
                    if (notification.parentNode === notificationsElement) {
                        notificationsElement.removeChild(notification);
                    }
                }, 500);
            }, 5000);
        }

        // Fonction pour obtenir le titre de la notification en fonction du type
        function getNotificationTitle(type) {
            switch (type) {
                case 'face_missing':
                case 'multiple_faces':
                    return 'Alerte visage';
                case 'audio_detected':
                    return 'Alerte audio';
                case 'tab_change':
                case 'window_blur':
                    return 'Alerte navigation';
                case 'object_detected':
                case 'phone_detected':
                    return 'Objet détecté';
                default:
                    return 'Alerte surveillance';
            }
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


        // Initialiser la détection d'objets si la surveillance est activée
        if (proctoringEnabled) {
            // Attendre que le système de surveillance principal soit initialisé
            initDetectionSystem();
            // setTimeout(() => {
            //     console.log("Initialisation de la détection d'objets...");
            //     if (typeof initObjectDetection === 'function') {

            //     } else {
            //         console.error("La fonction initObjectDetection n'est pas disponible");
            //     }
            // }, 3000);
        }
    });
</script>



<script src="../assets/js/proctoring-system.js"></script>
<script src="../assets/js/face-api.min.js"></script>
<script src="../assets/js/proctoring.js"></script>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.2/dist/coco-ssd.min.js"></script>
<script src="../assets/js/object-detection.js"></script>