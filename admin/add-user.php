<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error_message = '';
$success_message = '';

// Récupérer la liste des rôles
$roles_result = $conn->query("SELECT * FROM roles ORDER BY id");

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];
    $status = $_POST['status'];
    $created_by = $_SESSION['user_id'];

    // Validation des données
    if (empty($full_name) || empty($email) || empty($password) || empty($role_id)) {
        $error_message = "Tous les champs sont obligatoires.";
    } else {
        // Vérifier si l'email existe déjà
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Cette adresse email est déjà utilisée.";
        } else {
            // Hachage du mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insertion de l'utilisateur
            $insert_stmt = $conn->prepare("INSERT INTO users 
                                          (full_name, email, password, role_id, status, created_by, created_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW())");

            $insert_stmt->bind_param("sssiii", $full_name, $email, $hashed_password, $role_id, $status, $created_by);

            if ($insert_stmt->execute()) {
                $success_message = "L'utilisateur a été ajouté avec succès.";

                // Rediriger vers la liste des utilisateurs ou rester sur la page pour ajouter un autre utilisateur
                if (isset($_POST['save_and_list'])) {
                    header("Location: users.php");
                    exit;
                }

                // Réinitialiser le formulaire
                $full_name = '';
                $email = '';
                $role_id = '';
                $status = 1;
            } else {
                $error_message = "Erreur lors de l'ajout de l'utilisateur: " . $conn->error;
            }
        }
    }
}
// Récupérer la liste des rôles
include 'includes/header.php';
?>

<div class="card mb-20">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2" style="margin-left: 20px;margin-top: 20px;">

                <div class="page-path">

                    <a href="index.php">Accueil</a>
                    <span class="separator">/</span>
                    <a href="users.php">Utilisateurs</a>
                    <span class="separator">/</span>
                    <span class="breadcrumb-item active">Ajouter un utilisateur</span>

                </div>
                <div class="col-sm-6">
                    <h1 class="m-0">Ajouter un utilisateur</h1>
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
                                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="password">Mot de passe</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role_id">Rôle</label>
                                            <select class="form-control" id="role_id" name="role_id" required>
                                                <option value="">Sélectionner un rôle</option>
                                                <?php
                                                $roles_result->data_seek(0); // Réinitialiser le pointeur de résultat
                                                while ($role = $roles_result->fetch_assoc()):
                                                ?>
                                                    <option value="<?php echo $role['id']; ?>" <?php echo (isset($role_id) && $role['id'] == $role_id) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="status">Statut</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="1" <?php echo (isset($status) && $status == 1) ? 'selected' : ''; ?>>Actif</option>
                                                <option value="0" <?php echo (isset($status) && $status == 0) ? 'selected' : ''; ?>>Inactif</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="save" class="btn btn-primary">Enregistrer</button>
                                <button type="submit" name="save_and_list" class="btn btn-success">Enregistrer et retourner à la liste</button>
                                <a href="users.php" class="btn btn-default">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    $(document).ready(function() {
        // Validation du formulaire
        $('form').on('submit', function(e) {
            const password = $('#password').val();

            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                return false;
            }

            return true;
        });
    });
</script>

<?php
include 'includes/footer.php';
?>