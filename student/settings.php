<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un étudiant


// Récupérer l'ID de l'étudiant
$studentId = $_SESSION['user_id'];

// Récupérer les préférences actuelles de l'utilisateur
$preferencesQuery = $conn->prepare("
    SELECT * FROM user_preferences 
    WHERE user_id = ?
");
$preferencesQuery->bind_param("i", $studentId);
$preferencesQuery->execute();
$preferences = $preferencesQuery->get_result()->fetch_assoc();

// Si aucune préférence n'existe, créer des valeurs par défaut
if (!$preferences) {
    $defaultPreferences = [
        'theme' => 'light',
        'notifications_enabled' => 1,
        'email_notifications' => 1,
        'sound_enabled' => 1,
        'language' => 'fr',
        'timezone' => 'Europe/Paris',
        'accessibility_mode' => 0,
        'high_contrast' => 0,
        'font_size' => 'medium',
        'exam_countdown' => 1,
        'auto_save' => 1,
        'save_interval' => 60
    ];
    
    $insertQuery = $conn->prepare("
        INSERT INTO user_preferences 
        (user_id, theme, notifications_enabled, email_notifications, sound_enabled, language, timezone, 
        accessibility_mode, high_contrast, font_size, exam_countdown, auto_save, save_interval) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertQuery->bind_param(
        "isiiissisiii", 
        $studentId, 
        $defaultPreferences['theme'], 
        $defaultPreferences['notifications_enabled'], 
        $defaultPreferences['email_notifications'], 
        $defaultPreferences['sound_enabled'], 
        $defaultPreferences['language'], 
        $defaultPreferences['timezone'], 
        $defaultPreferences['accessibility_mode'], 
        $defaultPreferences['high_contrast'], 
        $defaultPreferences['font_size'], 
        $defaultPreferences['exam_countdown'], 
        $defaultPreferences['auto_save'], 
        $defaultPreferences['save_interval']
    );
    $insertQuery->execute();
    
    $preferences = $defaultPreferences;
    $preferences['id'] = $conn->insert_id;
}

// Traitement du formulaire
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les valeurs du formulaire
    $theme = $_POST['theme'] ?? 'light';
    $notificationsEnabled = isset($_POST['notifications_enabled']) ? 1 : 0;
    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
    $soundEnabled = isset($_POST['sound_enabled']) ? 1 : 0;
    $language = $_POST['language'] ?? 'fr';
    $timezone = $_POST['timezone'] ?? 'Europe/Paris';
    $accessibilityMode = isset($_POST['accessibility_mode']) ? 1 : 0;
    $highContrast = isset($_POST['high_contrast']) ? 1 : 0;
    $fontSize = $_POST['font_size'] ?? 'medium';
    $examCountdown = isset($_POST['exam_countdown']) ? 1 : 0;
    $autoSave = isset($_POST['auto_save']) ? 1 : 0;
    $saveInterval = intval($_POST['save_interval'] ?? 60);
    
    // Valider les données
    if ($saveInterval < 10 || $saveInterval > 300) {
        $saveInterval = 60; // Valeur par défaut si hors limites
    }
    
    // Mettre à jour les préférences
    $updateQuery = $conn->prepare("
        UPDATE user_preferences 
        SET theme = ?, notifications_enabled = ?, email_notifications = ?, sound_enabled = ?, 
        language = ?, timezone = ?, accessibility_mode = ?, high_contrast = ?, font_size = ?, 
        exam_countdown = ?, auto_save = ?, save_interval = ?, updated_at = NOW() 
        WHERE user_id = ?
    ");
    $updateQuery->bind_param(
        "siiissisiiiii", 
        $theme, 
        $notificationsEnabled, 
        $emailNotifications, 
        $soundEnabled, 
        $language, 
        $timezone, 
        $accessibilityMode, 
        $highContrast, 
        $fontSize, 
        $examCountdown, 
        $autoSave, 
        $saveInterval, 
        $studentId
    );
    
    if ($updateQuery->execute()) {
        $success_message = "Vos paramètres ont été mis à jour avec succès.";
        
        // Mettre à jour les préférences en mémoire
        $preferences['theme'] = $theme;
        $preferences['notifications_enabled'] = $notificationsEnabled;
        $preferences['email_notifications'] = $emailNotifications;
        $preferences['sound_enabled'] = $soundEnabled;
        $preferences['language'] = $language;
        $preferences['timezone'] = $timezone;
        $preferences['accessibility_mode'] = $accessibilityMode;
        $preferences['high_contrast'] = $highContrast;
        $preferences['font_size'] = $fontSize;
        $preferences['exam_countdown'] = $examCountdown;
        $preferences['auto_save'] = $autoSave;
        $preferences['save_interval'] = $saveInterval;
    } else {
        $error_message = "Une erreur est survenue lors de la mise à jour de vos paramètres.";
    }
}

// Récupérer la liste des fuseaux horaires
$timezones = DateTimeZone::listIdentifiers();

$pageTitle = "Paramètres";
include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Paramètres</h1>
        <p class="page-subtitle">Personnalisez votre expérience sur la plateforme</p>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Préférences utilisateur</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="settings-tabs">
                    <div class="tab-list">
                        <button type="button" class="tab-button active" data-tab="appearance">
                            <i class="fas fa-palette"></i> Apparence
                        </button>
                        <button type="button" class="tab-button" data-tab="notifications">
                            <i class="fas fa-bell"></i> Notifications
                        </button>
                        <button type="button" class="tab-button" data-tab="accessibility">
                            <i class="fas fa-universal-access"></i> Accessibilité
                        </button>
                        <button type="button" class="tab-button" data-tab="exam">
                            <i class="fas fa-file-alt"></i> Examens
                        </button>
                    </div>
                    
                    <div class="tab-content">
                        <!-- Onglet Apparence -->
                        <div class="tab-pane active" id="appearance">
                            <div class="form-group">
                                <label class="form-label">Thème</label>
                                <div class="theme-selector">
                                    <div class="theme-option <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>">
                                        <input type="radio" name="theme" id="theme-light" value="light" <?php echo $preferences['theme'] === 'light' ? 'checked' : ''; ?>>
                                        <label for="theme-light" class="theme-preview light-theme">
                                            <div class="theme-header"></div>
                                            <div class="theme-content">
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-main"></div>
                                            </div>
                                        </label>
                                        <div class="theme-name">Clair</div>
                                    </div>
                                    
                                    <div class="theme-option <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>">
                                        <input type="radio" name="theme" id="theme-dark" value="dark" <?php echo $preferences['theme'] === 'dark' ? 'checked' : ''; ?>>
                                        <label for="theme-dark" class="theme-preview dark-theme">
                                            <div class="theme-header"></div>
                                            <div class="theme-content">
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-main"></div>
                                            </div>
                                        </label>
                                        <div class="theme-name">Sombre</div>
                                    </div>
                                    
                                    <div class="theme-option <?php echo $preferences['theme'] === 'blue' ? 'selected' : ''; ?>">
                                        <input type="radio" name="theme" id="theme-blue" value="blue" <?php echo $preferences['theme'] === 'blue' ? 'checked' : ''; ?>>
                                        <label for="theme-blue" class="theme-preview blue-theme">
                                            <div class="theme-header"></div>
                                            <div class="theme-content">
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-main"></div>
                                            </div>
                                        </label>
                                        <div class="theme-name">Bleu</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Langue</label>
                                <select name="language" class="form-select">
                                    <option value="fr" <?php echo $preferences['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                                    <option value="en" <?php echo $preferences['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo $preferences['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                                    <option value="de" <?php echo $preferences['language'] === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Fuseau horaire</label>
                                <select name="timezone" class="form-select">
                                    <?php foreach ($timezones as $tz): ?>
                                        <option value="<?php echo $tz; ?>" <?php echo $preferences['timezone'] === $tz ? 'selected' : ''; ?>>
                                            <?php echo $tz; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Onglet Notifications -->
                        <div class="tab-pane" id="notifications">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="notifications_enabled" name="notifications_enabled" <?php echo $preferences['notifications_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notifications_enabled">Activer les notifications dans l'application</label>
                                </div>
                                <div class="form-text">Recevez des notifications en temps réel sur la plateforme.</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="email_notifications" name="email_notifications" <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Recevoir des notifications par email</label>
                                </div>
                                <div class="form-text">Recevez des emails pour les événements importants (nouveaux examens, résultats, etc.).</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="sound_enabled" name="sound_enabled" <?php echo $preferences['sound_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sound_enabled">Activer les sons de notification</label>
                                </div>
                                <div class="form-text">Jouez un son lorsque vous recevez une notification.</div>
                            </div>
                            
                            <div class="notification-types">
                                <h4>Types de notifications</h4>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_new_exam" name="notify_new_exam" checked>
                                        <label class="form-check-label" for="notify_new_exam">Nouveaux examens disponibles</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_exam_reminder" name="notify_exam_reminder" checked>
                                        <label class="form-check-label" for="notify_exam_reminder">Rappels d'examens</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_results" name="notify_results" checked>
                                        <label class="form-check-label" for="notify_results">Résultats d'examens</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_announcements" name="notify_announcements" checked>
                                        <label class="form-check-label" for="notify_announcements">Annonces importantes</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Onglet Accessibilité -->
                        <div class="tab-pane" id="accessibility">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="accessibility_mode" name="accessibility_mode" <?php echo $preferences['accessibility_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="accessibility_mode">Mode d'accessibilité</label>
                                </div>
                                <div class="form-text">Optimise l'interface pour une meilleure accessibilité.</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="high_contrast" name="high_contrast" <?php echo $preferences['high_contrast'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="high_contrast">Mode contraste élevé</label>
                                </div>
                                <div class="form-text">Augmente le contraste des couleurs pour une meilleure lisibilité.</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Taille de police</label>
                                <div class="font-size-selector">
                                    <div class="form-check form-check-inline">
                                        <input type="radio" class="form-check-input" id="font-small" name="font_size" value="small" <?php echo $preferences['font_size'] === 'small' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="font-small">Petite</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" class="form-check-input" id="font-medium" name="font_size" value="medium" <?php echo $preferences['font_size'] === 'medium' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="font-medium">Moyenne</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" class="form-check-input" id="font-large" name="font_size" value="large" <?php echo $preferences['font_size'] === 'large' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="font-large">Grande</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" class="form-check-input" id="font-xlarge" name="font_size" value="xlarge" <?php echo $preferences['font_size'] === 'xlarge' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="font-xlarge">Très grande</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="font-preview">
                                <h4>Aperçu de la taille de police</h4>
                                <p class="font-preview-text">Ceci est un exemple de texte avec la taille de police sélectionnée.</p>
                            </div>
                        </div>
                        
                        <!-- Onglet Examens -->
                        <div class="tab-pane" id="exam">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="exam_countdown" name="exam_countdown" <?php echo $preferences['exam_countdown'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="exam_countdown">Afficher le compte à rebours pendant les examens</label>
                                </div>
                                <div class="form-text">Affiche un minuteur indiquant le temps restant pendant les examens.</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="auto_save" name="auto_save" <?php echo $preferences['auto_save'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_save">Enregistrement automatique des réponses</label>
                                </div>
                                <div class="form-text">Enregistre automatiquement vos réponses pendant les examens.</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="save_interval">Intervalle d'enregistrement automatique (secondes)</label>
                                <input type="number" class="form-control" id="save_interval" name="save_interval" min="10" max="300" value="<?php echo $preferences['save_interval']; ?>">
                                <div class="form-text">Définit la fréquence d'enregistrement automatique de vos réponses (entre 10 et 300 secondes).</div>
                            </div>
                            
                            <div class="form-group">
                                <h4>Préférences de notification d'examen</h4>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="notify_before_exam" name="notify_before_exam" checked>
                                    <label class="form-check-label" for="notify_before_exam">Me notifier 24h avant un examen</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="notify_exam_start" name="notify_exam_start" checked>
                                    <label class="form-check-label" for="notify_exam_start">Me notifier au début d'un examen</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les paramètres
                    </button>
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h2 class="card-title">Sécurité du compte</h2>
        </div>
        <div class="card-body">
            <div class="security-section">
                <h3>Mot de passe</h3>
                <p>Votre mot de passe a été modifié pour la dernière fois le <strong>12/05/2023</strong>.</p>
                <a href="change-password.php" class="btn btn-outline-primary">
                    <i class="fas fa-key"></i> Changer de mot de passe
                </a>
            </div>
            
            <div class="security-section">
                <h3>Sessions actives</h3>
                <p>Vous êtes actuellement connecté sur les appareils suivants :</p>
                
                <div class="sessions-list">
                    <div class="session-item current">
                        <div class="session-icon">
                            <i class="fas fa-laptop"></i>
                        </div>
                        <div class="session-details">
                            <div class="session-device">Windows 10 - Chrome</div>
                            <div class="session-info">
                                <span class="session-location">Paris, France</span>
                                <span class="session-time">Connecté maintenant</span>
                            </div>
                            <div class="session-current">Session actuelle</div>
                        </div>
                    </div>
                    
                    <div class="session-item">
                        <div class="session-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="session-details">
                            <div class="session-device">Android - Chrome Mobile</div>
                            <div class="session-info">
                                <span class="session-location">Paris, France</span>
                                <span class="session-time">Dernière activité il y a 2 heures</span>
                            </div>
                            <button class="btn btn-sm btn-outline-danger session-logout">
                                <i class="fas fa-sign-out-alt"></i> Déconnecter
                            </button>
                        </div>
                    </div>
                </div>
                
                <button class="btn btn-outline-danger mt-3">
                    <i class="fas fa-sign-out-alt"></i> Déconnecter toutes les autres sessions
                </button>
            </div>
            
            <div class="security-section">
                <h3>Activité du compte</h3>
                <p>Consultez l'historique des connexions et des activités de votre compte.</p>
                <a href="account-activity.php" class="btn btn-outline-primary">
                    <i class="fas fa-history"></i> Voir l'historique d'activité
                </a>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h2 class="card-title">Données personnelles</h2>
        </div>
        <div class="card-body">
            <div class="data-section">
                <h3>Télécharger vos données</h3>
                <p>Vous pouvez télécharger une copie de vos données personnelles et de vos résultats d'examens.</p>
                <button class="btn btn-outline-primary">
                    <i class="fas fa-download"></i> Télécharger mes données
                </button>
            </div>
            
            <div class="data-section">
                <h3>Supprimer votre compte</h3>
                <p class="text-danger">Attention : La suppression de votre compte est irréversible et entraînera la perte de toutes vos données.</p>
                <button class="btn btn-outline-danger" data-toggle="modal" data-target="#deleteAccountModal">
                    <i class="fas fa-trash-alt"></i> Supprimer mon compte
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de suppression de compte -->
<div class="modal" id="deleteAccountModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supprimer votre compte</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Attention :</strong> Cette action est irréversible.
                </div>
                <p>La suppression de votre compte entraînera :</p>
                <ul>
                    <li>La suppression définitive de toutes vos données personnelles</li>
                    <li>La perte de tous vos résultats d'examens</li>
                    <li>La suppression de votre historique d'activité</li>
                </ul>
                <p>Êtes-vous sûr de vouloir continuer ?</p>
                <div class="form-group">
                    <label for="delete-confirmation">Pour confirmer, tapez "SUPPRIMER" ci-dessous :</label>
                    <input type="text" class="form-control" id="delete-confirmation" placeholder="SUPPRIMER">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>Supprimer définitivement</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Désactiver tous les onglets
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Activer l'onglet sélectionné
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Prévisualisation de la taille de police
    const fontSizeInputs = document.querySelectorAll('input[name="font_size"]');
    const fontPreviewText = document.querySelector('.font-preview-text');
    
    fontSizeInputs.forEach(input => {
        input.addEventListener('change', function() {
            fontPreviewText.className = 'font-preview-text';
            fontPreviewText.classList.add('font-' + this.value);
        });
    });
    
    // Initialiser la prévisualisation
    const selectedFontSize = document.querySelector('input[name="font_size"]:checked').value;
    fontPreviewText.classList.add('font-' + selectedFontSize);
    
    // Sélection de thème
    const themeOptions = document.querySelectorAll('.theme-option');
    
    themeOptions.forEach(option => {
        const radioInput = option.querySelector('input[type="radio"]');
        
        option.addEventListener('click', function() {
            radioInput.checked = true;
            
            // Mettre à jour la classe selected
            themeOptions.forEach(opt => {
                opt.classList.remove('selected');
            });
            option.classList.add('selected');
        });
    });
    
    // Validation de la suppression de compte
    const deleteConfirmInput = document.getElementById('delete-confirmation');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    deleteConfirmInput.addEventListener('input', function() {
        if (this.value === 'SUPPRIMER') {
            confirmDeleteBtn.disabled = false;
        } else {
            confirmDeleteBtn.disabled = true;
        }
    });
    
    // Intervalle d'enregistrement automatique
    const autoSaveCheckbox = document.getElementById('auto_save');
    const saveIntervalInput = document.getElementById('save_interval');
    
    autoSaveCheckbox.addEventListener('change', function() {
        saveIntervalInput.disabled = !this.checked;
    });
    
    // Initialiser l'état de l'intervalle d'enregistrement
    saveIntervalInput.disabled = !autoSaveCheckbox.checked;
});
</script>

<style>
/* Styles pour la page de paramètres */
.settings-tabs {
    display: flex;
    flex-direction: column;
}

.tab-list {
    display: flex;
    border-bottom: 1px solid var(--gray-300);
    margin-bottom: 1.5rem;
    overflow-x: auto;
}

.tab-button {
    padding: 1rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    white-space: nowrap;
}

.tab-button:hover {
    color: var(--primary-color);
}

.tab-button.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-content {
    flex: 1;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Sélecteur de thème */
.theme-selector {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.theme-option {
    position: relative;
    cursor: pointer;
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
}

.theme-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.theme-preview {
    width: 180px;
    height: 120px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
}

.theme-option.selected .theme-preview {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.3);
}

.theme-header {
    height: 20px;
}

.theme-content {
    display: flex;
    height: 100px;
}

.theme-sidebar {
    width: 30%;
}

.theme-main {
    width: 70%;
}

.theme-name {
    text-align: center;
    margin-top: 0.5rem;
    font-weight: 500;
}

/* Thèmes */
.light-theme .theme-header {
    background-color: #ffffff;
    border-bottom: 1px solid #e9ecef;
}

.light-theme .theme-sidebar {
    background-color: #ffffff;
    border-right: 1px solid #e9ecef;
}

.light-theme .theme-main {
    background-color: #f8f9fa;
}

.dark-theme .theme-header {
    background-color: #212529;
    border-bottom: 1px solid #343a40;
}

.dark-theme .theme-sidebar {
    background-color: #343a40;
    border-right: 1px solid #495057;
}

.dark-theme .theme-main {
    background-color: #212529;
}

.blue-theme .theme-header {
    background-color: #4361ee;
    border-bottom: 1px solid #3f37c9;
}

.blue-theme .theme-sidebar {
    background-color: #3a0ca3;
    border-right: 1px solid #3f37c9;
}

.blue-theme .theme-main {
    background-color: #f8f9fa;
}

/* Prévisualisation de la taille de police */
.font-preview {
    margin-top: 1.5rem;
    padding: 1rem;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
}

.font-preview-text {
    margin-top: 0.5rem;
}

.font-small {
    font-size: 0.875rem;
}

.font-medium {
    font-size: 1rem;
}

.font-large {
    font-size: 1.25rem;
}

.font-xlarge {
    font-size: 1.5rem;
}

/* Sections de sécurité et données */
.security-section, .data-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--gray-200);
}

.security-section:last-child, .data-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

/* Sessions actives */
.sessions-list {
    margin: 1.5rem 0;
}

.session-item {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
}

.session-item.current {
    background-color: rgba(67, 97, 238, 0.05);
    border-color: var(--primary-color);
}

.session-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.25rem;
    color: var(--gray-700);
}

.session-details {
    flex: 1;
}

.session-device {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.session-info {
    display: flex;
    justify-content: space-between;
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.session-current {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background-color: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Formulaire */
.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}

/* Alertes */
.alert {
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.alert i {
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.alert-success {
    background-color: rgba(76, 175, 80, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(76, 175, 80, 0.2);
}

.alert-danger {
    background-color: rgba(244, 67, 54, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(244, 67, 54, 0.2);
}

/* Responsive */
@media (max-width: 768px) {
    .theme-selector {
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions button {
        width: 100%;
    }
    
    .session-info {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
