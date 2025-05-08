<?php
/**
 * Script AJAX pour sauvegarder les réponses des étudiants
 * Ce script reçoit les données de la requête POST et enregistre la réponse dans la base de données
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
$questionId = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : $_SESSION['user_id'];
$selectedOptions = isset($_POST['selected_options']) ? $_POST['selected_options'] : null;
$answerText = isset($_POST['answer_text']) ? $_POST['answer_text'] : null;

// Vérifier que les données essentielles sont présentes
if ($attemptId <= 0 || $questionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides'
    ]);
    exit();
}

// Vérifier que l'utilisateur a le droit de modifier cette tentative
$checkAttemptQuery = $conn->prepare("
    SELECT * FROM exam_attempts 
    WHERE id = ? AND user_id = ? AND status = 'in_progress'
");
$checkAttemptQuery->bind_param("ii", $attemptId, $userId);
$checkAttemptQuery->execute();
$attemptResult = $checkAttemptQuery->get_result();

if ($attemptResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tentative non trouvée ou non modifiable'
    ]);
    exit();
}

// Récupérer les informations sur la question
$questionQuery = $conn->prepare("
    SELECT q.*, e.id as exam_id 
    FROM questions q 
    JOIN exams e ON q.exam_id = e.id 
    WHERE q.id = ?
");
$questionQuery->bind_param("i", $questionId);
$questionQuery->execute();
$questionResult = $questionQuery->get_result();

if ($questionResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Question non trouvée'
    ]);
    exit();
}

$question = $questionResult->fetch_assoc();
$examId = $question['exam_id'];
$questionType = $question['question_type'];

// Vérifier si une réponse existe déjà pour cette question dans cette tentative
$checkAnswerQuery = $conn->prepare("
    SELECT id FROM user_answers 
    WHERE attempt_id = ? AND question_id = ?
");
$checkAnswerQuery->bind_param("ii", $attemptId, $questionId);
$checkAnswerQuery->execute();
$answerResult = $checkAnswerQuery->get_result();
$answerExists = $answerResult->num_rows > 0;
$answerId = $answerExists ? $answerResult->fetch_assoc()['id'] : null;

// Déterminer si la réponse est correcte (pour les questions à choix)
$isCorrect = null;
$pointsAwarded = null;

if ($questionType === 'multiple_choice' || $questionType === 'single_choice' || $questionType === 'true_false') {
    // Pour les questions à choix, vérifier si la réponse est correcte
    if ($selectedOptions !== null) {
        // Récupérer les options correctes
        $correctOptionsQuery = $conn->prepare("
            SELECT id FROM question_options 
            WHERE question_id = ? AND is_correct = 1
        ");
        $correctOptionsQuery->bind_param("i", $questionId);
        $correctOptionsQuery->execute();
        $correctOptionsResult = $correctOptionsQuery->get_result();
        
        $correctOptions = [];
        while ($option = $correctOptionsResult->fetch_assoc()) {
            $correctOptions[] = $option['id'];
        }
        
        // Convertir les options sélectionnées en tableau
        $selectedOptionsArray = explode(',', $selectedOptions);
        
        // Pour les questions à choix unique ou vrai/faux
        if ($questionType === 'single_choice' || $questionType === 'true_false') {
            $isCorrect = in_array($selectedOptions, $correctOptions) ? 1 : 0;
        } 
        // Pour les questions à choix multiples
        else if ($questionType === 'multiple_choice') {
            // Vérifier si toutes les options correctes sont sélectionnées et aucune option incorrecte n'est sélectionnée
            $allCorrectSelected = true;
            $noIncorrectSelected = true;
            
            foreach ($correctOptions as $correctOption) {
                if (!in_array($correctOption, $selectedOptionsArray)) {
                    $allCorrectSelected = false;
                    break;
                }
            }
            
            foreach ($selectedOptionsArray as $selectedOption) {
                if (!in_array($selectedOption, $correctOptions)) {
                    $noIncorrectSelected = false;
                    break;
                }
            }
            
            $isCorrect = ($allCorrectSelected && $noIncorrectSelected) ? 1 : 0;
        }
        
        // Calculer les points attribués
        $pointsAwarded = $isCorrect ? $question['points'] : 0;
    }
}

// Préparer la requête SQL en fonction de l'existence ou non de la réponse
if ($answerExists) {
    // Mettre à jour la réponse existante
    $updateQuery = $conn->prepare("
        UPDATE user_answers 
        SET answer_text = ?, selected_options = ?, is_correct = ?, points_awarded = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateQuery->bind_param("ssddi", $answerText, $selectedOptions, $isCorrect, $pointsAwarded, $answerId);
    $success = $updateQuery->execute();
} else {
    // Insérer une nouvelle réponse
    $insertQuery = $conn->prepare("
        INSERT INTO user_answers 
        (attempt_id, question_id, user_id, answer_text, selected_options, is_correct, points_awarded, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertQuery->bind_param("iiissdd", $attemptId, $questionId, $userId, $answerText, $selectedOptions, $isCorrect, $pointsAwarded);
    $success = $insertQuery->execute();
}

// Vérifier si l'opération a réussi
if ($success) {
    // Mettre à jour le timestamp de la tentative
    $updateAttemptQuery = $conn->prepare("
        UPDATE exam_attempts 
        SET updated_at = NOW() 
        WHERE id = ?
    ");
    $updateAttemptQuery->bind_param("i", $attemptId);
    $updateAttemptQuery->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Réponse enregistrée avec succès',
        'is_correct' => $isCorrect,
        'points_awarded' => $pointsAwarded
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'enregistrement de la réponse: ' . $conn->error
    ]);
}
?>