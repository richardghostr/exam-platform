<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../student/includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn() || $_SESSION['user_type'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si la requête est en POST et contient des données JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || !isset($data['session_id']) || !isset($data['answers'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$session_id = intval($data['session_id']);
$answers = $data['answers'];

// Vérifier que la session appartient à l'étudiant connecté
$stmt = $conn->prepare("SELECT * FROM exam_sessions WHERE id = ? AND student_id = ? AND status = 'in_progress'");
$student_id = $_SESSION['user_id'];
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session non valide']);
    exit();
}

// Enregistrer ou mettre à jour les réponses
$success = true;
$conn->begin_transaction();

try {
    foreach ($answers as $question_id => $answer) {
        // Vérifier si une réponse existe déjà
        $stmt = $conn->prepare("SELECT id FROM exam_answers WHERE session_id = ? AND question_id = ?");
        $stmt->bind_param("ii", $session_id, $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Mettre à jour la réponse existante
            $stmt = $conn->prepare("UPDATE exam_answers SET answer = ?, updated_at = NOW() WHERE session_id = ? AND question_id = ?");
            $stmt->bind_param("sii", $answer, $session_id, $question_id);
        } else {
            // Insérer une nouvelle réponse
            $stmt = $conn->prepare("INSERT INTO exam_answers (session_id, question_id, answer, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iis", $session_id, $question_id, $answer);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de l'enregistrement de la réponse pour la question $question_id");
        }
    }
    
    // Mettre à jour la date de dernière activité de la session
    $stmt = $conn->prepare("UPDATE exam_sessions SET last_activity = NOW() WHERE id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    $error_message = $e->getMessage();
}

// Renvoyer la réponse
header('Content-Type: application/json');
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $error_message]);
}
