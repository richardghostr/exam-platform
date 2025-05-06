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

if (!$data || !isset($data['attempt_id']) || !isset($data['image_data'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$attempt_id = intval($data['attempt_id']);
$image_data = $data['image_data'];
$student_id = $_SESSION['user_id'];

// Vérifier que la tentative appartient à l'étudiant connecté
$stmt = $conn->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $attempt_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tentative non valide']);
    exit();
}

// Créer le répertoire de stockage s'il n'existe pas
$upload_dir = '../uploads/reference_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Extraire les données de l'image
$image_parts = explode(";base64,", $image_data);
$image_base64 = isset($image_parts[1]) ? $image_parts[1] : $image_data;
$image_decoded = base64_decode($image_base64);

// Générer un nom de fichier unique
$filename = 'reference_' . $attempt_id . '_' . time() . '.jpg';
$file_path = $upload_dir . $filename;

// Enregistrer l'image
if (file_put_contents($file_path, $image_decoded)) {
    // Enregistrer les informations dans la base de données
    $stmt = $conn->prepare("INSERT INTO proctoring_reference_images (attempt_id, student_id, image_path, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $attempt_id, $student_id, $filename);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Image de référence enregistrée avec succès',
            'image_path' => $filename
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement dans la base de données']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'image']);
}
