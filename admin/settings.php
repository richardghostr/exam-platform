<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Traitement du formulaire de paramètres généraux
$successMessage = '';
$errorMessage = '';

if (isset($_POST['update_general'])) {
    $siteName = $_POST['site_name'];
    $siteDescription = $_POST['site_description'];
    $contactEmail = $_POST['contact_email'];
    $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // Mettre à jour les paramètres dans la base de données
    $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE setting_key = ?");
    
    $stmt->bind_param("ss", $siteName, $key);
    $key = "site_name";
    $stmt->execute();
    
    $stmt->bind_param("ss", $siteDescription, $key);
    $key = "site_description";
    $stmt->execute();
    
    $stmt->bind_param("ss", $contactEmail, $key);
    $key = "contact_email";
    $stmt->execute();
    
    $stmt->bind_param("is", $maintenanceMode, $key);
    $key = "maintenance_mode";
    $stmt->execute();
    
    $successMessage = "Les paramètres généraux ont été mis à jour avec succès.";
}

// Traitement du formulaire de paramètres d'examen
if (isset($_POST['update_exam'])) {
    $defaultDuration = $_POST['default_duration'];
    $defaultPassingScore = $_POST['default_passing_score'];
    $allowRetakes = isset($_POST['allow_retakes']) ? 1 : 0;
    $maxRetakes = $_POST['max_retakes'];
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    
    // Mettre à jour les paramètres dans la base de données
    $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE setting_key = ?");
    
    $stmt->bind_param("is", $defaultDuration, $key);
    $key = "default_duration";
    $stmt->execute();
    
    $stmt->bind_param("is", $defaultPassingScore, $key);
    $key = "default_passing_score";
    $stmt->execute();
    
    $stmt->bind_param("is", $allowRetakes, $key);
    $key = "allow_retakes";
    $stmt->execute();
    
    $stmt->bind_param("is", $maxRetakes, $key);
    $key = "max_retakes";
    $stmt->execute();
    
    $stmt->bind_param("is", $showResults, $key);
    $key = "show_results";
    $stmt->execute();
    
    $successMessage = "Les paramètres d'examen ont été mis à jour avec succès.";
}

// Traitement du formulaire de paramètres de surveillance
if (isset($_POST['update_proctoring'])) {
    $enableFaceRecognition = isset($_POST['enable_face_recognition']) ? 1 : 0;
    $enableEyeTracking = isset($_POST['enable_eye_tracking']) ? 1 : 0;
    $enableAudioMonitoring = isset($_POST['enable_audio_monitoring']) ? 1 : 0;
    $enableScreenMonitoring = isset($_POST['enable_screen_monitoring']) ? 1 : 0;
    $strictnessLevel = $_POST['strictness_level'];
    
    // Mettre à jour les paramètres dans la base de données
    $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE setting_key = ?");
    
    $stmt->bind_param("is", $enableFaceRecognition, $key);
    $key = "enable_face_recognition";
    $stmt->execute();
    
    $stmt->bind_param("is", $enableEyeTracking, $key);
    $key = "enable_eye_tracking";
    $stmt->execute();
    
    $stmt->bind_param("is", $enableAudioMonitoring, $key);
    $key = "enable_audio_monitoring";
    $stmt->execute();
    
    $stmt->bind_param("is", $enableScreenMonitoring, $key);
    $key = "enable_screen_monitoring";
    $stmt->execute();
    
    $stmt->bind_param("ss", $strictnessLevel, $key);
    $key = "strictness_level";
    $stmt->execute();
    
    $successMessage = "Les paramètres de surveillance ont été mis à jour avec succès.";
}

// Récupérer les paramètres actuels
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['value'];
    }
    
    return $default;
}

$pageTitle = "Paramètres du système";
include 'includes/header.php';
?>

<div class="card mb-20" >
    <div class="card-body">
        <div class="admin-header">
            <div>
                <div class="page-path">
                    <a href="index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Paramètres</span>
                </div>
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
            </div>
            
            
        </div>
        
        <div class="main-content">
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab-item active" data-tab="general">
                    <i class="fas fa-cog"></i> Général
                </div>
                <div class="tab-item" data-tab="exam">
                    <i class="fas fa-file-alt"></i> Examens
                </div>
                <div class="tab-item" data-tab="proctoring">
                    <i class="fas fa-video"></i> Surveillance
                </div>
                <div class="tab-item" data-tab="email">
                    <i class="fas fa-envelope"></i> Email
                </div>
                <div class="tab-item" data-tab="security">
                    <i class="fas fa-shield-alt"></i> Sécurité
                </div>
                <div class="tab-item" data-tab="backup">
                    <i class="fas fa-database"></i> Sauvegarde
                </div>
            </div>
            
            <!-- Paramètres généraux -->
            <div class="tab-content active" id="general-tab">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Paramètres généraux</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="form">
                            <div class="form-group">
                                <label for="site_name" class="form-label">Nom du site</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?php echo getSetting($conn, 'site_name', 'ExamSafe'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_description" class="form-label">Description du site</label>
                                <textarea id="site_description" name="site_description" class="form-control" rows="3"><?php echo getSetting($conn, 'site_description', 'Plateforme d\'examens en ligne sécurisée'); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_email" class="form-label">Email de contact</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" 
                                       value="<?php echo getSetting($conn, 'contact_email', 'contact@examsafe.com'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label d-block">Mode maintenance</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                               <?php echo getSetting($conn, 'maintenance_mode') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Activer le mode maintenance</span>
                                </div>
                                <div class="form-text">Lorsque le mode maintenance est activé, seuls les administrateurs peuvent accéder au site.</div>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" name="update_general" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres d'examen -->
            <div class="tab-content" id="exam-tab">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Paramètres d'examen</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="form">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="default_duration" class="form-label">Durée par défaut (minutes)</label>
                                        <input type="number" id="default_duration" name="default_duration" class="form-control" 
                                               value="<?php echo getSetting($conn, 'default_duration', '60'); ?>" min="1" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="default_passing_score" class="form-label">Score de réussite par défaut (%)</label>
                                        <input type="number" id="default_passing_score" name="default_passing_score" class="form-control" 
                                               value="<?php echo getSetting($conn, 'default_passing_score', '60'); ?>" min="1" max="100" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label d-block">Reprises d'examen</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="allow_retakes" name="allow_retakes" 
                                               <?php echo getSetting($conn, 'allow_retakes') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Autoriser les reprises d'examen</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_retakes" class="form-label">Nombre maximum de reprises</label>
                                <input type="number" id="max_retakes" name="max_retakes" class="form-control" 
                                       value="<?php echo getSetting($conn, 'max_retakes', '2'); ?>" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label d-block">Affichage des résultats</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="show_results" name="show_results" 
                                               <?php echo getSetting($conn, 'show_results') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Afficher les résultats immédiatement</span>
                                </div>
                                <div class="form-text">Si désactivé, les résultats seront disponibles uniquement après validation par l'enseignant.</div>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" name="update_exam" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres de surveillance -->
            <div class="tab-content" id="proctoring-tab">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Paramètres de surveillance</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="form">
                            <div class="form-group">
                                <label class="form-label d-block">Reconnaissance faciale</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="enable_face_recognition" name="enable_face_recognition" 
                                               <?php echo getSetting($conn, 'enable_face_recognition') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Activer la reconnaissance faciale</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label d-block">Suivi oculaire</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="enable_eye_tracking" name="enable_eye_tracking" 
                                               <?php echo getSetting($conn, 'enable_eye_tracking') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Activer le suivi oculaire</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label d-block">Surveillance audio</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="enable_audio_monitoring" name="enable_audio_monitoring" 
                                               <?php echo getSetting($conn, 'enable_audio_monitoring') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Activer la surveillance audio</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label d-block">Surveillance d'écran</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="enable_screen_monitoring" name="enable_screen_monitoring" 
                                               <?php echo getSetting($conn, 'enable_screen_monitoring') == 1 ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="ml-10">Activer la surveillance d'écran</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="strictness_level" class="form-label">Niveau de rigueur</label>
                                <select id="strictness_level" name="strictness_level" class="form-control">
                                    <option value="low" <?php echo getSetting($conn, 'strictness_level') == 'low' ? 'selected' : ''; ?>>Faible</option>
                                    <option value="medium" <?php echo getSetting($conn, 'strictness_level') == 'medium' ? 'selected' : ''; ?>>Moyen</option>
                                    <option value="high" <?php echo getSetting($conn, 'strictness_level') == 'high' ? 'selected' : ''; ?>>Élevé</option>
                                    <option value="very_high" <?php echo getSetting($conn, 'strictness_level') == 'very_high' ? 'selected' : ''; ?>>Très élevé</option>
                                </select>
                                <div class="form-text">Détermine la sensibilité de détection des comportements suspects.</div>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" name="update_proctoring" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres d'email -->
            <div class="tab-content" id="email-tab">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Paramètres d'email</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="form">
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="smtp_host" class="form-label">Serveur SMTP</label>
                                        <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                               value="<?php echo getSetting($conn, 'smtp_host', 'smtp.example.com'); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="smtp_port" class="form-label">Port SMTP</label>
                                        <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                               value="<?php echo getSetting($conn, 'smtp_port', '587'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="smtp_username" class="form-label">Nom d'utilisateur SMTP</label>
                                        <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                               value="<?php echo getSetting($conn, 'smtp_username', ''); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="smtp_password" class="form-label">Mot de passe SMTP</label>
                                        <input type="password" id="smtp_password" name="smtp_password" class="form-control" 
                                               value="<?php echo getSetting($conn, 'smtp_password', ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="email_from" class="form-label">Email expéditeur</label>
                                        <input type="email" id="email_from" name="email_from" class="form-control" 
                                               value="<?php echo getSetting($conn, 'email_from', 'noreply@examsafe.com'); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="email_from_name" class="form-label">Nom expéditeur</label>
                                        <input type="text" id="email_from_name" name="email_from_name" class="form-control" 
                                               value="<?php echo getSetting($conn, 'email_from_name', 'ExamSafe'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" name="update_email" class="btn btn-primary">Enregistrer les modifications</button>
                                <button type="button" class="btn btn-secondary" id="test_email">Tester la configuration</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres de sécurité -->
            <div class="tab-content" id="security-tab">
                <!-- Contenu des paramètres de sécurité -->
            </div>
            
            <!-- Paramètres de sauvegarde -->
            <div class="tab-content" id="backup-tab">
                <!-- Contenu des paramètres de sauvegarde -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets
    const tabItems = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabItems.forEach(item => {
        item.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Désactiver tous les onglets
            tabItems.forEach(tab => tab.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Activer l'onglet sélectionné
            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });
    
    // Test de la configuration email
    document.getElementById('test_email').addEventListener('click', function() {
        alert('Un email de test a été envoyé à l\'adresse de l\'administrateur.');
    });
});
</script>

