<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit();
}

// Récupérer l'ID de l'utilisateur
$userId = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$userQuery = $conn->query("SELECT * FROM users WHERE id = $userId");
$user = $userQuery->fetch_assoc();

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = $conn->real_escape_string($_POST['first_name']);
        $lastName = $conn->real_escape_string($_POST['last_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $bio = $conn->real_escape_string($_POST['bio']);

        // Mettre à jour les informations de l'utilisateur
        $updateUser = $conn->query("
            UPDATE users SET 
                first_name = '$firstName', 
                last_name = '$lastName', 
                email = '$email', 
                phone = '$phone', 
                bio = '$bio', 
                updated_at = NOW() 
            WHERE id = $userId
        ");

        if ($updateUser) {
            // Mettre à jour les informations de session
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;

            // Rediriger avec un message de succès
            header("Location: profile.php?success=profile");
            exit();
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Vérifier si le mot de passe actuel est correct
        if (password_verify($currentPassword, $user['password'])) {
            // Vérifier si les nouveaux mots de passe correspondent
            if ($newPassword === $confirmPassword) {
                // Hacher le nouveau mot de passe
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Mettre à jour le mot de passe
                $updatePassword = $conn->query("
                    UPDATE users SET 
                        password = '$hashedPassword', 
                        updated_at = NOW() 
                    WHERE id = $userId
                ");

                if ($updatePassword) {
                    // Rediriger avec un message de succès
                    header("Location: profile.php?success=password");
                    exit();
                }
            } else {
                $passwordError = "Les nouveaux mots de passe ne correspondent pas.";
            }
        } else {
            $passwordError = "Le mot de passe actuel est incorrect.";
        }
    } elseif (isset($_POST['update_preferences'])) {
        $theme = $conn->real_escape_string($_POST['theme']);
        $language = $conn->real_escape_string($_POST['language']);
        $notifications = isset($_POST['notifications']) ? 1 : 0;

        // Mettre à jour les préférences de l'utilisateur
        $updatePreferences = $conn->query("
            UPDATE user_preferences SET 
                theme = '$theme', 
                language = '$language', 
                notifications = $notifications, 
                updated_at = NOW() 
            WHERE user_id = $userId
        ");

        if ($updatePreferences) {
            // Rediriger avec un message de succès
            header("Location: profile.php?success=preferences");
            exit();
        }
    }
}

// Récupérer les préférences de l'utilisateur
$preferencesQuery = $conn->query("SELECT * FROM user_preferences WHERE user_id = $userId");
$preferences = $preferencesQuery->fetch_assoc();

// Si les préférences n'existent pas, créer des valeurs par défaut
if (!$preferences) {
    $conn->query("INSERT INTO user_preferences (user_id, theme, language, notifications) VALUES ($userId, 'light', 'fr', 1)");
    $preferences = [
        'theme' => 'light',
        'language' => 'fr',
        'notifications' => 1
    ];
}

$pageTitle = "Mon profil";
include 'includes/header.php';
?>
<style>
   /* Styles de base pour les alertes */
.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    position: relative;
    font-size: 0.95rem;
    border: 1px solid transparent;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* Icônes des alertes */
.alert i {
    margin-right: 12px;
    font-size: 1.2rem;
}

/* Bouton de fermeture */
.close-alert {
    background: none;
    border: none;
    font-size: 1.3rem;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
    transition: opacity 0.3s;
    padding: 0;
    line-height: 1;
}

.close-alert:hover {
    opacity: 1;
}

/* Types d'alertes */
.alert-success {
    background-color: #e8f5e9;
    border-color: #c8e6c9;
    color: #2e7d32;
}

.alert-danger {
    background-color: #ffebee;
    border-color: #ffcdd2;
    color: #c62828;
}

.alert-warning {
    background-color: #fff8e1;
    border-color: #ffecb3;
    color: #f57c00;
}

.alert-info {
    background-color: #e3f2fd;
    border-color: #bbdefb;
    color: #1565c0;
}

/* Animation d'apparition */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert {
    animation: fadeIn 0.3s ease-out;
}

/* Variante avec icônes Font Awesome */
.alert .fas {
    min-width: 20px;
    text-align: center;
}

/* Alertes cliquables */
.alert-clickable {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.alert-clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Alertes avec bordure gauche colorée */
.alert-border-left {
    border-left-width: 4px;
    border-left-style: solid;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.alert-success.alert-border-left {
    border-left-color: #2e7d32;
}

.alert-danger.alert-border-left {
    border-left-color: #c62828;
}

.alert-warning.alert-border-left {
    border-left-color: #f57c00;
}

.alert-info.alert-border-left {
    border-left-color: #1565c0;
}

/* Responsive */
@media (max-width: 768px) {
    .alert {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
    
    .alert i {
        font-size: 1rem;
        margin-right: 10px;
    }
    
    .close-alert {
        font-size: 1.1rem;
        right: 10px;
    }
}
    .profile-sidebar {
        width: 100%;
        margin-bottom: 30px;
    }

    .profile-card {
        background-color: white;
        border-radius: 20px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }

    .profile-header {
        display: flex;
        align-items: center;
    }

    .profile-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #007bff;
        color: white;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 24px;
    }

    .profile-info {
        margin-left: 20px;
    }

    .profile-name {
        font-size: 24px;
        margin-bottom: 5px;
    }

    .profile-role {
        font-size: 16px;
        color: gray;
    }

    .profile-stats {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
    }
</style>
<div>

    <main class="main-content" style="width:100%;margin-left: 0px;">
        <div class="content-wrapper" >
            <div class="content-body" >
                <?php if (isset($_GET['success'])): ?>
                    <?php
                    $successMessages = [
                        'profile' => 'Votre profil a été mis à jour avec succès.',
                        'password' => 'Votre mot de passe a été modifié avec succès.',
                        'preferences' => 'Vos préférences ont été mises à jour avec succès.'
                    ];
                    $successMessage = $successMessages[$_GET['success']] ?? 'Opération réussie.';
                    ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $successMessage; ?></span>
                        <button class="close-alert">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="profile-container" >
                    <div class="profile-sidebar" >
                        <div class="profile-card" style="margin-left: 25px;margin-right: 20px;margin-top: 30px;">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div class="profile-info">
                                    <h2 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                    <p class="profile-role">Enseignant</p>
                                </div>
                            </div>
                            <div class="profile-body">
                                <div class="profile-stats">
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php
                                            $examCount = $conn->query("SELECT COUNT(*) as count FROM exams WHERE teacher_id = $userId")->fetch_assoc()['count'];
                                            echo $examCount;
                                            ?>
                                        </div>
                                        <div class="stat-label">Examens</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php
                                            $studentCount = $conn->query("
                                                    SELECT COUNT(DISTINCT user_id) as count 
                                                    FROM exam_results er 
                                                    JOIN exams e ON er.exam_id = e.id 
                                                    WHERE e.teacher_id = $userId
                                                ")->fetch_assoc()['count'];
                                            echo $studentCount;
                                            ?>
                                        </div>
                                        <div class="stat-label">Étudiants</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php
                                            $avgScore = $conn->query("
                                                    SELECT AVG(er.score) as avg_score 
                                                    FROM exam_results er 
                                                    JOIN exams e ON er.exam_id = e.id 
                                                    WHERE e.teacher_id = $userId AND er.status = 'completed'
                                                ")->fetch_assoc()['avg_score'];
                                            echo round($avgScore ?? 0, 1) . '%';
                                            ?>
                                        </div>
                                        <div class="stat-label">Score moyen</div>
                                    </div>
                                </div>
                                <div class="profile-contact" ><br>
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($user['phone'] ?? 'Non renseigné'); ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Membre depuis <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-content">
                        <div class="profile-tab active" id="profile-info">
                            <div class="card" style="margin-top:20px;margin-bottom:30px;border-radius: 20px;margin-right: 20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                                <div class="card-header">
                                    <h2 class="card-title">Informations personnelles</h2>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="first_name">Prénom</label>
                                                <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="last_name">Nom</label>
                                                <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="email">Email</label>
                                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="phone">Téléphone</label>
                                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="bio">Biographie</label>
                                            <textarea id="bio" name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="form-buttons">
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Enregistrer les modifications
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="profile-tab" id="change-password">
                            <div class="card" style="margin-top:20px;margin-bottom:30px;border-radius: 20px;margin-right: 20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                                <div class="card-header">
                                    <h2 class="card-title">Changer le mot de passe</h2>
                                </div>
                                <div class="card-body">
                                    <?php if (isset($passwordError)): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <span><?php echo $passwordError; ?></span>
                                            <button class="close-alert">&times;</button>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="">
                                        <div class="form-group">
                                            <label for="current_password">Mot de passe actuel</label>
                                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="new_password">Nouveau mot de passe</label>
                                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                        </div>

                                        <div class="password-requirements">
                                            <p>Le mot de passe doit :</p>
                                            <ul style="margin-left: 15px;">
                                                <li>Contenir au moins 8 caractères</li>
                                                <li>Inclure au moins une lettre majuscule</li>
                                                <li>Inclure au moins un chiffre</li>
                                                <li>Inclure au moins un caractère spécial</li>
                                            </ul>
                                        </div>

                                        <div class="form-buttons">
                                            <button type="submit" name="change_password" class="btn btn-primary">
                                                <i class="fas fa-lock"></i> Changer le mot de passe
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="profile-tab" id="preferences">
                            <div class="card" style="margin-top:20px;margin-bottom:30px;border-radius: 20px;margin-right: 20px;margin-left:25px;background-color: white;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                                <div class="card-header">
                                    <h2 class="card-title">Préférences</h2>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="form-group">
                                            <label for="theme">Thème</label>
                                            <select id="theme" name="theme" class="form-control">
                                                <option value="light" <?php echo $preferences['theme'] === 'light' ? 'selected' : ''; ?>>Clair</option>
                                                <option value="dark" <?php echo $preferences['theme'] === 'dark' ? 'selected' : ''; ?>>Sombre</option>
                                                <option value="system" <?php echo $preferences['theme'] === 'system' ? 'selected' : ''; ?>>Système</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="language">Langue</label>
                                            <select id="language" name="language" class="form-control">
                                                <option value="fr" <?php echo $preferences['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                                                <option value="en" <?php echo $preferences['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>Notifications</label>
                                            <div class="checkbox-group">
                                                <label class="checkbox-container">
                                                    <input type="checkbox" name="notifications" <?php echo $preferences['notifications'] ? 'checked' : ''; ?>>
                                                    <span class="checkmark"></span>
                                                    Recevoir des notifications par email
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-buttons">
                                            <button type="submit" name="update_preferences" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Enregistrer les préférences
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion des onglets
        const tabLinks = document.querySelectorAll('.profile-nav li');
        const tabContents = document.querySelectorAll('.profile-tab');

        tabLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                // Supprimer la classe active de tous les liens
                tabLinks.forEach(function(l) {
                    l.classList.remove('active');
                });

                // Ajouter la classe active au lien cliqué
                this.classList.add('active');

                // Masquer tous les contenus d'onglet
                tabContents.forEach(function(content) {
                    content.classList.remove('active');
                });

                // Afficher le contenu d'onglet correspondant
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Fermer les alertes
        document.querySelectorAll('.close-alert').forEach(function(btn) {
            btn.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });

        // Validation du formulaire de changement de mot de passe
        const passwordForm = document.querySelector('form[name="change_password"]');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(event) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    event.preventDefault();
                    alert('Les nouveaux mots de passe ne correspondent pas.');
                }

                // Vérifier la complexité du mot de passe
                const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
                if (!passwordRegex.test(newPassword)) {
                    event.preventDefault();
                    alert('Le mot de passe ne respecte pas les exigences de complexité.');
                }
            });
        }
    });
</script>