<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès interdit');
}
// Au début du fichier, après la vérification d'authentification
if (isset($_GET['token'])) {
    // Vérifier si le rapport existe déjà
    $query = "SELECT * FROM generated_reports WHERE token = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $_GET['token']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existingReport = $result->fetch_assoc();
        $reportType = $existingReport['report_type'];
        $startDate = $existingReport['start_date'];
        $endDate = $existingReport['end_date'];
        $format = $existingReport['format'];
    }
}

// Le reste du fichier reste inchangé...
// Récupérer les paramètres
$reportType = $_GET['type'] ?? 'exam_results';
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'pdf';

// Valider les dates
if (!strtotime($startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));
if (!strtotime($endDate)) $endDate = date('Y-m-d');

// Fonction pour générer les données du rapport
function generateReportData($conn, $reportType, $startDate, $endDate) {
    $data = [];
    
    switch ($reportType) {
        case 'exam_results':
            $query = "SELECT 
                        e.title as exam_title,
                        s.name as subject,
                        u.username,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        er.score,
                        er.passed,
                        er.completed_at
                      FROM exam_results er
                      JOIN exams e ON er.exam_id = e.id
                      JOIN users u ON er.user_id = u.id
                      JOIN subjects s ON e.subject = s.id
                      WHERE er.completed_at BETWEEN ? AND ?
                      ORDER BY er.completed_at DESC";
            break;
            
        case 'user_activity':
            $query = "SELECT 
                        u.username,
                        CONCAT(u.first_name, ' ', u.last_name) as full_name,
                        u.role,
                        COUNT(DISTINCT ea.id) as exam_attempts,
                        COUNT(DISTINCT CASE WHEN er.passed = 1 THEN er.id END) as passed_exams,
                        MAX(al.created_at) as last_activity
                      FROM users u
                      LEFT JOIN exam_attempts ea ON u.id = ea.user_id
                      LEFT JOIN exam_results er ON ea.id = er.attempt_id
                      LEFT JOIN activity_logs al ON u.id = al.user_id
                      WHERE al.created_at BETWEEN ? AND ?
                      GROUP BY u.id
                      ORDER BY last_activity DESC";
            break;
            
        case 'proctoring_incidents':
            $query = "SELECT 
                        pi.id,
                        e.title as exam_title,
                        u.username,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        pi.incident_type,
                        pi.severity,
                        pi.description,
                        pi.timestamp,
                        pi.status
                      FROM proctoring_incidents pi
                      JOIN exam_attempts ea ON pi.attempt_id = ea.id
                      JOIN exams e ON ea.exam_id = e.id
                      JOIN users u ON ea.user_id = u.id
                      WHERE pi.timestamp BETWEEN ? AND ?
                      ORDER BY pi.timestamp DESC";
            break;
            
        case 'system_usage':
            $query = "SELECT 
                DATE(e.created_at) as date,
                COUNT(*) as exams_created,
                SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as exams_completed,
                COUNT(DISTINCT e.created_by) as active_users
              FROM exams e
              WHERE e.created_at BETWEEN ? AND ?
              GROUP BY DATE(e.created_at)
              ORDER BY date DESC";
            break;
            
        default:
            $query = "SELECT 
                        e.title as exam_title,
                        s.name as subject,
                        u.username,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        er.score,
                        er.passed,
                        er.completed_at
                      FROM exam_results er
                      JOIN exams e ON er.exam_id = e.id
                      JOIN users u ON er.user_id = u.id
                      JOIN subjects s ON e.subject = s.id
                      WHERE er.completed_at BETWEEN ? AND ?
                      ORDER BY er.completed_at DESC";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Générer les données du rapport
$reportData = generateReportData($conn, $reportType, $startDate, $endDate);

// Générer le rapport dans le format demandé
switch ($format) {
    case 'pdf':
        generatePdfReport($reportType, $reportData, $startDate, $endDate);
        break;
        
    case 'excel':
        generateExcelReport($reportType, $reportData, $startDate, $endDate);
        break;
        
    case 'csv':
        generateCsvReport($reportType, $reportData, $startDate, $endDate);
        break;
        
    default:
        generatePdfReport($reportType, $reportData, $startDate, $endDate);
}

// Fonction pour générer un rapport PDF
function generatePdfReport($reportType, $data, $startDate, $endDate) {
    require_once '../vendor/autoload.php'; // Chemin vers autoload.php de composer
    
    $mpdf = new \Mpdf\Mpdf();
    
    // En-tête du rapport
    $html = '<h1 style="text-align: center;">Rapport: ' . getReportTitle($reportType) . '</h1>';
    $html .= '<p style="text-align: center;">Période: ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate)) . '</p>';
    $html .= '<p style="text-align: center;">Généré le: ' . date('d/m/Y H:i') . '</p>';
    $html .= '<hr>';
    
    // Tableau des données
    $html .= '<table border="1" cellspacing="0" cellpadding="5" style="width: 100%; border-collapse: collapse;">';
    
    // En-têtes du tableau
    if (!empty($data)) {
        $html .= '<thead><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Données
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
    } else {
        $html .= '<tr><td colspan="' . count(array_keys($data[0] ?? [])) . '" style="text-align: center;">Aucune donnée disponible</td></tr>';
    }
    
    $html .= '</table>';
    
    // Pied de page
    $html .= '<hr>';
    $html .= '<p style="text-align: right; font-size: 0.8em;">Plateforme ExamSafe - ' . date('Y') . '</p>';
    
    $mpdf->WriteHTML($html);
    
    // Nom du fichier
    $filename = 'rapport_' . $reportType . '_' . date('Ymd_His') . '.pdf';
    
    // Envoyer le PDF au navigateur
    $mpdf->Output($filename, 'D');
    exit;
}

// Fonction pour générer un rapport Excel
function generateExcelReport($reportType, $data, $startDate, $endDate) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="rapport_' . $reportType . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    
    // Titre
    echo '<tr><th colspan="' . count(array_keys($data[0] ?? [])) . '" style="text-align: center; font-size: 16px;">';
    echo 'Rapport: ' . getReportTitle($reportType) . ' - Période: ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate));
    echo '</th></tr>';
    
    // En-têtes
    if (!empty($data)) {
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
        }
        echo '</tr>';
        
        // Données
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="' . count(array_keys($data[0] ?? [])) . '" style="text-align: center;">Aucune donnée disponible</td></tr>';
    }
    
    echo '</table>';
    exit;
}

// Fonction pour générer un rapport CSV
function generateCsvReport($reportType, $data, $startDate, $endDate) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="rapport_' . $reportType . '_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-tête du fichier
    fputcsv($output, ['Rapport: ' . getReportTitle($reportType)]);
    fputcsv($output, ['Période: ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate))]);
    fputcsv($output, ['Généré le: ' . date('d/m/Y H:i')]);
    fputcsv($output, []); // Ligne vide
    
    // En-têtes des colonnes
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Données
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['Aucune donnée disponible']);
    }
    
    fclose($output);
    exit;
}

// Fonction helper pour obtenir le titre du rapport
function getReportTitle($reportType) {
    $titles = [
        'exam_results' => 'Résultats d\'examens',
        'user_activity' => 'Activité des utilisateurs',
        'proctoring_incidents' => 'Incidents de surveillance',
        'system_usage' => 'Utilisation du système'
    ];
    
    return $titles[$reportType] ?? 'Rapport';
}