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

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    
    // Validation des données
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "Tous les champs sont obligatoires.";
    } else {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Cette adresse email est déjà utilisée par un autre utilisateur.";
        } else {
            // Mise à jour du profil
            $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Votre profil a été mis à jour avec succès.";
                
                // Mettre à jour les données de session
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;
                
                // Récupérer les données mises à jour
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error_message = "Erreur lors de la mise à jour du profil: " . $conn->error;
            }
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation des données
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Vérifier le mot de passe actuel
        if (password_verify($current_password, $user['password'])) {
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Votre mot de passe a été changé avec succès.";
            } else {
                $error_message = "Erreur lors du changement de mot de passe: " . $conn->error;
            }
        } else {
            $error_message = "Le mot de passe actuel est incorrect.";
        }
    }
}

// Traitement du téléchargement de l'image de profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Vérifier l'extension du fichier
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
        } elseif ($file_size > 2097152) { // 2 MB
            $error_message = "La taille du fichier ne doit pas dépasser 2 MB.";
        } else {
            // Créer un nom de fichier unique
            $new_file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = '../uploads/profiles/' . $new_file_name;
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists('../uploads/profiles/')) {
                mkdir('../uploads/profiles/', 0777, true);
            }
            
            // Déplacer le fichier téléchargé
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Supprimer l'ancienne image si elle existe
                if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                    unlink('../' . $user['profile_image']);
                }
                
                // Mettre à jour la base de données
                $profile_image = 'uploads/profiles/' . $new_file_name;
                $update_stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $update_stmt->bind_param("si", $profile_image, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Votre image de profil a été mise à jour avec succès.";
                    
                    // Mettre à jour les données de session
                    $_SESSION['user_image'] = $profile_image;
                    
                    // Récupérer les données mises à jour
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $error_message = "Erreur lors de la mise à jour de l'image de profil: " . $conn->error;
                }
            } else {
                $error_message = "Erreur lors du téléchargement de l'image.";
            }
        }
    } else {
        $error_message = "Veuillez sélectionner une image à télécharger.";
    }
}

$pageTitle = "Mon profil";
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-20">
    <h1 class="page-title">Mon profil</h1>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-20">
            <div class="card-header">
                <h2 class="card-title">Photo de profil</h2>
            </div>
            <div class="card-body text-center">
                <div class="profile-image-container mb-20">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="../<?php echo $user['profile_image']; ?>" alt="Photo de profil" class="profile-image">
                    <?php else: ?>
                        <img src="../assets/images/avatar.png" alt="Photo de profil par défaut" class="profile-image">
                    <?php endif; ?>
                </div>
                
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="profile_image" class="form-label">Changer votre photo</label>
                        <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*">
                        <small class="form-text text-muted">JPG, JPEG, PNG ou GIF. Max 2MB.</small>
                    </div>
                    <button type="submit" name="upload_image" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Télécharger
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Informations du compte</h2>
            </div>
            <div class="card-body">
                <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Rôle:</strong> Administrateur</p>
                <p><strong>Date d'inscription:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                <p><strong>Dernière connexion:</strong> <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-20">
            <div class="card-header">
                <h2 class="card-title">Informations personnelles</h2>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name" class="form-label">Prénom</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name" class="form-label">Nom</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Changer le mot de passe</h2>
            </div>
            <div class="card-body">
                <form action="" method="post">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <small class="form-text text-muted">Le mot de passe doit contenir au moins 8 caractères.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.profile-image-container {
    width: 150px;
    height: 150px;
    margin: 0 auto;
    overflow: hidden;
    border-radius: 50%;
    border: 3px solid #f0f0f0;
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

<?php include 'includes/footer.php'; ?>
