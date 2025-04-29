<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
require_login('../login.php');

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Vérifier si les données sont valides
if (!$data || !isset($data['action']) || !isset($data['attempt_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

// Connexion à la base de données
include_once '../includes/db.php';

// Vérifier si la tentative existe et appartient à l'utilisateur
$attempt_query = "
    SELECT ea.id
    FROM exam_attempts ea
    JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
    WHERE ea.id = ? AND ee.student_id = ?
";

$stmt = $conn->prepare($attempt_query);
$stmt->bind_param("ii", $data['attempt_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

// Traiter l'action
switch ($data['action']) {
    case 'incident':
        // Enregistrer un incident de surveillance
        if (!isset($data['incident']) || !isset($data['incident']['type']) || !isset($data['incident']['severity'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données d\'incident invalides']);
            break;
        }
        
        $incident_type = $data['incident']['type'];
        $severity = $data['incident']['severity'];
        $details = isset($data['incident']['details']) ? $data['incident']['details'] : '';
        $timestamp = date('Y-m-d H:i:s');
        
        $insert_query = "
            INSERT INTO proctoring_incidents (attempt_id, incident_type, severity, timestamp, details)
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issss", $data['attempt_id'], $incident_type, $severity, $timestamp, $details);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'incident_id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de l\'enregistrement de l\'incident']);
        }
        
        $stmt->close();
        break;
        
    case 'warning':
        // Enregistrer un avertissement (type d'incident spécifique)
        if (!isset($data['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Message d\'avertissement manquant']);
            break;
        }
        
        $incident_type = 'warning';
        $severity = 'medium';
        $details = $data['message'];
        $timestamp = date('Y-m-d H:i:s');
        
        $insert_query = "
            INSERT INTO proctoring_incidents (attempt_id, incident_type, severity, timestamp, details)
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issss", $data['attempt_id'], $incident_type, $severity, $timestamp, $details);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'incident_id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de l\'enregistrement de l\'avertissement']);
        }
        
        $stmt->close();
        break;
        
    case 'heartbeat':
        // Mettre à jour le statut de la tentative (pour indiquer que l'étudiant est toujours actif)
        $update_query = "
            UPDATE exam_attempts
            SET last_activity = NOW()
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $data['attempt_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la mise à jour du statut']);
        }
        
        $stmt->close();
        break;
        
    case 'screenshot':
        // Enregistrer une capture d'écran (pour les incidents graves)
        if (!isset($data['image']) || !isset($data['incident_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données de capture d\'écran invalides']);
            break;
        }
        
        // Décoder l'image base64
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['image']));
        
        // Générer un nom de fichier unique
        $filename = 'screenshot_' . $data['attempt_id'] . '_' . $data['incident_id'] . '_' . time() . '.png';
        $filepath = '../uploads/screenshots/' . $filename;
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists('../uploads/screenshots/')) {
            mkdir('../uploads/screenshots/', 0755, true);
        }
        
        // Enregistrer l'image
        if (file_put_contents($filepath, $image_data)) {
            // Mettre à jour l'incident avec le chemin de la capture d'écran
            $update_query = "
                UPDATE proctoring_incidents
                SET screenshot_path = ?
                WHERE id = ? AND attempt_id = ?
            ";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sii", $filename, $data['incident_id'], $data['attempt_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erreur lors de la mise à jour de l\'incident']);
            }
            
            $stmt->close();
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de l\'enregistrement de la capture d\'écran']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non reconnue']);
}

// Fermer la connexion à la base de données
$conn->close();
