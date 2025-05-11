<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
// if (!isLoggedIn() || !isAdmin()) {
//     header('Location: login.php');
//     exit();
// }

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Vérifier si l'ID de l'examen est fourni
if (!isset($_POST['exam_id']) || empty($_POST['exam_id'])) {
    header('Location: manage-exams.php');
    exit();
}

$examId = intval($_POST['exam_id']);
$format = isset($_POST['format']) ? $_POST['format'] : 'csv';

// Vérifier si l'examen appartient à l'enseignant
$examQuery = $conn->query("SELECT * FROM exams WHERE id = $examId");
if ($examQuery->num_rows === 0) {
    header('Location: manage-exams.php');
    exit();
}

$exam = $examQuery->fetch_assoc();

// Récupérer les résultats de l'examen en fonction des options sélectionnées
$selectFields = [];
$joinClauses = [];

// Déterminer les champs à inclure
if (isset($_POST['include_student_info']) && $_POST['include_student_info']) {
    $selectFields = array_merge($selectFields, [
        'u.username',
        'u.first_name',
        'u.last_name',
        'u.email',
        'c.name as class_name'
    ]);
    $joinClauses[] = "LEFT JOIN user_classes uc ON u.id = uc.user_id";
    $joinClauses[] = "LEFT JOIN classes c ON uc.class_id = c.id";
}

if (isset($_POST['include_scores']) && $_POST['include_scores']) {
    $selectFields = array_merge($selectFields, [
        'er.score',
        'er.points_earned',
        'er.total_points',
        'er.passing_score',
        'er.passed',
        'er.status'
    ]);
}

if (isset($_POST['include_time']) && $_POST['include_time']) {
    $selectFields = array_merge($selectFields, [
        'er.completed_at',
        'er.time_spent'
    ]);
}

// Toujours inclure les champs de base
array_unshift($selectFields, 'er.id');

// Construire la requête SQL
$query = "SELECT " . implode(', ', $selectFields) . " 
          FROM exam_results er
          JOIN users u ON er.user_id = u.id
          " . implode(' ', $joinClauses) . "
          WHERE er.exam_id = $examId
          ORDER BY er.completed_at DESC";

$results = $conn->query($query);

// Préparer les données pour l'export
$exportData = [];
$headers = [];

// Déterminer les en-têtes en fonction des champs sélectionnés
if (isset($_POST['include_student_info']) && $_POST['include_student_info']) {
    $headers = array_merge($headers, [
        'Nom d\'utilisateur',
        'Prénom',
        'Nom',
        'Email',
        'Classe'
    ]);
}

if (isset($_POST['include_scores']) && $_POST['include_scores']) {
    $headers = array_merge($headers, [
        'Score (%)',
        'Points obtenus',
        'Points totaux',
        'Score de passage',
        'Statut',
        'Résultat'
    ]);
}

if (isset($_POST['include_time']) && $_POST['include_time']) {
    $headers = array_merge($headers, [
        'Date de complétion',
        'Temps passé (secondes)'
    ]);
}

// Remplir les données d'export
while ($row = $results->fetch_assoc()) {
    $exportRow = [];
    
    if (isset($_POST['include_student_info']) && $_POST['include_student_info']) {
        $exportRow = array_merge($exportRow, [
            $row['username'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['class_name'] ?? 'N/A'
        ]);
    }
    
    if (isset($_POST['include_scores']) && $_POST['include_scores']) {
        $exportRow = array_merge($exportRow, [
            $row['score'] ?? 'N/A',
            $row['points_earned'] ?? 'N/A',
            $row['total_points'] ?? 'N/A',
            $row['passing_score'] ?? 'N/A',
            $row['status'] ?? 'N/A',
            isset($row['passed']) ? ($row['passed'] ? 'Réussi' : 'Échoué') : 'N/A'
        ]);
    }
    
    if (isset($_POST['include_time']) && $_POST['include_time']) {
        $exportRow = array_merge($exportRow, [
            $row['completed_at'] ?? 'N/A',
            $row['time_spent'] ?? 'N/A'
        ]);
    }
    
    $exportData[] = $exportRow;
}

// Exporter selon le format demandé
switch ($format) {
    case 'csv':
        exportCSV($headers, $exportData, $exam['title']);
        break;
    case 'excel':
        exportExcel($headers, $exportData, $exam['title']);
        break;
    case 'pdf':
        exportPDF($headers, $exportData, $exam['title']);
        break;
    default:
        exportCSV($headers, $exportData, $exam['title']);
}

/**
 * Exporte les données au format CSV
 */
function exportCSV($headers, $data, $examTitle) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resultats_' . sanitizeFileName($examTitle) . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Écrire l'en-tête
    fputcsv($output, $headers, ';');
    
    // Écrire les données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}

/**
 * Exporte les données au format Excel
 */
function exportExcel($headers, $data, $examTitle) {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator("Exam Platform")
        ->setTitle("Résultats de l'examen " . $examTitle);
    
    // Ajouter les données
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Résultats');
    
    // Ajouter les en-têtes
    $sheet->fromArray($headers, NULL, 'A1');
    
    // Ajouter les données
    if (!empty($data)) {
        $sheet->fromArray($data, NULL, 'A2');
    }
    
    // Style pour les en-têtes
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'DDDDDD']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ]
    ];
    
    $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);
    
    // Auto-size columns
    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
    
    // Envoyer le fichier
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="resultats_' . sanitizeFileName($examTitle) . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit();
}

/**
 * Exporte les données au format PDF
 */
function exportPDF($headers, $data, $examTitle) {
    require_once '../libs/TCPDF-main/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Exam Platform');
    $pdf->SetAuthor('Exam Platform');
    $pdf->SetTitle('Résultats de l\'examen ' . $examTitle);
    $pdf->SetSubject('Résultats d\'examen');
    $pdf->SetKeywords('examen, résultats, éducation');
    
    $pdf->setHeaderData('', 0, 'Résultats de l\'examen', $examTitle);
    $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
    $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
    
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    
    // Créer le tableau HTML
    $html = '<table border="1" cellpadding="4">';
    
    // En-têtes
    $html .= '<tr style="background-color:#f2f2f2;font-weight:bold;">';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr>';
    
    // Données
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Envoyer le fichier
    $pdf->Output('resultats_' . sanitizeFileName($examTitle) . '.pdf', 'D');
    exit();
}

/**
 * Nettoie un nom de fichier
 */
function sanitizeFileName($name) {
    $name = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $name);
    return substr($name, 0, 50);
}