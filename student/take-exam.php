<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle étudiant
require_login('../login.php');
require_role('student', '../index.php');

// Vérifier si l'ID d'inscription est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$enrollment_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Connexion à la base de données
include_once '../includes/db.php';

// Vérifier si l'inscription existe et appartient à l'étudiant
$enrollment_query = "
    SELECT ee.*, e.title, e.description, e.duration, e.start_time, e.end_time, e.status as exam_status,
           e.passing_score, e.proctoring_level, ps.*
    FROM exam_enrollments ee
    JOIN exams e ON ee.exam_id = e.id
    LEFT JOIN proctoring_settings ps ON e.id = ps.exam_id
    WHERE ee.id = ? AND ee.student_id = ?
";

$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("ii", $enrollment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // L'inscription n'existe pas ou n'appartient pas à l'étudiant
    header('Location: index.php');
    exit;
}

$enrollment = $result->fetch_assoc();
$stmt->close();

// Vérifier si l'examen est en cours
$now = new DateTime();
$start_time = new DateTime($enrollment['start_time']);
$end_time = new DateTime($enrollment['end_time']);

if ($now < $start_time) {
    // L'examen n'a pas encore commencé
    header('Location: exam-details.php?id=' . $enrollment['exam_id'] . '&error=not_started');
    exit;
}

if ($now > $end_time) {
    // L'examen est terminé
    header('Location: exam-details.php?id=' . $enrollment['exam_id'] . '&error=expired');
    exit;
}

// Vérifier si une tentative existe déjà
$attempt_query = "
    SELECT id, status, start_time, end_time
    FROM exam_attempts
    WHERE enrollment_id = ?
    ORDER BY id DESC
    LIMIT 1
";

$stmt = $conn->prepare($attempt_query);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$attempt_result = $stmt->get_result();
$stmt->close();

$attempt_id = null;
$remaining_time = $enrollment['duration'] * 60; // Durée en secondes

if ($attempt_result->num_rows > 0) {
    $attempt = $attempt_result->fetch_assoc();
    
    if ($attempt['status'] === 'completed') {
        // L'étudiant a déjà terminé l'examen
        header('Location: exam-results.php?id=' . $enrollment['exam_id']);
        exit;
    }
    
    $attempt_id = $attempt['id'];
    
    // Calculer le temps restant
    $start_time = new DateTime($attempt['start_time']);
    $elapsed_seconds = $now->getTimestamp() - $start_time->getTimestamp();
    $remaining_time = max(0, ($enrollment['duration'] * 60) - $elapsed_seconds);
    
    if ($remaining_time <= 0 && $attempt['status'] === 'in_progress') {
        // Le temps est écoulé, mettre à jour le statut de la tentative
        $update_query = "UPDATE exam_attempts SET status = 'timed_out', end_time = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $attempt_id);
        $stmt->execute();
        $stmt->close();
        
        header('Location: exam-results.php?id=' . $enrollment['exam_id'] . '&timeout=1');
        exit;
    }
} else {
    // Créer une nouvelle tentative
    $insert_query = "
        INSERT INTO exam_attempts (enrollment_id, start_time, status, ip_address, browser_info)
        VALUES (?, NOW(), 'in_progress', ?, ?)
    ";
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $browser_info = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iss", $enrollment_id, $ip_address, $browser_info);
    $stmt->execute();
    $attempt_id = $stmt->insert_id;
    $stmt->close();
}

// Récupérer les questions de l'examen
$questions_query = "
    SELECT q.*, GROUP_CONCAT(ao.id, ':::', ao.option_text, ':::', ao.is_correct SEPARATOR '|||') as options
    FROM questions q
    LEFT JOIN answer_options ao ON q.id = ao.question_id
    WHERE q.exam_id = ?
    GROUP BY q.id
    ORDER BY q.id
";

$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $enrollment['exam_id']);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = $questions_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Récupérer les réponses déjà données
$answers_query = "
    SELECT sa.question_id, sa.answer_text, sa.selected_option_id
    FROM student_answers sa
    WHERE sa.attempt_id = ?
";

$stmt = $conn->prepare($answers_query);
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$answers_result = $stmt->get_result();
$answers = [];

while ($row = $answers_result->fetch_assoc()) {
    $answers[$row['question_id']] = [
        'answer_text' => $row['answer_text'],
        'selected_option_id' => $row['selected_option_id']
    ];
}

$stmt->close();

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($enrollment['title']); ?> - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        /* Styles spécifiques pour la page d'examen */
        body {
            overflow: hidden;
        }
        
        .exam-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 70px);
            padding-top: 70px;
        }
        
        .exam-header {
            background-color: var(--bg-color);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .exam-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .exam-timer {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .exam-timer i {
            margin-right: 0.5rem;
        }
        
        .exam-timer.warning {
            background-color: var(--warning-color);
            color: var(--bg-dark);
        }
        
        .exam-timer.danger {
            background-color: var(--danger-color);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
            100% {
                opacity: 1;
            }
        }
        
        .exam-content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem 1rem;
            margin-top: 60px;
        }
        
        .question-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--bg-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .question-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .question-options {
            display: grid;
            gap: 1rem;
        }
        
        .option-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .option-item:hover {
            background-color: var(--bg-light);
        }
        
        .option-item.selected {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .option-item input {
            margin-right: 1rem;
            margin-top: 0.3rem;
        }
        
        .option-text {
            flex: 1;
        }
        
        .text-answer textarea {
            width: 100%;
            min-height: 150px;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            resize: vertical;
        }
        
        .exam-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .exam-footer {
            background-color: var(--bg-color);
            border-top: 1px solid var(--border-color);
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .question-progress {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .question-number {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .question-number:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .question-number.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .question-number.answered {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .proctoring-status {
            position: fixed;
            top: 140px;
            right: 20px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--success-color);
        }
        
        .status-indicator.warning {
            background-color: var(--warning-color);
        }
        
        .status-indicator.error {
            background-color: var(--danger-color);
        }
        
        .webcam-preview {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 150px;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 2px solid var(--primary-color);
            z-index: 1000;
        }
        
        .webcam-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: var(--bg-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .modal-body {
            margin-bottom: 1.5rem;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <!-- En-tête simplifié -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="#">
                    <h1>ExamSafe</h1>
                </a>
            </div>
            <div class="exam-info">
                <span>Étudiant: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>
    </header>

    <!-- Conteneur d'examen -->
    <div class="exam-container">
        <div class="exam-header">
            <div class="exam-title"><?php echo htmlspecialchars($enrollment['title']); ?></div>
            <div class="exam-timer" id="exam-timer">
                <i class="fas fa-clock"></i>
                <span id="timer-display">Chargement...</span>
            </div>
        </div>
        
        <div class="exam-content">
            <form id="exam-form" method="post" action="submit-exam.php">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                <input type="hidden" name="enrollment_id" value="<?php echo $enrollment_id; ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-container" id="question-<?php echo $question['id']; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>">
                        <div class="question-text">
                            <span class="question-number-text"><?php echo $index + 1; ?>.</span>
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <?php if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false'): ?>
                            <div class="question-options">
                                <?php 
                                    $options = [];
                                    if (!empty($question['options'])) {
                                        $options_data = explode('|||', $question['options']);
                                        foreach ($options_data as $option_data) {
                                            $option_parts = explode(':::', $option_data);
                                            if (count($option_parts) === 3) {
                                                $options[] = [
                                                    'id' => $option_parts[0],
                                                    'text' => $option_parts[1],
                                                    'is_correct' => $option_parts[2]
                                                ];
                                            }
                                        }
                                    }
                                    
                                    $selected_option = isset($answers[$question['id']]) ? $answers[$question['id']]['selected_option_id'] : null;
                                ?>
                                
                                <?php foreach ($options as $option): ?>
                                    <div class="option-item <?php echo $selected_option == $option['id'] ? 'selected' : ''; ?>" onclick="selectOption(this, <?php echo $question['id']; ?>, <?php echo $option['id']; ?>)">
                                        <input type="radio" name="answer[<?php echo $question['id']; ?>]" value="<?php echo $option['id']; ?>" <?php echo $selected_option == $option['id'] ? 'checked' : ''; ?>>
                                        <div class="option-text"><?php echo htmlspecialchars($option['text']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['question_type'] === 'short_answer' || $question['question_type'] === 'essay'): ?>
                            <div class="text-answer">
                                <textarea name="answer[<?php echo $question['id']; ?>]" placeholder="Votre réponse ici..."><?php echo isset($answers[$question['id']]) ? htmlspecialchars($answers[$question['id']]['answer_text']) : ''; ?></textarea>
                            </div>
                        <?php endif; ?>
                        
                        <div class="exam-navigation">
                            <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-outline" onclick="showQuestion(<?php echo $index - 1; ?>)">Question précédente</button>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            
                            <?php if ($index < count($questions) - 1): ?>
                                <button type="button" class="btn btn-primary" onclick="showQuestion(<?php echo $index + 1; ?>)">Question suivante</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" onclick="showSubmitConfirmation()">Terminer l'examen</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>
        </div>
        
        <div class="exam-footer">
            <div class="question-progress" id="question-progress">
                <?php foreach ($questions as $index => $question): ?>
                    <?php 
                        $is_answered = isset($answers[$question['id']]);
                        $class = $index === 0 ? 'active' : '';
                        $class .= $is_answered ? ' answered' : '';
                    ?>
                    <div class="question-number <?php echo $class; ?>" onclick="showQuestion(<?php echo $index; ?>)" data-question-id="<?php echo $question['id']; ?>">
                        <?php echo $index + 1; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="btn btn-primary" onclick="showSubmitConfirmation()">Terminer l'examen</button>
        </div>
    </div>
    
    <!-- Statut de surveillance -->
    <div class="proctoring-status" id="proctoring-status">
        <div class="status-indicator" id="status-indicator"></div>
        <span id="status-text">Initialisation de la surveillance...</span>
    </div>
    
    <!-- Aperçu webcam -->
    <div class="webcam-preview" id="webcam-preview">
        <video id="webcam-video" autoplay muted></video>
    </div>
    
    <!-- Modal de confirmation de soumission -->
    <div class="modal" id="submit-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Terminer l'examen</h3>
                <button type="button" class="modal-close" onclick="hideModal('submit-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir terminer l'examen ? Cette action est irréversible.</p>
                <p id="unanswered-warning" class="text-warning" style="display: none;">Attention : Vous n'avez pas répondu à toutes les questions.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('submit-modal')">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="submitExam()">Terminer l'examen</button>
            </div>
        </div>
    </div>
    
    <!-- Modal d'avertissement de surveillance -->
    <div class="modal" id="warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Avertissement</h3>
                <button type="button" class="modal-close" onclick="hideModal('warning-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="warning-message">Un problème a été détecté avec la surveillance.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="hideModal('warning-modal')">J'ai compris</button>
            </div>
        </div>
    </div>

    <!-- Scripts JS -->
    <script>
        // Variables globales
        let currentQuestion = 0;
        const questions = <?php echo json_encode($questions); ?>;
        const answers = <?php echo json_encode($answers); ?>;
        let remainingTime = <?php echo $remaining_time; ?>;
        let timerInterval;
        proctoringStatus = 'initializing';
        webcamStream = null;
        let faceDetectionInterval;
        let eyeTrackingInterval;
        let audioMonitoringInterval;
        let screenMonitoringInterval;
        // Removed duplicate declaration of warningCount
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Démarrer le minuteur
            startTimer();
            
            // Initialiser la surveillance
            initProctoring();
            
            // Empêcher la fermeture de la page
            window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = 'Êtes-vous sûr de vouloir quitter l\'examen ? Vos réponses pourraient être perdues.';
                return e.returnValue;
            });
            
            // Sauvegarder automatiquement les réponses
            setInterval(saveAnswers, 30000); // Toutes les 30 secondes
            
            // Détecter les changements de focus de la fenêtre
            document.addEventListener('visibilitychange', handleVisibilityChange);
            
            // Détecter les tentatives de copier-coller
            document.addEventListener('copy', handleCopyPaste);
            document.addEventListener('paste', handleCopyPaste);
            document.addEventListener('cut', handleCopyPaste);
            
            // Détecter le redimensionnement de la fenêtre
            window.addEventListener('resize', handleResize);
        });
        
        // Fonctions de navigation entre les questions
        function showQuestion(index) {
            if (index < 0 || index >= questions.length) {
                return;
            }
            
                // Masquer la question actuelle
                document.getElementById('question-' + questions[currentQuestion].id).style.display = 'none';
                
            }


file="assets/js/proctoring.js"
/**
 * Module de surveillance automatisée pour ExamSafe
 * Ce script gère la reconnaissance faciale, le suivi du regard, la surveillance audio et le verrouillage du navigateur
 */

// Configuration
const proctoringConfig = {
    faceDetection: {
        enabled: true,
        checkInterval: 2000, // ms
        confidenceThreshold: 0.8,
        warningThreshold: 3
    },
    eyeTracking: {
        enabled: true,
        checkInterval: 1000, // ms
        lookAwayThreshold: 2000, // ms
        warningThreshold: 3
    },
    audioMonitoring: {
        enabled: true,
        checkInterval: 1000, // ms
        volumeThreshold: 0.2,
        warningThreshold: 3
    },
    screenMonitoring: {
        enabled: true,
        checkInterval: 1000, // ms
        warningThreshold: 2
    }
};

// Variables globales
let webcamStream = null;
let audioStream = null;
let faceDetectionModel = null;
let eyeTrackingModel = null;
let proctoringStatus = 'initializing';
let lastFaceDetection = null;
let lookAwayStartTime = null;
let warningCount = {
    face: 0,
    eyes: 0,
    audio: 0,
    screen: 0
};
let incidentLog = [];

// Initialisation de la surveillance
async function initProctoring() {
    try {
        updateStatus('initializing', 'Initialisation de la surveillance...');
        
        // Charger les modèles d'IA
        await loadModels();
        
        // Initialiser la webcam
        await initWebcam();
        
        // Initialiser la surveillance audio
        if (proctoringConfig.audioMonitoring.enabled) {
            await initAudioMonitoring();
        }
        
        // Démarrer les différentes surveillances
        startFaceDetection();
        startEyeTracking();
        startScreenMonitoring();
        
        updateStatus('active', 'Surveillance active');
    } catch (error) {
        console.error('Erreur d\'initialisation de la surveillance:', error);
        updateStatus('error', 'Erreur d\'initialisation de la surveillance');
        logIncident('initialization_error', 'high', error.message);
    }
}

// Chargement des modèles d'IA
async function loadModels() {
    return new Promise((resolve) => {
        // Simulation du chargement des modèles
        setTimeout(() => {
            faceDetectionModel = {
                detect: simulateFaceDetection
            };
            
            eyeTrackingModel = {
                track: simulateEyeTracking
            };
            
            resolve();
        }, 2000);
    });
}

// Initialisation de la webcam
async function initWebcam() {
    try {
        webcamStream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user'
            }
        });
        
        const videoElement = document.getElementById('webcam-video');
        videoElement.srcObject = webcamStream;
        
        return new Promise((resolve) => {
            videoElement.onloadedmetadata = () => {
                resolve();
            };
        });
    } catch (error) {
        updateStatus('error', 'Impossible d\'accéder à la webcam');
        logIncident('webcam_access_denied', 'high', error.message);
        throw error;
    }
}

// Initialisation de la surveillance audio
async function initAudioMonitoring() {
    try {
        audioStream = await navigator.mediaDevices.getUserMedia({
            audio: true
        });
        
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const analyser = audioContext.createAnalyser();
        const microphone = audioContext.createMediaStreamSource(audioStream);
        microphone.connect(analyser);
        
        analyser.fftSize = 256;
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        
        // Démarrer la surveillance audio
        audioMonitoringInterval = setInterval(() => {
            analyser.getByteFrequencyData(dataArray);
            
            // Calculer le volume moyen
            let sum = 0;
            for (let i = 0; i< bufferLength; i++) {
                sum += dataArray[i];
            }
            const average = sum / bufferLength / 255; // Normaliser entre 0 et 1
            
            // Détecter les sons suspects
            if (average > proctoringConfig.audioMonitoring.volumeThreshold) {
                handleAudioDetection(average);
            }
        }, proctoringConfig.audioMonitoring.checkInterval);
        
    } catch (error) {
        console.error('Erreur d\'initialisation de la surveillance audio:', error);
        logIncident('audio_access_denied', 'medium', error.message);
    }
}

// Démarrer la détection faciale
function startFaceDetection() {
    if (!proctoringConfig.faceDetection.enabled) return;
    
    faceDetectionInterval = setInterval(async () => {
        if (!webcamStream || !faceDetectionModel) return;
        
        try {
            const videoElement = document.getElementById('webcam-video');
            const result = await faceDetectionModel.detect(videoElement);
            
            if (result.length === 0) {
                // Aucun visage détecté
                handleNoFaceDetected();
            } else if (result.length > 1) {
                // Plusieurs visages détectés
                handleMultipleFacesDetected(result.length);
            } else {
                // Un visage détecté
                lastFaceDetection = Date.now();
                warningCount.face = Math.max(0, warningCount.face - 1);
                
                // Vérifier la confiance de la détection
                if (result[0].confidence < proctoringConfig.faceDetection.confidenceThreshold) {
                    handleLowConfidenceFaceDetection(result[0].confidence);
                }
            }
        } catch (error) {
            console.error('Erreur de détection faciale:', error);
        }
    }, proctoringConfig.faceDetection.checkInterval);
}

// Démarrer le suivi du regard
function startEyeTracking() {
    if (!proctoringConfig.eyeTracking.enabled) return;
    
    eyeTrackingInterval = setInterval(async () => {
        if (!webcamStream || !eyeTrackingModel) return;
        
        try {
            const videoElement = document.getElementById('webcam-video');
            const result = await eyeTrackingModel.track(videoElement);
            
            if (result.lookingAway) {
                // L'étudiant regarde ailleurs
                handleLookingAway(result);
            } else {
                // L'étudiant regarde l'écran
                lookAwayStartTime = null;
                warningCount.eyes = Math.max(0, warningCount.eyes - 1);
            }
        } catch (error) {
            console.error('Erreur de suivi du regard:', error);
        }
    }, proctoringConfig.eyeTracking.checkInterval);
}

// Démarrer la surveillance de l'écran
function startScreenMonitoring() {
    if (!proctoringConfig.screenMonitoring.enabled) return;
    
    // Surveiller les changements d'onglet
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            handleTabSwitch();
        }
    });
    
    // Surveiller les tentatives de copier-coller
    document.addEventListener('copy', handleCopyPaste);
    document.addEventListener('paste', handleCopyPaste);
    document.addEventListener('cut', handleCopyPaste);
    
    // Surveiller le redimensionnement de la fenêtre
    let originalWidth = window.innerWidth;
    let originalHeight = window.innerHeight;
    
    window.addEventListener('resize', () => {
        const widthDiff = Math.abs(window.innerWidth - originalWidth);
        const heightDiff = Math.abs(window.innerHeight - originalHeight);
        
        if (widthDiff > 100 || heightDiff > 100) {
            handleWindowResize(widthDiff, heightDiff);
            originalWidth = window.innerWidth;
            originalHeight = window.innerHeight;
        }
    });
}

// Gestionnaires d'incidents
function handleNoFaceDetected() {
    const now = Date.now();
    
    if (lastFaceDetection && (now - lastFaceDetection) > 3000) {
        warningCount.face++;
        
        if (warningCount.face >= proctoringConfig.faceDetection.warningThreshold) {
            updateStatus('warning', 'Visage non détecté');
            logIncident('face_not_detected', 'high', 'Visage non détecté pendant plus de 3 secondes');
            showWarning('Votre visage n\'est pas visible. Veuillez vous assurer que vous êtes bien cadré dans la webcam.');
            warningCount.face = 0;
        }
    }
}

function handleMultipleFacesDetected(count) {
    warningCount.face++;
    
    if (warningCount.face >= proctoringConfig.faceDetection.warningThreshold) {
        updateStatus('warning', 'Plusieurs visages détectés');
        logIncident('multiple_faces', 'high', `${count} visages détectés`);
        showWarning('Plusieurs personnes ont été détectées dans le champ de la caméra. Veuillez vous assurer d\'être seul pendant l\'examen.');
        warningCount.face = 0;
    }
}

function handleLowConfidenceFaceDetection(confidence) {
    warningCount.face++;
    
    if (warningCount.face >= proctoringConfig.faceDetection.warningThreshold) {
        updateStatus('warning', 'Visage partiellement visible');
        logIncident('low_confidence_face', 'medium', `Confiance de détection: ${confidence.toFixed(2)}`);
        showWarning('Votre visage n\'est que partiellement visible. Veuillez ajuster votre position face à la caméra.');
        warningCount.face = 0;
    }
}

function handleLookingAway(result) {
    const now = Date.now();
    
    if (!lookAwayStartTime) {
        lookAwayStartTime = now;
    } else if ((now - lookAwayStartTime) > proctoringConfig.eyeTracking.lookAwayThreshold) {
        warningCount.eyes++;
        
        if (warningCount.eyes >= proctoringConfig.eyeTracking.warningThreshold) {
            updateStatus('warning', 'Regard détourné');
            logIncident('looking_away', 'medium', `Direction du regard: ${result.direction}, durée: ${(now - lookAwayStartTime) / 1000}s`);
            showWarning('Vous semblez regarder ailleurs que votre écran. Veuillez vous concentrer sur votre examen.');
            warningCount.eyes = 0;
        }
    }
}

function handleAudioDetection(volume) {
    warningCount.audio++;
    
    if (warningCount.audio >= proctoringConfig.audioMonitoring.warningThreshold) {
        updateStatus('warning', 'Son détecté');
        logIncident('audio_detected', 'medium', `Volume: ${volume.toFixed(2)}`);
        showWarning('Des sons ont été détectés dans votre environnement. Veuillez vous assurer d\'être dans un endroit calme.');
        warningCount.audio = 0;
    }
}

function handleTabSwitch() {
    warningCount.screen++;
    
    updateStatus('warning', 'Changement d\'onglet');
    logIncident('tab_switch', 'high', 'L\'étudiant a changé d\'onglet ou de fenêtre');
    showWarning('Vous avez quitté l\'onglet de l\'examen. Cette action est enregistrée et peut être considérée comme une tentative de triche.');
}

function handleCopyPaste(event) {
    event.preventDefault();
    
    warningCount.screen++;
    
    updateStatus('warning', 'Copier-coller détecté');
    logIncident('copy_paste', 'medium', `Action: ${event.type}`);
    showWarning('Les actions de copier-coller sont désactivées pendant l\'examen.');
}

function handleWindowResize(widthDiff, heightDiff) {
    warningCount.screen++;
    
    if (warningCount.screen >= proctoringConfig.screenMonitoring.warningThreshold) {
        updateStatus('warning', 'Redimensionnement de fenêtre');
        logIncident('window_resize', 'medium', `Différence de taille: ${widthDiff}x${heightDiff}px`);
        showWarning('Vous avez redimensionné la fenêtre de l\'examen. Cette action est enregistrée.');
        warningCount.screen = 0;
    }
}

// Fonctions utilitaires
function updateStatus(status, message) {
    proctoringStatus = status;
    
    const statusIndicator = document.getElementById('status-indicator');
    const statusText = document.getElementById('status-text');
    
    if (statusIndicator && statusText) {
        statusText.textContent = message;
        
        statusIndicator.className = 'status-indicator';
        if (status === 'warning') {
            statusIndicator.classList.add('warning');
        } else if (status === 'error') {
            statusIndicator.classList.add('error');
        }
    }
}

function showWarning(message) {
    const warningMessage = document.getElementById('warning-message');
    if (warningMessage) {
        warningMessage.textContent = message;
    }
    
    showModal('warning-modal');
    
    // Envoyer l'avertissement au serveur
    sendWarningToServer(message);
}

function logIncident(type, severity, details) {
    const incident = {
        type,
        severity,
        details,
        timestamp: new Date().toISOString()
    };
    
    incidentLog.push(incident);
    
    // Envoyer l'incident au serveur
    sendIncidentToServer(incident);
}

function sendWarningToServer(message) {
    const attemptId = document.querySelector('input[name="attempt_id"]').value;
    
    fetch('../api/proctoring.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'warning',
            attempt_id: attemptId,
            message: message
        })
    }).catch(error => {
        console.error('Erreur d\'envoi d\'avertissement:', error);
    });
}

function sendIncidentToServer(incident) {
    const attemptId = document.querySelector('input[name="attempt_id"]').value;
    
    fetch('../api/proctoring.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'incident',
            attempt_id: attemptId,
            incident: incident
        })
    }).catch(error => {
        console.error('Erreur d\'envoi d\'incident:', error);
    });
}

// Fonctions de simulation pour les démonstrations
function simulateFaceDetection() {
    return new Promise(resolve => {
        // Simuler différents scénarios de détection faciale
        const scenarios = [
            // Visage détecté avec haute confiance (90% du temps)
            { result: [{ confidence: 0.95 }], probability: 0.9 },
            // Visage détecté avec faible confiance (5% du temps)
            { result: [{ confidence: 0.6 }], probability: 0.05 },
            // Aucun visage détecté (3% du temps)
            { result: [], probability: 0.03 },
            // Plusieurs visages détectés (2% du temps)
            { result: [{ confidence: 0.9 }, { confidence: 0.85 }], probability: 0.02 }
        ];
        
        const random = Math.random();
        let cumulativeProbability = 0;
        
        for (const scenario of scenarios) {
            cumulativeProbability += scenario.probability;
            if (random <= cumulativeProbability) {
                resolve(scenario.result);
                return;
            }
        }
        
        // Par défaut, retourner un visage détecté
        resolve([{ confidence: 0.95 }]);
    });
}

function simulateEyeTracking() {
    return new Promise(resolve => {
        // Simuler différents scénarios de suivi du regard
        const scenarios = [
            // Regarde l'écran (95% du temps)
            { result: { lookingAway: false }, probability: 0.95 },
            // Regarde ailleurs (5% du temps)
            { result: { lookingAway: true, direction: 'right' }, probability: 0.05 }
        ];
        
        const random = Math.random();
        let cumulativeProbability = 0;
        
        for (const scenario of scenarios) {
            cumulativeProbability += scenario.probability;
            if (random <= cumulativeProbability) {
                resolve(scenario.result);
                return;
            }
        }
        
        // Par défaut, retourner que l'étudiant regarde l'écran
        resolve({ lookingAway: false });
    });
}

// Nettoyage des ressources
function cleanupProctoring() {
    // Arrêter les intervalles
    if (faceDetectionInterval) clearInterval(faceDetectionInterval);
    if (eyeTrackingInterval) clearInterval(eyeTrackingInterval);
    if (audioMonitoringInterval) clearInterval(audioMonitoringInterval);
    
    // Arrêter les flux
    if (webcamStream) {
        webcamStream.getTracks().forEach(track => track.stop());
    }
    
    if (audioStream) {
        audioStream.getTracks().forEach(track => track.stop());
    }
}

// Fonctions d'interface utilisateur
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}
