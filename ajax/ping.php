<?php
// Fichier simple pour tester la connectivité au serveur
header('Content-Type: application/json');

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Répondre avec succès
echo json_encode([
    'success' => true,
    'message' => 'Connexion établie',
    'timestamp' => date('Y-m-d H:i:s'),
    'received_data' => $data
]);
