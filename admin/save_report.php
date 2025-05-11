<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès interdit']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Valider les données
$requiredFields = ['report_token', 'reportType', 'dateRange', 'format'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs manquants']);
        exit;
    }
}

// Déterminer les dates
$startDate = $endDate = date('Y-m-d');

if ($_POST['dateRange'] === 'custom') {
    if (empty($_POST['startDate']) || empty($_POST['endDate'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dates personnalisées manquantes']);
        exit;
    }
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
} else {
    $today = new DateTime();
    $endDate = $today->format('Y-m-d');
    
    $startDateObj = clone $today;
    switch ($_POST['dateRange']) {
        case '7days':
            $startDateObj->modify('-7 days');
            break;
        case '30days':
            $startDateObj->modify('-30 days');
            break;
        case '90days':
            $startDateObj->modify('-90 days');
            break;
        case 'year':
            $startDateObj->modify('-1 year');
            break;
    }
    $startDate = $startDateObj->format('Y-m-d');
}

// Créer le nom du rapport
$reportTypes = [
    'exam_results' => 'Résultats d\'examens',
    'user_activity' => 'Activité des utilisateurs',
    'proctoring_incidents' => 'Incidents de surveillance',
    'system_usage' => 'Utilisation du système'
];

$reportName = ($reportTypes[$_POST['reportType']] ?? 'Rapport') . ' - ' . 
              date('d/m/Y', strtotime($startDate)) . ' au ' . 
              date('d/m/Y', strtotime($endDate));

// Enregistrer le rapport dans la base de données
$query = "INSERT INTO generated_reports 
          (token, report_name, report_type, start_date, end_date, format, generated_at, generated_by) 
          VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";

$stmt = $conn->prepare($query);
$stmt->bind_param('ssssssi', 
    $_POST['report_token'],
    $reportName,
    $_POST['reportType'],
    $startDate,
    $endDate,
    $_POST['format'],
    $_SESSION['user_id']
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}