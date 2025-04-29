<?php
// Inclure la configuration si ce n'est pas déjà fait
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

// Connexion à la base de données
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion à la base de données: " . $conn->connect_error);
}

// Définir le jeu de caractères
$conn->set_charset("utf8mb4");

/**
 * Fonction pour exécuter une requête préparée et retourner le résultat
 * 
 * @param string $query Requête SQL avec placeholders
 * @param array $params Paramètres pour la requête
 * @param string $types Types des paramètres (i: integer, s: string, d: double, b: blob)
 * @return mixed Résultat de la requête ou false en cas d'erreur
 */
function db_query($query, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Erreur de préparation de la requête: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            // Déterminer automatiquement les types
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Erreur d'exécution de la requête: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

/**
 * Fonction pour récupérer une seule ligne de résultat
 * 
 * @param string $query Requête SQL avec placeholders
 * @param array $params Paramètres pour la requête
 * @param string $types Types des paramètres
 * @return array|null Ligne de résultat ou null si aucun résultat
 */
function db_fetch_row($query, $params = [], $types = '') {
    $result = db_query($query, $params, $types);
    
    if (!$result) {
        return null;
    }
    
    $row = $result->fetch_assoc();
    $result->free();
    
    return $row;
}

/**
 * Fonction pour récupérer toutes les lignes de résultat
 * 
 * @param string $query Requête SQL avec placeholders
 * @param array $params Paramètres pour la requête
 * @param string $types Types des paramètres
 * @return array Tableau de lignes de résultat
 */
function db_fetch_all($query, $params = [], $types = '') {
    $result = db_query($query, $params, $types);
    
    if (!$result) {
        return [];
    }
    
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    
    return $rows;
}

/**
 * Fonction pour insérer des données et récupérer l'ID généré
 * 
 * @param string $query Requête SQL avec placeholders
 * @param array $params Paramètres pour la requête
 * @param string $types Types des paramètres
 * @return int|false ID généré ou false en cas d'erreur
 */
function db_insert($query, $params = [], $types = '') {
    global $conn;
    
    $result = db_query($query, $params, $types);
    
    if ($result === false) {
        return false;
    }
    
    return $conn->insert_id;
}

/**
 * Fonction pour mettre à jour des données et récupérer le nombre de lignes affectées
 * 
 * @param string $query Requête SQL avec placeholders
 * @param array $params Paramètres pour la requête
 * @param string $types Types des paramètres
 * @return int|false Nombre de lignes affectées ou false en cas d'erreur
 */
function db_update($query, $params = [], $types = '') {
    global $conn;
    
    $result = db_query($query, $params, $types);
    
    if ($result === false) {
        return false;
    }
    
    return $conn->affected_rows;
}

/**
 * Fonction pour échapper une chaîne de caractères
 * 
 * @param string $string Chaîne à échapper
 * @return string Chaîne échappée
 */
function db_escape($string) {
    global $conn;
    return $conn->real_escape_string($string);
}
