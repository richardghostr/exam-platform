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

if (!$data || !isset($data['attempt_id']) || !isset($data['incident_type']) || !isset($data['image_data'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$attempt_id = intval($data['attempt_id']);
$incident_type = $data['incident_type'];
$description = isset($data['description']) ? $data['description'] : '';
$image_data = $data['image_data'];
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
$user_id = $_SESSION['user_id'];

// Vérifier que la tentative appartient à l'étudiant connecté
$stmt = $conn->prepare("SELECT ea.*, e.id as exam_id FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.id = ? AND ea.user_id = ?");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tentative non valide']);
    exit();
}

$attempt_data = $result->fetch_assoc();
$exam_id = $attempt_data['exam_id'];

// Créer le répertoire de stockage s'il n'existe pas
$upload_dir = '../uploads/proctoring/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Extraire les données de l'image
$image_parts = explode(";base64,", $image_data);
$image_base64 = isset($image_parts[1]) ? $image_parts[1] : $image_data;
$image_decoded = base64_decode($image_base64);

// Générer un nom de fichier unique
$filename = 'incident_' . $attempt_id . '_' . time() . '.jpg';
$file_path = $upload_dir . $filename;

// Enregistrer l'image
if (file_put_contents($file_path, $image_decoded)) {
    // Commencer une transaction
    $conn->begin_transaction();

    try {
        // Enregistrer l'incident s'il n'existe pas déjà
        $incident_id = 0;
        $stmt = $conn->prepare("SELECT id FROM proctoring_incidents WHERE attempt_id = ? AND user_id = ? AND incident_type = ? AND timestamp = ?");
        $stmt->bind_param("iiss", $attempt_id, $user_id, $incident_type, $timestamp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $incident_id = $result->fetch_assoc()['id'];
            
            // Mettre à jour l'image de l'incident existant
            $stmt = $conn->prepare("UPDATE proctoring_incidents SET image_path = ? WHERE id = ?");
            $stmt->bind_param("si", $filename, $incident_id);
            $stmt->execute();
        } else {
            // Créer un nouvel incident avec l'image
            $stmt = $conn->prepare("INSERT INTO proctoring_incidents 
                                    (attempt_id, incident_type, severity, description, status, timestamp, created_at, exam_id, user_id, image_path) 
                                    VALUES (?, ?, 'medium', ?, 'pending', ?, NOW(), ?, ?, ?)");
            $stmt->bind_param("issssis", $attempt_id, $incident_type, $description, $timestamp, $exam_id, $user_id, $filename);
            $stmt->execute();
            $incident_id = $conn->insert_id;
        }
        
        // Enregistrer également dans la table proctoring_images
        $stmt = $conn->prepare("INSERT INTO proctoring_images (session_id, student_id, incident_type, description, image_path, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
        // Utiliser l'ID de session d'examen si disponible, sinon utiliser l'ID de tentative
        $session_id = $attempt_id; // Fallback à l'ID de tentative
        
        // Vérifier s'il existe une session pour cet examen et cet utilisateur
        $stmt_session = $conn->prepare("SELECT id FROM exam_sessions WHERE exam_id = ? AND user_id = ? AND status = 'in_progress'");
        $stmt_session->bind_param("ii", $exam_id, $user_id);
        $stmt_session->execute();
        $session_result = $stmt_session->get_result();
        
        if ($session_result->num_rows > 0) {
            $session_id = $session_result->fetch_assoc()['id'];
        }
        
        $stmt->bind_param("iissss", $session_id, $user_id, $incident_type, $description, $filename, $timestamp);
        $stmt->execute();
        
        // Mettre à jour le compteur d'incidents dans la session d'examen
        $stmt = $conn->prepare("UPDATE exam_sessions SET incident_count = incident_count + 1 WHERE id = ? AND status = 'in_progress'");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        
        // Valider la transaction
        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Image d\'incident enregistrée avec succès',
            'incident_id' => $incident_id,
            'image_path' => $filename
        ]);
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollback();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'image']);
}
