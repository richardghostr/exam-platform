<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès non autorisé');
}

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Récupérer les paramètres du rapport
$reportType = isset($_POST['reportType']) ? $_POST['reportType'] : 'exam_results';
$examId = isset($_POST['examId']) ? $_POST['examId'] : 'all';
$format = isset($_POST['format']) ? $_POST['format'] : 'pdf';

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Construire la requête SQL en fonction du type de rapport
$sql = '';
$reportTitle = '';
$filename = 'rapport_' . date('Y-m-d');

// Condition pour filtrer par examen spécifique
$examCondition = ($examId != 'all') ? "AND e.id = $examId" : "";

switch ($reportType) {
    case 'exam_results':
        $reportTitle = "Rapport des résultats d'examens";
        $sql = "SELECT e.title as exam_title, s.name as subject, u.first_name, u.last_name, u.email, 
                er.score, er.completed_at, er.time_spent, er.passed
                FROM exam_results er
                JOIN exams e ON er.exam_id = e.id
                JOIN users u ON er.user_id = u.id
                JOIN subjects s ON e.subject = s.id
                WHERE e.teacher_id = $teacherId $examCondition
                ORDER BY e.title, u.last_name, u.first_name";
        $filename = 'resultats_examens_' . date('Y-m-d');
        break;
        
    case 'student_performance':
        $reportTitle = "Rapport de performance des étudiants";
        $sql = "SELECT u.first_name, u.last_name, u.email, 
                COUNT(DISTINCT er.exam_id) as exams_taken,
                AVG(er.score) as avg_score,
                SUM(CASE WHEN er.passed = 1 THEN 1 ELSE 0 END) as exams_passed,
                AVG(er.time_spent) as avg_time_spent
                FROM users u
                JOIN exam_results er ON u.id = er.user_id
                JOIN exams e ON er.exam_id = e.id
                WHERE e.teacher_id = $teacherId $examCondition AND u.role = 'student'
                GROUP BY u.id
                ORDER BY avg_score DESC";
        $filename = 'performance_etudiants_' . date('Y-m-d');
        break;
        
    case 'proctoring_incidents':
        $reportTitle = "Rapport des incidents de surveillance";
        $sql = "SELECT e.title as exam_title, u.first_name, u.last_name, u.email,
                p.incident_type, p.severity, p.description, p.timestamp, p.status
                FROM proctoring_incidents p
                JOIN exams e ON p.exam_id = e.id
                JOIN users u ON p.user_id = u.id
                WHERE e.teacher_id = $teacherId $examCondition
                ORDER BY p.timestamp DESC";
        $filename = 'incidents_surveillance_' . date('Y-m-d');
        break;
        
    case 'question_analysis':
        $reportTitle = "Analyse des questions";
        $sql = "SELECT e.title as exam_title, q.question_text, q.difficulty,
                COUNT(qa.id) as attempts,
                SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                (SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(qa.id)) * 100 as success_rate
                FROM questions q
                JOIN exams e ON q.exam_id = e.id
                LEFT JOIN question_attempts qa ON q.id = qa.question_id
                WHERE e.teacher_id = $teacherId $examCondition
                GROUP BY q.id
                ORDER BY e.title, success_rate";
        $filename = 'analyse_questions_' . date('Y-m-d');
        break;
        
    default:
        header('HTTP/1.1 400 Bad Request');
        exit('Type de rapport non pris en charge');
}

// Exécuter la requête
$result = $conn->query($sql);

if (!$result) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Erreur lors de la récupération des données: ' . $conn->error);
}

// Si l'examen spécifique est sélectionné, récupérer son titre
$examTitle = '';
if ($examId != 'all') {
    $examQuery = $conn->query("SELECT title FROM exams WHERE id = $examId");
    if ($examQuery && $examRow = $examQuery->fetch_assoc()) {
        $examTitle = $examRow['title'];
        $reportTitle .= " - " . $examTitle;
    }
}

// Générer le rapport selon le format demandé
switch ($format) {
    case 'pdf':
        generatePDFReport($result, $reportTitle, $filename, $reportType);
        break;
        
    case 'excel':
        generateExcelReport($result, $reportTitle, $filename);
        break;
        
    case 'csv':
        generateCSVReport($result, $filename);
        break;
        
    default:
        header('HTTP/1.1 400 Bad Request');
        exit('Format de rapport non pris en charge');
}

// Fonction pour générer un rapport PDF
function generatePDFReport($result, $reportTitle, $filename, $reportType) {
    require_once '../vendor/autoload.php';
    
    // Créer un nouveau document PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('ExamSafe');
    $pdf->SetTitle($reportTitle);
    $pdf->SetSubject('Rapport ExamSafe');
    
    // Définir les marges
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Définir la rupture de page automatique
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Définir la police
    $pdf->SetFont('helvetica', '', 10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Logo et titre du rapport
    $pdf->Image('../assets/images/logo.png', 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 30, $reportTitle, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    
    // Date du rapport
    $pdf->Cell(0, 10, 'Date du rapport: ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(5);
    
    // Créer le tableau
    if ($result->num_rows > 0) {
        $fields = $result->fetch_fields();
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = ucfirst(str_replace('_', ' ', $field->name));
        }
        
        // Largeur des colonnes
        $colWidth = 180 / count($headers);
        
        // En-têtes du tableau
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('helvetica', 'B', 10);
        foreach ($headers as $header) {
            $pdf->Cell($colWidth, 7, $header, 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Données du tableau
        $pdf->SetFont('helvetica', '', 9);
        while ($row = $result->fetch_assoc()) {
            foreach ($row as $value) {
                // Formater les valeurs numériques
                if (is_numeric($value) && strpos($value, '.') !== false) {
                    $value = number_format($value, 2, ',', ' ');
                }
                $pdf->Cell($colWidth, 6, $value, 1, 0, 'L');
            }
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(0, 10, 'Aucune donnée disponible pour ce rapport', 0, 1, 'C');
    }
    
    // Ajouter des statistiques supplémentaires selon le type de rapport
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Résumé', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    switch ($reportType) {
        case 'exam_results':
            // Calculer des statistiques sur les résultats
            $result->data_seek(0);
            $totalStudents = $result->num_rows;
            $totalPassed = 0;
            $totalScore = 0;
            
            while ($row = $result->fetch_assoc()) {
                $totalScore += $row['score'];
                if ($row['passed']) $totalPassed++;
            }
            
            $avgScore = $totalStudents > 0 ? $totalScore / $totalStudents : 0;
            $passRate = $totalStudents > 0 ? ($totalPassed / $totalStudents) * 100 : 0;
            
            $pdf->Cell(0, 6, "Nombre total d'étudiants: $totalStudents", 0, 1, 'L');
            $pdf->Cell(0, 6, "Score moyen: " . number_format($avgScore, 2, ',', ' ') . "%", 0, 1, 'L');
            $pdf->Cell(0, 6, "Taux de réussite: " . number_format($passRate, 2, ',', ' ') . "%", 0, 1, 'L');
            break;
            
        case 'student_performance':
            // Calculer des statistiques sur la performance
            $result->data_seek(0);
            $totalStudents = $result->num_rows;
            $totalExams = 0;
            $totalPassed = 0;
            
            while ($row = $result->fetch_assoc()) {
                $totalExams += $row['exams_taken'];
                $totalPassed += $row['exams_passed'];
            }
            
            $avgExams = $totalStudents > 0 ? $totalExams / $totalStudents : 0;
            $passRate = $totalExams > 0 ? ($totalPassed / $totalExams) * 100 : 0;
            
            $pdf->Cell(0, 6, "Nombre total d'étudiants: $totalStudents", 0, 1, 'L');
            $pdf->Cell(0, 6, "Nombre moyen d'examens par étudiant: " . number_format($avgExams, 2, ',', ' '), 0, 1, 'L');
            $pdf->Cell(0, 6, "Taux de réussite global: " . number_format($passRate, 2, ',', ' ') . "%", 0, 1, 'L');
            break;
            
        case 'proctoring_incidents':
            // Calculer des statistiques sur les incidents
            $result->data_seek(0);
            $totalIncidents = $result->num_rows;
            $incidentTypes = [];
            
            while ($row = $result->fetch_assoc()) {
                $type = $row['incident_type'];
                if (!isset($incidentTypes[$type])) {
                    $incidentTypes[$type] = 0;
                }
                $incidentTypes[$type]++;
            }
            
            $pdf->Cell(0, 6, "Nombre total d'incidents: $totalIncidents", 0, 1, 'L');
            foreach ($incidentTypes as $type => $count) {
                $percentage = ($count / $totalIncidents) * 100;
                $pdf->Cell(0, 6, "$type: $count (" . number_format($percentage, 2, ',', ' ') . "%)", 0, 1, 'L');
            }
            break;
            
        case 'question_analysis':
            // Calculer des statistiques sur les questions
            $result->data_seek(0);
            $totalQuestions = $result->num_rows;
            $totalAttempts = 0;
            $totalCorrect = 0;
            $difficultyStats = [
                'easy' => ['count' => 0, 'success' => 0],
                'medium' => ['count' => 0, 'success' => 0],
                'hard' => ['count' => 0, 'success' => 0]
            ];
            
            while ($row = $result->fetch_assoc()) {
                $totalAttempts += $row['attempts'];
                $totalCorrect += $row['correct_answers'];
                $difficulty = $row['difficulty'];
                
                if (isset($difficultyStats[$difficulty])) {
                    $difficultyStats[$difficulty]['count']++;
                    $difficultyStats[$difficulty]['success'] += $row['success_rate'];
                }
            }
            
            $overallSuccessRate = $totalAttempts > 0 ? ($totalCorrect / $totalAttempts) * 100 : 0;
            
            $pdf->Cell(0, 6, "Nombre total de questions: $totalQuestions", 0, 1, 'L');
            $pdf->Cell(0, 6, "Taux de réussite global: " . number_format($overallSuccessRate, 2, ',', ' ') . "%", 0, 1, 'L');
            
            foreach ($difficultyStats as $difficulty => $stats) {
                if ($stats['count'] > 0) {
                    $avgSuccess = $stats['success'] / $stats['count'];
                    $pdf->Cell(0, 6, "Taux de réussite " . ucfirst($difficulty) . ": " . number_format($avgSuccess, 2, ',', ' ') . "%", 0, 1, 'L');
                }
            }
            break;
    }
    
    // Pied de page
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Ce rapport a été généré automatiquement par ExamSafe. © ' . date('Y') . ' ExamSafe', 0, 0, 'C');
    
    // Fermer et générer le PDF
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}

// Fonction pour générer un rapport Excel
function generateExcelReport($result, $reportTitle, $filename) {
    // Créer un nouveau document Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rapport');
    
    // Ajouter le titre du rapport
    $sheet->setCellValue('A1', $reportTitle);
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($result->field_count) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Ajouter la date du rapport
    $sheet->setCellValue('A2', 'Date du rapport: ' . date('d/m/Y'));
    $sheet->mergeCells('A2:' . Coordinate::stringFromColumnIndex($result->field_count) . '2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Ajouter les en-têtes
    $headers = [];
    $columnLetter = 'A'; // Commencer à la colonne A
    
    if ($result->num_rows > 0) {
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            $header = ucfirst(str_replace('_', ' ', $field->name));
            $headers[] = $header;
            $sheet->setCellValue($columnLetter . '4', $header); // Ligne 4
            $columnLetter++;
        }
    }
    
    // Style des en-têtes
    $lastHeaderColumn = Coordinate::stringFromColumnIndex(count($headers));
    $headerRange = 'A4:' . $lastHeaderColumn . '4';
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6E6E6']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ];
    $sheet->getStyle($headerRange)->applyFromArray($headerStyle);
    
    // Ajouter les données
    $rowIndex = 5; // Commencer à la ligne 5
    while ($row = $result->fetch_assoc()) {
        $columnLetter = 'A';
        foreach ($row as $value) {
            $sheet->setCellValue($columnLetter . $rowIndex, $value);
            $columnLetter++;
        }
        $rowIndex++;
    }
    
    // Style des données
    $dataRange = 'A5:' . $lastHeaderColumn . ($rowIndex - 1);
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ];
    $sheet->getStyle($dataRange)->applyFromArray($dataStyle);
    
    // Ajuster automatiquement la largeur des colonnes
    foreach (range('A', $lastHeaderColumn) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Créer le writer Excel
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    
    // Envoyer le fichier au navigateur
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}

// Fonction pour générer un rapport CSV
function generateCSVReport($result, $filename) {
    // Définir les en-têtes HTTP
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Créer un fichier de sortie
    $output = fopen('php://output', 'w');
    
    // Ajouter l'en-tête UTF-8 BOM pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Ajouter les en-têtes
    if ($result->num_rows > 0) {
        $fields = $result->fetch_fields();
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = ucfirst(str_replace('_', ' ', $field->name));
        }
        fputcsv($output, $headers);
    }
    
    // Ajouter les données
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}