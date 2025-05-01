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

// Récupérer les statistiques générales
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$totalTeachers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
$totalExams = $conn->query("SELECT COUNT(*) as count FROM exams")->fetch_assoc()['count'];
$totalCompletedExams = $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE status = 'completed'")->fetch_assoc()['count'];

// Récupérer les données pour le graphique des examens par mois
$examsByMonth = $conn->query("
    SELECT 
        MONTH(created_at) as month, 
        COUNT(*) as count 
    FROM exams 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY month
");

// Récupérer les données pour le graphique des résultats moyens
$avgResults = $conn->query("
    SELECT 
        e.subject,
        AVG(er.score) as avg_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE er.status = 'completed'
    GROUP BY e.subject
    ORDER BY avg_score DESC
    LIMIT 10
");

// Récupérer les incidents de surveillance
$proctorIncidents = $conn->query("
    SELECT 
        p.exam_id,
        e.title,
        u.username,
        p.incident_type,
        p.timestamp,
        p.details
    FROM proctoring_incidents p
    JOIN exams e ON p.exam_id = e.id
    JOIN users u ON p.user_id = u.id
    ORDER BY p.timestamp DESC
    LIMIT 20
");

$pageTitle = "Rapports et Statistiques";
include 'includes/header.php';
?>

<h1 class="page-title">Rapports et Statistiques</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Étudiants</h3>
        </div>
        <div class="stat-value"><?php echo $totalStudents; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 5.2% vs mois dernier
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Enseignants</h3>
        </div>
        <div class="stat-value"><?php echo $totalTeachers; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 3.1% vs mois dernier
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Examens</h3>
        </div>
        <div class="stat-value"><?php echo $totalExams; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 7.8% vs mois dernier
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <h3 class="stat-title">Examens Complétés</h3>
        </div>
        <div class="stat-value"><?php echo $totalCompletedExams; ?></div>
        <div class="stat-trend trend-up">
            <i class="fas fa-arrow-up trend-icon"></i> 12.4% vs mois dernier
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Examens créés par mois</h2>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="chart-container">
                <canvas id="examsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Score moyen par matière</h2>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="chart-container">
                <canvas id="scoresChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2 class="section-title">Incidents de surveillance récents</h2>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Examen</th>
                            <th>Étudiant</th>
                            <th>Type d'incident</th>
                            <th>Date</th>
                            <th>Détails</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proctorIncidents->num_rows > 0): ?>
                            <?php while ($incident = $proctorIncidents->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($incident['title']); ?></td>
                                    <td><?php echo htmlspecialchars($incident['username']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo getIncidentBadgeClass($incident['incident_type']); ?>">
                                            <?php echo htmlspecialchars($incident['incident_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($incident['timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($incident['details'], 0, 50)) . (strlen($incident['details']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm view-details" data-id="<?php echo $incident['exam_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Aucun incident récent</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="row">
        <div class="col-md-6">
            <div class="section-header">
                <h2 class="section-title">Générer des rapports</h2>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form id="reportForm" class="admin-form">
                        <div class="form-group">
                            <label for="reportType" class="form-label">Type de rapport</label>
                            <select id="reportType" name="reportType" class="form-control">
                                <option value="exam_results">Résultats d'examens</option>
                                <option value="user_activity">Activité des utilisateurs</option>
                                <option value="proctoring_incidents">Incidents de surveillance</option>
                                <option value="system_usage">Utilisation du système</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="dateRange" class="form-label">Période</label>
                            <select id="dateRange" name="dateRange" class="form-control">
                                <option value="7days">7 dern  name="dateRange" class="form-control">
                                <option value="7days">7 derniers jours</option>
                                <option value="30days">30 derniers jours</option>
                                <option value="90days">90 derniers jours</option>
                                <option value="year">Année en cours</option>
                                <option value="custom">Personnalisé</option>
                            </select>
                        </div>
                        
                        <div id="customDateRange" class="form-row" style="display: none;">
                            <div class="form-col">
                                <label for="startDate" class="form-label">Date de début</label>
                                <input type="date" id="startDate" name="startDate" class="form-control">
                            </div>
                            <div class="form-col">
                                <label for="endDate" class="form-label">Date de fin</label>
                                <input type="date" id="endDate" name="endDate" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="format" class="form-label">Format</label>
                            <select id="format" name="format" class="form-control">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Générer le rapport</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="section-header">
                <h2 class="section-title">Rapports récents</h2>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nom du rapport</th>
                                    <th>Date de génération</th>
                                    <th>Type</th>
                                    <th>Format</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Résultats d'examens - Avril 2023</td>
                                    <td>15/04/2023</td>
                                    <td>Résultats d'examens</td>
                                    <td>PDF</td>
                                    <td>
                                        <button class="btn btn-primary btn-sm"><i class="fas fa-download"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Incidents de surveillance - T1 2023</td>
                                    <td>01/04/2023</td>
                                    <td>Incidents de surveillance</td>
                                    <td>Excel</td>
                                    <td>
                                        <button class="btn btn-primary btn-sm"><i class="fas fa-download"></i></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Activité des utilisateurs - Mars 2023</td>
                                    <td>31/03/2023</td>
                                    <td>Activité des utilisateurs</td>
                                    <td>CSV</td>
                                    <td>
                                        <button class="btn btn-primary btn-sm"><i class="fas fa-download"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration du graphique des examens par mois
    const examsCtx = document.getElementById('examsChart').getContext('2d');
    const examsChart = new Chart(examsCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Nombre d\'examens',
                data: [12, 19, 15, 25, 22, 30, 18, 15, 20, 25, 28, 30],
                backgroundColor: 'rgba(99, 102, 241, 0.6)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 1
            }]
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
    
    // Configuration du graphique des scores moyens
    const scoresCtx = document.getElementById('scoresChart').getContext('2d');
    const scoresChart = new Chart(scoresCtx, {
        type: 'horizontalBar',
        data: {
            labels: ['Mathématiques', 'Physique', 'Chimie', 'Biologie', 'Histoire', 'Géographie', 'Anglais'],
            datasets: [{
                label: 'Score moyen (%)',
                data: [75, 68, 82, 79, 85, 72, 88],
                backgroundColor: 'rgba(16, 185, 129, 0.6)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    
    // Afficher/masquer les dates personnalisées
    document.getElementById('dateRange').addEventListener('change', function() {
        const customDateRange = document.getElementById('customDateRange');
        if (this.value === 'custom') {
            customDateRange.style.display = 'flex';
        } else {
            customDateRange.style.display = 'none';
        }
    });
    
    // Soumission du formulaire de rapport
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        alert('Le rapport est en cours de génération et sera disponible dans quelques instants.');
    });
});

// Helper function for incident badges
function getIncidentBadgeClass(type) {
    switch(type) {
        case 'face_detection':
            return 'danger';
        case 'eye_tracking':
            return 'warning';
        case 'audio':
            return 'info';
        case 'screen':
            return 'secondary';
        default:
            return 'primary';
    }
}
</script>

<?php
// Helper function for incident badges
function getIncidentBadgeClass($type) {
    switch($type) {
        case 'face_detection':
            return 'danger';
        case 'eye_tracking':
            return 'warning';
        case 'audio':
            return 'info';
        case 'screen':
            return 'secondary';
        default:
            return 'primary';
    }
}
?>

<?php include 'includes/footer.php'; ?>
