<?php
// Inclure les fichiers de configuration et fonctions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le rôle admin
require_login('../login.php');
require_role('admin', '../index.php');

// Connexion à la base de données
include_once '../includes/db.php';

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtres
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Construire la requête SQL avec les filtres
$where_clauses = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(e.title LIKE ? OR e.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($category > 0) {
    $where_clauses[] = "e.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Requête pour compter le nombre total d'examens
$count_sql = "SELECT COUNT(*) as total FROM exams e $where_sql";
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_exams = $count_row['total'];
$total_pages = ceil($total_exams / $per_page);

// Requête pour récupérer les examens avec pagination
$exams_sql = "
    SELECT e.*, u.username as creator_name, c.name as category_name,
           (SELECT COUNT(*) FROM exam_enrollments WHERE exam_id = e.id) as enrollment_count,
           (SELECT COUNT(*) FROM exam_attempts ea JOIN exam_enrollments ee ON ea.enrollment_id = ee.id WHERE ee.exam_id = e.id) as attempt_count
    FROM exams e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN exam_categories c ON e.category_id = c.id
    $where_sql
    ORDER BY e.created_at DESC
    LIMIT ?, ?
";

$exams_stmt = $conn->prepare($exams_sql);
$params[] = $offset;
$params[] = $per_page;
$types .= 'ii';

$exams_stmt->bind_param($types, ...$params);
$exams_stmt->execute();
$exams_result = $exams_stmt->get_result();
$exams = $exams_result->fetch_all(MYSQLI_ASSOC);

// Récupérer les catégories pour le filtre
$categories_query = "SELECT * FROM exam_categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les examens - ExamSafe</title>
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
                    <h1>Gérer les examens</h1>
                    <div class="page-actions">
                        <a href="create-exam.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Créer un examen
                        </a>
                    </div>
                </div>
                
                <!-- Filtres -->
                <div class="filters-card">
                    <form method="GET" action="" class="filters-form">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label for="search">Rechercher</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Titre ou description...">
                            </div>
                            <div class="filter-group">
                                <label for="status">Statut</label>
                                <select id="status" name="status">
                                    <option value="">Tous</option>
                                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Publié</option>
                                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archivé</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="category">Catégorie</label>
                                <select id="category" name="category">
                                    <option value="0">Toutes</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category === $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                <a href="manage-exams.php" class="btn btn-outline">Réinitialiser</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Liste des examens -->
                <div class="card">
                    <div class="card-header">
                        <h2>Liste des examens</h2>
                        <span class="badge"><?php echo $total_exams; ?> examens</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Titre</th>
                                        <th>Catégorie</th>
                                        <th>Créateur</th>
                                        <th>Date de création</th>
                                        <th>Statut</th>
                                        <th>Inscriptions</th>
                                        <th>Tentatives</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exams)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucun examen trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($exams as $exam): ?>
                                            <tr>
                                                <td>
                                                    <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="exam-title">
                                                        <?php echo htmlspecialchars($exam['title']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($exam['category_name'] ?? 'Non catégorisé'); ?></td>
                                                <td><?php echo htmlspecialchars($exam['creator_name']); ?></td>
                                                <td><?php echo format_date($exam['created_at']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $exam['status']; ?>">
                                                        <?php 
                                                        switch ($exam['status']) {
                                                            case 'draft':
                                                                echo 'Brouillon';
                                                                break;
                                                            case 'published':
                                                                echo 'Publié';
                                                                break;
                                                            case 'archived':
                                                                echo 'Archivé';
                                                                break;
                                                            default:
                                                                echo $exam['status'];
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $exam['enrollment_count']; ?></td>
                                                <td><?php echo $exam['attempt_count']; ?></td>
                                                <td class="actions-cell">
                                                    <div class="actions-dropdown">
                                                        <button class="actions-btn">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="actions-dropdown-content">
                                                            <a href="edit-exam.php?id=<?php echo $exam['id']; ?>">
                                                                <i class="fas fa-edit"></i> Modifier
                                                            </a>
                                                            <a href="preview-exam.php?id=<?php echo $exam['id']; ?>">
                                                                <i class="fas fa-eye"></i> Aperçu
                                                            </a>
                                                            <a href="exam-results.php?id=<?php echo $exam['id']; ?>">
                                                                <i class="fas fa-chart-bar"></i> Résultats
                                                            </a>
                                                            <?php if ($exam['status'] !== 'published'): ?>
                                                                <a href="api/exams.php?action=publish&id=<?php echo $exam['id']; ?>" class="text-success">
                                                                    <i class="fas fa-check-circle"></i> Publier
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="api/exams.php?action=unpublish&id=<?php echo $exam['id']; ?>" class="text-warning">
                                                                    <i class="fas fa-pause-circle"></i> Dépublier
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="api/exams.php?action=duplicate&id=<?php echo $exam['id']; ?>" class="text-info">
                                                                <i class="fas fa-copy"></i> Dupliquer
                                                            </a>
                                                            <a href="api/exams.php?action=delete&id=<?php echo $exam['id']; ?>" class="text-danger delete-confirm">
                                                                <i class="fas fa-trash-alt"></i> Supprimer
                                                            </a>
                                                        </div>
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
                                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>" 
                                       class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <script>
        // Confirmation de suppression
        document.querySelectorAll('.delete-confirm').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer cet examen ? Cette action est irréversible.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
