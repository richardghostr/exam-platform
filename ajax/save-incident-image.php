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

if (!$data || !isset($data['session_id']) || !isset($data['incident_type']) || !isset($data['image_data'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$session_id = intval($data['session_id']);
$incident_type = $data['incident_type'];
$description = isset($data['description']) ? $data['description'] : '';
$image_data = $data['image_data'];
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

// Extraire les données de l'image base64
$image_parts = explode(";base64,", $image_data);
$image_base64 = isset($image_parts[1]) ? $image_parts[1] : $image_data;
$image_binary = base64_decode($image_base64);

// Créer le dossier de stockage s'il n'existe pas
$upload_dir = '../uploads/proctoring/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Générer un nom de fichier unique
$filename = 'incident_' . $session_id . '_' . time() . '_' . uniqid() . '.jpg';
$file_path = $upload_dir . $filename;

// Enregistrer l'image
if (!file_put_contents($file_path, $image_binary)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'image']);
    exit();
}

// Enregistrer les informations de l'image dans la base de données
$stmt = $conn->prepare("INSERT INTO proctoring_images (session_id, student_id, incident_type, description, image_path, timestamp) 
                        VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissss", $session_id, $student_id, $incident_type, $description, $filename, $timestamp);

if (!$stmt->execute()) {
    // Supprimer l'image si l'enregistrement en base de données échoue
    unlink($file_path);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement des informations de l\'image']);
    exit();
}

// Renvoyer la réponse
header('Content-Type: application/json');
echo json_encode(['success' => true, 'image_id' => $conn->insert_id, 'filename' => $filename]);
