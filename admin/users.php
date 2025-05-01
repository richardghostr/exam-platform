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

// Traitement des actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $action = $_GET['action'];
    
    switch ($action) {
        case 'activate':
            $conn->query("UPDATE users SET status = 'active' WHERE id = $userId");
            $successMessage = "L'utilisateur a été activé avec succès.";
            break;
            
        case 'deactivate':
            $conn->query("UPDATE users SET status = 'inactive' WHERE id = $userId");
            $successMessage = "L'utilisateur a été désactivé avec succès.";
            break;
            
        case 'delete':
            // Vérifier si l'utilisateur a des examens ou des résultats
            $hasExams = $conn->query("SELECT COUNT(*) as count FROM exams WHERE teacher_id = $userId")->fetch_assoc()['count'] > 0;
            $hasResults = $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE user_id = $userId")->fetch_assoc()['count'] > 0;
            
            if ($hasExams || $hasResults) {
                $errorMessage = "Impossible de supprimer cet utilisateur car il a des examens ou des résultats associés.";
            } else {
                $conn->query("DELETE FROM users WHERE id = $userId");
                $successMessage = "L'utilisateur a été supprimé avec succès.";
            }
            break;
    }
}

// Filtres et recherche
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête SQL
$sql = "SELECT * FROM users WHERE 1=1";

// Ajouter le filtre de rôle
if ($roleFilter !== 'all') {
    $sql .= " AND role = '$roleFilter'";
}

// Ajouter le filtre de statut
if ($statusFilter !== 'all') {
    $sql .= " AND status = '$statusFilter'";
}

// Ajouter la recherche
if (!empty($searchTerm)) {
    $sql .= " AND (username LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%' OR first_name LIKE '%$searchTerm%' OR last_name LIKE '%$searchTerm%')";
}

$sql .= " ORDER BY created_at DESC";

// Exécuter la requête
$users = $conn->query($sql);

$pageTitle = "Gestion des utilisateurs";
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-20">
    <h1 class="page-title" style="margin-left: 5px;">Gestion des utilisateurs</h1>
    <a href="add-user.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Ajouter un utilisateur
    </a>
</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger">
        <?php echo $errorMessage; ?>
    </div>
<?php endif; ?>

<div class="card mb-20">
    <div class="card-header">
        <h2 class="card-title">Filtres</h2>
    </div>
    <div class="card-body">
        <form action="" method="get" class="d-flex gap-20">
            <div class="form-group" style="flex: 1;">
                <label for="role" class="form-label">Rôle</label>
                <select id="role" name="role" class="form-control">
                    <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>Tous les rôles</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Étudiants</option>
                    <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Enseignants</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Administrateurs</option>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1;">
                <label for="status" class="form-label">Statut</label>
                <select id="status" name="status" class="form-control">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                </select>
            </div>
            
            <div class="form-group" style="flex: 2;">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Nom, email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
            </div>
            
            <div class="form-group d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Liste des utilisateurs</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Date d'inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-10">
                                        <img src="../assets/images/avatar.png" alt="Avatar" class="user-avatar" style="width: 32px; height: 32px;">
                                        <div>
                                            <div><?php echo htmlspecialchars($user['username']); ?></div>
                                            <div class="text-muted"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo getRoleBadgeClass($user['role']); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getStatusBadgeClass($user['status']); ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex gap-10">
                                        <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="?action=deactivate&id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-user-slash"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-user-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Aucun utilisateur trouvé</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Helper functions for badges
function getRoleBadgeClass($role) {
    switch($role) {
        case 'admin':
            return 'danger';
        case 'teacher':
            return 'info';
        case 'student':
            return 'success';
        default:
            return 'secondary';
    }
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'active':
            return 'success';
        case 'inactive':
            return 'warning';
        case 'pending':
            return 'info';
        default:
            return 'secondary';
    }
}
?>

<?php include 'includes/footer.php'; ?>
