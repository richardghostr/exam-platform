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
        // Récupérer les résultats
        handleGetRequest();
        break;
        
    case 'POST':
        // Soumettre un examen ou noter une réponse
        handlePostRequest();
        break;
        
    case 'PUT':
        // Mettre à jour une note
        handlePutRequest();
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
    if (isset($_GET['exam_id'])) {
        // Récupérer les résultats d'un examen spécifique
        getExamResults($_GET['exam_id']);
    } elseif (isset($_GET['attempt_id'])) {
        // Récupérer les résultats d'une tentative spécifique
        getAttemptResults($_GET['attempt_id']);
    } elseif (isset($_GET['student_id'])) {
        // Récupérer les résultats d'un étudiant spécifique
        getStudentResults($_GET['student_id']);
    } else {
        // Récupérer tous les résultats (pour les enseignants et administrateurs)
        getAllResults();
    }
}

/**
 * Récupère les résultats d'un examen spécifique
 * 
 * @param int $exam_id ID de l'examen
 */
function getExamResults($exam_id) {
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
        // Les administrateurs peuvent voir tous les résultats
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   u.id as student_id, u.username, u.full_name,
                   e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE e.id = ?
            ORDER BY ea.end_time DESC
        ";
        $params = [$exam_id];
    } elseif ($role === 'teacher') {
        // Les enseignants peuvent voir les résultats de leurs propres examens
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   u.id as student_id, u.username, u.full_name,
                   e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE e.id = ? AND e.created_by = ?
            ORDER BY ea.end_time DESC
        ";
        $params = [$exam_id, $user_id];
    } else {
        // Les étudiants peuvent voir leurs propres résultats
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE e.id = ? AND ee.student_id = ?
            ORDER BY ea.end_time DESC
        ";
        $params = [$exam_id, $user_id];
    }
    
    // Exécuter la requête
    $results = db_fetch_all($query, $params);
    
    // Retourner les résultats
    echo json_encode(['success' => true, 'results' => $results]);
}

/**
 * Récupère les résultats d'une tentative spécifique
 * 
 * @param int $attempt_id ID de la tentative
 */
function getAttemptResults($attempt_id) {
    // Vérifier si l'ID est valide
    if (!is_numeric($attempt_id) || $attempt_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de tentative invalide']);
        return;
    }
    
    // Déterminer les permissions en fonction du rôle
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Vérifier si l'utilisateur a le droit de voir cette tentative
    $check_query = "
        SELECT ea.id, e.created_by, ee.student_id, e.id as exam_id, e.title as exam_title
        FROM exam_attempts ea
        JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
        JOIN exams e ON ee.exam_id = e.id
        WHERE ea.id = ?
    ";
    
    $attempt = db_fetch_row($check_query, [$attempt_id]);
    
    if (!$attempt) {
        http_response_code(404);
        echo json_encode(['error' => 'Tentative non trouvée']);
        return;
    }
    
    // Vérifier les permissions
    if ($role === 'student' && $attempt['student_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    if ($role === 'teacher' && $attempt['created_by'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Récupérer les informations de la tentative
    $attempt_query = "
        SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
               u.id as student_id, u.username, u.full_name,
               e.title as exam_title, e.passing_score
        FROM exam_attempts ea
        JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
        JOIN users u ON ee.student_id = u.id
        JOIN exams e ON ee.exam_id = e.id
        WHERE ea.id = ?
    ";
    
    $attempt_info = db_fetch_row($attempt_query, [$attempt_id]);
    
    // Récupérer les réponses de la tentative
    $answers_query = "
        SELECT sa.question_id, sa.answer_text, sa.selected_option_id, sa.is_correct, sa.points_awarded,
               q.question_text, q.question_type, q.points,
               GROUP_CONCAT(ao.id, ':::', ao.option_text, ':::', ao.is_correct SEPARATOR '|||') as options
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        LEFT JOIN answer_options ao ON q.id = ao.question_id
        WHERE sa.attempt_id = ?
        GROUP BY sa.question_id
        ORDER BY q.id
    ";
    
    $answers = db_fetch_all($answers_query, [$attempt_id]);
    
    // Récupérer les incidents de surveillance
    $incidents_query = "
        SELECT pi.id, pi.incident_type, pi.severity, pi.timestamp, pi.details, pi.screenshot_path
        FROM proctoring_incidents pi
        WHERE pi.attempt_id = ?
        ORDER BY pi.timestamp DESC
    ";
    
    $incidents = db_fetch_all($incidents_query, [$attempt_id]);
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'attempt' => $attempt_info,
        'answers' => $answers,
        'incidents' => $incidents
    ]);
}

/**
 * Récupère les résultats d'un étudiant spécifique
 * 
 * @param int $student_id ID de l'étudiant
 */
function getStudentResults($student_id) {
    // Vérifier si l'ID est valide
    if (!is_numeric($student_id) || $student_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID d\'étudiant invalide']);
        return;
    }
    
    // Déterminer les permissions en fonction du rôle
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Vérifier les permissions
    if ($role === 'student' && $student_id != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Construire la requête en fonction du rôle
    if ($role === 'admin') {
        // Les administrateurs peuvent voir tous les résultats
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   e.id as exam_id, e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE ee.student_id = ?
            ORDER BY ea.end_time DESC
        ";
        $params = [$student_id];
    } elseif ($role === 'teacher') {
        // Les enseignants peuvent voir les résultats de leurs propres examens
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   e.id as exam_id, e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE ee.student_id = ? AND e.created_by = ?
            ORDER BY ea.end_time DESC
        ";
        $params = [$student_id, $user_id];
    } else {
        // Les étudiants peuvent voir leurs propres résultats
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   e.id as exam_id, e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE ee.student_id = ?
            ORDER BY ea.end_time DESC
        ";
        $params = [$student_id];
    }
    
    // Exécuter la requête
    $results = db_fetch_all($query, $params);
    
    // Récupérer les statistiques de l'étudiant
    $stats_query = "
        SELECT 
            COUNT(DISTINCT ea.id) as total_exams_taken,
            AVG(ea.score) as average_score,
            SUM(CASE WHEN ea.score >= e.passing_score THEN 1 ELSE 0 END) as exams_passed,
            SUM(CASE WHEN ea.score < e.passing_score THEN 1 ELSE 0 END) as exams_failed
        FROM exam_attempts ea
        JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
        JOIN exams e ON ee.exam_id = e.id
        WHERE ee.student_id = ? AND ea.status = 'completed'
    ";
    
    $stats = db_fetch_row($stats_query, [$student_id]);
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'results' => $results,
        'stats' => $stats
    ]);
}

/**
 * Récupère tous les résultats (pour les enseignants et administrateurs)
 */
function getAllResults() {
    // Vérifier si l'utilisateur a le droit de voir tous les résultats
    if (!has_role(['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Récupérer les paramètres de pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    // Récupérer les paramètres de filtrage
    $search = isset($_GET['search']) ? $_GET['search'] : null;
    
    // Déterminer les permissions en fonction du rôle
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Construire la requête en fonction du rôle
    if ($role === 'admin') {
        // Les administrateurs peuvent voir tous les résultats
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   u.id as student_id, u.username, u.full_name,
                   e.id as exam_id, e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
        ";
        $count_query = "
            SELECT COUNT(*) as total
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
        ";
        $params = [];
        $count_params = [];
    } else {
        // Les enseignants peuvent voir les résultats de leurs propres examens
        $query = "
            SELECT ea.id as attempt_id, ea.start_time, ea.end_time, ea.score, ea.status as attempt_status,
                   u.id as student_id, u.username, u.full_name,
                   e.id as exam_id, e.title as exam_title, e.passing_score
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE e.created_by = ?
        ";
        $count_query = "
            SELECT COUNT(*) as total
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE e.created_by = ?
        ";
        $count_query = "
            SELECT COUNT(*) as total
            FROM exam_attempts ea
            JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
            JOIN users u ON ee.student_id = u.id
            JOIN exams e ON ee.exam_id = e.id
            WHERE e.created_by = ?
        ";
        $params = [$user_id];
        $count_params = [$user_id];
    }
    
    // Ajouter la recherche
    if ($search) {
        $search_term = '%' . $search . '%';
        $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR e.title LIKE ?)";
        $count_query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR e.title LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    // Ajouter l'ordre et la pagination
    $query .= " ORDER BY ea.end_time DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Exécuter les requêtes
    $results = db_fetch_all($query, $params);
    $count_result = db_fetch_row($count_query, $count_params);
    $total = $count_result['total'];
    
    // Calculer la pagination
    $total_pages = ceil($total / $limit);
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'results' => $results,
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
    // Récupérer les données JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Vérifier si les données sont valides
    if (!$data || !isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    // Traiter l'action
    switch ($data['action']) {
        case 'submit_exam':
            // Soumettre un examen
            submitExam($data);
            break;
            
        case 'grade_answer':
            // Noter une réponse
            gradeAnswer($data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action non reconnue']);
    }
}

/**
 * Soumet un examen
 * 
 * @param array $data Données de soumission
 */
function submitExam($data) {
    // Vérifier si l'utilisateur est un étudiant
    if (!has_role('student')) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Vérifier si les données nécessaires sont présentes
    if (!isset($data['attempt_id']) || !isset($data['enrollment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        return;
    }
    
    $attempt_id = $data['attempt_id'];
    $enrollment_id = $data['enrollment_id'];
    $answers = isset($data['answers']) ? $data['answers'] : [];
    
    // Vérifier si la tentative existe et appartient à l'utilisateur
    $check_query = "
        SELECT ea.id, ea.status, ee.exam_id
        FROM exam_attempts ea
        JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
        WHERE ea.id = ? AND ee.id = ? AND ee.student_id = ?
    ";
    
    $attempt = db_fetch_row($check_query, [$attempt_id, $enrollment_id, $_SESSION['user_id']]);
    
    if (!$attempt) {
        http_response_code(404);
        echo json_encode(['error' => 'Tentative non trouvée']);
        return;
    }
    
    // Vérifier si la tentative est déjà terminée
    if ($attempt['status'] !== 'in_progress') {
        http_response_code(400);
        echo json_encode(['error' => 'La tentative est déjà terminée']);
        return;
    }
    
    // Récupérer les questions de l'examen
    $questions_query = "
        SELECT q.*, GROUP_CONCAT(ao.id, ':::', ao.is_correct SEPARATOR '|||') as options
        FROM questions q
        LEFT JOIN answer_options ao ON q.id = ao.question_id
        WHERE q.exam_id = ?
        GROUP BY q.id
    ";
    
    $questions = db_fetch_all($questions_query, [$attempt['exam_id']]);
    $questions_by_id = [];
    
    foreach ($questions as $question) {
        $questions_by_id[$question['id']] = $question;
    }
    
    // Traiter les réponses
    $total_points = 0;
    $earned_points = 0;
    $graded_questions = 0;
    
    foreach ($answers as $question_id => $answer) {
        $question = $questions_by_id[$question_id] ?? null;
        
        if (!$question) {
            continue;
        }
        
        $answer_text = null;
        $selected_option_id = null;
        $is_correct = null;
        $points_awarded = 0;
        
        // Ajouter les points de la question au total
        $total_points += $question['points'];
        
        // Traiter la réponse en fonction du type de question
        if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
            // Pour les QCM et vrai/faux
            $selected_option_id = intval($answer);
            
            // Vérifier si l'option sélectionnée est correcte
            if (!empty($question['options'])) {
                $options_data = explode('|||', $question['options']);
                foreach ($options_data as $option_data) {
                    $option_parts = explode(':::', $option_data);
                    if (count($option_parts) === 2 && intval($option_parts[0]) === $selected_option_id) {
                        $is_correct = $option_parts[1] == '1';
                        break;
                    }
                }
            }
            
            // Attribuer les points si la réponse est correcte
            if ($is_correct) {
                $points_awarded = $question['points'];
                $earned_points += $points_awarded;
            }
            
            $graded_questions++;
        } else {
            // Pour les réponses courtes et les essais
            $answer_text = $answer;
            
            // Ces questions nécessitent une évaluation manuelle
            // Elles seront notées plus tard par l'enseignant
        }
        
        // Enregistrer la réponse
        $insert_answer_query = "
            INSERT INTO student_answers (attempt_id, question_id, answer_text, selected_option_id, is_correct, points_awarded)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                answer_text = VALUES(answer_text),
                selected_option_id = VALUES(selected_option_id),
                is_correct = VALUES(is_correct),
                points_awarded = VALUES(points_awarded)
        ";
        
        db_query($insert_answer_query, [$attempt_id, $question_id, $answer_text, $selected_option_id, $is_correct, $points_awarded]);
    }
    
    // Calculer le score si toutes les questions ont été notées automatiquement
    $score = null;
    if ($graded_questions === count($questions)) {
        $score = ($total_points > 0) ? ($earned_points / $total_points) * 100 : 0;
    }
    
    // Mettre à jour le statut de la tentative
    $update_attempt_query = "
        UPDATE exam_attempts
        SET status = 'completed', end_time = NOW(), score = ?
        WHERE id = ?
    ";
    
    db_query($update_attempt_query, [$score, $attempt_id]);
    
    // Mettre à jour le statut de l'inscription
    $update_enrollment_query = "
        UPDATE exam_enrollments
        SET status = 'completed'
        WHERE id = ?
    ";
    
    db_query($update_enrollment_query, [$enrollment_id]);
    
    // Retourner un succès
    echo json_encode([
        'success' => true,
        'score' => $score,
        'total_points' => $total_points,
        'earned_points' => $earned_points
    ]);
}

/**
 * Note une réponse manuellement
 * 
 * @param array $data Données de notation
 */
function gradeAnswer($data) {
    // Vérifier si l'utilisateur a le droit de noter une réponse
    if (!has_role(['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Vérifier si les données nécessaires sont présentes
    if (!isset($data['attempt_id']) || !isset($data['question_id']) || !isset($data['points_awarded'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        return;
    }
    
    $attempt_id = $data['attempt_id'];
    $question_id = $data['question_id'];
    $points_awarded = $data['points_awarded'];
    $feedback = isset($data['feedback']) ? $data['feedback'] : null;
    
    // Vérifier si la tentative existe
    $check_query = "
        SELECT ea.id, ee.exam_id, e.created_by
        FROM exam_attempts ea
        JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
        JOIN exams e ON ee.exam_id = e.id
        WHERE ea.id = ?
    ";
    
    $attempt = db_fetch_row($check_query, [$attempt_id]);
    
    if (!$attempt) {
        http_response_code(404);
        echo json_encode(['error' => 'Tentative non trouvée']);
        return;
    }
    
    // Vérifier si l'utilisateur a le droit de noter cette tentative
    if ($_SESSION['role'] === 'teacher' && $attempt['created_by'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Vérifier si la question existe
    $question_query = "SELECT * FROM questions WHERE id = ? AND exam_id = ?";
    $question = db_fetch_row($question_query, [$question_id, $attempt['exam_id']]);
    
    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Question non trouvée']);
        return;
    }
    
    // Vérifier si la réponse existe
    $answer_query = "SELECT * FROM student_answers WHERE attempt_id = ? AND question_id = ?";
    $answer = db_fetch_row($answer_query, [$attempt_id, $question_id]);
    
    if (!$answer) {
        http_response_code(404);
        echo json_encode(['error' => 'Réponse non trouvée']);
        return;
    }
    
    // Mettre à jour la note
    $update_query = "
        UPDATE student_answers
        SET points_awarded = ?, feedback = ?
        WHERE attempt_id = ? AND question_id = ?
    ";
    
    db_query($update_query, [$points_awarded, $feedback, $attempt_id, $question_id]);
    
    // Recalculer le score total de la tentative
    $score_query = "
        SELECT 
            SUM(q.points) as total_points,
            SUM(sa.points_awarded) as earned_points
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.attempt_id = ?
    ";
    
    $score_result = db_fetch_row($score_query, [$attempt_id]);
    
    $total_points = $score_result['total_points'];
    $earned_points = $score_result['earned_points'];
    $score = ($total_points > 0) ? ($earned_points / $total_points) * 100 : 0;
    
    // Mettre à jour le score de la tentative
    $update_attempt_query = "
        UPDATE exam_attempts
        SET score = ?
        WHERE id = ?
    ";
    
    db_query($update_attempt_query, [$score, $attempt_id]);
    
    // Retourner un succès
    echo json_encode([
        'success' => true,
        'score' => $score,
        'total_points' => $total_points,
        'earned_points' => $earned_points
    ]);
}

/**
 * Gère les requêtes PUT
 */
function handlePutRequest() {
    // Vérifier si l'utilisateur a le droit de modifier une note
    if (!has_role(['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Récupérer les données JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    // Vérifier si les données sont valides
    if (!$data || !isset($data['attempt_id']) || !isset($data['score'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données invalides']);
        return;
    }
    
    $attempt_id = $data['attempt_id'];
    $score = $data['score'];
    
    // Vérifier si la tentative existe
    $check_query = "
        SELECT ea.id, e.created_by
        FROM exam_attempts ea
        JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
        JOIN exams e ON ee.exam_id = e.id
        WHERE ea.id = ?
    ";
    
    $attempt = db_fetch_row($check_query, [$attempt_id]);
    
    if (!$attempt) {
        http_response_code(404);
        echo json_encode(['error' => 'Tentative non trouvée']);
        return;
    }
    
    // Vérifier si l'utilisateur a le droit de modifier cette tentative
    if ($_SESSION['role'] === 'teacher' && $attempt['created_by'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        return;
    }
    
    // Mettre à jour le score
    $update_query = "
        UPDATE exam_attempts
        SET score = ?
        WHERE id = ?
    ";
    
    db_query($update_query, [$score, $attempt_id]);
    
    // Retourner un succès
    echo json_encode(['success' => true]);
}
