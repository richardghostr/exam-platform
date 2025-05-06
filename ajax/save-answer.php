<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// // Vérifier si l'utilisateur est connecté
// if (!isLoggedIn() || $_SESSION['user_type'] !== 'student') {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Non autorisé']);
//     exit();
// }

// // Vérifier si la requête est en POST
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
//     exit();
// }

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// if (!$data || !isset($data['session_id']) || !isset($data['question_id']) || !isset($data['answer'])) {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Données invalides']);
//     exit();
// }

$session_id = intval($data['session_id']);
$question_id = intval($data['question_id']);
$answer = $data['answer'];
$student_id = $_SESSION['user_id'];

// Vérifier que la session appartient à l'étudiant connecté et est en cours
$stmt = $conn->prepare("SELECT * FROM exam_sessions WHERE id = ? AND user_id = ? AND status = 'in_progress'");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session non valide ou terminée']);
    exit();
}

// Commencer une transaction
$conn->begin_transaction();

try {
    // Vérifier si une réponse existe déjà pour cette question
    $stmt = $conn->prepare("SELECT id FROM user_answers WHERE attempt_id = ? AND question_id = ?");
    $stmt->bind_param("ii", $session_id, $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Mettre à jour la réponse existante
        $answer_id = $result->fetch_assoc()['id'];
        $stmt = $conn->prepare("UPDATE user_answers SET answer_text = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $answer, $answer_id);
    } else {
        // Insérer une nouvelle réponse
        $stmt = $conn->prepare("INSERT INTO user_answers (attempt_id , question_id, user_id, answer_text, created_at) 
                                VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $session_id, $question_id, $student_id, $answer);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de l'enregistrement de la réponse: " . $stmt->error);
    }
    
    // Mettre à jour la date de dernière activité de la session
    $stmt = $conn->prepare("UPDATE exam_sessions SET last_activity = NOW() WHERE id = ?");
    $stmt->bind_param("i", $session_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de la mise à jour de l'activité de la session: " . $stmt->error);
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
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
