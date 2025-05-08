<?php
/**
 * Script AJAX pour signaler automatiquement les incidents de surveillance
 * Ce script est appelé automatiquement lorsque le système de surveillance détecte un comportement suspect
 */

// Inclure les fichiers nécessaires
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Initialiser la réponse
$response = [
    'success' => false,
    'message' => 'Une erreur est survenue'
];

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $response['message'] = 'Vous devez être connecté pour effectuer cette action';
    echo json_encode($response);
    exit();
}

// Vérifier si la requête est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Méthode non autorisée';
    echo json_encode($response);
    exit();
}

try {
    // Récupérer les données de base
    $attemptId = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : $_SESSION['user_id'];
    
    // Vérifier si on traite un incident unique ou un lot d'incidents
    $isBatch = isset($_POST['incidents']) && is_array($_POST['incidents']);
    
    if ($isBatch) {
        // Traitement par lot d'incidents
        $incidents = $_POST['incidents'];
        $successCount = 0;
        $failCount = 0;
        $incidentIds = [];
        
        foreach ($incidents as $incident) {
            $result = processIncident($incident, $attemptId, $userId, $conn);
            if ($result['success']) {
                $successCount++;
                $incidentIds[] = $result['incident_id'];
            } else {
                $failCount++;
            }
        }
        
        $response = [
            'success' => $successCount > 0,
            'message' => "Traitement terminé: $successCount incidents enregistrés, $failCount échecs",
            'incident_ids' => $incidentIds
        ];
    } else {
        // Traitement d'un incident unique
        $incidentData = [
            'incident_type' => isset($_POST['incident_type']) ? $_POST['incident_type'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'image_data' => isset($_POST['image_data']) ? $_POST['image_data'] : null,
            'exam_id' => isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0,
            'details' => isset($_POST['details']) ? $_POST['details'] : null
        ];
        
        $result = processIncident($incidentData, $attemptId, $userId, $conn);
        $response = $result;
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ];
}

// Retourner la réponse
echo json_encode($response);

/**
 * Traite un incident individuel
 * 
 * @param array $incidentData Les données de l'incident
 * @param int $attemptId L'ID de la tentative d'examen
 * @param int $userId L'ID de l'utilisateur
 * @param mysqli $conn La connexion à la base de données
 * @return array Résultat du traitement
 */
function processIncident($incidentData, $attemptId, $userId, $conn) {
    // Vérifier les données minimales requises
    if ($attemptId <= 0 || empty($incidentData['incident_type']) || empty($incidentData['description'])) {
        return [
            'success' => false,
            'message' => 'Données d\'incident incomplètes'
        ];
    }
    
    // Récupérer l'ID de l'examen si non fourni
    $examId = $incidentData['exam_id'] > 0 ? $incidentData['exam_id'] : getExamIdFromAttempt($attemptId, $conn);
    if (!$examId) {
        return [
            'success' => false,
            'message' => 'Impossible de déterminer l\'examen associé'
        ];
    }
    
    // Déterminer la sévérité de l'incident
    $severity = determineSeverity($incidentData['incident_type'], $incidentData['description']);
    
    // Traiter l'image si elle est fournie
    $imagePath = null;
    if (!empty($incidentData['image_data'])) {
        $imagePath = saveIncidentImage($incidentData['image_data'], $attemptId, $userId, $incidentData['incident_type']);
    }
    
    // Préparer les détails supplémentaires
    $details = !empty($incidentData['details']) ? $incidentData['details'] : json_encode([
        'browser' => $_SERVER['HTTP_USER_AGENT'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        'timestamp' => date('Y-m-d H:i:s'),
        'automatic' => true
    ]);
    
    // Enregistrer l'incident dans la base de données
    $timestamp = date('Y-m-d H:i:s');
    $insertIncidentQuery = $conn->prepare("
        INSERT INTO proctoring_incidents (
            attempt_id, exam_id, user_id, incident_type, 
            severity, description, status, timestamp, 
            created_at, image_path, details, resolved, reviewed
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, 'pending', ?, 
            NOW(), ?, ?, 0, 0
        )
    ");
    
    if (!$insertIncidentQuery) {
        return [
            'success' => false,
            'message' => 'Erreur de préparation de la requête: ' . $conn->error
        ];
    }
    
    $status = 'pending';
    $insertIncidentQuery->bind_param(
        "iiissssss",
        $attemptId, $examId, $userId, $incidentData['incident_type'],
        $severity, $incidentData['description'], $timestamp,
        $imagePath, $details
    );
    
    if (!$insertIncidentQuery->execute()) {
        return [
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement de l\'incident: ' . $insertIncidentQuery->error
        ];
    }
    
    $incidentId = $conn->insert_id;
    
    // Mettre à jour le compteur d'incidents pour la session d'examen
    updateIncidentCount($examId, $userId, $conn);
    
    // Vérifier si le nombre d'incidents dépasse un seuil critique
    checkIncidentThreshold($examId, $userId, $conn);
    
    return [
        'success' => true,
        'message' => 'Incident enregistré avec succès',
        'incident_id' => $incidentId,
        'severity' => $severity
    ];
}

/**
 * Récupère l'ID de l'examen à partir de l'ID de tentative
 */
function getExamIdFromAttempt($attemptId, $conn) {
    $examQuery = $conn->prepare("
        SELECT exam_id FROM exam_attempts 
        WHERE id = ?
    ");
    
    if (!$examQuery) {
        return 0;
    }
    
    $examQuery->bind_param("i", $attemptId);
    $examQuery->execute();
    $examResult = $examQuery->get_result();
    
    if ($examResult->num_rows > 0) {
        return $examResult->fetch_assoc()['exam_id'];
    }
    
    return 0;
}

/**
 * Met à jour le compteur d'incidents pour la session d'examen
 */
function updateIncidentCount($examId, $userId, $conn) {
    $updateSessionQuery = $conn->prepare("
        UPDATE exam_sessions 
        SET incident_count = incident_count + 1 
        WHERE exam_id = ? AND user_id = ? AND status = 'in_progress'
    ");
    
    if (!$updateSessionQuery) {
        return false;
    }
    
    $updateSessionQuery->bind_param("ii", $examId, $userId);
    return $updateSessionQuery->execute();
}

/**
 * Vérifie si le nombre d'incidents dépasse un seuil critique
 */
function checkIncidentThreshold($examId, $userId, $conn) {
    // Récupérer le nombre actuel d'incidents
    $checkThresholdQuery = $conn->prepare("
        SELECT incident_count FROM exam_sessions 
        WHERE exam_id = ? AND user_id = ? AND status = 'in_progress'
    ");
    
    if (!$checkThresholdQuery) {
        return false;
    }
    
    $checkThresholdQuery->bind_param("ii", $examId, $userId);
    $checkThresholdQuery->execute();
    $thresholdResult = $checkThresholdQuery->get_result();
    
    if ($thresholdResult->num_rows > 0) {
        $incidentCount = $thresholdResult->fetch_assoc()['incident_count'];
        
        // Récupérer le seuil d'incidents depuis les paramètres
        $thresholdQuery = $conn->query("
            SELECT value FROM settings 
            WHERE setting_key = 'incident_threshold'
        ");
        
        $threshold = $thresholdQuery && $thresholdQuery->num_rows > 0 
            ? intval($thresholdQuery->fetch_assoc()['value']) 
            : 5;
        
        // Si le nombre d'incidents dépasse le seuil, notifier l'enseignant
        if ($incidentCount >= $threshold) {
            notifyTeacher($examId, $userId, $incidentCount, $conn);
            return true;
        }
    }
    
    return false;
}

/**
 * Détermine la sévérité d'un incident en fonction de son type et de sa description
 */
function determineSeverity($type, $description) {
    // Incidents de haute sévérité
    $highSeverityKeywords = [
        'multiple faces', 'plusieurs visages', 'no face', 'aucun visage',
        'phone', 'téléphone', 'device', 'appareil', 'cheating', 'triche',
        'book', 'livre', 'notes', 'talking', 'parle', 'conversation',
        'object detected', 'objet détecté', 'multiple persons', 'plusieurs personnes'
    ];
    
    // Incidents de sévérité moyenne
    $mediumSeverityKeywords = [
        'looking away', 'regarde ailleurs', 'distracted', 'distrait',
        'movement', 'mouvement', 'noise', 'bruit', 'audio', 'sound', 'son',
        'tab change', 'changement d\'onglet', 'window', 'fenêtre'
    ];
    
    // Types d'incidents à haute sévérité
    $highSeverityTypes = ['face_missing', 'multiple_faces', 'object_detected', 'phone_detected'];
    
    // Types d'incidents à sévérité moyenne
    $mediumSeverityTypes = ['face_looking_away', 'audio_detected', 'tab_change'];
    
    // Vérifier le type d'incident
    if (in_array($type, $highSeverityTypes)) {
        return 'high';
    }
    
    if (in_array($type, $mediumSeverityTypes)) {
        return 'medium';
    }
    
    // Vérifier les mots-clés de haute sévérité
    foreach ($highSeverityKeywords as $keyword) {
        if (stripos($description, $keyword) !== false) {
            return 'high';
        }
    }
    
    // Vérifier les mots-clés de sévérité moyenne
    foreach ($mediumSeverityKeywords as $keyword) {
        if (stripos($description, $keyword) !== false) {
            return 'medium';
        }
    }
    
    // Par défaut, sévérité faible
    return 'low';
}

/**
 * Sauvegarde une image d'incident à partir de données base64
 */
function saveIncidentImage($imageData, $attemptId, $userId, $incidentType) {
    // Vérifier si les données commencent par "data:image"
    if (strpos($imageData, 'data:image') !== 0) {
        return null;
    }
    
    // Extraire le type MIME et les données
    $parts = explode(';base64,', $imageData);
    if (count($parts) != 2) {
        return null;
    }
    
    $mimeType = str_replace('data:', '', $parts[0]);
    $imageData = base64_decode($parts[1]);
    
    if ($imageData === false) {
        return null;
    }
    
    // Créer le dossier de stockage s'il n'existe pas
    $uploadDir = '../uploads/proctoring/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Générer un nom de fichier unique
    $timestamp = time();
    $filename = "incident_{$attemptId}_{$userId}_{$incidentType}_{$timestamp}.jpg";
    $filePath = $uploadDir . $filename;
    
    // Sauvegarder l'image
    if (file_put_contents($filePath, $imageData)) {
        // Retourner le chemin relatif pour stockage en base de données
        return 'uploads/proctoring/' . $filename;
    }
    
    return null;
}

/**
 * Notifie l'enseignant d'un nombre élevé d'incidents
 */
function notifyTeacher($examId, $studentId, $incidentCount, $conn) {
    // Récupérer l'ID de l'enseignant responsable de l'examen
    $teacherQuery = $conn->prepare("
        SELECT teacher_id, title FROM exams 
        WHERE id = ?
    ");
    
    if (!$teacherQuery) {
        return false;
    }
    
    $teacherQuery->bind_param("i", $examId);
    $teacherQuery->execute();
    $teacherResult = $teacherQuery->get_result();
    
    if ($teacherResult->num_rows > 0) {
        $examData = $teacherResult->fetch_assoc();
        $teacherId = $examData['teacher_id'];
        $examTitle = $examData['title'];
        
        if ($teacherId) {
            // Récupérer le nom de l'étudiant
            $studentQuery = $conn->prepare("
                SELECT CONCAT(first_name, ' ', last_name) as student_name 
                FROM users 
                WHERE id = ?
            ");
            
            if (!$studentQuery) {
                return false;
            }
            
            $studentQuery->bind_param("i", $studentId);
            $studentQuery->execute();
            $studentResult = $studentQuery->get_result();
            $studentName = $studentResult->num_rows > 0 
                ? $studentResult->fetch_assoc()['student_name'] 
                : "Étudiant #" . $studentId;
            
            // Créer une notification pour l'enseignant
            $notificationTitle = "Alerte de surveillance";
            $notificationMessage = "L'étudiant {$studentName} a accumulé {$incidentCount} incidents pendant l'examen '{$examTitle}'. Une vérification est recommandée.";
            
            $insertNotificationQuery = $conn->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    ?, ?, ?, 'warning', 0, NOW()
                )
            ");
            
            if (!$insertNotificationQuery) {
                return false;
            }
            
            $insertNotificationQuery->bind_param(
                "iss",
                $teacherId, $notificationTitle, $notificationMessage
            );
            
            return $insertNotificationQuery->execute();
        }
    }
    
    return false;
}
?>