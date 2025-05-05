<?php
header('Content-Type: application/json');

// Vérifier si les données sont reçues
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['gazeData']) || empty($data['gazeData'])) {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée reçue.']);
    exit;
}

$gazeData = $data['gazeData'];
$filename = 'gaze_data_' . date('Y-m-d_H-i-s') . '.json';

// Enregistrer les données dans un fichier JSON
try {
    file_put_contents($filename, json_encode($gazeData, JSON_PRETTY_PRINT));
    echo json_encode([
        'success' => true,
        'message' => 'Données enregistrées avec succès.',
        'file' => $filename
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
    ]);
}