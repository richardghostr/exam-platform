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

// Récupérer les données du formulaire
$attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
$enrollment_id = isset($_POST['enrollment_id']) ? intval($_POST['enrollment_id']) : 0;
$answers = isset($_POST['answer']) ? $_POST['answer'] : [];

// Vérifier si les données sont valides
if ($attempt_id <= 0 || $enrollment_id <= 0 || empty($answers)) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

// Connexion à la base de données
include_once '../includes/db.php';

// Vérifier si la tentative existe et appartient à l'utilisateur
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
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    $stmt->close();
    $conn->close();
    exit;
}

$attempt = $result->fetch_assoc();
$stmt->close();

// Vérifier si la tentative est en cours
if ($attempt['status'] !== 'in_progress') {
    http_response_code(400);
    echo json_encode(['error' => 'La tentative n\'est pas en cours']);
    $conn->close();
    exit;
}

// Récupérer les questions de l'examen
$questions_query = "
    SELECT q.id, q.question_type
    FROM questions q
    WHERE q.exam_id = ?
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
$saved_count = 0;

foreach ($answers as $question_id => $answer) {
    // Vérifier si la question existe
    if (!isset($questions[$question_id])) {
        continue;
    }
    
    $answer_text = null;
    $selected_option_id = null;
    
    if ($questions[$question_id]['question_type'] === 'multiple_choice' || $questions[$question_id]['question_type'] === 'true_false') {
        // Pour les QCM et vrai/faux
        $selected_option_id = intval($answer);
    } else {
        // Pour les réponses courtes et les essais
        $answer_text = $answer;
    }
    
    // Vérifier si une réponse existe déjà
    $check_query = "
        SELECT id
        FROM student_answers
        WHERE attempt_id = ? AND question_id = ?
    ";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $attempt_id, $question_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    $stmt->close();
    
    if ($check_result->num_rows > 0) {
        // Mettre à jour la réponse existante
        $update_query = "
            UPDATE student_answers
            SET answer_text = ?, selected_option_id = ?
            WHERE attempt_id = ? AND question_id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("siii", $answer_text, $selected_option_id, $attempt_id, $question_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insérer une nouvelle réponse
        $insert_query = "
            INSERT INTO student_answers (attempt_id, question_id, answer_text, selected_option_id)
            VALUES (?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iisi", $attempt_id, $question_id, $answer_text, $selected_option_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $saved_count++;
}

// Mettre à jour l'horodatage de la dernière activité
$update_activity_query = "
    UPDATE exam_attempts
    SET last_activity = NOW()
    WHERE id = ?
";

$stmt = $conn->prepare($update_activity_query);
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$stmt->close();

// Fermer la connexion à la base de données
$conn->close();

// Renvoyer une réponse de succès
echo json_encode([
    'success' => true,
    'saved_count' => $saved_count
]);
