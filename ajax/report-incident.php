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

if (!$data || !isset($data['session_id']) || !isset($data['incident_type']) || !isset($data['description'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$session_id = intval($data['session_id']);
$incident_type = $data['incident_type'];
$description = $data['description'];
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');

// Vérifier que la session appartient à l'étudiant connecté
$stmt = $conn->prepare("SELECT * FROM exam_sessions WHERE id = ? AND student_id = ?");
$student_id = $_SESSION['user_id'];
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session non valide']);
    exit();
}

// Enregistrer l'incident
$stmt = $conn->prepare("INSERT INTO proctoring_incidents (session_id, student_id, incident_type, description, timestamp) 
                        VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $session_id, $student_id, $incident_type, $description, $timestamp);

if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'incident']);
    exit();
}

// Renvoyer la réponse
header('Content-Type: application/json');
echo json_encode(['success' => true, 'incident_id' => $conn->insert_id]);
