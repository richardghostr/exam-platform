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
    $action = $_GET['action'];
    $examId = (int)$_GET['id'];
    
    if ($action === 'delete') {
        // Supprimer l'examen
        $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        // Rediriger pour éviter la resoumission du formulaire
        header('Location: manage-exams.php?message=deleted');
        exit();
    } elseif ($action === 'publish') {
        // Publier l'examen
        $stmt = $conn->prepare("UPDATE exams SET status = 'published' WHERE id = ?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        header('Location: manage-exams.php?message=published');
        exit();
    } elseif ($action === 'unpublish') {
        // Dépublier l'examen
        $stmt = $conn->prepare("UPDATE exams SET status = 'draft' WHERE id = ?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        
        header('Location: manage-exams.php?message=unpublished');
        exit();
    }
}

// Récupérer les messages de succès
$message = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'deleted':
            $message = "L'examen a été supprimé avec succès.";
            break;
        case 'published':
            $message = "L'examen a été publié avec succès.";
            break;
        case 'unpublished':
            $message = "L'examen a été dépublié avec succès.";
            break;
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$subjectFilter = isset($_GET['subject']) ? $_GET['subject'] : '';

// Construire la requête SQL avec les filtres
$sql = "SELECT e.*, s.name as subject_name, u.username as creator_name 
        FROM exams e 
        LEFT JOIN subjects s ON e.subject = s.id 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE 1=1";

$countSql = "SELECT COUNT(*) as total FROM exams WHERE 1=1";
$params = [];
$types = "";

if (!empty($searchTerm)) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ?)";
    $countSql .= " AND (title LIKE ? OR description LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($statusFilter)) {
    $sql .= " AND e.status = ?";
    $countSql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($subjectFilter)) {
    $sql .= " AND e.subject = ?";
    $countSql .= " AND subject = ?";
    $params[] = $subjectFilter;
    $types .= "i";
}

$sql .= " ORDER BY e.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

// Exécuter la requête pour obtenir les examens
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compter le nombre total d'examens pour la pagination
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    // Enlever les deux derniers paramètres (offset et limit)
    array_pop($params);
    array_pop($params);
    if (!empty($params)) {
        $countStmt->bind_param(substr($types, 0, -2), ...$params);
    }
}
$countStmt->execute();
$totalExams = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalExams / $limit);

// Récupérer la liste des matières pour le filtre
$subjects = [];
$subjectStmt = $conn->prepare("SELECT * FROM subjects ORDER BY name");
$subjectStmt->execute();
$subjectResult = $subjectStmt->get_result();
while ($row = $subjectResult->fetch_assoc()) {
    $subjects[] = $row;
}

$pageTitle = "Gestion des examens";
include 'includes/header.php';
?>

<div class="card mb-20">
    
    <div class="card-body">
        <div class="admin-header">
            <div>
                <div class="page-path">
                    <a href="index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Examens</span>
                </div>
                <h1 class="page-title"><?php echo $pageTitle; ?></h1>
            </div>
        </div>
        
        <div class="main-content">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="section-header">
                <h2 class="section-title" style="margin-left: 20px;">Liste des examens</h2>
                <div class="section-actions">
                    <a href="create-exam.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouvel examen
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title" style="margin-right: 5px;">Examens (<?php echo $totalExams; ?>)</h3>
                        <div class="d-flex gap-10">
                            <select id="statusFilter" class="form-control">
                                <option value="">Tous les statuts</option>
                                <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Publié</option>
                                <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>Planifié</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Terminé</option>
                            </select>
                            <select id="subjectFilter" class="form-control">
                                <option value="">Toutes les matières</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $subjectFilter == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo $subject['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Matière</th>
                                    <th>Durée</th>
                                    <th>Date de début</th>
                                    <th>Statut</th>
                                    <th>Créé par</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($exams)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Aucun examen trouvé.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($exams as $exam): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="exam-icon">
                                                        <i class="fas fa-file-alt"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold"><?php echo htmlspecialchars($exam['title']); ?></div>
                                                        <div class="text-muted"><?php echo substr(htmlspecialchars($exam['description']), 0, 50) . (strlen($exam['description']) > 50 ? '...' : ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                                            <td><?php echo $exam['duration']; ?> min</td>
                                            <td>
                                                <?php 
                                                    echo !empty($exam['start_date']) 
                                                        ? date('d/m/Y H:i', strtotime($exam['start_date'])) 
                                                        : 'Non défini'; 
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    
                                                    switch ($exam['status']) {
                                                        case 'draft':
                                                            $statusClass = 'badge-warning';
                                                            $statusText = 'Brouillon';
                                                            break;
                                                        case 'published':
                                                            $statusClass = 'badge-success';
                                                            $statusText = 'Publié';
                                                            break;
                                                        case 'scheduled':
                                                            $statusClass = 'badge-info';
                                                            $statusText = 'Planifié';
                                                            break;
                                                        case 'completed':
                                                            $statusClass = 'badge-secondary';
                                                            $statusText = 'Terminé';
                                                            break;
                                                    }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($exam['creator_name']); ?></td>
                                            <td>
                                                <div class="d-flex gap-10">
                                                <a href="add-questions.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-icon-sm btn-secondary" title="Ajouter des questions">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                    <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-icon-sm btn-secondary" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-icon-sm btn-info" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($exam['status'] === 'draft' || $exam['status'] === 'scheduled'): ?>
                                                        <a href="manage-exams.php?action=publish&id=<?php echo $exam['id']; ?>" class="btn btn-icon-sm btn-success" title="Publier" onclick="return confirm('Êtes-vous sûr de vouloir publier cet examen ?');">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php elseif ($exam['status'] === 'published'): ?>
                                                        <a href="manage-exams.php?action=unpublish&id=<?php echo $exam['id']; ?>" class="btn btn-icon-sm btn-warning" title="Dépublier" onclick="return confirm('Êtes-vous sûr de vouloir dépublier cet examen ?');">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="manage-exams.php?action=delete&id=<?php echo $exam['id']; ?>" class="btn btn-icon-sm btn-danger" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet examen ? Cette action est irréversible.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <div class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&subject=<?php echo urlencode($subjectFilter); ?>" class="page-link"><?php echo $i; ?></a>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recherche
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    
    // Filtres
    const statusFilter = document.getElementById('statusFilter');
    const subjectFilter = document.getElementById('subjectFilter');
    
    statusFilter.addEventListener('change', applyFilters);
    subjectFilter.addEventListener('change', applyFilters);
    
    function applyFilters() {
        const searchValue = searchInput.value;
        const statusValue = statusFilter.value;
        const subjectValue = subjectFilter.value;
        
        window.location.href = `manage-exams.php?search=${encodeURIComponent(searchValue)}&status=${encodeURIComponent(statusValue)}&subject=${encodeURIComponent(subjectValue)}`;
    }
});
</script>
