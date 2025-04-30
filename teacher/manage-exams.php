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
                    $conn->query("DELETE FROM exam_questions WHERE exam_id = $examId");
                    
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
                    $questions = $conn->query("SELECT * FROM exam_questions WHERE exam_id = $examId");
                    
                    while ($question = $questions->fetch_assoc()) {
                        $stmt = $conn->prepare("
                            INSERT INTO exam_questions (
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
    LEFT JOIN exam_questions eq ON e.id = eq.exam_id
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

$pageTitle = "Gérer les examens";
include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/teacher.css">

<div class="teacher-container">
    <div class="teacher-sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo">
                <div class="logo-icon">E</div>
                <span class="logo-text">ExamSafe</span>
            </a>
            <button class="sidebar-toggle" id="sidebarCollapseBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-category">Menu principal</div>
            <ul class="menu-items">
                <li class="menu-item">
                    <a href="index.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="menu-item-text">Tableau de bord</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="create-exam.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-plus-circle"></i></span>
                        <span class="menu-item-text">Créer un examen</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="manage-exams.php" class="menu-link active">
                        <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
                        <span class="menu-item-text">Gérer les examens</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="grade-exams.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-check-circle"></i></span>
                        <span class="menu-item-text">Noter les examens</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reports.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
                        <span class="menu-item-text">Rapports</span>
                    </a>
                </li>
            </ul>
            
            <div class="menu-category">Configuration</div>
            <ul class="menu-items">
                <li class="menu-item">
                    <a href="../profile.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-user"></i></span>
                        <span class="menu-item-text">Mon profil</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../logout.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span class="menu-item-text">Déconnexion</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="teacher-content">
        <div class="teacher-header">
            <div class="header-left">
                <button id="sidebarToggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="header-title"><?php echo $pageTitle; ?></h1>
            </div>
            
            <div class="header-right">
                <div class="notifications">
                    <i class="fas fa-bell notifications-icon"></i>
                    <span class="notifications-badge">3</span>
                </div>
                
                <div class="user-profile">
                    <img src="../assets/images/avatar.png" alt="Avatar" class="user-avatar">
                    <span class="user-name"><?php echo $_SESSION['username']; ?></span>
                    <i class="fas fa-chevron-down dropdown-toggle"></i>
                    
                    <div class="dropdown-menu">
                        <a href="../profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Mon profil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-content">
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
                            <input type="text" name="search" placeholder="Rechercher un examen..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <button type="submit"><i class="fas fa-search"></i></button>
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
                    <a href="create-exam.php" class="btn btn-primary btn-icon">
                        <i class="fas fa-plus"></i> Créer un examen
                    </a>
                </div>
            </div>
            
            <?php if ($exams->num_rows > 0): ?>
                <?php while ($exam = $exams->fetch_assoc()): ?>
                    <div class="exam-card">
                        <div class="exam-header">
                            <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
                            <span class="status-badge <?php echo $exam['status']; ?>">
                                <?php echo ucfirst($exam['status']); ?>
                            </span>
                        </div>
                        
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
                        
                        <div class="exam-actions">
                            <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-icon">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                            <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-info btn-icon">
                                <i class="fas fa-eye"></i> Aperçu
                            </a>
                            <a href="view-results.php?id=<?php echo $exam['id']; ?>" class="btn btn-success btn-icon">
                                <i class="fas fa-chart-bar"></i> Résultats
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-secondary dropdown-toggle">
                                    <i class="fas fa-ellipsis-v"></i> Plus
                                </button>
                                <div class="dropdown-menu">
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
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
                    <h3>Aucun examen trouvé</h3>
                    <p>Vous n'avez pas encore créé d'examen ou aucun examen ne correspond à vos critères de recherche.</p>
                    <a href="create-exam.php" class="btn btn-primary">Créer un examen</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu latéral
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    const teacherSidebar = document.querySelector('.teacher-sidebar');
    const teacherContent = document.querySelector('.teacher-content');
    
    sidebarToggle.addEventListener('click', function() {
        teacherSidebar.classList.toggle('show');
    });
    
    sidebarCollapseBtn.addEventListener('click', function() {
        teacherSidebar.classList.toggle('collapsed');
        teacherContent.classList.toggle('expanded');
        
        // Changer l'icône du bouton
        const icon = this.querySelector('i');
        if (icon.classList.contains('fa-chevron-left')) {
            icon.classList.replace('fa-chevron-left', 'fa-chevron-right');
        } else {
            icon.classList.replace('fa-chevron-right', 'fa-chevron-left');
        }
    });
    
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

<?php include '../includes/footer.php'; ?>
