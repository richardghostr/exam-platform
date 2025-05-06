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

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || !isset($data['attempt_id']) || !isset($data['incident_type'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$attempt_id = intval($data['attempt_id']);
$incident_type = $data['incident_type'];
$description = isset($data['description']) ? $data['description'] : '';
$severity = isset($data['severity']) ? $data['severity'] : 'medium';
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
$user_id = $_SESSION['user_id'];

// Vérifier que la tentative appartient à l'étudiant connecté
$stmt = $conn->prepare("SELECT ea.*, e.id as exam_id FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.id = ? AND ea.user_id = ? AND ea.status = 'in_progress'");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tentative non valide ou terminée']);
    exit();
}

$attempt_data = $result->fetch_assoc();
$exam_id = $attempt_data['exam_id'];

// Commencer une transaction
$conn->begin_transaction();

try {
    // Enregistrer l'incident
    $stmt = $conn->prepare("INSERT INTO proctoring_incidents 
                            (attempt_id, incident_type, severity, description, status, timestamp, created_at, exam_id, user_id) 
                            VALUES (?, ?, ?, ?, 'pending', ?, NOW(), ?, ?)");
    $stmt->bind_param("issssii", $attempt_id, $incident_type, $severity, $description, $timestamp, $exam_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de l'enregistrement de l'incident: " . $stmt->error);
    }
    
    $incident_id = $conn->insert_id;
    
    // Mettre à jour le compteur d'incidents dans la session d'examen
    $stmt = $conn->prepare("UPDATE exam_sessions SET incident_count = incident_count + 1 WHERE exam_id = ? AND user_id = ? AND status = 'in_progress'");
    $stmt->bind_param("ii", $exam_id, $user_id);
    $stmt->execute();
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'incident_id' => $incident_id,
        'message' => 'Incident signalé avec succès'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
