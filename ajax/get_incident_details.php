<?php

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Debug - enregistrer la requête
error_log("Requête reçue pour l'incident ID: " . $_GET['id']);

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$incidentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($incidentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID d\'incident invalide']);
    exit();
}

try {
    $query = "
        SELECT pi.*, ea.exam_id, e.title as exam_title, 
               u.username, u.first_name, u.last_name
        FROM proctoring_incidents pi
        JOIN exam_attempts ea ON pi.attempt_id = ea.id
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON ea.user_id = u.id
        WHERE pi.id = ? AND e.teacher_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $teacherId = $_SESSION['user_id'];
    $stmt->bind_param("ii", $incidentId, $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Incident non trouvé ou accès refusé',
            'debug' => ['incidentId' => $incidentId, 'teacherId' => $teacherId]
        ]);
        exit();
    }
    
    $incident = $result->fetch_assoc();
    
    // Debug - vérifier les données retournées
    error_log("Données retournées: " . print_r($incident, true));
    
    echo json_encode([
        'success' => true,
        'incident' => $incident,
        'debug' => ['query' => $query, 'params' => [$incidentId, $teacherId]]
    ]);

} catch (Exception $e) {
    error_log("Erreur dans get_incident_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => $e->getMessage()
    ]);
}