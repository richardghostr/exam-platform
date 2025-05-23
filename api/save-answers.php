<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../student/includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer et décoder les données JSON
$json_data = file_get_contents('php://input');
if (!$json_data) {
    // Fallback pour les données de formulaire traditionnelles si aucun JSON n'est détecté
    $attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $selected_options = isset($_POST['selected_options']) ? $_POST['selected_options'] : null;
    $answer_text = isset($_POST['answer_text']) ? $_POST['answer_text'] : null;
} else {
    // Traiter les données JSON
    $data = json_decode($json_data, true);
    
    if (!$data) {
        // Erreur de décodage JSON
        error_log("Erreur de décodage JSON: " . json_last_error_msg());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides: ' . json_last_error_msg()]);
        exit();
    }
    
    $attempt_id = isset($data['attempt_id']) ? intval($data['attempt_id']) : 0;
    $question_id = isset($data['question_id']) ? intval($data['question_id']) : 0;
    $selected_options = isset($data['selected_options']) ? $data['selected_options'] : null;
    $answer_text = isset($data['answer_text']) ? $data['answer_text'] : null;
}

// Validation des données requises
if (!$attempt_id || !$question_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données requises manquantes (attempt_id ou question_id)']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier que la tentative appartient à l'étudiant connecté et est en cours
$stmt = $conn->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tentative non valide ou terminée']);
    exit();
}

// Vérifier que la question appartient à l'examen
$attempt = $result->fetch_assoc();
$exam_id = $attempt['exam_id'];

$stmt = $conn->prepare("SELECT * FROM questions WHERE id = ? AND exam_id = ?");
$stmt->bind_param("ii", $question_id, $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Question non valide pour cet examen']);
    exit();
}

$question = $result->fetch_assoc();
$question_type = $question['question_type'];

// Debug log
error_log("Sauvegarde de réponse - Type: {$question_type}, Question ID: {$question_id}, Attempt ID: {$attempt_id}");
error_log("Données - Selected Options: " . ($selected_options ?? 'null') . ", Answer Text: " . ($answer_text ?? 'null'));

// Commencer une transaction
$conn->begin_transaction();

try {
    // Vérifier si une réponse existe déjà pour cette question
    $stmt = $conn->prepare("SELECT id FROM user_answers WHERE attempt_id = ? AND question_id = ?");
    $stmt->bind_param("ii", $attempt_id, $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Mettre à jour la réponse existante
        $answer_id = $result->fetch_assoc()['id'];
        
        if ($question_type === 'multiple_choice' || $question_type === 'single_choice' || $question_type === 'true_false') {
            $stmt = $conn->prepare("UPDATE user_answers SET selected_options = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $selected_options, $answer_id);
        } else {
            $stmt = $conn->prepare("UPDATE user_answers SET answer_text = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $answer_text, $answer_id);
        }
    } else {
        // Insérer une nouvelle réponse
        if ($question_type === 'multiple_choice' || $question_type === 'single_choice' || $question_type === 'true_false') {
            $stmt = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_options, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $attempt_id, $question_id, $selected_options, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, answer_text, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $attempt_id, $question_id, $answer_text, $user_id);
        }
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de l'enregistrement de la réponse: " . $stmt->error);
    }
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Réponse enregistrée avec succès'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollback();
    
    error_log("Erreur lors de la sauvegarde de réponse: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
