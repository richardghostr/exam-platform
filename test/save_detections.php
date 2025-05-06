<?php
header('Content-Type: application/json');

// Récupérer les données JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['detections'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

// Préparer les données à enregistrer
$detections = [
    'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
    'detections' => $data['detections']
];

// Enregistrer dans un fichier JSON
$filename = 'detections_' . date('Ymd_His') . '.json';
try {
    file_put_contents($filename, json_encode($detections, JSON_PRETTY_PRINT));
    echo json_encode([
        'success' => true,
        'message' => 'Détections enregistrées',
        'file' => $filename,
        'count' => count($data['detections'])
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}  










