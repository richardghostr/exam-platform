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

if (!isset($_GET['token'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Token manquant');
}

$token = $_GET['token'];

// Récupérer les informations du rapport
$query = "SELECT * FROM generated_reports WHERE token = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Rapport non trouvé');
}

$report = $result->fetch_assoc();

// Reconstruire les paramètres pour régénérer le rapport
$params = [
    'type' => $report['report_type'],
    'start' => $report['start_date'],
    'end' => $report['end_date'],
    'format' => $report['format']
];

// Rediriger vers le générateur de rapport avec les mêmes paramètres
header('Location: generate_report.php?' . http_build_query($params));
exit;