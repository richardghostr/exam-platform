<?php
include 'includes/header.php';
include 'includes/sidebar.php';
include '../includes/db_connection.php';

// Vérifier si l'ID de l'utilisateur est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = $_GET['id'];
$error_message = '';
$success_message = '';

// Récupérer les détails de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Utilisateur non trouvé'); window.location.href='users.php';</script>";
    exit;
}

$user = $result->fetch_assoc();

// Récupérer la liste des rôles
$roles_result = $conn->query("SELECT * FROM roles ORDER BY id");

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $role_id = $_POST['role_id'];
    $status = $_POST['status'];
    $updated_by = $_SESSION['user_id'];
    
    // Validation des données
    if (empty($full_name) || empty($email) || empty($role_id)) {
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
            // Mise à jour de l'utilisateur
            $update_stmt = $conn->prepare("UPDATE users SET 
                                          full_name = ?, 
                                          email = ?, 
                                          role_id = ?, 
                                          status = ?, 
                                          updated_by = ?, 
                                          updated_at = NOW() 
                                          WHERE id = ?");
            
            $update_stmt->bind_param("ssiiii", $full_name, $email, $role_id, $status, $updated_by, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "L'utilisateur a été mis à jour avec succès.";
                
                // Mise à jour du mot de passe si fourni
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    
                    $pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $pwd_stmt->bind_param("si", $password, $user_id);
                    
                    if ($pwd_stmt->execute()) {
                        $success_message .= " Le mot de passe a également été mis à jour.";
                    } else {
                        $error_message = "Erreur lors de la mise à jour du mot de passe: " . $conn->error;
                    }
                }
                
                // Récupérer les données mises à jour
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error_message = "Erreur lors de la mise à jour de l'utilisateur: " . $conn->error;
            }
        }
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Modifier l'utilisateur</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="users.php">Utilisateurs</a></li>
                        <li class="breadcrumb-item active">Modifier l'utilisateur</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Erreur!</h5>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Succès!</h5>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Informations de l'utilisateur</h3>
                        </div>
                        <form method="post" action="">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="full_name">Nom complet</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="password">Mot de passe (laisser vide pour ne pas modifier)</label>
                                            <input type="password" class="form-control" id="password" name="password">
                                            <small class="form-text text-muted">Laissez ce champ vide si vous ne souhaitez pas modifier le mot de passe.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role_id">Rôle</label>
                                            <select class="form-control" id="role_id" name="role_id" required>
                                                <?php while ($role = $roles_result->fetch_assoc()): ?>
                                                    <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="status">Statut</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="1" <?php echo ($user['status'] == 1) ? 'selected' : ''; ?>>Actif</option>
                                                <option value="0" <?php echo ($user['status'] == 0) ? 'selected' : ''; ?>>Inactif</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Date d'inscription</label>
                                            <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                <a href="users.php" class="btn btn-default">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include 'includes/footer.php';
?>
