<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Récupérer les données
$attemptId = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
$incidentType = isset($_POST['incident_type']) ? $_POST['incident_type'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';

// Valider les données
if ($attemptId === 0 || empty($incidentType) || empty($description)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Vérifier si la tentative existe
$attemptQuery = $conn->prepare("SELECT * FROM exam_attempts WHERE id = ?");
$attemptQuery->bind_param("i", $attemptId);
$attemptQuery->execute();
$attemptResult = $attemptQuery->get_result();

if ($attemptResult->num_rows === 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Attempt not found']);
    exit();
}

// Déterminer la sévérité de l'incident
$severity = 'medium'; // Par défaut
if (in_array($incidentType, ['face_not_detected', 'multiple_faces', 'webcam_access_denied'])) {
    $severity = 'high';
} elseif (in_array($incidentType, ['tab_switch', 'screen_activity'])) {
    $severity = 'medium';
} else {
    $severity = 'low';
}

// Enregistrer l'incident
$insertQuery = $conn->prepare("
    INSERT INTO proctoring_incidents (attempt_id, incident_type, severity, description, timestamp, created_at) 
    VALUES (?, ?, ?, ?, NOW(), NOW())
");
$insertQuery->bind_param("isss", $attemptId, $incidentType, $severity, $description);
$success = $insertQuery->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Incident reported successfully']);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Failed to report incident: ' . $conn->error]);
}
