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

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-exams.php');
    exit();
}

$examId = intval($_GET['id']);

// Vérifier si l'examen appartient à l'enseignant
$examQuery = $conn->query("SELECT * FROM exams WHERE id = $examId AND teacher_id = $teacherId");
if ($examQuery->num_rows === 0) {
    header('Location: manage-exams.php');
    exit();
}

$exam = $examQuery->fetch_assoc();

// Récupérer les classes assignées à cet examen
$classesQuery = $conn->query("
    SELECT c.* 
    FROM classes c 
    JOIN exam_classes ec ON c.id = ec.class_id 
    WHERE ec.exam_id = $examId 
    ORDER BY c.name ASC
");

// Récupérer les questions de l'examen
$questionsQuery = $conn->query("
    SELECT q.*, COUNT(qo.id) as option_count 
    FROM questions q 
    LEFT JOIN question_options qo ON q.id = qo.question_id 
    WHERE q.exam_id = $examId 
    GROUP BY q.id 
    ORDER BY q.id ASC
");

// Récupérer les statistiques de l'examen
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_attempts,
        AVG(CASE WHEN status = 'completed' THEN score ELSE NULL END) as avg_score,
        MIN(CASE WHEN status = 'completed' THEN score ELSE NULL END) as min_score,
        MAX(CASE WHEN status = 'completed' THEN score ELSE NULL END) as max_score
    FROM exam_results 
    WHERE exam_id = $examId
");
$stats = $statsQuery->fetch_assoc();

$pageTitle = "Détails de l'examen";
include 'includes/header.php';
?>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        
        <div class="content-wrapper">
            <div class="content-header">
                <h1 class="page-title">Détails de l'examen</h1>
                <nav class="breadcrumb">
                    <ol>
                        <li><a href="index.php">Tableau de bord</a></li>
                        <li><a href="manage-exams.php">Gérer les examens</a></li>
                        <li class="active">Détails de l'examen</li>
                    </ol>
                </nav>
            </div>
            
            <div class="content-body">
                <div class="exam-header">
                    <div class="exam-title">
                        <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
                        <span class="status-badge <?php echo $exam['status']; ?>">
                            <?php echo ucfirst($exam['status']); ?>
                        </span>
                    </div>
                    <div class="exam-actions">
                        <a href="edit-exam.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-success">
                            <i class="fas fa-question-circle"></i> Gérer les questions
                        </a>
                        <a href="view-results.php?exam_id=<?php echo $examId; ?>" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> Voir les résultats
                        </a>
                        <button class="btn btn-danger" id="deleteExamBtn">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Informations générales</h2>
                            </div>
                            <div class="card-body">
                                <div class="exam-info">
                                    <div class="info-row">
                                        <div class="info-label">Matière:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($exam['subject']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Description:</div>
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Durée:</div>
                                        <div class="info-value"><?php echo $exam['duration']; ?> minutes</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Score de réussite:</div>
                                        <div class="info-value"><?php echo $exam['passing_score']; ?>%</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Nombre de questions:</div>
                                        <div class="info-value"><?php echo $exam['question_count']; ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Période:</div>
                                        <div class="info-value">
                                            Du <?php echo date('d/m/Y H:i', strtotime($exam['start_date'])); ?> 
                                            au <?php echo date('d/m/Y H:i', strtotime($exam['end_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Options:</div>
                                        <div class="info-value">
                                            <div class="option-badges">
                                                <?php if ($exam['proctoring_enabled']): ?>
                                                    <span class="badge badge-primary">Surveillance activée</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($exam['randomize_questions']): ?>
                                                    <span class="badge badge-info">Questions aléatoires</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($exam['show_results']): ?>
                                                    <span class="badge badge-success">Résultats immédiats</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($exam['has_essay']): ?>
                                                    <span class="badge badge-warning">Questions à réponse libre</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Questions (<?php echo $questionsQuery->num_rows; ?>)</h2>
                                <div class="card-actions">
                                    <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Ajouter
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($questionsQuery->num_rows > 0): ?>
                                    <div class="questions-list">
                                        <?php $questionNumber = 1; ?>
                                        <?php while ($question = $questionsQuery->fetch_assoc()): ?>
                                            <div class="question-item">
                                                <div class="question-header">
                                                    <div class="question-number"><?php echo $questionNumber++; ?></div>
                                                    <div class="question-type">
                                                        <?php 
                                                            $typeLabels = [
                                                                'multiple_choice' => 'Choix multiples',
                                                                'single_choice' => 'Choix unique',
                                                                'true_false' => 'Vrai/Faux',
                                                                'essay' => 'Réponse libre'
                                                            ];
                                                            echo $typeLabels[$question['question_type']] ?? $question['question_type'];
                                                        ?>
                                                    </div>
                                                    <div class="question-points"><?php echo $question['points']; ?> pts</div>
                                                </div>
                                                <div class="question-content">
                                                    <div class="question-text"><?php echo $question['question_text']; ?></div>
                                                    
                                                    <?php if ($question['question_type'] !== 'essay'): ?>
                                                        <?php 
                                                            $options = $conn->query("SELECT * FROM question_options WHERE question_id = {$question['id']} ORDER BY id ASC");
                                                        ?>
                                                        <div class="question-options">
                                                            <?php while ($option = $options->fetch_assoc()): ?>
                                                                <div class="option-item <?php echo $option['is_correct'] ? 'correct' : ''; ?>">
                                                                    <span class="option-marker"><?php echo $option['is_correct'] ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?></span>
                                                                    <span class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                                                </div>
                                                            <?php endwhile; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-question-circle"></i>
                                        </div>
                                        <h3>Aucune question</h3>
                                        <p>Cet examen ne contient pas encore de questions.</p>
                                        <a href="add-questions.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Ajouter des questions
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Statistiques</h2>
                            </div>
                            <div class="card-body">
                                <?php if ($stats['total_attempts'] > 0): ?>
                                    <div class="stats-container">
                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['total_attempts']; ?></div>
                                                <div class="stat-label">Tentatives totales</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo $stats['completed_attempts']; ?></div>
                                                <div class="stat-label">Examens complétés</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo round($stats['avg_score'], 1); ?>%</div>
                                                <div class="stat-label">Score moyen</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon">
                                                <i class="fas fa-trophy"></i>
                                            </div>
                                            <div class="stat-info">
                                                <div class="stat-value"><?php echo round($stats['max_score'], 1); ?>%</div>
                                                <div class="stat-label">Meilleur score</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <a href="view-results.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary">
                                            <i class="fas fa-chart-bar"></i> Voir tous les résultats
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <h3>Aucune donnée</h3>
                                        <p>Aucun étudiant n'a encore passé cet examen.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Classes assignées</h2>
                            </div>
                            <div class="card-body">
                                <?php if ($classesQuery->num_rows > 0): ?>
                                    <div class="classes-list">
                                        <?php while ($class = $classesQuery->fetch_assoc()): ?>
                                            <div class="class-item">
                                                <div class="class-icon">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div class="class-info">
                                                    <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
                                                    <div class="class-details"><?php echo htmlspecialchars($class['description']); ?></div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h3>Aucune classe assignée</h3>
                                        <p>Cet examen n'est assigné à aucune classe.</p>
                                        <a href="edit-exam.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Modifier l'examen
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Lien d'accès</h2>
                            </div>
                            <div class="card-body">
                                <div class="exam-link">
                                    <div class="link-info">
                                        <p>Partagez ce lien avec vos étudiants pour qu'ils puissent accéder à l'examen :</p>
                                        <div class="link-container">
                                            <input type="text" class="form-control" id="examLink" value="<?php echo BASE_URL . 'exam.php?id=' . $examId; ?>" readonly>
                                            <button class="btn btn-primary copy-link" data-clipboard-target="#examLink">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="qr-code">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode(BASE_URL . 'exam.php?id=' . $examId); ?>" alt="QR Code">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal" id="deleteExamModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmer la suppression</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer cet examen ? Cette action est irréversible et supprimera toutes les questions et résultats associés.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary close-modal">Annuler</button>
            <a href="delete-exam.php?id=<?php echo $examId; ?>" class="btn btn-danger">Supprimer</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser le presse-papier
    new ClipboardJS('.copy-link');
    
    // Gestion du modal de suppression
    const deleteExamBtn = document.getElementById('deleteExamBtn');
    const deleteExamModal = document.getElementById('deleteExamModal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    
    deleteExamBtn.addEventListener('click', function() {
        deleteExamModal.style.display = 'block';
    });
    
    closeModalBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            deleteExamModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === deleteExamModal) {
            deleteExamModal.style.display = 'none';
        }
    });
    
    // Notification de copie
    document.querySelector('.copy-link').addEventListener('click', function() {
        const notification = document.createElement('div');
        notification.className = 'copy-notification';
        notification.innerHTML = '<i class="fas fa-check"></i> Lien copié !';
        document.body.appendChild(notification);
        
        setTimeout(function() {
            notification.classList.add('show');
        }, 10);
        
        setTimeout(function() {
            notification.classList.remove('show');
            setTimeout(function() {
                document.body.removeChild(notification);
            }, 300);
        }, 2000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
