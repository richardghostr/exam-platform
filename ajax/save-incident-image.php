<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn() || $_SESSION['user_type'] !== 'student') {
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

if (!$data || !isset($data['attempt_id']) || !isset($data['incident_type']) || !isset($data['image_data'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$attempt_id = intval($data['attempt_id']);
$incident_type = $data['incident_type'];
$description = isset($data['description']) ? $data['description'] : '';
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
$student_id = $_SESSION['user_id'];

// Vérifier que la tentative appartient à l'étudiant connecté
$stmt = $conn->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tentative non valide ou terminée']);
    exit();
}

// Commencer une transaction
$conn->begin_transaction();

try {
    // Extraire les données de l'image base64
    $image_parts = explode(";base64,", $data['image_data']);
    $image_base64 = isset($image_parts[1]) ? $image_parts[1] : $data['image_data'];
    $image_binary = base64_decode($image_base64);
    
    // Créer le dossier de stockage s'il n'existe pas
    $upload_dir = '../uploads/proctoring/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Générer un nom de fichier unique
    $filename = 'incident_' . $attempt_id . '_' . time() . '_' . uniqid() . '.jpg';
    $file_path = $upload_dir . $filename;
    
    // Enregistrer l'image
    if (!file_put_contents($file_path, $image_binary)) {
        throw new Exception("Erreur lors de l'enregistrement de l'image");
    }
    
    // Enregistrer l'incident avec l'image
    $stmt = $conn->prepare("INSERT INTO proctoring_incidents 
                            (attempt_id, student_id, incident_type, description, image_path, timestamp, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iissss", $attempt_id, $student_id, $incident_type, $description, $filename, $timestamp);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de l'enregistrement de l'incident: " . $stmt->error);
    }
    
    $incident_id = $conn->insert_id;
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'incident_id' => $incident_id,
        'image_path' => $filename,
        'message' => 'Image d\'incident sauvegardée avec succès'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

