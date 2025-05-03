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
$questionId = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
$answerType = isset($_POST['answer_type']) ? $_POST['answer_type'] : '';
$selectedOptions = isset($_POST['selected_options']) ? $_POST['selected_options'] : '';
$answerText = isset($_POST['answer_text']) ? $_POST['answer_text'] : '';

// Valider les données
if ($attemptId === 0 || $questionId === 0 || empty($answerType)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Vérifier si la tentative appartient à l'étudiant
$studentId = $_SESSION['user_id'];
$attemptQuery = $conn->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?");
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

// Vérifier si la question appartient à l'examen
$questionQuery = $conn->prepare("
    SELECT q.*, e.id as exam_id 
    FROM questions q 
    JOIN exams e ON q.exam_id = e.id 
    WHERE q.id = ? AND e.id = ?
");
$questionQuery->bind_param("ii", $questionId, $attempt['exam_id']);
$questionQuery->execute();
$questionResult = $questionQuery->get_result();

if ($questionResult->num_rows === 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Question not found']);
    exit();
}

$question = $questionResult->fetch_assoc();

// Vérifier si une réponse existe déjà pour cette question
$existingAnswerQuery = $conn->prepare("SELECT id FROM user_answers WHERE attempt_id = ? AND question_id = ?");
$existingAnswerQuery->bind_param("ii", $attemptId, $questionId);
$existingAnswerQuery->execute();
$existingAnswerResult = $existingAnswerQuery->get_result();
$answerExists = $existingAnswerResult->num_rows > 0;

// Déterminer si la réponse est correcte
$isCorrect = null;
$pointsAwarded = 0;

if ($answerType === 'multiple_choice') {
    // Récupérer toutes les options correctes
    $correctOptionsQuery = $conn->prepare("SELECT id FROM question_options WHERE question_id = ? AND is_correct = 1");
    $correctOptionsQuery->bind_param("i", $questionId);
    $correctOptionsQuery->execute();
    $correctOptionsResult = $correctOptionsQuery->get_result();
    
    $correctOptions = [];
    while ($option = $correctOptionsResult->fetch_assoc()) {
        $correctOptions[] = $option['id'];
    }
    
    // Comparer les options sélectionnées avec les options correctes
    $selectedOptionsArray = explode(',', $selectedOptions);
    $selectedOptionsArray = array_map('intval', $selectedOptionsArray);
    
    // La réponse est correcte si toutes les options correctes sont sélectionnées et aucune option incorrecte n'est sélectionnée
    $allCorrectSelected = count(array_diff($correctOptions, $selectedOptionsArray)) === 0;
    $noIncorrectSelected = count(array_diff($selectedOptionsArray, $correctOptions)) === 0;
    
    $isCorrect = $allCorrectSelected && $noIncorrectSelected;
    $pointsAwarded = $isCorrect ? $question['points'] : 0;
    
} elseif ($answerType === 'single_choice' || $answerType === 'true_false') {
    // Vérifier si l'option sélectionnée est correcte
    $optionQuery = $conn->prepare("SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?");
    $optionQuery->bind_param("ii", $selectedOptions, $questionId);
    $optionQuery->execute();
    $optionResult = $optionQuery->get_result();
    
    if ($optionResult->num_rows > 0) {
        $option = $optionResult->fetch_assoc();
        $isCorrect = $option['is_correct'] == 1;
        $pointsAwarded = $isCorrect ? $question['points'] : 0;
    }
    
} elseif ($answerType === 'essay') {
    // Les questions à réponse libre nécessitent une notation manuelle
    $isCorrect = null;
    $pointsAwarded = null;
}

// Enregistrer ou mettre à jour la réponse
if ($answerExists) {
    // Mettre à jour la réponse existante
    $updateQuery = $conn->prepare("
        UPDATE user_answers 
        SET answer_text = ?, selected_options = ?, is_correct = ?, points_awarded = ?, updated_at = NOW() 
        WHERE attempt_id = ? AND question_id = ?
    ");
    $updateQuery->bind_param("ssdiii", $answerText, $selectedOptions, $isCorrect, $pointsAwarded, $attemptId, $questionId);
    $success = $updateQuery->execute();
} else {
    // Insérer une nouvelle réponse
    $insertQuery = $conn->prepare("
        INSERT INTO user_answers (attempt_id, question_id, answer_text, selected_options, is_correct, points_awarded, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertQuery->bind_param("iissdi", $attemptId, $questionId, $answerText, $selectedOptions, $isCorrect, $pointsAwarded);
    $success = $insertQuery->execute();
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Answer saved successfully']);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Failed to save answer: ' . $conn->error]);
}
