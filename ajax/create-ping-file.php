<?php
// Fichier pour créer le fichier ping.php s'il n'existe pas
header('Content-Type: application/json');

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier si le fichier ping.php existe déjà
if (!file_exists('ping.php')) {
    // Contenu du fichier ping.php
    $content = '<?php
// Fichier simple pour tester la connectivité au serveur
header(\'Content-Type: application/json\');

// Vérifier si la requête est en POST
if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
    echo json_encode([\'success\' => false, \'message\' => \'Méthode non autorisée\']);
    exit();
}

// Récupérer les données JSON
$json_data = file_get_contents(\'php://input\');
$data = json_decode($json_data, true);

// Répondre avec succès
echo json_encode([
    \'success\' => true,
    \'message\' => \'Connexion établie\',
    \'timestamp\' => date(\'Y-m-d H:i:s\'),
    \'received_data\' => $data
]);';

    // Écrire le fichier
    if (file_put_contents('ping.php', $content)) {
        echo json_encode(['success' => true, 'message' => 'Fichier ping.php créé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du fichier ping.php']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'Le fichier ping.php existe déjà']);
}
