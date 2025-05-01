<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Définir le titre de la page
$pageTitle = "Gérer les examens";

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Traitement des actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $examId = $_GET['id'];
    $action = $_GET['action'];
    
    // Vérifier que l'examen appartient à l'enseignant
    $stmt = $conn->prepare("SELECT id FROM exams WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $examId, $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        switch ($action) {
            case 'activate':
                $conn->query("UPDATE exams SET status = 'active' WHERE id = $examId");
                $successMessage = "L'examen a été activé avec succès.";
                break;
                
            case 'deactivate':
                $conn->query("UPDATE exams SET status = 'inactive' WHERE id = $examId");
                $successMessage = "L'examen a été désactivé avec succès.";
                break;
                
            case 'delete':
                // Vérifier s'il y a des résultats associés à cet examen
                $hasResults = $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE exam_id = $examId")->fetch_assoc()['count'] > 0;
                
                if ($hasResults) {
                    $errorMessage = "Impossible de supprimer cet examen car il a déjà des résultats associés.";
                } else {
                    // Supprimer les questions de l'examen
                    $conn->query("DELETE FROM questions WHERE exam_id = $examId");
                    
                    // Supprimer l'examen
                    $conn->query("DELETE FROM exams WHERE id = $examId");
                    
                    $successMessage = "L'examen a été supprimé avec succès.";
                }
                break;
                
            case 'duplicate':
                // Récupérer les données de l'examen
                $exam = $conn->query("SELECT * FROM exams WHERE id = $examId")->fetch_assoc();
                
                // Créer une copie de l'examen
                $stmt = $conn->prepare("
                    INSERT INTO exams (
                        teacher_id, title, subject, description, duration, passing_score, 
                        start_date, end_date, status, has_essay, randomize_questions, 
                        show_results, allow_retake, max_retakes, enable_face_recognition, 
                        enable_eye_tracking, enable_audio_monitoring, enable_screen_monitoring
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $title = $exam['title'] . ' (copie)';
                $stmt->bind_param(
                    "isssidssiiiiiiiii",
                    $teacherId, $title, $exam['subject'], $exam['description'], $exam['duration'], $exam['passing_score'],
                    $exam['start_date'], $exam['end_date'], $exam['has_essay'], $exam['randomize_questions'],
                    $exam['show_results'], $exam['allow_retake'], $exam['max_retakes'], $exam['enable_face_recognition'],
                    $exam['enable_eye_tracking'], $exam['enable_audio_monitoring'], $exam['enable_screen_monitoring']
                );
                
                if ($stmt->execute()) {
                    $newExamId = $conn->insert_id;
                    
                    // Copier les questions de l'examen
                    $questions = $conn->query("SELECT * FROM questions WHERE exam_id = $examId");
                    
                    while ($question = $questions->fetch_assoc()) {
                        $stmt = $conn->prepare("
                            INSERT INTO questions (
                                exam_id, question_text, question_type, options, correct_answer, points
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->bind_param(
                            "issssi",
                            $newExamId, $question['question_text'], $question['question_type'], 
                            $question['options'], $question['correct_answer'], $question['points']
                        );
                        
                        $stmt->execute();
                    }
                    
                    $successMessage = "L'examen a été dupliqué avec succès.";
                } else {
                    $errorMessage = "Une erreur s'est produite lors de la duplication de l'examen.";
                }
                break;
        }
    } else {
        $errorMessage = "Vous n'avez pas l'autorisation de modifier cet examen.";
    }
}

// Filtres et recherche
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête SQL
$sql = "
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.duration,
        e.status,
        e.start_date,
        e.end_date,
        e.created_at,
        COUNT(DISTINCT er.user_id) as participants,
        AVG(er.score) as avg_score,
        COUNT(DISTINCT eq.id) as question_count
    FROM exams e
    LEFT JOIN exam_results er ON e.id = er.exam_id
    LEFT JOIN questions eq ON e.id = eq.exam_id
    WHERE e.teacher_id = $teacherId
";

// Ajouter le filtre de statut
if ($statusFilter !== 'all') {
    $sql .= " AND e.status = '$statusFilter'";
}

// Ajouter la recherche
if (!empty($searchTerm)) {
    $sql .= " AND (e.title LIKE '%$searchTerm%' OR e.subject LIKE '%$searchTerm%')";
}

$sql .= " GROUP BY e.id ORDER BY e.created_at DESC";

// Exécuter la requête
$exams = $conn->query($sql);

// Inclure le header
include 'includes/header.php';
?>

<!-- Manage Exams Content -->
<div class="dashboard">
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
    
    <div class="filters-bar">
        <div class="search-box">
            <form action="" method="get">
                <div class="search-input">
                    <input style="border-radius: 5px;height: 40px;width: 400px;"type="text" name="search" placeholder="Rechercher un examen..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit"><i class="fas fa-search" style="margin-left: -390px;"></i></button>
                </div>
            </form>
        </div>
        
        <div class="status-filter">
            <a href="?status=all" class="filter-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">Tous</a>
            <a href="?status=active" class="filter-btn <?php echo $statusFilter === 'active' ? 'active' : ''; ?>">Actifs</a>
            <a href="?status=draft" class="filter-btn <?php echo $statusFilter === 'draft' ? 'active' : ''; ?>">Brouillons</a>
            <a href="?status=scheduled" class="filter-btn <?php echo $statusFilter === 'scheduled' ? 'active' : ''; ?>">Planifiés</a>
            <a href="?status=completed" class="filter-btn <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">Terminés</a>
        </div>
        
        <div class="create-btn">
            <a href="create-exam.php" class="btn btn-primary" style="text-decoration: none;">
                <i class="fas fa-plus"></i> Créer un examen
            </a>
        </div>
    </div>
    
    <div class="exams-grid">
        <?php if ($exams->num_rows > 0): ?>
            <?php while ($exam = $exams->fetch_assoc()): ?>
                <div class="card exam-card">
                    <div class="card-header">
                        <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <span class="status-badge <?php echo $exam['status']; ?>">
                            <?php echo ucfirst($exam['status']); ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <div class="exam-details">
                            <div class="detail-item">
                                <i class="fas fa-book"></i>
                                <span><?php echo htmlspecialchars($exam['subject']); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $exam['duration']; ?> minutes</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-question-circle"></i>
                                <span><?php echo $exam['question_count']; ?> questions</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-user-graduate"></i>
                                <span><?php echo $exam['participants']; ?> participants</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-chart-line"></i>
                                <span><?php echo $exam['avg_score'] ? round($exam['avg_score'], 1) . '%' : 'N/A'; ?></span>
                            </div>
                        </div>
                        
                        <div class="exam-dates">
                            <div class="date-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Début: <?php echo date('d/m/Y H:i', strtotime($exam['start_date'])); ?></span>
                            </div>
                            <div class="date-item">
                                <i class="fas fa-calendar-check"></i>
                                <span>Fin: <?php echo date('d/m/Y H:i', strtotime($exam['end_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="exam-actions" style="margin-left: -15px;text-decoration: none;">
                        
                            <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm" style="text-decoration: none;">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                            <a style="text-decoration: none;" href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> Aperçu
                            </a>
                            <a style="text-decoration: none;background-color:purple ;border:none"href="view-results.php?id=<?php echo $exam['id']; ?>" class="btn btn-success btn-sm" >
                                <i class="fas fa-chart-bar"></i> Résultats
                            </a>
                            <a style="text-decoration: none;"href="add-questions.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-success btn-sm" >
                                <i class="fas fa-plus"></i> Questions
                            </a>
                            <a href="?action=delete&id=<?php echo $exam['id']; ?>"class="btn btn-success btn-sm" style="background-color: red;border:none">
                                <i class="fas fa-trash"></i> 
                            </a>
                            <div class="dropdown">
                                <!-- <button class="btn btn-secondary btn-sm dropdown-toggle">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button> -->
                                <!-- <div class="dropdown-menu" >
                                    <?php if ($exam['status'] !== 'active'): ?>
                                        <a href="?action=activate&id=<?php echo $exam['id']; ?>" class="dropdown-item">
                                            <i class="fas fa-play"></i> Activer
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=deactivate&id=<?php echo $exam['id']; ?>" class="dropdown-item">
                                            <i class="fas fa-pause"></i> Désactiver
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=duplicate&id=<?php echo $exam['id']; ?>" class="dropdown-item">
                                        <i class="fas fa-copy"></i> Dupliquer
                                    </a>
                                    <a href="export-exam.php?id=<?php echo $exam['id']; ?>" class="dropdown-item">
                                        <i class="fas fa-download"></i> Exporter
                                    </a>
                                    <?php if ($exam['participants'] == 0): ?>
                                        <a href="?action=delete&id=<?php echo $exam['id']; ?>" class="dropdown-item text-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet examen ?');">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    <?php endif; ?>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Aucun examen trouvé</h3>
                <p>Vous n'avez pas encore créé d'examen ou aucun examen ne correspond à vos critères de recherche.</p>
                <a href="create-exam.php" class="btn btn-primary">Créer un examen</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des dropdowns
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdownMenu = this.nextElementSibling;
            
            // Fermer tous les autres dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                if (menu !== dropdownMenu) {
                    menu.classList.remove('show');
                }
            });
            
            dropdownMenu.classList.toggle('show');
        });
    });
    
    // Fermer les dropdowns quand on clique ailleurs
    window.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            menu.classList.remove('show');
        });
    });
});
</script>

