<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../student/includes/auth.php';

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

if (!$data || !isset($data['session_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$session_id = intval($data['session_id']);
$student_id = $_SESSION['user_id'];

// Vérifier que la session appartient à l'étudiant connecté et est en cours
$stmt = $conn->prepare("SELECT es.*, e.id as exam_id, e.auto_grade, e.total_points 
                        FROM exam_sessions es 
                        JOIN exams e ON es.exam_id = e.id 
                        WHERE es.id = ? AND es.student_id = ? AND es.status = 'in_progress'");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session non valide ou déjà terminée']);
    exit();
}

$session = $result->fetch_assoc();
$exam_id = $session['exam_id'];
$auto_grade = $session['auto_grade'];
$total_points = $session['total_points'];

// Commencer une transaction
$conn->begin_transaction();

try {
    // Marquer la session comme terminée
    $stmt = $conn->prepare("UPDATE exam_sessions SET status = 'completed', end_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $session_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de la finalisation de l'examen: " . $stmt->error);
    }
    
    // Si la correction automatique est activée, calculer le score
    if ($auto_grade) {
        // Récupérer les questions et les réponses
        $stmt = $conn->prepare("SELECT q.id, q.question_type, q.correct_answer, eq.points, ea.answer 
                                FROM questions q 
                                JOIN exam_questions eq ON q.id = eq.question_id 
                                LEFT JOIN exam_answers ea ON q.id = ea.question_id AND ea.session_id = ? 
                                WHERE eq.exam_id = ?");
        $stmt->bind_param("ii", $session_id, $exam_id);
        $stmt->execute();
        $questions_result = $stmt->get_result();
        
        $earned_points = 0;
        $needs_manual_grading = false;
        
        while ($question = $questions_result->fetch_assoc()) {
            // Vérifier si la réponse est correcte (pour les questions à correction automatique)
            if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'single_choice' || $question['question_type'] === 'true_false') {
                if ($question['answer'] === $question['correct_answer']) {
                    $earned_points += $question['points'];
                }
            } else {
                // Les questions de type essay et short_answer nécessitent une correction manuelle
                $needs_manual_grading = true;
            }
        }
        
        // Calculer le score en pourcentage
        $score_percentage = ($total_points > 0) ? ($earned_points / $total_points) * 100 : 0;
        
        // Enregistrer le score
        $stmt = $conn->prepare("UPDATE exam_sessions SET score = ?, max_score = ?, needs_grading = ?, score_percentage = ? WHERE id = ?");
        $stmt->bind_param("ddidd", $earned_points, $total_points, $needs_manual_grading, $score_percentage, $session_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de l'enregistrement du score: " . $stmt->error);
        }
    }
    
    // Enregistrer les incidents de surveillance non résolus
    $stmt = $conn->prepare("UPDATE proctoring_incidents SET resolved = 0, reviewed = 0 WHERE session_id = ? AND resolved IS NULL");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'redirect' => 'exam-result.php?session_id=' . $session_id
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
