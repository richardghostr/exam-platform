<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle admin
require_login('../login.php');
require_role('admin', '../index.php');

// Connexion à la base de données
include_once '../includes/db.php';

// Traitement des actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($action === 'delete' && $user_id > 0) {
        // Vérifier que l'utilisateur n'est pas l'administrateur actuel
        if ($user_id == $_SESSION['user_id']) {
            $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            // Supprimer l'utilisateur
            $delete_query = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Utilisateur supprimé avec succès.";
            } else {
                $error_message = "Erreur lors de la suppression de l'utilisateur: " . $conn->error;
            }
            
            $stmt->close();
        }
    } elseif ($action === 'update' && $user_id > 0) {
        $role = isset($_POST['role']) ? $_POST['role'] : '';
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        
        if (!empty($role) && !empty($status)) {
            // Mettre à jour le rôle et le statut de l'utilisateur
            $update_query = "UPDATE users SET role = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssi", $role, $status, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Utilisateur mis à jour avec succès.";
            } else {
                $error_message = "Erreur lors de la mise à jour de l'utilisateur: " . $conn->error;
            }
            
            $stmt->close();
        }
    }
}

// Paramètres de pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Construction de la requête
$query = "SELECT * FROM users WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM users WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $count_query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $count_query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Ajouter l'ordre et la limite
$query .= " ORDER BY created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

// Préparer et exécuter la requête pour compter le nombre total d'utilisateurs
$count_stmt = $conn->prepare($count_query);
if (!empty($types) && !empty($params)) {
    $count_types = substr($types, 0, -2); // Enlever les deux derniers caractères (ii pour offset et limit)
    $count_params = array_slice($params, 0, -2); // Enlever les deux derniers paramètres
    
    if (!empty($count_types)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_users = $count_row['total'];
$total_pages = ceil($total_users / $limit);
$count_stmt->close();

// Préparer et exécuter la requête pour récupérer les utilisateurs
$stmt = $conn->prepare($query);
if (!empty($types) && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - ExamSafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Contenu principal -->
        <main class="admin-main">
            <!-- En-tête -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Contenu de la page -->
            <div class="admin-content">
                <div class="page-header">
                    <h1>Gestion des utilisateurs</h1>
                    <a href="add-user.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter un utilisateur
                    </a>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Filtres et recherche -->
                <section class="filters-section">
                    <form action="" method="GET" class="filters-form">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="role" class="form-control">
                                <option value="">Tous les rôles</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Enseignant</option>
                                <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Étudiant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="">Tous les statuts</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspendu</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline">Filtrer</button>
                        <a href="users.php" class="btn btn-link">Réinitialiser</a>
                    </form>
                </section>
                
                <!-- Liste des utilisateurs -->
                <section class="users-section">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom d'utilisateur</th>
                                    <th>Nom complet</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Date d'inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucun utilisateur trouvé</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                                    <?php 
                                                        switch($user['role']) {
                                                            case 'admin': echo 'Administrateur'; break;
                                                            case 'teacher': echo 'Enseignant'; break;
                                                            case 'student': echo 'Étudiant'; break;
                                                            default: echo ucfirst($user['role']);
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                                    <?php 
                                                        switch($user['status']) {
                                                            case 'active': echo 'Actif'; break;
                                                            case 'inactive': echo 'Inactif'; break;
                                                            case 'pending': echo 'En attente'; break;
                                                            case 'suspended': echo 'Suspendu'; break;
                                                            default: echo ucfirst($user['status']);
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <button class="btn-icon delete-btn" data-id="<?php echo $user['id']; ?>" title="Supprimer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link">
                                    <i class="fas fa-chevron-left"></i> Précédent
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link">
                                    Suivant <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmer la suppression</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete-user-id" value="">
                    <button type="button" class="btn btn-outline" id="cancel-delete">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du modal de suppression
            const modal = document.getElementById('delete-modal');
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const closeButton = document.querySelector('.close');
            const cancelButton = document.getElementById('cancel-delete');
            const deleteUserIdInput = document.getElementById('delete-user-id');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    deleteUserIdInput.value = userId;
                    modal.style.display = 'block';
                });
            });
            
            closeButton.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            cancelButton.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
