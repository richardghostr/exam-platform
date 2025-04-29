<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';
include_once '../includes/db.php';

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

// Traiter les différentes méthodes HTTP
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Récupérer les examens
        handleGetRequest();
        break;
        
    case 'POST':
        // Créer un examen
        handlePostRequest();
        break;
        
    case 'PUT':
        // Mettre à jour un examen
        handlePutRequest();
        break;
        
    case 'DELETE':
        // Supprimer un examen
        handleDeleteRequest();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
}

/**
 * Gère les requêtes GET
 */
function handleGetRequest() {
    // Vérifier si un ID d'examen est fourni
    if (isset($_GET['id'])) {
        // Récupérer un examen spécifique
        getExam($_GET['id']);
    } else {
        // Récupérer la liste des examens
        getExams();
    }
}

/**
 * Récupère un examen spécifique
 * 
 * @param int $exam_id ID de l'examen
 */
function getExam($exam_id) {
    // Vérifier si l'ID est valide
    if (!is_numeric($exam_id) || $exam_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID d\'examen invalide']);
        return;
    }
    
    // Déterminer les permissions en fonction du rôle
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Construire la requête en fonction du rôle
    if ($role === 'admin') {
        // Les administrateurs peuvent voir tous les examens
        $query = "
            SELECT e.*, u.username as creator_name, u.full_name as creator_full_name,
                   (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) as enrollment_count,
                   (SELECT COUNT(*) FROM exam_attempts ea JOIN exam_enrollments ee ON ea.enrollment_id = ee.id WHERE ee.exam_id = e.id) as attempt_count
            FROM exams e
            JOIN users u ON e.created_by = u.id
            WHERE e.id = ?
        ";
        $params = [$exam_id];
    } elseif ($role === 'teacher') {
        // Les enseignants peuvent voir leurs propres examens
        $query = "
            SELECT e.*, u.username as creator_name, u.full_name as creator_full_name,
                   (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) as enrollment_count,
                   (SELECT COUNT(*) FROM exam_attempts ea JOIN exam_enrollments ee ON ea.enrollment_id = ee.id WHERE ee.exam_id = e.id) as attempt_count
            FROM exams e
            JOIN users u ON e.created_by = u.id
            WHERE e.id = ? AND e.created_by = ?
        ";
        $params = [$exam_id, $user_id];
    } else {
        // Les étudiants peuvent voir les examens auxquels ils sont inscrits
        $query = "
            SELECT e.*, u.username as creator_name, u.full_name as creator_full_name,
                   ee.id as enrollment_id, ee.status as enrollment_status,
                   (SELECT COUNT(*) FROM exam_attempts WHERE enrollment_id = ee.id) as attempt_count
            FROM exams e
            JOIN users u ON e.created_by = u.id
            JOIN exam_enrollments ee ON e.id = ee.exam_id
            WHERE e.id = ? AND ee.student_id = ? AND e.status = 'published'
        ";
        $params = [$exam_id, $user_id];
    }
    
    // Exécuter la requête
    $exam = db_fetch_row($query, $params);
    
    if (!$exam) {
        http_response_code(404);
        echo json_encode(['error' => 'Examen non trouvé']);
        return;
    }
    
    // Récupérer les questions si l'utilisateur est un enseignant ou un administrateur
    if ($role === 'admin' || ($role === 'teacher' && $exam['created_by'] == $user_id)) {
        $questions_query = "
            SELECT q.*, GROUP_CONCAT(ao.id, ':::', ao.option_text, ':::', ao.is_correct SEPARATOR '|||') as options
            FROM questions q
            LEFT JOIN answer_options ao ON q.id = ao.question_id
            WHERE q.exam_id = ?
            GROUP BY q.id
            ORDER BY q.id
        ";
        
        $questions = db_fetch_all($questions_query, [$exam_id]);
        $exam['questions'] = $questions;
    }
    
    // Récupérer les paramètres de surveillance
    if ($role === 'admin' || ($role === 'teacher' && $exam['created_by'] == $user_id)) {
        $proctoring_query = "
            SELECT *
            FROM proctoring_settings
            WHERE exam_id = ?
        ";
        
        $proctoring = db_fetch_row($proctoring_query, [$exam_id]);
        $exam['proctoring_settings'] = $proctoring;
    }
    
    // Retourner l'examen
    echo json_encode(['success' => true, 'exam' => $exam]);
}

/**
 * Récupère la liste des examens
 */
function getExams() {
    // Récupérer les paramètres de pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    // Récupérer les paramètres de filtrage
    $filter = isset($_GET['filter']) ? $_GET['filter'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;
    
    // Déterminer les permissions en fonction du rôle
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Construire la requête en fonction du rôle
    if ($role === 'admin') {
        // Les administrateurs peuvent voir tous les examens
        $query = "
            SELECT e.*, u.username as creator_name, u.full_name as creator_full_name,
                   (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) as enrollment_count,
                   (SELECT COUNT(*) FROM exam_attempts ea JOIN exam_enrollments ee ON ea.enrollment_id = ee.id WHERE ee.exam_id = e.id) as attempt_count
            FROM exams e
            JOIN users u ON e.created_by = u.id
        ";
        $count_query = "SELECT COUNT(*) as total FROM exams";
        $params = [];
        $count_params = [];
    } elseif ($role === 'teacher') {
        // Les enseignants peuvent voir leurs propres examens
        $query = "
            SELECT e.*, u.username as creator_name, u.full_name as creator_full_name,
                   (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) as enrollment_count,
                   (SELECT COUNT(*) FROM exam_attempts ea JOIN exam_enrollments ee ON ea.enrollment_id = ee.id WHERE ee.exam_id = e.id) as attempt_count
            FROM exams e
            JOIN users u ON e.created_by = u.id
            WHERE e.created_by = ?
        ";
        $count_query = "SELECT COUNT(*) as total FROM exams WHERE created_by = ?";
        $params = [$user_id];
        $count_params = [$user_id];
    } else {
        // Les étudiants peuvent voir les examens auxquels ils sont inscrits ou qui sont disponibles
        $query = "
            SELECT e.*, u.username as creator_name, u.full_name as creator_full_name,
                   ee.id as enrollment_id, ee.status as enrollment_status,
                   (SELECT COUNT(*) FROM exam_attempts WHERE enrollment_id = ee.id) as attempt_count
            FROM exams e
            JOIN users u ON e.created_by = u.id
            LEFT JOIN exam_enrollments ee ON e.id = ee.exam_id AND ee.student_id = ?
            WHERE (ee.id IS NOT NULL OR e.status = 'published')
        ";
        $count_query = "
            SELECT COUNT(*) as total
            FROM exams e
            LEFT JOIN exam_enrollments ee ON e.id = ee.exam_id AND ee.student_id = ?
            WHERE (ee.id IS NOT NULL OR e.status = 'published')
        ";
        $params = [$user_id];
        $count_params = [$user_id];
    }
    
    // Ajouter les filtres
    if ($filter) {
        switch ($filter) {
            case 'upcoming':
                $query .= " AND e.start_time > NOW()";
                $count_query .= " AND e.start_time > NOW()";
                break;
                
            case 'current':
                $query .= " AND NOW() BETWEEN e.start_time AND e.end_time";
                $count_query .= " AND NOW() BETWEEN e.start_time AND e.end_time";
                break;
                
            case 'past':
                $query .= " AND e.end_time < NOW()";
                $count_query .= " AND e.end_time < NOW()";
                break;
                
            case 'published':
                $query .= " AND e.status = 'published'";
                $count_query .= " AND e.status = 'published'";
                break;
                
            case 'draft':
                $query .= " AND e.status = 'draft'";
                $count_query .= " AND e.status = 'draft'";
                break;
        }
    }
    
    // Ajouter la recherche
    if ($search) {
        $search_term = '%' . $search . '%';
        $query .= " AND (e.title LIKE ? OR e.description LIKE ?)";
        $count_query .= " AND (e.title LIKE ? OR e.description LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    // Ajouter l'ordre et la pagination
    $query .= " ORDER BY e.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Exécuter les requêtes
    $exams = db_fetch_all($query, $params);
    $count_result = db_fetch_row($count_query, $count_params);
    $total = $count_result['total'];
    
    // Calculer la pagination
    $total_pages = ceil($total / $limit);
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'exams' => $exams,
        'pagination' => [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $total_pages
        ]
    ]);
}

/**
 * Gère les requêtes POST
 */
function handlePostRequest() {
    // Vérifier si l'utilisateur a le droit de créer un examen
    if (!has_role(['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Récupérer les données JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Vérifier si les données sont valides
    if (!$data || !isset($data['title']) || !isset($data['description']) || !isset($data['duration'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    // Valider les données
    if (empty($data['title']) || empty($data['description']) || !is_numeric($data['duration']) || $data['duration'] <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    // Préparer les données de l'examen
    $exam_data = [
        'title' => $data['title'],
        'description' => $data['description'],
        'duration' => $data['duration'],
        'start_time' => isset($data['start_time']) ? $data['start_time'] : null,
        'end_time' => isset($data['end_time']) ? $data['end_time'] : null,
        'passing_score' => isset($data['passing_score']) ? $data['passing_score'] : 60,
        'status' => isset($data['status']) ? $data['status'] : 'draft',
        'created_by' => $_SESSION['user_id'],
        'proctoring_level' => isset($data['proctoring_level']) ? $data['proctoring_level'] : 'standard'
    ];
    
    // In  => isset($data['proctoring_level']) ? $data['proctoring_level'] : 'standard'
    
    // Insérer l'examen dans la base de données
    $query = "
        INSERT INTO exams (title, description, duration, start_time, end_time, passing_score, status, created_by, proctoring_level)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $params = [
        $exam_data['title'],
        $exam_data['description'],
        $exam_data['duration'],
        $exam_data['start_time'],
        $exam_data['end_time'],
        $exam_data['passing_score'],
        $exam_data['status'],
        $exam_data['created_by'],
        $exam_data['proctoring_level']
    ];
    
    $exam_id = db_insert($query, $params);
    
    if (!$exam_id) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la création de l\'examen']);
        return;
    }
    
    // Traiter les paramètres de surveillance si fournis
    if (isset($data['proctoring_settings'])) {
        $proctoring_settings = $data['proctoring_settings'];
        
        $proctoring_query = "
            INSERT INTO proctoring_settings (
                exam_id, face_detection, eye_tracking, audio_monitoring, 
                screen_monitoring, browser_lockdown, face_detection_sensitivity,
                eye_tracking_sensitivity, audio_monitoring_sensitivity
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $proctoring_params = [
            $exam_id,
            isset($proctoring_settings['face_detection']) ? (int)$proctoring_settings['face_detection'] : 1,
            isset($proctoring_settings['eye_tracking']) ? (int)$proctoring_settings['eye_tracking'] : 1,
            isset($proctoring_settings['audio_monitoring']) ? (int)$proctoring_settings['audio_monitoring'] : 1,
            isset($proctoring_settings['screen_monitoring']) ? (int)$proctoring_settings['screen_monitoring'] : 1,
            isset($proctoring_settings['browser_lockdown']) ? (int)$proctoring_settings['browser_lockdown'] : 1,
            isset($proctoring_settings['face_detection_sensitivity']) ? $proctoring_settings['face_detection_sensitivity'] : 'medium',
            isset($proctoring_settings['eye_tracking_sensitivity']) ? $proctoring_settings['eye_tracking_sensitivity'] : 'medium',
            isset($proctoring_settings['audio_monitoring_sensitivity']) ? $proctoring_settings['audio_monitoring_sensitivity'] : 'medium'
        ];
        
        db_insert($proctoring_query, $proctoring_params);
    }
    
    // Traiter les questions si fournies
    if (isset($data['questions']) && is_array($data['questions'])) {
        foreach ($data['questions'] as $question) {
            // Vérifier si les données de la question sont valides
            if (!isset($question['question_text']) || !isset($question['question_type']) || !isset($question['points'])) {
                continue;
            }
            
            // Insérer la question
            $question_query = "
                INSERT INTO questions (exam_id, question_text, question_type, points)
                VALUES (?, ?, ?, ?)
            ";
            
            $question_params = [
                $exam_id,
                $question['question_text'],
                $question['question_type'],
                $question['points']
            ];
            
            $question_id = db_insert($question_query, $question_params);
            
            if (!$question_id) {
                continue;
            }
            
            // Traiter les options de réponse pour les QCM
            if (($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') && 
                isset($question['options']) && is_array($question['options'])) {
                
                foreach ($question['options'] as $option) {
                    // Vérifier si les données de l'option sont valides
                    if (!isset($option['option_text'])) {
                        continue;
                    }
                    
                    // Insérer l'option
                    $option_query = "
                        INSERT INTO answer_options (question_id, option_text, is_correct)
                        VALUES (?, ?, ?)
                    ";
                    
                    $option_params = [
                        $question_id,
                        $option['option_text'],
                        isset($option['is_correct']) ? (int)$option['is_correct'] : 0
                    ];
                    
                    db_insert($option_query, $option_params);
                }
            }
        }
    }
    
    // Retourner l'ID de l'examen créé
    echo json_encode(['success' => true, 'exam_id' => $exam_id]);
}

/**
 * Gère les requêtes PUT
 */
function handlePutRequest() {
    // Vérifier si l'utilisateur a le droit de modifier un examen
    if (!has_role(['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Récupérer les données JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Vérifier si les données sont valides
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    $exam_id = $data['id'];
    
    // Vérifier si l'examen existe et si l'utilisateur a le droit de le modifier
    $check_query = "SELECT created_by FROM exams WHERE id = ?";
    $exam = db_fetch_row($check_query, [$exam_id]);
    
    if (!$exam) {
        http_response_code(404);
        echo json_encode(['error' => 'Examen non trouvé']);
        return;
    }
    
    // Vérifier si l'utilisateur est le créateur de l'examen ou un administrateur
    if ($exam['created_by'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Préparer les données à mettre à jour
    $update_fields = [];
    $params = [];
    
    if (isset($data['title'])) {
        $update_fields[] = "title = ?";
        $params[] = $data['title'];
    }
    
    if (isset($data['description'])) {
        $update_fields[] = "description = ?";
        $params[] = $data['description'];
    }
    
    if (isset($data['duration'])) {
        $update_fields[] = "duration = ?";
        $params[] = $data['duration'];
    }
    
    if (isset($data['start_time'])) {
        $update_fields[] = "start_time = ?";
        $params[] = $data['start_time'];
    }
    
    if (isset($data['end_time'])) {
        $update_fields[] = "end_time = ?";
        $params[] = $data['end_time'];
    }
    
    if (isset($data['passing_score'])) {
        $update_fields[] = "passing_score = ?";
        $params[] = $data['passing_score'];
    }
    
    if (isset($data['status'])) {
        $update_fields[] = "status = ?";
        $params[] = $data['status'];
    }
    
    if (isset($data['proctoring_level'])) {
        $update_fields[] = "proctoring_level = ?";
        $params[] = $data['proctoring_level'];
    }
    
    // Mettre à jour l'examen si des champs sont à modifier
    if (!empty($update_fields)) {
        $query = "UPDATE exams SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $params[] = $exam_id;
        
        $result = db_update($query, $params);
        
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la mise à jour de l\'examen']);
            return;
        }
    }
    
    // Mettre à jour les paramètres de surveillance si fournis
    if (isset($data['proctoring_settings'])) {
        $proctoring_settings = $data['proctoring_settings'];
        
        // Vérifier si des paramètres existent déjà
        $check_proctoring_query = "SELECT id FROM proctoring_settings WHERE exam_id = ?";
        $existing_proctoring = db_fetch_row($check_proctoring_query, [$exam_id]);
        
        if ($existing_proctoring) {
            // Mettre à jour les paramètres existants
            $proctoring_query = "
                UPDATE proctoring_settings SET
                    face_detection = ?,
                    eye_tracking = ?,
                    audio_monitoring = ?,
                    screen_monitoring = ?,
                    browser_lockdown = ?,
                    face_detection_sensitivity = ?,
                    eye_tracking_sensitivity = ?,
                    audio_monitoring_sensitivity = ?
                WHERE exam_id = ?
            ";
            
            $proctoring_params = [
                isset($proctoring_settings['face_detection']) ? (int)$proctoring_settings['face_detection'] : 1,
                isset($proctoring_settings['eye_tracking']) ? (int)$proctoring_settings['eye_tracking'] : 1,
                isset($proctoring_settings['audio_monitoring']) ? (int)$proctoring_settings['audio_monitoring'] : 1,
                isset($proctoring_settings['screen_monitoring']) ? (int)$proctoring_settings['screen_monitoring'] : 1,
                isset($proctoring_settings['browser_lockdown']) ? (int)$proctoring_settings['browser_lockdown'] : 1,
                isset($proctoring_settings['face_detection_sensitivity']) ? $proctoring_settings['face_detection_sensitivity'] : 'medium',
                isset($proctoring_settings['eye_tracking_sensitivity']) ? $proctoring_settings['eye_tracking_sensitivity'] : 'medium',
                isset($proctoring_settings['audio_monitoring_sensitivity']) ? $proctoring_settings['audio_monitoring_sensitivity'] : 'medium',
                $exam_id
            ];
            
            db_update($proctoring_query, $proctoring_params);
        } else {
            // Insérer de nouveaux paramètres
            $proctoring_query = "
                INSERT INTO proctoring_settings (
                    exam_id, face_detection, eye_tracking, audio_monitoring, 
                    screen_monitoring, browser_lockdown, face_detection_sensitivity,
                    eye_tracking_sensitivity, audio_monitoring_sensitivity
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $proctoring_params = [
                $exam_id,
                isset($proctoring_settings['face_detection']) ? (int)$proctoring_settings['face_detection'] : 1,
                isset($proctoring_settings['eye_tracking']) ? (int)$proctoring_settings['eye_tracking'] : 1,
                isset($proctoring_settings['audio_monitoring']) ? (int)$proctoring_settings['audio_monitoring'] : 1,
                isset($proctoring_settings['screen_monitoring']) ? (int)$proctoring_settings['screen_monitoring'] : 1,
                isset($proctoring_settings['browser_lockdown']) ? (int)$proctoring_settings['browser_lockdown'] : 1,
                isset($proctoring_settings['face_detection_sensitivity']) ? $proctoring_settings['face_detection_sensitivity'] : 'medium',
                isset($proctoring_settings['eye_tracking_sensitivity']) ? $proctoring_settings['eye_tracking_sensitivity'] : 'medium',
                isset($proctoring_settings['audio_monitoring_sensitivity']) ? $proctoring_settings['audio_monitoring_sensitivity'] : 'medium'
            ];
            
            db_insert($proctoring_query, $proctoring_params);
        }
    }
    
    // Retourner un succès
    echo json_encode(['success' => true]);
}

/**
 * Gère les requêtes DELETE
 */
function handleDeleteRequest() {
    // Vérifier si l'utilisateur a le droit de supprimer un examen
    if (!has_role(['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Récupérer les données JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Vérifier si les données sont valides
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    $exam_id = $data['id'];
    
    // Vérifier si l'examen existe et si l'utilisateur a le droit de le supprimer
    $check_query = "SELECT created_by FROM exams WHERE id = ?";
    $exam = db_fetch_row($check_query, [$exam_id]);
    
    if (!$exam) {
        http_response_code(404);
        echo json_encode(['error' => 'Examen non trouvé']);
        return;
    }
    
    // Vérifier si l'utilisateur est le créateur de l'examen ou un administrateur
    if ($exam['created_by'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Supprimer l'examen et toutes ses données associées
    // Note: Cela suppose que les contraintes de clé étrangère sont définies avec ON DELETE CASCADE
    $query = "DELETE FROM exams WHERE id = ?";
    $result = db_update($query, [$exam_id]);
    
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la suppression de l\'examen']);
        return;
    }
    
    // Retourner un succès
    echo json_encode(['success' => true]);
}
