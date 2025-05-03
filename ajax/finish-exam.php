<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isLoggedIn() || !isStudent()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Récupérer les données
$attemptId = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;

// Valider les données
if ($attemptId === 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing attempt ID']);
    exit();
}

// Vérifier si la tentative appartient à l'étudiant
$studentId = $_SESSION['user_id'];
$attemptQuery = $conn->prepare("
    SELECT ea.*, e.passing_score, e.has_essay 
    FROM exam_attempts ea 
    JOIN exams e ON ea.exam_id = e.id 
    WHERE ea.id = ? AND ea.user_id = ?
");
$attemptQuery->bind_param("ii", $attemptId, $studentId);
$attemptQuery->execute();
$attemptResult = $attemptQuery->get_result();

if ($attemptResult->num_rows === 0) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized attempt']);
    exit();
}

$attempt = $attemptResult->fetch_assoc();

// Vérifier si l'examen est toujours en cours
if ($attempt['status'] !== 'in_progress') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Exam is not in progress']);
    exit();
}

// Calculer le score
$scoreQuery = $conn->prepare("
    SELECT 
        SUM(CASE WHEN is_correct = 1 THEN points_awarded ELSE 0 END) as earned_points,
        SUM(q.points) as total_points
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    WHERE ua.attempt_id = ?
");
$scoreQuery->bind_param("i", $attemptId);
$scoreQuery->execute();
$scoreResult = $scoreQuery->get_result();
$scoreData = $scoreResult->fetch_assoc();

$earnedPoints = $scoreData['earned_points'] ?? 0;
$totalPoints = $scoreData['total_points'] ?? 0;

// Calculer le pourcentage
$score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;

// Déterminer le statut final
$status = 'completed';
if ($attempt['has_essay']) {
    // Si l'examen contient des questions à réponse libre, il nécessite une notation manuelle
    $status = 'needs_grading';
} else {
    // Sinon, on peut déterminer immédiatement si l'étudiant a réussi ou échoué
    $status = $score >= $attempt['passing_score'] ? 'passed' : 'failed';
}

// Mettre à jour la tentative
$updateQuery = $conn->prepare("
    UPDATE exam_attempts 
    SET status = ?, end_time = NOW(), score = ? 
    WHERE id = ?
");
$updateQuery->bind_param("sdi", $status, $score, $attemptId);
$success = $updateQuery->execute();

if ($success) {
    // Enregistrer l'activité
    logActivity($studentId, 'exam_completed', 'exam_attempts', $attemptId, "Examen terminé avec un score de " . round($score, 2) . "%");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Exam completed successfully',
        'score' => round($score, 2),
        'status' => $status
    ]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Failed to complete exam: ' . $conn->error]);
}
