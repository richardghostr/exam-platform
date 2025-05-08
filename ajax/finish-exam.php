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

// Initialiser la réponse
$response = [
    'success' => false,
    'message' => 'Une erreur est survenue'
];

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $response['message'] = 'Vous devez être connecté pour effectuer cette action';
    echo json_encode($response);
    exit();
}

// Vérifier si la requête est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Méthode non autorisée';
    echo json_encode($response);
    exit();
}

// Récupérer et valider les données
$attemptId = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
$timeExpired = isset($_POST['time_expired']) ? (bool)$_POST['time_expired'] : false;
$userId = $_SESSION['user_id'];

// Vérifier que l'ID de tentative est valide
if ($attemptId <= 0) {
    $response['message'] = 'ID de tentative invalide';
    echo json_encode($response);
    exit();
}

try {
    // Vérifier que la tentative existe et appartient à l'utilisateur
    $checkAttemptQuery = $conn->prepare("
        SELECT a.*, e.id as exam_id, e.title as exam_title, e.passing_score, e.duration
        FROM exam_attempts a
        JOIN exams e ON a.exam_id = e.id
        WHERE a.id = ? AND a.user_id = ?
    ");
    
    if (!$checkAttemptQuery) {
        throw new Exception("Erreur de préparation de la requête: " . $conn->error);
    }
    
    $checkAttemptQuery->bind_param("ii", $attemptId, $userId);
    $checkAttemptQuery->execute();
    $attemptResult = $checkAttemptQuery->get_result();
    
    if ($attemptResult->num_rows === 0) {
        $response['message'] = 'Tentative non trouvée ou non autorisée';
        echo json_encode($response);
        exit();
    }
    
    $attempt = $attemptResult->fetch_assoc();
    $examId = $attempt['exam_id'];
    $examTitle = $attempt['exam_title'];
    $passingScore = $attempt['passing_score'];
    
    // Vérifier si la tentative est déjà terminée
    if ($attempt['status'] !== 'in_progress') {
        $response['message'] = 'Cette tentative est déjà terminée';
        echo json_encode($response);
        exit();
    }
    
    // Mettre à jour la tentative pour indiquer qu'elle est terminée
    $updateAttemptQuery = $conn->prepare("
        UPDATE exam_attempts 
        SET status = 'completed', end_time = NOW() 
        WHERE id = ?
    ");
    
    if (!$updateAttemptQuery) {
        throw new Exception("Erreur de préparation de la requête: " . $conn->error);
    }
    
    $updateAttemptQuery->bind_param("i", $attemptId);
    if (!$updateAttemptQuery->execute()) {
        throw new Exception("Erreur lors de la mise à jour de la tentative: " . $updateAttemptQuery->error);
    }
    
    // Mettre à jour la session d'examen si elle existe
    $updateSessionQuery = $conn->prepare("
        UPDATE exam_sessions 
        SET status = 'completed', end_time = NOW() 
        WHERE exam_id = ? AND user_id = ? AND status = 'in_progress'
    ");
    
    if ($updateSessionQuery) {
        $updateSessionQuery->bind_param("ii", $examId, $userId);
        $updateSessionQuery->execute();
    }
    
    // Calculer le temps passé
    $startTime = strtotime($attempt['start_time']);
    $endTime = time();
    $timeSpent = $endTime - $startTime;
    
    // Récupérer les réponses de l'utilisateur
    $answersQuery = $conn->prepare("
        SELECT * FROM user_answers 
        WHERE attempt_id = ?
    ");
    
    if (!$answersQuery) {
        throw new Exception("Erreur de préparation de la requête: " . $conn->error);
    }
    
    $answersQuery->bind_param("i", $attemptId);
    $answersQuery->execute();
    $answersResult = $answersQuery->get_result();
    
    // Récupérer les questions de l'examen
    $questionsQuery = $conn->prepare("
        SELECT * FROM questions 
        WHERE exam_id = ?
    ");
    
    if (!$questionsQuery) {
        throw new Exception("Erreur de préparation de la requête: " . $conn->error);
    }
    
    $questionsQuery->bind_param("i", $examId);
    $questionsQuery->execute();
    $questionsResult = $questionsQuery->get_result();
    
    // Calculer le score
    $totalPoints = 0;
    $earnedPoints = 0;
    $questionCount = 0;
    $answeredCount = 0;
    
    // Créer un tableau des réponses indexé par question_id
    $answers = [];
    while ($answer = $answersResult->fetch_assoc()) {
        $answers[$answer['question_id']] = $answer;
        $answeredCount++;
        
        if ($answer['is_correct']) {
            $earnedPoints += $answer['points_awarded'] ?: 0;
        }
    }
    
    // Calculer le total des points possibles
    while ($question = $questionsResult->fetch_assoc()) {
        $totalPoints += $question['points'];
        $questionCount++;
    }
    
    // Calculer le score en pourcentage
    $scorePercentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
    $passed = $scorePercentage >= $passingScore;
    
    // Mettre à jour le score de la tentative
    $updateScoreQuery = $conn->prepare("
        UPDATE exam_attempts 
        SET score = ? 
        WHERE id = ?
    ");
    
    if (!$updateScoreQuery) {
        throw new Exception("Erreur de préparation de la requête: " . $conn->error);
    }
    
    $updateScoreQuery->bind_param("di", $scorePercentage, $attemptId);
    $updateScoreQuery->execute();
    
    // Créer une notification pour l'utilisateur
    $notificationTitle = "Examen terminé";
    $notificationMessage = "Vous avez terminé l'examen '$examTitle'.";
    
    $insertNotificationQuery = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    
    if ($insertNotificationQuery) {
        $notificationType = $passed ? 'success' : 'info';
        $insertNotificationQuery->bind_param("isss", $userId, $notificationTitle, $notificationMessage, $notificationType);
        $insertNotificationQuery->execute();
    }
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'message' => 'Examen terminé avec succès',
        'attempt_id' => $attemptId,
        'score' => $scorePercentage,
        'passed' => $passed,
        'questions_total' => $questionCount,
        'questions_answered' => $answeredCount
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ];
}

// Retourner la réponse
echo json_encode($response);
?>