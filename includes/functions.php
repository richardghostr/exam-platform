<?php
/**
 * Fonctions utilitaires pour la plateforme d'examens
 */

/**
 * Nettoie les données d'entrée
 * 
 * @param string $data Données à nettoyer
 * @return string Données nettoyées
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Vérifie si l'utilisateur est connecté
 * 
 * @return bool True si l'utilisateur est connecté, sinon False
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 * 
 * @param string|array $roles Rôle(s) à vérifier
 * @return bool True si l'utilisateur a le rôle spécifié, sinon False
 */
function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    } else {
        return $_SESSION['role'] === $roles;
    }
}

/**
 * Redirige vers une page si l'utilisateur n'est pas connecté
 * 
 * @param string $redirect_url URL de redirection
 */
function require_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Redirige vers une page si l'utilisateur n'a pas le rôle requis
 * 
 * @param string|array $roles Rôle(s) requis
 * @param string $redirect_url URL de redirection
 */
function require_role($roles, $redirect_url = 'index.php') {
    if (!has_role($roles)) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Génère un jeton CSRF
 * 
 * @return string Jeton CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie si le jeton CSRF est valide
 * 
 * @param string $token Jeton CSRF à vérifier
 * @return bool True si le jeton est valide, sinon False
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Formate une date
 * 
 * @param string $date Date à formater
 * @param string $format Format de sortie
 * @return string Date formatée
 */
function format_date($date, $format = 'd/m/Y H:i') {
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Génère une chaîne aléatoire
 * 
 * @param int $length Longueur de la chaîne
 * @return string Chaîne aléatoire
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Envoie un email
 * 
 * @param string $to Adresse email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $message Corps de l'email
 * @param array $headers En-têtes supplémentaires
 * @return bool True si l'email a été envoyé, sinon False
 */
function send_email($to, $subject, $message, $headers = []) {
    $default_headers = [
        'From' => EMAIL_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To' => EMAIL_FROM,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $headers = array_merge($default_headers, $headers);
    $header_string = '';
    
    foreach ($headers as $key => $value) {
        $header_string .= "$key: $value\r\n";
    }
    
    return mail($to, $subject, $message, $header_string);
}

/**
 * Affiche un message d'alerte
 * 
 * @param string $message Message à afficher
 * @param string $type Type d'alerte (success, danger, warning, info)
 * @return string HTML de l'alerte
 */
function alert($message, $type = 'info') {
    $icon = '';
    switch ($type) {
        case 'success':
            $icon = '<i class="fas fa-check-circle"></i>';
            break;
        case 'danger':
            $icon = '<i class="fas fa-exclamation-circle"></i>';
            break;
        case 'warning':
            $icon = '<i class="fas fa-exclamation-triangle"></i>';
            break;
        case 'info':
            $icon = '<i class="fas fa-info-circle"></i>';
            break;
    }
    return '<div class="alert alert-' . $type . '">' . $icon . ' ' . $message . '</div>';
}

/**
 * Tronque un texte à une longueur donnée
 * 
 * @param string $text Texte à tronquer
 * @param int $length Longueur maximale
 * @param string $suffix Suffixe à ajouter si le texte est tronqué
 * @return string Texte tronqué
 */
function truncate_text($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Génère une pagination
 * 
 * @param int $total_items Nombre total d'éléments
 * @param int $items_per_page Nombre d'éléments par page
 * @param int $current_page Page actuelle
 * @param string $url_pattern Modèle d'URL avec {page} comme placeholder
 * @return string HTML de la pagination
 */
function pagination($total_items, $items_per_page, $current_page, $url_pattern) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Bouton précédent
    if ($current_page > 1) {
        $prev_url = str_replace('{page}', $current_page - 1, $url_pattern);
        $html .= '<a href="' . $prev_url . '" class="pagination-prev"><i class="fas fa-chevron-left"></i> Précédent</a>';
    } else {
        $html .= '<span class="pagination-prev disabled"><i class="fas fa-chevron-left"></i> Précédent</span>';
    }
    
    // Pages
    $html .= '<div class="pagination-pages">';
    
    // Première page
    if ($current_page > 3) {
        $first_url = str_replace('{page}', 1, $url_pattern);
        $html .= '<a href="' . $first_url . '" class="pagination-page">1</a>';
        
        if ($current_page > 4) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
    }
    
    // Pages autour de la page actuelle
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $page_url = str_replace('{page}', $i, $url_pattern);
        
        if ($i == $current_page) {
            $html .= '<span class="pagination-page active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $page_url . '" class="pagination-page">' . $i . '</a>';
        }
    }
    
    // Dernière page
    if ($current_page < $total_pages - 2) {
        if ($current_page < $total_pages - 3) {
            $html .= '<span class="pagination-ellipsis">...</span>';
        }
        
        $last_url = str_replace('{page}', $total_pages, $url_pattern);
        $html .= '<a href="' . $last_url . '" class="pagination-page">' . $total_pages . '</a>';
    }
    
    $html .= '</div>';
    
    // Bouton suivant
    if ($current_page < $total_pages) {
        $next_url = str_replace('{page}', $current_page + 1, $url_pattern);
        $html .= '<a href="' . $next_url . '" class="pagination-next">Suivant <i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="pagination-next disabled">Suivant <i class="fas fa-chevron-right"></i></span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Vérifie si une chaîne est un JSON valide
 * 
 * @param string $string Chaîne à vérifier
 * @return bool True si la chaîne est un JSON valide, sinon False
 */
function is_json($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Génère un slug à partir d'une chaîne
 * 
 * @param string $string Chaîne à convertir en slug
 * @return string Slug
 */
function generate_slug($string) {
    // Remplacer les caractères accentués
    $string = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $string);
    // Remplacer les espaces par des tirets
    $string = preg_replace('/\s+/', '-', $string);
    // Supprimer les caractères spéciaux
    $string = preg_replace('/[^a-z0-9\-]/', '', $string);
    // Supprimer les tirets multiples
    $string = preg_replace('/-+/', '-', $string);
    // Supprimer les tirets au début et à la fin
    $string = trim($string, '-');
    
    return $string;
}

/**
 * Convertit une taille en octets en une chaîne lisible
 * 
 * @param int $bytes Taille en octets
 * @param int $precision Précision décimale
 * @return string Taille formatée
 */
function format_file_size($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Obtient l'extension d'un fichier
 * 
 * @param string $filename Nom du fichier
 * @return string Extension du fichier
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Vérifie si un fichier est une image
 * 
 * @param string $filename Nom du fichier
 * @return bool True si le fichier est une image, sinon False
 */
function is_image($filename) {
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $extension = get_file_extension($filename);
    
    return in_array($extension, $image_extensions);
}

/**
 * Génère un nom de fichier unique
 * 
 * @param string $original_filename Nom de fichier original
 * @return string Nom de fichier unique
 */
function generate_unique_filename($original_filename) {
    $extension = get_file_extension($original_filename);
    $basename = pathinfo($original_filename, PATHINFO_FILENAME);
    $basename = generate_slug($basename);
    
    return $basename . '_' . time() . '_' . substr(md5(rand()), 0, 6) . '.' . $extension;
}
// Define the isLoggedIn function if not already defined
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']); // Example logic, adjust as needed
    }
}

// Define the isAdmin function if not already defined
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; // Example logic, adjust as needed
    }
}
function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

/**
 * Définit un message flash qui sera affiché lors de la prochaine requête
 * 
 * @param string $type Type de message (success, danger, warning, info)
 * @param string $message Contenu du message
 * @return void
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Affiche tous les messages flash stockés en session
 * 
 * @return void
 */
function displayFlashMessages() {
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        echo '<div class="flash-messages">';
        
        foreach ($_SESSION['flash_messages'] as $message) {
            echo '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">';
            echo $message['message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Supprimer les messages après les avoir affichés
        unset($_SESSION['flash_messages']);
    }
}

/**
 * Retourne une classe CSS en fonction du type d'incident
 * 
 * @param string $incidentType Type d'incident (warning, moderate, severe)
 * @return string Classe CSS correspondante
 */
function getIncidentClass($incidentType) {
    switch ($incidentType) {
        case INCIDENT_WARNING:
            return 'warning';
        case INCIDENT_MODERATE:
            return 'info';
        case INCIDENT_SEVERE:
            return 'danger';
        default:
            return 'secondary';
    }
}
function safeRound($value, $precision = 0) {
    return round($value ?? 0, $precision);
}

function isStudent() {
   return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}


function logActivity($userId, $action, $entity, $entityId, $description) {
    global $conn;
    $query = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, entity, entity_id, description, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $query->bind_param("issis", $userId, $action, $entity, $entityId, $description);
    $query->execute();
}