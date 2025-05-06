<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../student/includes/auth.php';

// // Vérifier si l'utilisateur est connecté
// if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Non autorisé']);
//     exit();
// }

// // Vérifier si la requête est en POST
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
//     exit();
// }

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// if (!$data || !isset($data['attempt_id'])) {
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Données invalides']);
//     exit();
// }

$attempt_id = intval($data['attempt_id']);
$user_id = $_SESSION['user_id'];

// Vérifier que la tentative appartient à l'étudiant connecté et est en cours
$stmt = $conn->prepare("SELECT ea.*, e.id as exam_id, e.passing_score, e.has_essay 
                        FROM exam_attempts ea 
                        JOIN exams e ON ea.exam_id = e.id 
                        WHERE ea.id = ? AND ea.user_id = ? AND ea.status = 'in_progress'");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tentative non valide ou déjà terminée']);
    exit();
}

$attempt = $result->fetch_assoc();
$exam_id = $attempt['exam_id'];
$passing_score = $attempt['passing_score'];
$has_essay = $attempt['has_essay'];

// Commencer une transaction
$conn->begin_transaction();

try {
    // Calculer le temps passé
    $stmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, start_time, NOW()) as time_spent FROM exam_attempts WHERE id = ?");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $time_result = $stmt->get_result();
    $time_data = $time_result->fetch_assoc();
    $time_spent = $time_data['time_spent'];
    
    // Marquer la tentative comme terminée
    $stmt = $conn->prepare("UPDATE exam_attempts SET status = 'completed', end_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $attempt_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erreur lors de la finalisation de l'examen: " . $stmt->error);
    }
    
    // Calculer le score si l'examen n'a pas de questions à essai
    if (!$has_essay) {
        // Récupérer les questions et les réponses
        $stmt = $conn->prepare("SELECT q.id, q.question_type, q.points, qo.id as option_id, qo.is_correct, ua.selected_options, ua.answer_text 
                                FROM questions q 
                                LEFT JOIN question_options qo ON q.id = qo.question_id 
                                LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.attempt_id = ? 
                                WHERE q.exam_id = ?");
        $stmt->bind_param("ii", $attempt_id, $exam_id);
        $stmt->execute();
        $questions_result = $stmt->get_result();
        
        $total_points = 0;
        $earned_points = 0;
        $question_data = [];
        
        // Organiser les données par question
        while ($row = $questions_result->fetch_assoc()) {
            $question_id = $row['id'];
            
            if (!isset($question_data[$question_id])) {
                $question_data[$question_id] = [
                    'type' => $row['question_type'],
                    'points' => $row['points'],
                    'options' => [],
                    'selected_options' => $row['selected_options'],
                    'answer_text' => $row['answer_text']
                ];
                $total_points += $row['points'];
            }
            
            if ($row['option_id']) {
                $question_data[$question_id]['options'][$row['option_id']] = $row['is_correct'];
            }
        }
        
        // Évaluer chaque réponse
        foreach ($question_data as $question_id => $question) {
            $is_correct = false;
            
            if ($question['type'] === 'multiple_choice' || $question['type'] === 'single_choice') {
                if ($question['selected_options']) {
                    $selected = explode(',', $question['selected_options']);
                    $correct_count = 0;
                    $total_correct = 0;
                    
                    foreach ($question['options'] as $option_id => $is_option_correct) {
                        if ($is_option_correct) {
                            $total_correct++;
                            if (in_array($option_id, $selected)) {
                                $correct_count++;
                            }
                        } else if (in_array($option_id, $selected)) {
                            $correct_count--;
                        }
                    }
                    
                    if ($total_correct > 0 && $correct_count == $total_correct) {
                        $is_correct = true;
                        $earned_points += $question['points'];
                    }
                }
            } else if ($question['type'] === 'true_false') {
                if ($question['selected_options']) {
                    $selected = $question['selected_options'];
                    foreach ($question['options'] as $option_id => $is_option_correct) {
                        if ($is_option_correct && $selected == $option_id) {
                            $is_correct = true;
                            $earned_points += $question['points'];
                            break;
                        }
                    }
                }
            }
            
            // Mettre à jour le statut de correction pour cette réponse
            $stmt = $conn->prepare("UPDATE user_answers SET is_correct = ?, points_awarded = ? WHERE attempt_id = ? AND question_id = ?");
            $points_awarded = $is_correct ? $question['points'] : 0;
            $stmt->bind_param("idii", $is_correct, $points_awarded, $attempt_id, $question_id);
            $stmt->execute();
        }
        
        // Calculer le score en pourcentage
        $score_percentage = ($total_points > 0) ? ($earned_points / $total_points) * 100 : 0;
        $passed = ($score_percentage >= $passing_score) ? 1 : 0;
        
        // Créer l'entrée dans exam_results
        $stmt = $conn->prepare("INSERT INTO exam_results (exam_id, user_id, score, total_points, points_earned, passing_score, passed, time_spent, completed_at, status, is_graded) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'completed', '1')");
        $stmt->bind_param("iidiidii", $exam_id, $user_id, $score_percentage, $total_points, $earned_points, $passing_score, $passed, $time_spent);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de l'enregistrement des résultats: " . $stmt->error);
        }
        
        $result_id = $conn->insert_id;
    } else {
        // Si l'examen contient des questions à essai, marquer comme nécessitant une correction manuelle
        $stmt = $conn->prepare("INSERT INTO exam_results (exam_id, user_id, score, total_points, points_earned, passing_score, passed, time_spent, completed_at, status, is_graded) 
                                VALUES (?, ?, 0, 0, 0, ?, 0, ?, NOW(), 'waiting_grading', '0')");
        $stmt->bind_param("iiii", $exam_id, $user_id, $passing_score, $time_spent);
        
        if (!$stmt->execute()) {
            throw new Exception("Erreur lors de l'enregistrement des résultats: " . $stmt->error);
        }
        
        $result_id = $conn->insert_id;
    }
    
    // Mettre à jour les incidents de surveillance non résolus
    $stmt = $conn->prepare("UPDATE proctoring_incidents SET resolved = 0, reviewed = 0 WHERE attempt_id = ? AND resolved IS NULL");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    
    // Valider la transaction
    $conn->commit();
    
    // Renvoyer la réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Examen terminé avec succès',
        'result_id' => $result_id,
        'redirect' => 'exam-result.php?id=' . $result_id
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
