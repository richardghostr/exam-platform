<?php
ini_set('memory_limit', '256M');
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
// Charger PHPMailer
require_once '../vendor/autoload.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Récupérer les données JSON de la requête
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$attemptId = $data['attempt_id'] ?? 0;

// Vérifier que la tentative appartient à l'utilisateur
$stmt = $conn->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $attemptId, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Tentative non trouvée ou non autorisée']);
    exit();
}

$attempt = $result->fetch_assoc();

// Récupérer les détails de l'examen
$examStmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$examStmt->bind_param("i", $attempt['exam_id']);
$examStmt->execute();
$exam = $examStmt->get_result()->fetch_assoc();

// Récupérer les questions et réponses
$questionsStmt = $conn->prepare("
    SELECT q.*, ua.answer_text, ua.selected_options, ua.is_correct, ua.points_awarded
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.attempt_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.id ASC
");
$questionsStmt->bind_param("ii", $attemptId, $attempt['exam_id']);
$questionsStmt->execute();
$questions = $questionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les options pour chaque question
foreach ($questions as &$question) {
    $optionsStmt = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC");
    $optionsStmt->bind_param("i", $question['id']);
    $optionsStmt->execute();
    $question['options'] = $optionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($question); // Détruire la référence

// Traiter les différentes actions
switch ($action) {
    case 'send_email':
        sendEmail($data, $attempt, $exam, $questions);
        break;

    case 'generate_pdf':
        generatePdf($attempt, $exam, $questions);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        exit();
}

function sendEmail($data, $attempt, $exam, $questions)
{
    global $conn;

    // Nettoyer les entrées
    $recipientEmails = array_filter(array_map('trim', explode(',', $data['recipient_email'])));
    $subject = filter_var($data['subject'], FILTER_SANITIZE_STRING);
    $message = filter_var($data['message'], FILTER_SANITIZE_STRING);
    $sendCopy = filter_var($data['send_copy'], FILTER_VALIDATE_BOOLEAN);

    // Valider les emails
    $validEmails = [];
    foreach ($recipientEmails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validEmails[] = $email;
        }
    }

    if (empty($validEmails)) {
        return ['success' => false, 'message' => 'Aucune adresse email valide fournie'];
    }

    // Récupérer les infos utilisateur
    $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();

    // Générer le PDF temporaire
    $pdfContent = generateTempPdf($attempt, $exam, $questions);

    // Envoyer les emails avec PHPMailer
    require_once '../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        // Expéditeur
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addReplyTo($user['email'], $user['full_name']);

        // Destinataires
        foreach ($validEmails as $email) {
            $mail->addAddress($email);
        }

        // Copie à l'expéditeur si demandé
        if ($sendCopy) {
            $mail->addCC($user['email']);
        }

        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Générer le contenu HTML
        ob_start();
        include '../templates/email_results.php';
        $mail->Body = ob_get_clean();
        $mail->AltBody = strip_tags($message);

        // Joindre le PDF
        $mail->addStringAttachment($pdfContent, 'Resultats_Examen_' . date('Y-m-d') . '.pdf');

        // Envoyer l'email
        $mail->send();

        // Enregistrer dans les logs
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $action = "Résultats envoyés par email";
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $logStmt->bind_param("ississ", $_SESSION['user_id'], $action, 'exam_attempt', $attempt['id'], $ip, $userAgent);
        $logStmt->execute();

        return ['success' => true];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erreur lors de l'envoi de l'email: " . $mail->ErrorInfo
        ];
    }
}
function generateTempPdf($attempt, $exam, $questions)
{
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Configuration du PDF (identique à votre fonction generatePdf)
    // ... (le code de génération PDF que vous avez déjà)
    // Configuration du document
    $pdf->SetCreator('ExamSafe');
    $pdf->SetAuthor('ExamSafe Platform');
    $pdf->SetTitle('Résultats: ' . $exam['title']);
    $pdf->SetSubject('Résultats d\'examen');

    // Marges
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);

    // Ajouter une page
    $pdf->AddPage();

    // Logo et titre
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 15, 'Résultats de l\'examen', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $exam['title'], 0, 1, 'C');
    $pdf->Ln(10);

    // Informations générales
    $pdf->SetFont('helvetica', '', 12);

    $info = "Étudiant: " . $_SESSION['full_name'] . "\n";
    $info .= "Date: " . date('d/m/Y H:i', strtotime($attempt['start_time'])) . "\n";
    $info .= "Score: " . round($attempt['score'], 1) . "%\n";
    $info .= "Statut: " . ($attempt['score'] >= $exam['passing_score'] ? "Réussi" : "Échoué") . "\n";
    $info .= "Score minimum requis: " . $exam['passing_score'] . "%";

    $pdf->MultiCell(0, 10, $info, 0, 'L');
    $pdf->Ln(15);

    // Détails des questions
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Détail des questions', 0, 1);

    foreach ($questions as $index => $question) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Question ' . ($index + 1) . ' (' . $question['points_awarded'] . '/' . $question['points'] . ' points)', 0, 1);

        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 8, $question['question_text'], 0, 'L');

        if ($question['question_type'] !== 'essay') {
            foreach ($question['options'] as $option) {
                $prefix = '';
                if (in_array($option['id'], explode(',', $question['selected_options'] ?? ''))) {
                    $prefix = $option['is_correct'] ? '✓ ' : '✗ ';
                } elseif ($option['is_correct']) {
                    $prefix = '[Correct] ';
                }

                $pdf->Cell(5);
                $pdf->MultiCell(0, 8, $prefix . $option['option_text'], 0, 'L');
            }
        } else {
            $pdf->Cell(5);
            $pdf->MultiCell(0, 8, 'Réponse: ' . $question['answer_text'], 0, 'L');
        }

        $pdf->Ln(5);
    }

    // Pied de page
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Généré le ' . date('d/m/Y à H:i') . ' par ExamSafe', 0, 0, 'C');

    // Nom du fichier
    $filename = 'resultats_' . preg_replace('/[^a-z0-9]/i', '_', $exam['title']) . '_' . date('Y-m-d') . '.pdf';

    // En-têtes HTTP pour forcer le téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Retourner le contenu plutôt que de l'output
    return $pdf->Output('', 'S');
}
function generatePdf($attempt, $exam, $questions)
{
    // Inclure TCPDF
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

    try {
        // Créer une nouvelle instance de TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Configuration du document
        $pdf->SetCreator('ExamSafe');
        $pdf->SetAuthor('ExamSafe Platform');
        $pdf->SetTitle('Résultats: ' . $exam['title']);
        $pdf->SetSubject('Résultats d\'examen');

        // Marges
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);

        // Ajouter une page
        $pdf->AddPage();

        // Logo et titre
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'Résultats de l\'examen', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $exam['title'], 0, 1, 'C');
        $pdf->Ln(10);

        // Informations générales
        $pdf->SetFont('helvetica', '', 12);

        $info = "Étudiant: " . $_SESSION['full_name'] . "\n";
        $info .= "Date: " . date('d/m/Y H:i', strtotime($attempt['start_time'])) . "\n";
        $info .= "Score: " . round($attempt['score'], 1) . "%\n";
        $info .= "Statut: " . ($attempt['score'] >= $exam['passing_score'] ? "Réussi" : "Échoué") . "\n";
        $info .= "Score minimum requis: " . $exam['passing_score'] . "%";

        $pdf->MultiCell(0, 10, $info, 0, 'L');
        $pdf->Ln(15);

        // Détails des questions
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Détail des questions', 0, 1);

        foreach ($questions as $index => $question) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Question ' . ($index + 1) . ' (' . $question['points_awarded'] . '/' . $question['points'] . ' points)', 0, 1);

            $pdf->SetFont('helvetica', '', 11);
            $pdf->MultiCell(0, 8, $question['question_text'], 0, 'L');

            if ($question['question_type'] !== 'essay') {
                foreach ($question['options'] as $option) {
                    $prefix = '';
                    if (in_array($option['id'], explode(',', $question['selected_options'] ?? ''))) {
                        $prefix = $option['is_correct'] ? '✓ ' : '✗ ';
                    } elseif ($option['is_correct']) {
                        $prefix = '[Correct] ';
                    }

                    $pdf->Cell(5);
                    $pdf->MultiCell(0, 8, $prefix . $option['option_text'], 0, 'L');
                }
            } else {
                $pdf->Cell(5);
                $pdf->MultiCell(0, 8, 'Réponse: ' . $question['answer_text'], 0, 'L');
            }

            $pdf->Ln(5);
        }

        // Pied de page
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Généré le ' . date('d/m/Y à H:i') . ' par ExamSafe', 0, 0, 'C');

        // Nom du fichier
        $filename = 'resultats_' . preg_replace('/[^a-z0-9]/i', '_', $exam['title']) . '_' . date('Y-m-d') . '.pdf';

        // En-têtes HTTP pour forcer le téléchargement
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        // Sortie directe du PDF
        $pdf->Output($filename, 'D');
        exit();
    } catch (Exception $e) {
        // En cas d'erreur
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
        ]);
        exit();
    }
}
