<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle étudiant
require_login('../login.php');
require_role('student', '../index.php');

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Récupérer les données du formulaire
$attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
$enrollment_id = isset($_POST['enrollment_id']) ? intval($_POST['enrollment_id']) : 0;
$answers = isset($_POST['answer']) ? $_POST['answer'] : [];

// Vérifier si les données sont valides
if ($attempt_id <= 0 || $enrollment_id <= 0) {
    header('Location: index.php?error=invalid_data');
    exit;
}

// Connexion à la base de données
include_once '../includes/db.php';

// Vérifier si la tentative existe et appartient à l'étudiant
$attempt_query = "
    SELECT ea.id, ea.status, ee.exam_id
    FROM exam_attempts ea
    JOIN exam_enrollments ee ON ea.enrollment_id = ee.id
    WHERE ea.id = ? AND ee.id = ? AND ee.student_id = ?
";

$stmt = $conn->prepare($attempt_query);
$stmt->bind_param("iii", $attempt_id, $enrollment_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php?error=unauthorized');
    $stmt->close();
    $conn->close();
    exit;
}

$attempt = $result->fetch_assoc();
$stmt->close();

// Vérifier si la tentative est déjà terminée
if ($attempt['status'] !== 'in_progress') {
    header('Location: exam-results.php?id=' . $attempt['exam_id']);
    $conn->close();
    exit;
}

// Récupérer les questions de l'examen
$questions_query = "
    SELECT q.*, GROUP_CONCAT(ao.id, ':::', ao.is_correct SEPARATOR '|||') as options
    FROM questions q
    LEFT JOIN answer_options ao ON q.id = ao.question_id
    WHERE q.exam_id = ?
    GROUP BY q.id
";

$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $attempt['exam_id']);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = [];

while ($row = $questions_result->fetch_assoc()) {
    $questions[$row['id']] = $row;
}

$stmt->close();

// Traiter les réponses
$total_points = 0;
$earned_points = 0;
$graded_questions = 0;

foreach ($questions as $question_id => $question) {
    $answer_text = null;
    $selected_option_id = null;
    $is_correct = null;
    $points_awarded = 0;
    
    // Ajouter les points de la question au total
    $total_points += $question['points'];
    
    // Vérifier si une réponse a été donnée pour cette question
    if (isset($answers[$question_id])) {
        if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
            // Pour les QCM et vrai/faux
            $selected_option_id = intval($answers[$question_id]);
            
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
            $answer_text = $answers[$question_id];
            
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
        
        $stmt = $conn->prepare($insert_answer_query);
        $stmt->bind_param("iisidi", $attempt_id, $question_id, $answer_text, $selected_option_id, $is_correct, $points_awarded);
        $stmt->execute();
        $stmt->close();
    }
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

$stmt = $conn->prepare($update_attempt_query);
$stmt->bind_param("di", $score, $attempt_id);
$stmt->execute();
$stmt->close();

// Mettre à jour le statut de l'inscription
$update_enrollment_query = "
    UPDATE exam_enrollments
    SET status = 'completed'
    WHERE id = ?
";

$stmt = $conn->prepare($update_enrollment_query);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$stmt->close();

// Fermer la connexion à la base de données
$conn->close();

// Rediriger vers la page des résultats
header('Location: exam-results.php?id=' . $attempt['exam_id'] . '&completed=1');
exit;
