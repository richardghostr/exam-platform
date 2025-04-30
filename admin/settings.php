<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

// Traitement du formulaire de paramètres généraux
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
    
    $stmt->bind_param("is", $strictnessLevel, $key);
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

<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <h1><?php echo $pageTitle; ?></h1>
        </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-tabs">
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab">
                        <i class="fas fa-cog"></i> Général
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="exam-tab" data-toggle="tab" href="#exam" role="tab">
                        <i class="fas fa-file-alt"></i> Examens
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="proctoring-tab" data-toggle="tab" href="#proctoring" role="tab">
                        <i class="fas fa-video"></i> Surveillance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="email-tab" data-toggle="tab" href="#email" role="tab">
                        <i class="fas fa-envelope"></i> Email
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                        <i class="fas fa-shield-alt"></i> Sécurité
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="backup-tab" data-toggle="tab" href="#backup" role="tab">
                        <i class="fas fa-database"></i> Sauvegarde
                    </a>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabsContent">
                <!-- Paramètres généraux -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="settings-card">
                        <h2>Paramètres généraux</h2>
                        <form method="post" action="" class="admin-form">
                            <div class="form-group">
                                <label for="site_name">Nom du site</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?php echo getSetting($conn, 'site_name', 'ExamSafe'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_description">Description du site</label>
                                <textarea id="site_description" name="site_description" class="form-control" rows="3"><?php echo getSetting($conn, 'site_description', 'Plateforme d\'examens en ligne sécurisée'); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_email">Email de contact</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" 
                                       value="<?php echo getSetting($conn, 'contact_email', 'contact@examsafe.com'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode" 
                                           <?php echo getSetting($conn, 'maintenance_mode') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="maintenance_mode">Mode maintenance</label>
                                </div>
                                <small class="form-text text-muted">Lorsque le mode maintenance est activé, seuls les administrateurs peuvent accéder au site.</small>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_general" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Paramètres d'examen -->
                <div class="tab-pane fade" id="exam" role="tabpanel">
                    <div class="settings-card">
                        <h2>Paramètres d'examen</h2>
                        <form method="post" action="" class="admin-form">
                            <div class="form-group">
                                <label for="default_duration">Durée par défaut (minutes)</label>
                                <input type="number" id="default_duration" name="default_duration" class="form-control" 
                                       value="<?php echo getSetting($conn, 'default_duration', '60'); ?>" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="default_passing_score">Score de réussite par défaut (%)</label>
                                <input type="number" id="default_passing_score" name="default_passing_score" class="form-control" 
                                       value="<?php echo getSetting($conn, 'default_passing_score', '60'); ?>" min="1" max="100" required>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="allow_retakes" name="allow_retakes" 
                                           <?php echo getSetting($conn, 'allow_retakes') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="allow_retakes">Autoriser les reprises d'examen</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_retakes">Nombre maximum de reprises</label>
                                <input type="number" id="max_retakes" name="max_retakes" class="form-control" 
                                       value="<?php echo getSetting($conn, 'max_retakes', '2'); ?>" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="show_results" name="show_results" 
                                           <?php echo getSetting($conn, 'show_results') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="show_results">Afficher les résultats immédiatement</label>
                                </div>
                                <small class="form-text text-muted">Si désactivé, les résultats seront disponibles uniquement après validation par l'enseignant.</small>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_exam" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Paramètres de surveillance -->
                <div class="tab-pane fade" id="proctoring" role="tabpanel">
                    <div class="settings-card">
                        <h2>Paramètres de surveillance</h2>
                        <form method="post" action="" class="admin-form">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enable_face_recognition" name="enable_face_recognition" 
                                           <?php echo getSetting($conn, 'enable_face_recognition') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="enable_face_recognition">Activer la reconnaissance faciale</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enable_eye_tracking" name="enable_eye_tracking" 
                                           <?php echo getSetting($conn, 'enable_eye_tracking') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="enable_eye_tracking">Activer le suivi oculaire</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enable_audio_monitoring" name="enable_audio_monitoring" 
                                           <?php echo getSetting($conn, 'enable_audio_monitoring') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="enable_audio_monitoring">Activer la surveillance audio</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enable_screen_monitoring" name="enable_screen_monitoring" 
                                           <?php echo getSetting($conn, 'enable_screen_monitoring') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="enable_screen_monitoring">Activer la surveillance d'écran</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="strictness_level">Niveau de rigueur</label>
                                <select id="strictness_level" name="strictness_level" class="form-control">
                                    <option value="low" <?php echo getSetting($conn, 'strictness_level') == 'low' ? 'selected' : ''; ?>>Faible</option>
                                    <option value="medium" <?php echo getSetting($conn, 'strictness_level') == 'medium' ? 'selected' : ''; ?>>Moyen</option>
                                      == 'medium' ? 'selected' : ''; ?>>Moyen</option>
                                    <option value="high" <?php echo getSetting($conn, 'strictness_level') == 'high' ? 'selected' : ''; ?>>Élevé</option>
                                    <option value="very_high" <?php echo getSetting($conn, 'strictness_level') == 'very_high' ? 'selected' : ''; ?>>Très élevé</option>
                                </select>
                                <small class="form-text text-muted">Détermine la sensibilité de détection des comportements suspects.</small>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_proctoring" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Paramètres d'email -->
                <div class="tab-pane fade" id="email" role="tabpanel">
                    <div class="settings-card">
                        <h2>Paramètres d'email</h2>
                        <form method="post" action="" class="admin-form">
                            <div class="form-group">
                                <label for="smtp_host">Serveur SMTP</label>
                                <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                       value="<?php echo getSetting($conn, 'smtp_host', 'smtp.example.com'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port">Port SMTP</label>
                                <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                       value="<?php echo getSetting($conn, 'smtp_port', '587'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_username">Nom d'utilisateur SMTP</label>
                                <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                       value="<?php echo getSetting($conn, 'smtp_username', ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_password">Mot de passe SMTP</label>
                                <input type="password" id="smtp_password" name="smtp_password" class="form-control" 
                                       value="<?php echo getSetting($conn, 'smtp_password', ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email_from">Email expéditeur</label>
                                <input type="email" id="email_from" name="email_from" class="form-control" 
                                       value="<?php echo getSetting($conn, 'email_from', 'noreply@examsafe.com'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email_from_name">Nom expéditeur</label>
                                <input type="text" id="email_from_name" name="email_from_name" class="form-control" 
                                       value="<?php echo getSetting($conn, 'email_from_name', 'ExamSafe'); ?>">
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_email" class="btn btn-primary">Enregistrer les modifications</button>
                                <button type="button" class="btn btn-secondary" id="test_email">Tester la configuration</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Paramètres de sécurité -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="settings-card">
                        <h2>Paramètres de sécurité</h2>
                        <form method="post" action="" class="admin-form">
                            <div class="form-group">
                                <label for="session_timeout">Délai d'expiration de session (minutes)</label>
                                <input type="number" id="session_timeout" name="session_timeout" class="form-control" 
                                       value="<?php echo getSetting($conn, 'session_timeout', '30'); ?>" min="1">
                            </div>
                            
                            <div class="form-group">
                                <label for="max_login_attempts">Nombre maximum de tentatives de connexion</label>
                                <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control" 
                                       value="<?php echo getSetting($conn, 'max_login_attempts', '5'); ?>" min="1">
                            </div>
                            
                            <div class="form-group">
                                <label for="lockout_time">Durée de verrouillage (minutes)</label>
                                <input type="number" id="lockout_time" name="lockout_time" class="form-control" 
                                       value="<?php echo getSetting($conn, 'lockout_time', '15'); ?>" min="1">
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enable_2fa" name="enable_2fa" 
                                           <?php echo getSetting($conn, 'enable_2fa') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="enable_2fa">Activer l'authentification à deux facteurs</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="force_password_change" name="force_password_change" 
                                           <?php echo getSetting($conn, 'force_password_change') == 1 ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="force_password_change">Forcer le changement de mot de passe périodiquement</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_expiry_days">Expiration du mot de passe (jours)</label>
                                <input type="number" id="password_expiry_days" name="password_expiry_days" class="form-control" 
                                       value="<?php echo getSetting($conn, 'password_expiry_days', '90'); ?>" min="1">
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_security" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Paramètres de sauvegarde -->
                <div class="tab-pane fade" id="backup" role="tabpanel">
                    <div class="settings-card">
                        <h2>Sauvegarde et restauration</h2>
                        <div class="backup-actions">
                            <div class="backup-section">
                                <h3>Créer une sauvegarde</h3>
                                <p>Créez une sauvegarde complète de la base de données et des fichiers du système.</p>
                                <button type="button" class="btn btn-primary" id="create_backup">
                                    <i class="fas fa-download"></i> Créer une sauvegarde
                                </button>
                            </div>
                            
                            <div class="backup-section">
                                <h3>Sauvegardes automatiques</h3>
                                <form method="post" action="" class="admin-form">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="enable_auto_backup" name="enable_auto_backup" 
                                                   <?php echo getSetting($conn, 'enable_auto_backup') == 1 ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="enable_auto_backup">Activer les sauvegardes automatiques</label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="backup_frequency">Fréquence de sauvegarde</label>
                                        <select id="backup_frequency" name="backup_frequency" class="form-control">
                                            <option value="daily" <?php echo getSetting($conn, 'backup_frequency') == 'daily' ? 'selected' : ''; ?>>Quotidienne</option>
                                            <option value="weekly" <?php echo getSetting($conn, 'backup_frequency') == 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                                            <option value="monthly" <?php echo getSetting($conn, 'backup_frequency') == 'monthly' ? 'selected' : ''; ?>>Mensuelle</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="backup_retention">Conservation des sauvegardes (jours)</label>
                                        <input type="number" id="backup_retention" name="backup_retention" class="form-control" 
                                               value="<?php echo getSetting($conn, 'backup_retention', '30'); ?>" min="1">
                                    </div>
                                    
                                    <div class="form-buttons">
                                        <button type="submit" name="update_backup" class="btn btn-primary">Enregistrer les modifications</button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="backup-section">
                                <h3>Restaurer une sauvegarde</h3>
                                <p>Restaurez le système à partir d'une sauvegarde précédente.</p>
                                <form method="post" action="" enctype="multipart/form-data" class="admin-form">
                                    <div class="form-group">
                                        <label for="backup_file">Fichier de sauvegarde</label>
                                        <input type="file" id="backup_file" name="backup_file" class="form-control-file">
                                    </div>
                                    
                                    <div class="form-buttons">
                                        <button type="submit" name="restore_backup" class="btn btn-warning">
                                            <i class="fas fa-upload"></i> Restaurer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="backup-history">
                            <h3>Historique des sauvegardes</h3>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Nom du fichier</th>
                                            <th>Date de création</th>
                                            <th>Taille</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>backup_2023-04-15.zip</td>
                                            <td>15/04/2023 08:30</td>
                                            <td>25.4 MB</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Télécharger</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> Restaurer</button>
                                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Supprimer</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>backup_2023-04-08.zip</td>
                                            <td>08/04/2023 08:30</td>
                                            <td>24.8 MB</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Télécharger</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> Restaurer</button>
                                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Supprimer</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>backup_2023-04-01.zip</td>
                                            <td>01/04/2023 08:30</td>
                                            <td>24.2 MB</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Télécharger</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> Restaurer</button>
                                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Supprimer</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test de la configuration email
    document.getElementById('test_email').addEventListener('click', function() {
        alert('Un email de test a été envoyé à l\'adresse de l\'administrateur.');
    });
    
    // Création d'une sauvegarde
    document.getElementById('create_backup').addEventListener('click', function() {
        alert('La sauvegarde est en cours de création. Vous serez notifié lorsqu\'elle sera terminée.');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
