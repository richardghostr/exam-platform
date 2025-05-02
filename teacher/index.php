<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Définir le titre de la page
$pageTitle = "Tableau de bord";

// Récupérer l'ID de l'enseignant
$teacherId = $_SESSION['user_id'];

// Récupérer les statistiques des examens de l'enseignant
$examStats = $conn->query("
    SELECT 
        COUNT(*) as total_exams,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_exams,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_exams,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exams
    FROM exams
    WHERE teacher_id = $teacherId
")->fetch_assoc();

// Récupérer les statistiques des étudiants
$studentStats = $conn->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_students,
        AVG(score) as avg_score,
        MAX(score) as max_score,
        MIN(score) as min_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
")->fetch_assoc();

// Récupérer les examens récents
$recentExams = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.status,
        e.created_at,
        COUNT(DISTINCT er.user_id) as participants,
        AVG(er.score) as avg_score
    FROM exams e
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE e.teacher_id = $teacherId
    GROUP BY e.id
    ORDER BY e.created_at DESC
    LIMIT 5
");

// Récupérer les examens à noter
$examsToGrade = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.subject,
        COUNT(er.id) as submissions,
        COUNT(CASE WHEN er.is_graded = 0 THEN er.id ELSE NULL END) as pending_grades
    FROM exams e
    JOIN exam_results er ON e.id = er.exam_id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed' AND e.has_essay = 1
    GROUP BY e.id
    HAVING pending_grades > 0
    ORDER BY e.created_at DESC
");

// Récupérer les incidents de surveillance récents
$proctorIncidents = $conn->query("
    SELECT 
        p.id,
        p.exam_id,
        e.title as exam_title,
        u.username,
        u.first_name,
        u.last_name,
        p.incident_type,
        p.timestamp,
        p.details
    FROM proctoring_incidents p
    JOIN exams e ON p.exam_id = e.id
    JOIN users u ON p.user_id = u.id
    WHERE e.teacher_id = $teacherId
    ORDER BY p.timestamp DESC
    LIMIT 5
");

// Inclure le header
include 'includes/header.php';
?>

<!-- Dashboard Content -->
<div class="dashboard">
    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card gradient-orange">
            <div class="card-body">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-title">Total des examens</h3>
                    <p class="stat-value"><?php echo $examStats['total_exams']; ?></p>
                    <p class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 12% depuis le mois dernier
                    </p>
                </div>
            </div>
        </div>
        
        <div class="stat-card gradient-blue">
            <div class="card-body">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-title">Étudiants</h3>
                    <p class="stat-value"><?php echo $studentStats['total_students']; ?></p>
                    <p class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 8% depuis le mois dernier
                    </p>
                </div>
            </div>
        </div>
        
        <div class="stat-card gradient-green">
            <div class="card-body">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-details">
                    <h3 class="stat-title">Score moyen</h3>
                    <p class="stat-value"><?php echo $studentStats['avg_score'] !== null ? round($studentStats['avg_score'], 1) . '%' : 'N/A'; ?></p>
                    <p class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 5% depuis le mois dernier
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="charts-section">
        <div class="card chart-card">
            <div class="card-header">
                <h2 class="card-title">Activité des examens</h2>
                <div class="card-actions">
                    <button class="btn btn-icon">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="examsActivityChart" height="250"></canvas>
            </div>
        </div>
        
        <div class="card chart-card">
            <div class="card-header">
                <h2 class="card-title">Répartition des statuts</h2>
                <div class="card-actions">
                    <button class="btn btn-icon">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="examStatusChart" height="250"></canvas>
                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-color" style="background-color: #4CAF50;"></span>
                        <span class="legend-label">Actifs</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color" style="background-color: #FFC107;"></span>
                        <span class="legend-label">Brouillons</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color" style="background-color: #2196F3;"></span>
                        <span class="legend-label">Terminés</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Exams Table -->
    <div class="card table-card">
        <div class="card-header">
            <h2 class="card-title">Examens récents</h2>
            <div class="card-actions">
                <a href="manage-exams.php" class="btn btn-primary btn-sm" style="text-decoration: none;">Voir tous</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Matière</th>
                            <th>Statut</th>
                            <th>Participants</th>
                            <th>Score moyen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentExams->num_rows > 0): ?>
                            <?php while ($exam = $recentExams->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="table-user">
                                            <div class="user-info">
                                                <span class="user-name"><?php echo htmlspecialchars($exam['title']); ?></span>
                                                <span class="user-date"><?php echo date('d/m/Y', strtotime($exam['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $exam['status']; ?>">
                                            <?php echo ucfirst($exam['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $exam['participants']; ?></td>
                                    <td><?php echo $exam['avg_score'] ? round($exam['avg_score'], 1) . '%' : 'N/A'; ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-icon btn-sm" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view-results.php?id=<?php echo $exam['id']; ?>" class="btn btn-icon btn-sm" title="Résultats">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Aucun examen récent</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Exams to Grade and Incidents -->
    <div class="dashboard-row">
        <div class="card table-card">
            <div class="card-header">
                <h2 class="card-title">Examens à noter</h2>
                <div class="card-actions">
                    <a href="grade-exams.php" class="btn btn-primary btn-sm" style="text-decoration: none;">Voir tous</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($examsToGrade->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Soumissions</th>
                                    <th>En attente</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($exam = $examsToGrade->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="table-user">
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars($exam['title']); ?></span>
                                                    <span class="user-date"><?php echo htmlspecialchars($exam['subject']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $exam['submissions']; ?></td>
                                        <td>
                                            <span class="badge badge-warning"><?php echo $exam['pending_grades']; ?></span>
                                        </td>
                                        <td>
                                            <a href="grade-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                Noter
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <p>Aucun examen en attente de notation</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card table-card">
            <div class="card-header">
                <h2 class="card-title">Incidents récents</h2>
                <div class="card-actions">
                    <a href="proctoring-incidents.php" class="btn btn-primary btn-sm" style="text-decoration: none;">Voir tous</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($proctorIncidents->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Examen</th>
                                    <th>Étudiant</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($incident = $proctorIncidents->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($incident['exam_title']); ?></td>
                                        <td><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></td>
                                        <td>
                                            <span class="incident-badge <?php echo strtolower($incident['incident_type']); ?>">
                                                <?php echo htmlspecialchars($incident['incident_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-sm view-incident" data-id="<?php echo $incident['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <p>Aucun incident récent</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour afficher les détails d'un incident -->
<div class="modal" id="incidentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Détails de l'incident</h3>
                <button type="button" class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="incident-details">
                    <div class="incident-info">
                        <p><strong>Examen:</strong> <span id="incident-exam"></span></p>
                        <p><strong>Étudiant:</strong> <span id="incident-student"></span></p>
                        <p><strong>Type d'incident:</strong> <span id="incident-type"></span></p>
                        <p><strong>Date:</strong> <span id="incident-date"></span></p>
                    </div>
                    <div class="incident-description">
                        <h4>Description</h4>
                        <p id="incident-description"></p>
                    </div>
                    <div class="incident-evidence">
                        <h4>Preuves</h4>
                        <div id="incident-evidence-container">
                            <img id="incident-image" src="../assets/images/placeholder.jpg" alt="Preuve de l'incident">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="review-incident">Examiner</button>
            </div>
        </div>
    </div>
</div>

<?php
// Ajouter les scripts JS pour les graphiques
$extraJs = [
    'https://cdn.jsdelivr.net/npm/chart.js'
];
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Exams Activity Chart
    const examsActivityCtx = document.getElementById('examsActivityChart').getContext('2d');
    const examsActivityChart = new Chart(examsActivityCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Examens créés',
                data: [12, 19, 15, 8, 22, 14, 10, 17, 21, 15, 19, 23],
                backgroundColor: '#4e73df',
                borderColor: '#4e73df',
                borderWidth: 1
            }, {
                label: 'Examens complétés',
                data: [10, 15, 12, 6, 17, 10, 8, 15, 18, 12, 15, 20],
                backgroundColor: '#36b9cc',
                borderColor: '#36b9cc',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                }
            }
        }
    });
    
    // Exam Status Chart
    const examStatusCtx = document.getElementById('examStatusChart').getContext('2d');
    const examStatusChart = new Chart(examStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Actifs', 'Brouillons', 'Terminés'],
            datasets: [{
                data: [
                    <?php echo $examStats['active_exams']; ?>, 
                    <?php echo $examStats['draft_exams']; ?>, 
                    <?php echo $examStats['completed_exams']; ?>
                ],
                backgroundColor: [
                    '#4CAF50',
                    '#FFC107',
                    '#2196F3'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Gestion du modal d'incident
    const viewIncidentBtns = document.querySelectorAll('.view-incident');
    const incidentModal = document.getElementById('incidentModal');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    
    viewIncidentBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const incidentId = this.getAttribute('data-id');
            // Ici, vous feriez normalement une requête AJAX pour récupérer les détails de l'incident
            // Pour l'exemple, nous allons simplement remplir le modal avec des données fictives
            document.getElementById('incident-exam').textContent = "Physique quantique";
            document.getElementById('incident-student').textContent = "Jean Dupont";
            document.getElementById('incident-type').textContent = "Détection de visage";
            document.getElementById('incident-date').textContent = "18/04/2023, 09:15";
            document.getElementById('incident-description').textContent = "L'étudiant a quitté le champ de vision de la caméra pendant plus de 30 secondes.";
            
            incidentModal.classList.add('show');
        });
    });
    
    closeModalBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            incidentModal.classList.remove('show');
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target == incidentModal) {
            incidentModal.classList.remove('show');
        }
    });
    
    // Bouton d'examen d'incident
    document.getElementById('review-incident').addEventListener('click', function() {
        alert('Redirection vers la page de révision détaillée de l\'incident...');
        // Ici, vous redirigeriez normalement vers une page de révision détaillée
    });
});
</script>
