<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


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
$questionsQuery = $conn->prepare("
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
$proctoringEnabled = $exam['proctoring_enabled'] == 1;

$pageTitle = "Passer l'examen: " . $exam['title'];
$hideNavigation = true; // Cacher la navigation pendant l'examen
$extraCss = ['../assets/css/exam.css'];
$extraJs = ['../assets/js/exam.js'];
if ($proctoringEnabled) {
    $extraJs[] = '../assets/js/proctoring.js';
}

include 'includes/header.php';
?>

<div class="exam-container">
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
                <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
            </div>
        </div>
    </div>
    
    <?php if ($proctoringEnabled): ?>
    <div class="proctoring-bar">
        <div class="proctoring-status">
            <i class="fas fa-video"></i> Surveillance active
        </div>
        <div class="proctoring-warnings" id="proctoringWarnings">
            <span class="warning-count">0</span> incidents détectés
        </div>
    </div>
    
    <div class="webcam-container" id="webcamContainer">
        <video id="webcam" autoplay playsinline></video>
        <canvas id="canvas" style="display: none;"></canvas>
    </div>
    <?php endif; ?>
    
    <div class="exam-body">
        <div class="questions-navigation">
            <div class="navigation-header">
                <h3>Questions</h3>
                <div class="progress-info">
                    <span id="answeredCount">0</span> / <?php echo count($questions); ?> répondues
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
                <div class="question-slide" id="question-<?php echo $question['id']; ?>" data-index="<?php echo $index; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>">
                    <div class="question-header">
                        <div class="question-number">Question <?php echo $index + 1; ?> sur <?php echo count($questions); ?></div>
                        <div class="question-points"><?php echo $question['points']; ?> points</div>
                    </div>
                    
                    <div class="question-content">
                        <div class="question-text"><?php echo $question['question_text']; ?></div>
                        
                        <div class="question-answer">
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <?php 
                                    $optionsQuery = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
                                    $optionsQuery->bind_param("i", $question['id']);
                                    $optionsQuery->execute();
                                    $optionsResult = $optionsQuery->get_result();
                                    $selectedOptions = isset($answers[$question['id']]) ? explode(',', $answers[$question['id']]['selected_options']) : [];
                                ?>
                                <div class="options-list multiple-choice">
                                    <?php while ($option = $optionsResult->fetch_assoc()): ?>
                                        <div class="option-item">
                                            <label class="option-label">
                                                <input type="checkbox" name="question_<?php echo $question['id']; ?>[]" 
                                                       value="<?php echo $option['id']; ?>"
                                                       class="option-input"
                                                       data-question-id="<?php echo $question['id']; ?>"
                                                       <?php echo in_array($option['id'], $selectedOptions) ? 'checked' : ''; ?>>
                                                <span class="option-checkbox"></span>
                                                <span class="option-text"><?php echo $option['option_text']; ?></span>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            
                            <?php elseif ($question['question_type'] === 'single_choice'): ?>
                                <?php 
                                    $optionsQuery = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
                                    $optionsQuery->bind_param("i", $question['id']);
                                    $optionsQuery->execute();
                                    $optionsResult = $optionsQuery->get_result();
                                    $selectedOption = isset($answers[$question['id']]) ? $answers[$question['id']]['selected_options'] : '';
                                ?>
                                <div class="options-list single-choice">
                                    <?php while ($option = $optionsResult->fetch_assoc()): ?>
                                        <div class="option-item">
                                            <label class="option-label">
                                                <input type="radio" name="question_<?php echo $question['id']; ?>" 
                                                       value="<?php echo $option['id']; ?>"
                                                       class="option-input"
                                                       data-question-id="<?php echo $question['id']; ?>"
                                                       <?php echo $selectedOption == $option['id'] ? 'checked' : ''; ?>>
                                                <span class="option-radio"></span>
                                                <span class="option-text"><?php echo $option['option_text']; ?></span>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                
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
                                              rows="8"><?php echo $answerText; ?></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="question-footer">
                        <div class="save-status" id="saveStatus-<?php echo $question['id']; ?>">
                            <i class="fas fa-check-circle"></i> Réponse enregistrée
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
                        <div class="stat-value" id="answeredQuestionsCount">0</div>
                        <div class="stat-label">Questions répondues</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="unansweredQuestionsCount"><?php echo count($questions); ?></div>
                        <div class="stat-label">Questions sans réponse</div>
                    </div>
                </div>
                
                <div class="warning-message" id="unansweredWarning">
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

<!-- Script pour gérer l'examen -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables globales
    const attemptId = <?php echo $attemptId; ?>;
    const examId = <?php echo $examId; ?>;
    const questions = <?php echo json_encode($questions); ?>;
    const answers = <?php echo json_encode($answers); ?>;
    let currentQuestionIndex = 0;
    let answeredQuestions = {};
    
    // Initialiser le compteur de questions répondues
    function initAnsweredQuestions() {
        let count = 0;
        questions.forEach(question => {
            if (answers[question.id]) {
                answeredQuestions[question.id] = true;
                count++;
            }
        });
        document.getElementById('answeredCount').textContent = count;
        updateFinishButtonState();
    }
    
    // Mettre à jour l'état du bouton de fin d'examen
    function updateFinishButtonState() {
        const answeredCount = Object.keys(answeredQuestions).length;
        document.getElementById('answeredQuestionsCount').textContent = answeredCount;
        document.getElementById('unansweredQuestionsCount').textContent = questions.length - answeredCount;
        
        if (answeredCount < questions.length) {
            document.getElementById('unansweredWarning').style.display = 'flex';
        } else {
            document.getElementById('unansweredWarning').style.display = 'none';
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
                finishExam();
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
        const questionElement = document.getElementById(`question-${questions[index].id}`);
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
        document.querySelector(`.question-button[data-index="${index}"]`).classList.add('current');
    }
    
    // Sauvegarder une réponse
    function saveAnswer(questionId, answer, type) {
        // Afficher l'indicateur de sauvegarde
        const saveStatus = document.getElementById(`saveStatus-${questionId}`);
        saveStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        saveStatus.style.display = 'block';
        
        // Préparer les données
        let formData = new FormData();
        formData.append('attempt_id', attemptId);
        formData.append('question_id', questionId);
        formData.append('answer_type', type);
        
        if (type === 'multiple_choice') {
            formData.append('selected_options', answer.join(','));
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
                saveStatus.innerHTML = '<i class="fas fa-check-circle"></i> Réponse enregistrée';
                
                // Marquer la question comme répondue
                answeredQuestions[questionId] = true;
                document.querySelector(`.question-button[data-question-id="${questionId}"]`).classList.add('answered');
                
                // Mettre à jour le compteur
                document.getElementById('answeredCount').textContent = Object.keys(answeredQuestions).length;
                updateFinishButtonState();
            } else {
                saveStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur d\'enregistrement';
            }
            
            // Cacher le statut après 3 secondes
            setTimeout(() => {
                saveStatus.style.opacity = '0';
                setTimeout(() => {
                    saveStatus.style.display = 'none';
                    saveStatus.style.opacity = '1';
                }, 300);
            }, 3000);
        })
        .catch(error => {
            console.error('Error:', error);
            saveStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur d\'enregistrement';
        });
    }
    
    // Terminer l'examen
    function finishExam() {
        // Envoyer la requête pour terminer l'examen
        fetch('../ajax/finish-exam.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `attempt_id=${attemptId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = `exam-result.php?attempt_id=${attemptId}`;
            } else {
                alert('Une erreur est survenue lors de la finalisation de l\'examen. Veuillez réessayer.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue. Veuillez réessayer.');
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
                saveAnswer(questionId, selectedOptions, 'multiple_choice');
            });
        });
        
        document.querySelectorAll('input[type="radio"]').forEach(input => {
            input.addEventListener('change', function() {
                const questionId = this.dataset.questionId;
                const selectedOption = this.value;
                saveAnswer(questionId, selectedOption, this.name.includes('true_false') ? 'true_false' : 'single_choice');
            });
        });
        
        document.querySelectorAll('.essay-input').forEach(textarea => {
            let saveTimeout;
            textarea.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                const questionId = this.dataset.questionId;
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
    }
    
    // Initialiser l'application
    initAnsweredQuestions();
    initTimer();
    initEvents();
    showQuestion(0);
    
    <?php if ($proctoringEnabled): ?>
    // Initialiser la surveillance
    initProctoring();
    <?php endif; ?>
});
</script>

<?php if ($proctoringEnabled): ?>
<script>
function initProctoring() {
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d');
    const warningsContainer = document.getElementById('proctoringWarnings');
    let warningsCount = 0;
    let stream = null;
    let faceCheckInterval = null;
    let lastCaptureTime = 0;
    const captureInterval = 10000; // 10 secondes entre chaque capture
    
    // Demander l'accès à la webcam
    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
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
    
    // Envoyer l'image pour analyse
    function sendImageForAnalysis(imageData) {
        // Cette fonction simule l'envoi de l'image à un service d'analyse
        // Dans une implémentation réelle, vous enverriez l'image à une API
        console.log('Image capturée pour analyse');
    }
    
    // Signaler un incident de surveillance
    function reportProctoringIncident(type, description) {
        warningsCount++;
        warningsContainer.querySelector('.warning-count').textContent = warningsCount;
        
        // Afficher une notification
        const notification = document.createElement('div');
        notification.className = 'proctoring-notification';
        notification.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">Alerte de surveillance</div>
                <div class="notification-message">${description}</div>
            </div>
        `;
        document.body.appendChild(notification);
        
        // Animer la notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Cacher la notification après 5 secondes
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
        
        // Enregistrer l'incident dans la base de données
        const formData = new FormData();
        formData.append('attempt_id', <?php echo $attemptId; ?>);
        formData.append('incident_type', type);
        formData.append('description', description);
        
        fetch('../ajax/report-incident.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Incident reported:', data);
        })
        .catch(error => {
            console.error('Error reporting incident:', error);
        });
    }
    
    // Nettoyer les ressources lors de la fermeture de la page
    window.addEventListener('beforeunload', function() {
        if (faceCheckInterval) {
            clearInterval(faceCheckInterval);
        }
        
        if (stream) {
            stream.getTracks().forEach(track => {
                track.stop();
            });
        }
    });
}
</script>
<?php endif; ?>
