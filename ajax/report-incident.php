<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Vérifier que la requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Récupérer les données POST (utilisation de file_get_contents pour les données JSON)
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = $_POST; // Fallback pour les données FormData
}

$attemptId = isset($data['attempt_id']) ? intval($data['attempt_id']) : 0;
$incidentType = isset($data['incident_type']) ? trim($data['incident_type']) : '';
$description = isset($data['description']) ? trim($data['description']) : '';
$imageData = isset($data['image_data']) ? $data['image_data'] : null;

// Valider les données
if ($attemptId <= 0 || empty($incidentType) || empty($description)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Données invalides',
        'received_data' => $data // Pour le débogage
    ]);
    exit();
}

try {
    // Récupérer l'examen et l'utilisateur associés à la tentative
    $stmt = $conn->prepare("
        SELECT exam_id, user_id 
        FROM exam_attempts 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $attemptId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Tentative d'examen non trouvée");
    }
    
    $attemptData = $result->fetch_assoc();
    $examId = $attemptData['exam_id'];
    $userId = $attemptData['user_id'];
    
    // Déterminer la sévérité
    $severity = 'medium';
    if (in_array($incidentType, ['multiple_faces', 'phone_detected', 'tab_change'])) {
        $severity = 'high';
    } elseif (in_array($incidentType, ['face_missing', 'audio_detected'])) {
        $severity = 'low';
    }
    
    // Gérer l'image si elle est fournie
    $imagePath = null;
    if ($imageData && strpos($imageData, 'data:image') === 0) {
        $uploadDir = '../../uploads/proctoring/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $imageName = 'incident_' . $attemptId . '_' . time() . '.jpg';
        $imagePath = 'uploads/proctoring/' . $imageName;
        $fullPath = '../../' . $imagePath;
        
        // Convertir l'image base64 en fichier
        list($type, $imageData) = explode(';', $imageData);
        list(, $imageData) = explode(',', $imageData);
        $imageBinary = base64_decode($imageData);
        
        file_put_contents($fullPath, $imageBinary);
    }
    
    // Enregistrer l'incident
    $stmt = $conn->prepare("
        INSERT INTO proctoring_incidents (
            attempt_id, 
            exam_id, 
            user_id,
            incident_type, 
            severity, 
            description, 
            status, 
            timestamp,
            created_at,
            image_path,
            details
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW(), ?, ?)
    ");
    
    $details = json_encode([
        'browser' => $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Inconnu'
    ]);
    
    $stmt->bind_param(
        "iiisssss", 
        $attemptId,
        $examId,
        $userId,
        $incidentType, 
        $severity, 
        $description,
        $imagePath,
        $details
    );
    
    if ($stmt->execute()) {
        $incidentId = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Incident enregistré',
            'incident_id' => $incidentId
        ]);
    } else {
        throw new Exception("Erreur SQL: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Erreur report-incident: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage(),
        'error_details' => $conn->error ?? null
    ]);
}