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

if (!$data || !isset($data['attempt_id']) || !isset($data['objects'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$attempt_id = intval($data['attempt_id']);
$objects = $data['objects'];
$description = isset($data['description']) ? $data['description'] : 'Objets interdits détectés';
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
$student_id = $_SESSION['user_id'];
$image_data = isset($data['image_data']) ? $data['image_data'] : null;

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
    // Enregistrer l'incident
    $stmt = $conn->prepare("INSERT INTO proctoring_incidents 
                            (attempt_id, student_id, incident_type, description, severity, timestamp, created_at) 
                            VALUES (?, ?, 'object_detection', ?, 'high', ?, NOW())");
    $stmt->bind_param("iiss", $attempt_id, $student_id, $description, $timestamp);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de l'enregistrement de l'incident: " . $stmt->error);
    }
    
    $incident_id = $conn->insert_id;
    
    // Enregistrer les objets détectés
    foreach ($objects as $object) {
        $object_class = $object['class'];
        $confidence = $object['score'];
        
        $stmt = $conn->prepare("INSERT INTO detected_objects 
                                (incident_id, object_type, confidence, created_at) 
                                VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("isd", $incident_id, $object_class, $confidence);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de l'enregistrement de l'objet détecté: " . $stmt->error);
        }
    }
    
    // Sauvegarder l'image si disponible
    if ($image_data) {
        $image_path = '../uploads/incidents/' . $incident_id . '_' . time() . '.jpg';
        $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $image_data = base64_decode($image_data);
        
        if (file_put_contents($image_path, $image_data)) {
            $relative_path = str_replace('../', '', $image_path);
            
            $stmt = $conn->prepare("UPDATE proctoring_incidents SET image_path = ? WHERE id = ?");
            $stmt->bind_param("si", $relative_path, $incident_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'enregistrement de l'image: " . $stmt->error);
            }
        }
    }
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'incident_id' => $incident_id,
        'message' => 'Objets détectés signalés avec succès'
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
