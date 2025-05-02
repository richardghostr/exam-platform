<?php
// Configuration de base
define('SITE_NAME', 'ExamSafe');
define('SITE_URL', 'http://localhost/exam-platform/');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'exam_platform');

// Configuration des emails
define('EMAIL_FROM', 'noreply@examsafe.com');
define('EMAIL_NAME', 'ExamSafe');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1); // Mettre à 0 en production

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    
// Configuration de la session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Mettre à 1 en production avec HTTPS

    session_start();
}

// Types d'utilisateurs
define('USER_ADMIN', 'admin');
define('USER_TEACHER', 'teacher');
define('USER_STUDENT', 'student');
// Chemins de l'application
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');
// Types d'incidents
define('INCIDENT_WARNING', 'warning');
define('INCIDENT_MODERATE', 'moderate');
define('INCIDENT_SEVERE', 'severe');
// Statuts d'examen
define('EXAM_DRAFT', 'draft');
define('EXAM_ACTIVE', 'active');
define('EXAM_COMPLETED', 'completed');
define('EXAM_ARCHIVED', 'archived');

// Types de questions
define('QUESTION_MCQ', 'mcq');
define('QUESTION_TRUE_FALSE', 'true_false');
define('QUESTION_SHORT_ANSWER', 'short_answer');
define('QUESTION_ESSAY', 'essay');
// Statuts d'examen

define('QUESTION_MATCHING', 'matching');


// Créer les dossiers nécessaires s'ils n'existent pas
$directories = [
    UPLOADS_PATH,
    UPLOADS_PATH . '/screenshots',
    UPLOADS_PATH . '/certificates',
    UPLOADS_PATH . '/profile_images'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
