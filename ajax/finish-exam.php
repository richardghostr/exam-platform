<?php
/**
 * Script AJAX pour finaliser un examen
 * Ce script est appelé lorsque l'étudiant termine l'examen ou que le temps est écoulé
 */

// Inclure les fichiers nécessaires
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé'
    ]);
    exit();
}

// Vérifier si la requête est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

// Récupérer et valider les données
$attemptId = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
$timeExpired = isset($_POST['time_expired']) ? (bool)$_POST['time_expired'] : false;
$userId = $_SESSION['user_id'];

// Vérifier que l'ID de tentative est valide
if ($attemptId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de tentative invalide'
    ]);
    exit();
}

// Vérifier que l'utilisateur a le droit de terminer cette tentative
$checkAttemptQuery = $conn->prepare("
    SELECT a.*, e.id as exam_id, e.passing_score, e.duration, e.show_results
    FROM exam_attempts a
    JOIN exams e ON a.exam_id = e.id
    WHERE a.id = ? AND a.user_id = ? AND a.status = 'in_progress'
");
$checkAttemptQuery->bind_param("ii", $attemptId, $userId);
$checkAttemptQuery->execute();
$attemptResult = $checkAttemptQuery->get_result();

if ($attemptResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tentative non trouvée ou déjà terminée'
    ]);
    exit();
}

$attempt = $attemptResult->fetch_assoc();
$examId = $attempt['exam_id'];
$passingScore = $attempt['passing_score'];
$duration = $attempt['duration'];
$showResults = $attempt['show_results'];
$startTime = strtotime($attempt['start_time']);

// Calculer le temps passé en secondes
$endTime = time();
$timeSpent = $endTime - $startTime;

// Si le temps est expiré, utiliser la durée maximale
if ($timeExpired) {
    $timeSpent = $duration * 60;
}

// Mettre à jour la tentative pour indiquer qu'elle est terminée
$updateAttemptQuery = $conn->prepare("
    UPDATE exam_attempts 
    SET status = 'completed', end_time = NOW() 
    WHERE id = ?
");
$updateAttemptQuery->bind_param("i", $attemptId);
$updateAttemptQuery->execute();

// Mettre à jour la session d'examen si elle existe
$updateSessionQuery = $conn->prepare("
    UPDATE exam_sessions 
    SET status = 'completed', end_time = NOW() 
    WHERE exam_id = ? AND user_id = ? AND status = 'in_progress'
");
$updateSessionQuery->bind_param("ii", $examId, $userId);
$updateSessionQuery->execute();

// Récupérer toutes les questions de l'examen
$questionsQuery = $conn->prepare("
    SELECT q.* 
    FROM questions q 
    WHERE q.exam_id = ?
");
$questionsQuery->bind_param("i", $examId);
$questionsQuery->execute();
$questionsResult = $questionsQuery->get_result();

$totalQuestions = $questionsResult->num_rows;
$totalPoints = 0;
$questions = [];

while ($question = $questionsResult->fetch_assoc()) {
    $questions[] = $question;
    $totalPoints += $question['points'];
}

// Récupérer toutes les réponses de l'étudiant pour cette tentative
$answersQuery = $conn->prepare("
    SELECT ua.* 
    FROM user_answers ua 
    WHERE ua.attempt_id = ?
");
$answersQuery->bind_param("i", $attemptId);
$answersQuery->execute();
$answersResult = $answersQuery->get_result();

$pointsEarned = 0;
$answeredQuestions = 0;
$answers = [];

while ($answer = $answersResult->fetch_assoc()) {
    $answers[$answer['question_id']] = $answer;
    $answeredQuestions++;
    
    // Ajouter les points si la réponse est correcte ou si des points ont été attribués
    if ($answer['is_correct'] == 1 || $answer['points_awarded'] > 0) {
        $pointsEarned += $answer['points_awarded'] > 0 ? $answer['points_awarded'] : 0;
    }
}

// Calculer le score en pourcentage
$scorePercentage = $totalPoints > 0 ? ($pointsEarned / $totalPoints) * 100 : 0;
$passed = $scorePercentage >= $passingScore;

// Déterminer le statut final
$finalStatus = $passed ? 'passed' : 'failed';

// Créer une entrée dans la table exam_results
$insertResultQuery = $conn->prepare("
    INSERT INTO exam_results (
        exam_id, user_id, score, total_points, points_earned, 
        passing_score, passed, time_spent, completed_at, 
        created_at, status, is_graded
    ) VALUES (
        ?, ?, ?, ?, ?, 
        ?, ?, ?, NOW(), 
        NOW(), ?, ?
    )
");

// Déterminer si l'examen est automatiquement noté ou nécessite une notation manuelle
$needsManualGrading = false;
foreach ($questions as $question) {
    if ($question['question_type'] === 'essay' || $question['question_type'] === 'short_answer') {
        $needsManualGrading = true;
        break;
    }
}

$isGraded = $needsManualGrading ? 'partial' : 'complete';
$insertResultQuery->bind_param(
    "iddddiiiss",
    $examId, $userId, $scorePercentage, $totalPoints, $pointsEarned,
    $passingScore, $passed, $timeSpent, $finalStatus, $isGraded
);
$insertResultQuery->execute();
$resultId = $conn->insert_id;

// Mettre à jour le statut de la tentative pour indiquer qu'elle est notée
$updateAttemptStatusQuery = $conn->prepare("
    UPDATE exam_attempts 
    SET status = 'graded', score = ? 
    WHERE id = ?
");
$updateAttemptStatusQuery->bind_param("di", $scorePercentage, $attemptId);
$updateAttemptStatusQuery->execute();

// Créer des entrées dans question_answers pour chaque réponse
foreach ($answers as $questionId => $answer) {
    $insertQuestionAnswerQuery = $conn->prepare("
        INSERT INTO question_answers (
            question_id, exam_result_id, user_id, 
            answer_text, selected_option_id, is_correct, 
            points_earned, created_at
        ) VALUES (
            ?, ?, ?, 
            ?, ?, ?, 
            ?, NOW()
        )
    ");
    
    $selectedOptionId = null;
    if (!empty($answer['selected_options'])) {
        // Pour les questions à choix unique, prendre la première option
        $selectedOptions = explode(',', $answer['selected_options']);
        if (count($selectedOptions) > 0) {
            $selectedOptionId = $selectedOptions[0];
        }
    }
    
    $insertQuestionAnswerQuery->bind_param(
        "iiisidi",
        $questionId, $resultId, $userId,
        $answer['answer_text'], $selectedOptionId, $answer['is_correct'],
        $answer['points_awarded']
    );
    $insertQuestionAnswerQuery->execute();
}

// Créer une notification pour l'étudiant
$notificationTitle = "Examen terminé";
$notificationMessage = "Vous avez terminé l'examen '" . getExamTitle($examId, $conn) . "'.";
if ($showResults) {
    $notificationMessage .= " Votre score est de " . number_format($scorePercentage, 2) . "%.";
    if ($passed) {
        $notificationMessage .= " Félicitations, vous avez réussi!";
    } else {
        $notificationMessage .= " Malheureusement, vous n'avez pas atteint le score minimum requis.";
    }
}

$insertNotificationQuery = $conn->prepare("
    INSERT INTO notifications (
        user_id, title, message, type, created_at
    ) VALUES (
        ?, ?, ?, ?, NOW()
    )
");
$notificationType = $passed ? 'success' : 'info';
$insertNotificationQuery->bind_param(
    "isss",
    $userId, $notificationTitle, $notificationMessage, $notificationType
);
$insertNotificationQuery->execute();

// Enregistrer l'activité
logActivity($userId, 'finish_exam', 'exam', $examId, $conn);

// Retourner une réponse JSON
echo json_encode([
    'success' => true,
    'message' => 'Examen terminé avec succès',
    'result_id' => $resultId,
    'score' => $scorePercentage,
    'passed' => $passed,
    'show_results' => $showResults
]);

/**
 * Fonction pour récupérer le titre d'un examen
 */
function getExamTitle($examId, $conn) {
    $query = $conn->prepare("SELECT title FROM exams WHERE id = ?");
    $query->bind_param("i", $examId);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['title'];
    }
    
    return "Examen #" . $examId;
}

/**
 * Fonction pour enregistrer une activité
 */
function logActivity($userId, $action, $entityType, $entityId, $conn) {
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $query = $conn->prepare("
        INSERT INTO activity_logs (
            user_id, action, entity_type, entity_id, 
            ip_address, user_agent, created_at
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, NOW()
        )
    ");
    
    $query->bind_param(
        "ississ",
        $userId, $action, $entityType, $entityId,
        $ipAddress, $userAgent
    );
    
    $query->execute();
}
?>