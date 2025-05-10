<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit();
}

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

// S'assurer que les valeurs ne sont pas nulles
$examStats['active_exams'] = $examStats['active_exams'] ?? 0;
$examStats['draft_exams'] = $examStats['draft_exams'] ?? 0;
$examStats['completed_exams'] = $examStats['completed_exams'] ?? 0;
$examStats['total_exams'] = $examStats['total_exams'] ?? 0;

// Récupérer les statistiques des étudiants
$studentStatsQuery = $conn->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_students,
        AVG(score) as avg_score,
        MAX(score) as max_score,
        MIN(score) as min_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE e.teacher_id = $teacherId AND er.status = 'completed'
");
$up = $conn->prepare("UPDATE `exam_results` er JOIN `exam_attempts` ea ON er.id=ea.id SET er.score =ea.score,er.points_earned=ea.score WHERE er.exam_id=ea.exam_id AND er.user_id=ea.user_id AND er.created_at=ea.created_at");
$up->execute();
$studentStats = $studentStatsQuery->fetch_assoc();

// S'assurer que les valeurs ne sont pas nulles
$studentStats['total_students'] = $studentStats['total_students'] ?? 0;
$studentStats['avg_score'] = $studentStats['avg_score'] ?? 0;
$studentStats['max_score'] = $studentStats['max_score'] ?? 0;
$studentStats['min_score'] = $studentStats['min_score'] ?? 0;

// Récupérer les données mensuelles pour le graphique d'activité
$currentYear = date('Y');
$monthlyData = [];

// Initialiser les tableaux pour chaque mois
for ($i = 1; $i <= 12; $i++) {
    $monthlyData['created'][$i] = 0;
    $monthlyData['completed'][$i] = 0;
}

// Récupérer les examens créés par mois
$createdExamsQuery = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as count
    FROM exams
    WHERE teacher_id = $teacherId AND YEAR(created_at) = $currentYear
    GROUP BY MONTH(created_at)
");

while ($row = $createdExamsQuery->fetch_assoc()) {
    $monthlyData['created'][$row['month']] = (int)$row['count'];
}

// Récupérer les examens complétés par mois
$completedExamsQuery = $conn->query("
    SELECT 
        MONTH(e.end_date) as month,
        COUNT(DISTINCT e.id) as count
    FROM exams e
    WHERE e.teacher_id = $teacherId 
    AND e.status = 'completed' 
    AND YEAR(e.end_date) = $currentYear
    GROUP BY MONTH(e.end_date)
");

while ($row = $completedExamsQuery->fetch_assoc()) {
    $monthlyData['completed'][$row['month']] = (int)$row['count'];
}

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
        p.details,
        p.image_path
    FROM proctoring_incidents p
    JOIN exams e ON p.exam_id = e.id
    JOIN users u ON p.user_id = u.id
    WHERE e.teacher_id = $teacherId
    ORDER BY p.timestamp DESC
    LIMIT 5
");

// Définir les scripts supplémentaires à charger

$monthlyData['created'] = array_values($monthlyData['created']);
$monthlyData['completed'] = array_values($monthlyData['completed']);
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
            <div class="card-body" style="max-height: 300px;">
                <canvas id="examsActivityChart" height="650"></canvas>
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
            <div class="card-body" style="max-height: 300px;">
                <canvas id="examStatusChart"></canvas>
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
                                            <a href="view-results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-icon btn-sm" title="Résultats">
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
                <?php if ($examsToGrade && $examsToGrade->num_rows > 0): ?>
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
                <?php if ($proctorIncidents && $proctorIncidents->num_rows > 0): ?>
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
                                            <button class="btn btn-icon btn-sm view-incident"
                                                data-id="<?php echo $incident['id']; ?>"
                                                data-exam="<?php echo htmlspecialchars($incident['exam_title']); ?>"
                                                data-student="<?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?>"
                                                data-type="<?php echo htmlspecialchars($incident['incident_type']); ?>"
                                                data-date="<?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?>"
                                                data-details="<?php echo htmlspecialchars($incident['details']); ?>"
                                                data-image="<?php echo !empty($incident['image_path']) ? htmlspecialchars($incident['image_path']) : '../assets/images/placeholder.jpg'; ?>">
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
// Ajoutez cette condition à la requête SQL si vous voulez filtrer par enseignant
$teacherId = $_SESSION['user_id']; // ID de l'enseignant connecté
$examsByStatus = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        YEAR(created_at) as year,
        status,
        COUNT(*) as count
    FROM exams
    WHERE teacher_id = $teacherId
    GROUP BY YEAR(created_at), MONTH(created_at), status
    ORDER BY year, month, status
");

// Préparer les données pour le graphique
$months = [];
$statusData = [
    'draft' => [],
    'published' => [],
    'scheduled' => [],
    'completed' => []
];

// Initialiser les données
while ($row = $examsByStatus->fetch_assoc()) {
    $monthYear = date('M Y', mktime(0, 0, 0, $row['month'], 1, $row['year']));

    if (!in_array($monthYear, $months)) {
        $months[] = $monthYear;
    }

    $statusData[$row['status']][$monthYear] = $row['count'];
}

// Remplir les données manquantes avec 0
foreach ($statusData as $status => $data) {
    foreach ($months as $month) {
        if (!isset($data[$month])) {
            $statusData[$status][$month] = 0;
        }
    }
    // Réorganiser les données dans l'ordre des mois
    ksort($statusData[$status]);
}

// Convertir en JSON pour JavaScript
$monthsJson = json_encode($months);
$draftDataJson = json_encode(array_values($statusData['draft']));
$publishedDataJson = json_encode(array_values($statusData['published']));
$scheduledDataJson = json_encode(array_values($statusData['scheduled']));
$completedDataJson = json_encode(array_values($statusData['completed']));
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. GRAPHIQUE D'ACTIVITÉ (Barres)
        const activityCtx = document.getElementById('examsActivityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                        label: 'Examens créés',
                        data: [<?php echo implode(',', $monthlyData['created']); ?>],
                        backgroundColor: 'rgba(78, 115, 223, 0.8)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Examens terminés',
                        data: [<?php echo implode(',', $monthlyData['completed']); ?>],
                        backgroundColor: 'rgba(54, 185, 204, 0.8)',
                        borderColor: 'rgba(54, 185, 204, 1)',
                        borderWidth: 1
                    }
                ],

            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // 2. GRAPHIQUE DE RÉPARTITION (Anneau)
        const statusCtx = document.getElementById('examStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Actifs', 'Brouillons', 'Terminés'],
                datasets: [{
                    data: [<?php echo $publishedDataJson; ?>, <?php echo $draftDataJson; ?>, <?php echo $completedDataJson; ?>],
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)'
                    ],
                    borderColor: [
                        'rgba(99, 102, 241, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Désactive le ratio par défaut
                width: 150, // Largeur fixe
                height: 100,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right'
                    }
                },
            }
        });

        // Gestion du modal d'incident
        const viewIncidentBtns = document.querySelectorAll('.view-incident');
        const incidentModal = document.getElementById('incidentModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');

        if (viewIncidentBtns.length > 0 && incidentModal && closeModalBtns.length > 0) {
            viewIncidentBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    // Récupérer les données de l'incident depuis les attributs data-*
                    const exam = this.getAttribute('data-exam');
                    const student = this.getAttribute('data-student');
                    const type = this.getAttribute('data-type');
                    const date = this.getAttribute('data-date');
                    const details = this.getAttribute('data-details');
                    const imagePath = this.getAttribute('data-image');

                    // Remplir le modal avec les données
                    document.getElementById('incident-exam').textContent = exam;
                    document.getElementById('incident-student').textContent = student;
                    document.getElementById('incident-type').textContent = type;
                    document.getElementById('incident-date').textContent = date;
                    document.getElementById('incident-description').textContent = details;
                    document.getElementById('incident-image').src = imagePath;

                    // Afficher le modal
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
            const reviewIncidentBtn = document.getElementById('review-incident');
            if (reviewIncidentBtn) {
                reviewIncidentBtn.addEventListener('click', function() {
                    alert('Redirection vers la page de révision détaillée de l\'incident...');
                    // Ici, vous redirigeriez normalement vers une page de révision détaillée
                });
            }
        }
    });
</script>