<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';
include_once '../includes/db.php';

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Vérifier si les données sont valides
if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

// Traiter l'action
switch ($data['action']) {
    case 'login':
        // Vérifier les données de connexion
        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Identifiants manquants']);
            exit;
        }
        
        $username = $data['username'];
        $password = $data['password'];
        $remember = isset($data['remember']) ? (bool)$data['remember'] : false;
        
        // Authentifier l'utilisateur
        $user = authenticate_user($username, $password, $remember);
        
        if ($user) {
            // Succès
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            // Échec
            http_response_code(401);
            echo json_encode(['error' => 'Identifiants incorrects']);
        }
        break;
        
    case 'register':
        // Vérifier les données d'inscription
        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password']) || !isset($data['full_name']) || !isset($data['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données d\'inscription incomplètes']);
            exit;
        }
        
        // Valider l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email invalide']);
            exit;
        }
        
        // Valider le mot de passe
        if (strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Le mot de passe doit contenir au moins 8 caractères']);
            exit;
        }
        
        // Valider le rôle
        $allowed_roles = ['student', 'teacher', 'admin'];
        if (!in_array($data['role'], $allowed_roles)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rôle invalide']);
            exit;
        }
        
        // Enregistrer l'utilisateur
        $user_id = register_user([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'full_name' => $data['full_name'],
            'role' => $data['role']
        ]);
        
        if ($user_id) {
            // Succès
            echo json_encode([
                'success' => true,
                'user_id' => $user_id
            ]);
        } else {
            // Échec
            http_response_code(409);
            echo json_encode(['error' => 'Ce nom d\'utilisateur ou cette adresse email est déjà utilisé(e)']);
        }
        break;
        
    case 'logout':
        // Déconnecter l'utilisateur
        logout_user();
        echo json_encode(['success' => true]);
        break;
        
    case 'forgot_password':
        // Vérifier si l'email est fourni
        if (!isset($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email manquant']);
            exit;
        }
        
        // Générer un token de réinitialisation
        $token = generate_password_reset_token($data['email']);
        
        if ($token) {
            // Construire l'URL de réinitialisation
            $reset_url = SITE_URL . '/reset-password.php?token=' . $token;
            
            // Récupérer les informations de l'utilisateur
            $user_query = "SELECT full_name FROM users WHERE email = ?";
            $user = db_fetch_row($user_query, [$data['email']]);
            
            // Préparer l'email
            $subject = 'Réinitialisation de votre mot de passe - ' . SITE_NAME;
            $message = '
                <html>
                <head>
                    <title>Réinitialisation de votre mot de passe</title>
                </head>
                <body>
                    <p>Bonjour ' . htmlspecialchars($user['full_name']) . ',</p>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe sur ' . SITE_NAME . '.</p>
                    <p>Veuillez cliquer sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                    <p><a href="' . $reset_url . '">' . $reset_url . '</a></p>
                    <p>Ce lien est valable pendant 24 heures.</p>
                    <p>Si vous n\'avez pas demandé cette réinitialisation, veuillez ignorer cet email.</p>
                    <p>Cordialement,<br>L\'équipe ' . SITE_NAME . '</p>
                </body>
                </html>
            ';
            
            // Envoyer l'email
            if (send_email($data['email'], $subject, $message)) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erreur lors de l\'envoi de l\'email']);
            }
        } else {
            // L'email n'existe pas, mais pour des raisons de sécurité, nous ne le divulguons pas
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'reset_password':
        // Vérifier si le token et le nouveau mot de passe sont fournis
        if (!isset($data['token']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
        }
        
        // Valider le mot de passe
        if (strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Le mot de passe doit contenir au moins 8 caractères']);
            exit;
        }
        
        // Vérifier le token
        $user_id = verify_password_reset_token($data['token']);
        
        if ($user_id) {
            // Réinitialiser le mot de passe
            if (reset_password($user_id, $data['password'])) {
                // Supprimer le token
                delete_password_reset_token($data['token']);
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erreur lors de la réinitialisation du mot de passe']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Token invalide ou expiré']);
        }
        break;
        
    case 'change_password':
        // Vérifier si l'utilisateur est connecté
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        // Vérifier si les mots de passe sont fournis
        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            exit;
        }
        
        // Valider le nouveau mot de passe
        if (strlen($data['new_password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères']);
            exit;
        }
        
        // Changer le mot de passe
        if (change_password($_SESSION['user_id'], $data['current_password'], $data['new_password'])) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Mot de passe actuel incorrect']);
        }
        break;
        
    case 'update_profile':
        // Vérifier si l'utilisateur est connecté
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        // Préparer les données à mettre à jour
        $user_data = [];
        
        if (isset($data['full_name'])) {
            $user_data['full_name'] = $data['full_name'];
        }
        
        if (isset($data['email'])) {
            // Valider l'email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email invalide']);
                exit;
            }
            
            $user_data['email'] = $data['email'];
        }
        
        // Mettre à jour le profil
        if (update_user_profile($_SESSION['user_id'], $user_data)) {
            // Mettre à jour la session
            if (isset($user_data['full_name'])) {
                $_SESSION['full_name'] = $user_data['full_name'];
            }
            
            if (isset($user_data['email'])) {
                $_SESSION['email'] = $user_data['email'];
            }
            
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la mise à jour du profil']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action non reconnue']);
}
