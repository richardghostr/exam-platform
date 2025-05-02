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

// Fonction pour obtenir la classe de couleur en fonction du score
function getScoreClass($score) {
    if ($score >= 80) return 'success';
    if ($score >= 60) return 'info';
    if ($score >= 40) return 'warning';
    return 'danger';
}

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Récupérer les examens qui ont des questions à réponse libre à noter
$sql = "
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.status,
        COUNT(er.id) as total_submissions,
        SUM(CASE WHEN er.is_graded = 0 THEN 1 ELSE 0 END) as pending_grades
    FROM exams e
    JOIN exam_results er ON e.id = er.exam_id
    WHERE e.teacher_id = $teacherId 
    AND e.has_essay = 1
    AND er.status = 'completed'
    GROUP BY e.id
    HAVING pending_grades > 0
    ORDER BY e.created_at DESC
";

$examsToGrade = $conn->query($sql);

// Récupérer les examens récemment notés
$recentlyGraded = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        u.id as user_id,
        u.username,
        u.first_name,
        u.last_name,
        er.score,
        er.graded_at
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    JOIN users u ON er.user_id = u.id
    WHERE e.teacher_id = $teacherId 
    AND er.is_graded = 1
    ORDER BY er.graded_at DESC
    LIMIT 5
");

$pageTitle = "Noter les examens";
include 'includes/header.php';
?>
<style>
    /* Style général */

/* En-tête */
.h2 {
    font-size: 1.75rem;
    font-weight: 600;
    color: #2c3e50;
}


/* Boutons */
.btn {
    font-size: 0.875rem;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.75rem;
}

.btn-outline-secondary {
    border-color: #d1d3e2;
    color: #6e707e;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
}

.btn-primary {
    background-color: #4e73df;
    border-color: #4e73df;
}

.btn-primary:hover {
    background-color: #3a5bc7;
    border-color: #3a5bc7;
}

.btn-success {
    background-color: #1cc88a;
    border-color: #1cc88a;
}

.btn-success:hover {
    background-color: #17a673;
    border-color: #17a673;
}

.btn-outline-info {
    border-color: #36b9cc;
    color: #36b9cc;
}

.btn-outline-info:hover {
    background-color: #36b9cc;
    color: white;
}

/* Cartes */
.card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    background-color: white;
    margin-left: 25px;
    margin-right: 25px;
}

.card-header {
    background-color: white;
    border-bottom: 1px solid #e0e0e0;
    font-weight: 600;
    border-radius: 20px 20px 0 0;
}





/* Tableaux */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 12px 15px;
    text-align: left;
    border-bottom: 2px solid #e0e0e0;
}

.table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

/* Badges */
.badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 20px;
}

.bg-primary {
    background-color: #4e73df !important;
}

.bg-success {
    background-color: #1cc88a !important;
}

.bg-info {
    background-color: #36b9cc !important;
}

.bg-warning {
    background-color: #f6c23e !important;
    color: #212529 !important;
}

.bg-secondary {
    background-color: #858796 !important;
}

.bg-danger {
    background-color: #e74a3b !important;
}

/* Avatars et icônes */
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #4e73df;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.75rem;
}

.exam-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Barres de progression */
.progress {
    height: 8px;
    border-radius: 4px;
    background-color: #eaecf4;
}

.progress-bar {
    border-radius: 4px;
}

/* Listes */
.list-group-item {
    border: 1px solid #e0e0e0;
    padding: 12px 15px;
}

/* Formulaires */
.form-control {
    border: 1px solid #d1d3e2;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.875rem;
}

.form-control:focus {
    border-color: #bac8f3;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.input-group-text {
    background-color: #f8f9fa;
    border: 1px solid #d1d3e2;
    font-size: 0.875rem;
}

/* Zones de texte */
.question-text {
    font-size: 1rem;
    line-height: 1.6;
}

.student-answer {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 3px solid #4e73df;
}

/* Toast */
.toast {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Icônes Font Awesome */
.fas {
    font-size: 0.875em;
}

/* Utilitaires */
.text-muted {
    color: #858796 !important;
}

.py-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
}

/* Responsive */
@media (max-width: 768px) {
    .card-body {
        padding: 15px;
    }
    
    .table td, .table th {
        padding: 8px 10px;
    }
    
    .btn {
        padding: 6px 12px;
    }
}
</style>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2" style="margin-left: 25px;margin-right: 25px;margin-top: 20px;margin-bottom: 20px;">
                        <button type="button" class="btn btn-secondary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <button type="button" class="btn btn-info" style="margin-left: 10px;">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Examens à noter -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Examens à noter</h5>
                    <span class="badge bg-primary rounded-pill">
                        <?php echo $examsToGrade->num_rows; ?> examens
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($examsToGrade->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titre</th>
                                        <th>Matière</th>
                                        <th>Statut</th>
                                        <th>Soumissions</th>
                                        <th>En attente</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($exam = $examsToGrade->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="exam-icon me-3">
                                                        <i class="fas fa-file-alt text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($exam['title']); ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $exam['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($exam['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $exam['total_submissions']; ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark"><?php echo $exam['pending_grades']; ?></span>
                                            </td>
                                            <td>
                                                <a href="grade-exam-submissions.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-check-circle me-1"></i> Noter
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success fa-4x"></i>
                            </div>
                            <h4>Aucun examen à noter</h4>
                            <p class="text-muted">Tous les examens ont été notés ou aucun examen ne contient de questions à réponse libre.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Examens récemment notés -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Examens récemment notés</h5>
                </div>
                <div class="card-body">
                    <?php if ($recentlyGraded->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Examen</th>
                                        <th>Étudiant</th>
                                        <th>Score</th>
                                        <th>Date de notation</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($result = $recentlyGraded->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="exam-icon me-3">
                                                        <i class="fas fa-file-alt text-info"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($result['title']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($result['subject']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-2">
                                                        <?php echo strtoupper(substr($result['first_name'], 0, 1) . substr($result['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 8px; width: 80px;">
                                                    <div class="progress-bar bg-<?php echo getScoreClass($result['score']); ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $result['score']; ?>%;" 
                                                         aria-valuenow="<?php echo $result['score']; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="ms-1"><?php echo $result['score']; ?>%</small>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($result['graded_at'])); ?></td>
                                            <td>
                                                <a href="view-submission.php?exam_id=<?php echo $result['id']; ?>&user_id=<?php echo $result['user_id']; ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i> Voir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-history text-secondary fa-4x"></i>
                            </div>
                            <h4>Aucun examen récemment noté</h4>
                            <p class="text-muted">Vous n'avez pas encore noté d'examens.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Exemple de notation -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Exemple de notation</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Question</h6>
                                </div>
                                <div class="card-body">
                                    <p class="question-text">
                                        Expliquez les principes fondamentaux de la programmation orientée objet et donnez des exemples concrets de leur application.
                                    </p>
                                    <div class="question-info mt-3">
                                        <span class="badge bg-info me-2">Question à réponse libre</span>
                                        <span class="badge bg-secondary">10 points</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Réponse de l'étudiant</h6>
                                </div>
                                <div class="card-body">
                                    <div class="student-answer">
                                        <p>La programmation orientée objet (POO) est un paradigme de programmation basé sur le concept d'objets qui contiennent des données et du code. Les principes fondamentaux sont :</p>
                                        
                                        <ol>
                                            <li><strong>Encapsulation</strong> : Regrouper les données et les méthodes qui les manipulent. Par exemple, une classe "Compte" qui encapsule le solde et les méthodes pour déposer/retirer de l'argent.</li>
                                            
                                            <li><strong>Héritage</strong> : Permet à une classe d'hériter des propriétés d'une autre classe. Par exemple, une classe "CompteCourant" qui hérite de "Compte" mais ajoute des fonctionnalités spécifiques.</li>
                                            
                                            <li><strong>Polymorphisme</strong> : Capacité d'un objet à prendre plusieurs formes. Par exemple, une méthode "calculerIntérêts" qui se comporte différemment selon qu'elle est appelée sur un "CompteCourant" ou un "CompteEpargne".</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Notation</h6>
                                </div>
                                <div class="card-body">
                                    <form>
                                        <div class="mb-3">
                                            <label for="score" class="form-label">Score</label>
                                            <div class="input-group">
                                                <input type="number" id="score" class="form-control" min="0" max="10" value="8">
                                                <span class="input-group-text">/ 10</span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="feedback" class="form-label">Commentaire</label>
                                            <textarea id="feedback" class="form-control" rows="6">Bonne explication des concepts fondamentaux de la POO. Les exemples sont pertinents et bien choisis.

Il manque cependant une mention de l'abstraction qui est aussi un concept important en POO. Vous auriez pu également approfondir davantage le polymorphisme avec des exemples plus concrets.

Dans l'ensemble, une réponse solide qui démontre une bonne compréhension des principes de la POO.</textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between"><br>
                                            <button type="button" class="btn btn-outline-secondary">
                                                <i class="fas fa-arrow-left me-1"></i> Précédent
                                            </button>
                                            <div><br>
                                                <button type="button" class="btn btn-success me-2">
                                                    <i class="fas fa-save me-1"></i> Enregistrer
                                                </button>
                                                <button type="button" class="btn btn-primary">
                                                    Suivant <i class="fas fa-arrow-right ms-1"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Critères d'évaluation</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group" style="border: none;">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Explication des concepts (4 points)
                                            <span class="badge bg-primary rounded-pill">3</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Exemples pertinents (3 points)
                                            <span class="badge bg-primary rounded-pill">3</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Clarté et structure (2 points)
                                            <span class="badge bg-primary rounded-pill">2</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Exhaustivité (1 point)
                                            <span class="badge bg-primary rounded-pill">0</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Gestion de la soumission du formulaire d'exemple
    const saveBtn = document.querySelector('.btn-success');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            // Simuler une sauvegarde
            const toast = new bootstrap.Toast(document.getElementById('saveToast'));
            toast.show();
        });
    }
});

// Fonction pour obtenir la classe de couleur en fonction du score
function getScoreClass(score) {
    if (score >= 80) return 'success';
    if (score >= 60) return 'info';
    if (score >= 40) return 'warning';
    return 'danger';
}
</script>

<!-- Toast pour la sauvegarde -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11" >
    <div id="saveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" style="margin-left: 25px;margin-right: 25px;padding: 20px;margin-bottom: 20px;background-color: #fff;">
        <div class="toast-header">
            <i class="fas fa-check-circle text-success me-2"></i>
            <strong class="me-auto">Notation enregistrée</strong>
            <small>À l'instant</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" >
            La notation a été enregistrée avec succès.
        </div>
    </div>
</div>