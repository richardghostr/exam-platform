<?php
/**
 * Fonctions d'authentification pour la plateforme d'examens
 */

/**
 * Authentifie un utilisateur
 * 
 * @param string $username Nom d'utilisateur ou email
 * @param string $password Mot de passe
 * @param bool $remember Option "Se souvenir de moi"
 * @return array|bool Données de l'utilisateur ou false si échec
 */
function authenticate_user($username, $password, $remember = false) {
    global $conn;
    
    // Préparer la requête pour éviter les injections SQL
    $stmt = $conn->prepare("SELECT id, username, password, email, full_name, role, status FROM users WHERE (username = ? OR email = ?)");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Vérifier si le compte est actif
        if ($user['status'] !== 'active') {
            return false;
        }
        
        // Vérifier le mot de passe
        if (password_verify($password, $user['password'])) {
            // Créer la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Mettre à jour la dernière connexion
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Gérer "Se souvenir de moi"
            if ($remember) {
                $token = generate_remember_token($user['id']);
                if ($token) {
                    set_remember_cookie($token);
                }
            }
            
            return $user;
        }
    }
    
    $stmt->close();
    return false;
}

/**
 * Déconnecte l'utilisateur
 */
function logout_user() {
    // Supprimer le token de "Se souvenir de moi" s'il existe
    if (isset($_COOKIE['remember_token'])) {
        delete_remember_token($_COOKIE['remember_token']);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Détruire la session
    session_unset();
    session_destroy();
}

/**
 * Génère un token "Se souvenir de moi" et l'enregistre en base de données
 * 
 * @param int $user_id ID de l'utilisateur
 * @return string|bool Token généré ou false si échec
 */
function generate_remember_token($user_id) {
    global $conn;
    
    // Générer un token unique
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    
    // Hacher le validator pour le stockage
    $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
    
    // Définir la date d'expiration (30 jours)
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
    
    // Supprimer les anciens tokens pour cet utilisateur
    $delete_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'remember'");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Enregistrer le nouveau token
    $insert_stmt = $conn->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, type, expires) VALUES (?, ?, ?, 'remember', ?)");
    $insert_stmt->bind_param("isss", $user_id, $selector, $hashed_validator, $expires);
    $result = $insert_stmt->execute();
    $insert_stmt->close();
    
    if ($result) {
        // Retourner le token complet (selector:validator)
        return $selector . ':' . $validator;
    }
    
    return false;
}

/**
 * Définit le cookie "Se souvenir de moi"
 * 
 * @param string $token Token à stocker dans le cookie
 */
function set_remember_cookie($token) {
    // Définir le cookie pour 30 jours
    setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
}

/**
 * Supprime un token "Se souvenir de moi" de la base de données
 * 
 * @param string $token Token à supprimer
 * @return bool True si succès, sinon False
 */
function delete_remember_token($token) {
    global $conn;
    
    // Extraire le selector du token
    $parts = explode(':', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    $selector = $parts[0];
    
    // Supprimer le token
    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE selector = ? AND type = 'remember'");
    $stmt->bind_param("s", $selector);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Vérifie et authentifie un utilisateur via le cookie "Se souvenir de moi"
 * 
 * @return bool True si l'authentification réussit, sinon False
 */
function check_remember_me() {
    global $conn;
    
    // Vérifier si le cookie existe
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    // Extraire le selector et le validator
    $parts = explode(':', $_COOKIE['remember_token']);
    if (count($parts) !== 2) {
        return false;
    }
    
    $selector = $parts[0];
    $validator = $parts[1];
    
    // Rechercher le token dans la base de données
    $stmt = $conn->prepare("
        SELECT ut.hashed_validator, u.id, u.username, u.email, u.full_name, u.role
        FROM user_tokens ut
        JOIN users u ON ut.user_id = u.id
        WHERE ut.selector = ? AND ut.type = 'remember' AND ut.expires > NOW() AND u.status = 'active'
    ");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Vérifier le validator
        if (password_verify($validator, $row['hashed_validator'])) {
            // Authentifier l'utilisateur
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role'] = $row['role'];
            
            // Mettre à jour la dernière connexion
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Générer un nouveau token pour prolonger la session
            $new_token = generate_remember_token($row['id']);
            if ($new_token) {
                set_remember_cookie($new_token);
            }
            
            $stmt->close();
            return true;
        }
    }
    
    $stmt->close();
    
    // Supprimer le cookie invalide
    setcookie('remember_token', '', time() - 3600, '/');
    return false;
}

/**
 * Enregistre un nouvel utilisateur
 * 
 * @param array $user_data Données de l'utilisateur
 * @return int|bool ID de l'utilisateur créé ou false si échec
 */
function register_user($user_data) {
    global $conn;
    
    // Vérifier si le nom d'utilisateur ou l'email existe déjà
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check_stmt->bind_param("ss", $user_data['username'], $user_data['email']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        return false;
    }
    
    $check_stmt->close();
    
    // Hacher le mot de passe
    $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
    
    // Insérer le nouvel utilisateur
    // $insert_stmt = $conn ->(PASSWORD_DEFAULT);
    
    // Insérer le nouvel utilisateur
    $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("sssss", $user_data['username'], $user_data['email'], $hashed_password, $user_data['full_name'], $user_data['role']);
    
    if ($insert_stmt->execute()) {
        $user_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        return $user_id;
    }
    
    $insert_stmt->close();
    return false;
}

/**
 * Réinitialise le mot de passe d'un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $new_password Nouveau mot de passe
 * @return bool True si succès, sinon False
 */
function reset_password($user_id, $new_password) {
    global $conn;
    
    // Hacher le nouveau mot de passe
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Génère un token de réinitialisation de mot de passe
 * 
 * @param string $email Email de l'utilisateur
 * @return string|bool Token généré ou false si échec
 */
function generate_password_reset_token($email) {
    global $conn;
    
    // Vérifier si l'email existe
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        return false;
    }
    
    $user = $check_result->fetch_assoc();
    $user_id = $user['id'];
    $check_stmt->close();
    
    // Générer un token unique
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    
    // Hacher le validator pour le stockage
    $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
    
    // Définir la date d'expiration (24 heures)
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24);
    
    // Supprimer les anciens tokens pour cet utilisateur
    $delete_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'password_reset'");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Enregistrer le nouveau token
    $insert_stmt = $conn->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, type, expires) VALUES (?, ?, ?, 'password_reset', ?)");
    $insert_stmt->bind_param("isss", $user_id, $selector, $hashed_validator, $expires);
    $result = $insert_stmt->execute();
    $insert_stmt->close();
    
    if ($result) {
        // Retourner le token complet (selector:validator)
        return $selector . ':' . $validator;
    }
    
    return false;
}

/**
 * Vérifie un token de réinitialisation de mot de passe
 * 
 * @param string $token Token à vérifier
 * @return int|bool ID de l'utilisateur ou false si échec
 */
function verify_password_reset_token($token) {
    global $conn;
    
    // Extraire le selector et le validator
    $parts = explode(':', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    $selector = $parts[0];
    $validator = $parts[1];
    
    // Rechercher le token dans la base de données
    $stmt = $conn->prepare("
        SELECT ut.user_id, ut.hashed_validator
        FROM user_tokens ut
        JOIN users u ON ut.user_id = u.id
        WHERE ut.selector = ? AND ut.type = 'password_reset' AND ut.expires > NOW() AND u.status = 'active'
    ");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Vérifier le validator
        if (password_verify($validator, $row['hashed_validator'])) {
            $stmt->close();
            return $row['user_id'];
        }
    }
    
    $stmt->close();
    return false;
}

/**
 * Supprime un token de réinitialisation de mot de passe
 * 
 * @param string $token Token à supprimer
 * @return bool True si succès, sinon False
 */
function delete_password_reset_token($token) {
    global $conn;
    
    // Extraire le selector du token
    $parts = explode(':', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    $selector = $parts[0];
    
    // Supprimer le token
    $stmt = $conn->prepare("DELETE FROM user_tokens WHERE selector = ? AND type = 'password_reset'");
    $stmt->bind_param("s", $selector);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Met à jour le profil d'un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @param array $user_data Données à mettre à jour
 * @return bool True si succès, sinon False
 */
function update_user_profile($user_id, $user_data) {
    global $conn;
    
    // Construire la requête de mise à jour
    $query = "UPDATE users SET ";
    $params = [];
    $types = "";
    
    // Ajouter les champs à mettre à jour
    if (isset($user_data['full_name'])) {
        $query .= "full_name = ?, ";
        $params[] = $user_data['full_name'];
        $types .= "s";
    }
    
    if (isset($user_data['email'])) {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $user_data['email'], $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            return false;
        }
        
        $check_stmt->close();
        
        $query .= "email = ?, ";
        $params[] = $user_data['email'];
        $types .= "s";
    }
    
    if (isset($user_data['profile_image'])) {
        $query .= "profile_image = ?, ";
        $params[] = $user_data['profile_image'];
        $types .= "s";
    }
    
    // Supprimer la virgule finale
    $query = rtrim($query, ", ");
    
    // Ajouter la condition WHERE
    $query .= " WHERE id = ?";
    $params[] = $user_id;
    $types .= "i";
    
    // Exécuter la requête
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Change le mot de passe d'un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $current_password Mot de passe actuel
 * @param string $new_password Nouveau mot de passe
 * @return bool True si succès, sinon False
 */
function change_password($user_id, $current_password, $new_password) {
    global $conn;
    
    // Vérifier le mot de passe actuel
    $check_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 1) {
        $user = $check_result->fetch_assoc();
        
        // Vérifier si le mot de passe actuel est correct
        if (password_verify($current_password, $user['password'])) {
            $check_stmt->close();
            
            // Mettre à jour le mot de passe
            return reset_password($user_id, $new_password);
        }
    }
    
    $check_stmt->close();
    return false;
}
